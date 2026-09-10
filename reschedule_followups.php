<?php
/**
 * CLI Tool: Reschedule Missing Follow-up Campaigns
 * Usage: php reschedule_followups.php
 * 
 * Re-activates and schedules pending follow-up steps for all threads that were
 * prematurely stopped by the previous false "Recipient replied" bug.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use App\Models\FollowupCampaign;
use App\Services\AutomationEngine;

new App();

echo "========================================================\n";
echo " Gmail Automation: Follow-up Rescheduling Tool\n";
echo "========================================================\n\n";

// 1. Find all threads where:
// - Source mailbox is connected
// - Not historical baseline and not manually stopped
// - No pending follow-up job currently waiting in scheduled_jobs
// - Either auto-reply was already sent (reply_count >= 1) or campaign was stopped/replied
$sql = "
    SELECT t.* FROM email_threads t
    INNER JOIN gmail_accounts a ON a.id = COALESCE(NULLIF(t.source_mailbox_id, 0), t.gmail_account_id)
    WHERE a.status = 'connected'
      AND t.automation_status NOT IN ('historical', 'stopped')
      AND t.id NOT IN (
          SELECT thread_id FROM scheduled_jobs 
          WHERE job_type = 'follow_up' AND status = 'pending'
      )
    ORDER BY t.id DESC
";

$threads = Database::query($sql);
echo "Found " . count($threads) . " candidate thread(s) to evaluate.\n\n";

$rescheduledCount = 0;
$skippedCount = 0;

foreach ($threads as $tRow) {
    $thread = EmailThread::find((int)$tRow['id']);
    if (!$thread) continue;

    $sourceMailboxId = (int)($thread->source_mailbox_id ?: $thread->gmail_account_id);
    $account = GmailAccount::find($sourceMailboxId);
    if (!$account || $account->status !== 'connected') continue;

    // Check if recipient genuinely replied AFTER our last outgoing message
    if (!empty($thread->last_outgoing_at)) {
        $actualReply = Database::first(
            "SELECT id, received_at, subject FROM email_messages 
             WHERE thread_id = :tid 
               AND direction = 'incoming' 
               AND received_at > :last_out 
             LIMIT 1",
            [
                'tid' => $thread->id,
                'last_out' => date('Y-m-d H:i:s', strtotime($thread->last_outgoing_at) + 60),
            ]
        );

        if ($actualReply) {
            // Customer genuinely replied to us - do not disturb
            $skippedCount++;
            continue;
        }
    }

    // Only reschedule if auto reply was already sent OR campaign had been stopped/replied prematurely
    if ($thread->reply_count === 0 && empty($thread->last_outgoing_at)) {
        // Auto-reply hasn't been sent yet (scheduled for future).
        // Reactivate thread so when auto-reply fires, follow-up will be scheduled.
        $thread->update(['automation_status' => 'active']);
        $campaign = FollowupCampaign::findByThreadId($thread->id);
        if ($campaign && $campaign->campaign_status === 'replied') {
            $campaign->update(['campaign_status' => 'active']);
        }
        continue;
    }

    // Reactivate thread & campaign
    $thread->update(['automation_status' => 'active']);
    $campaign = FollowupCampaign::findByThreadId($thread->id);
    if ($campaign) {
        $campaign->update(['campaign_status' => 'active']);
    }

    $engine = new AutomationEngine($account);
    $currentStep = (int)$thread->followup_count;

    try {
        $job = $engine->scheduleNextFollowupStep($thread, $currentStep);
        if ($job) {
            echo "  ✓ [Thread {$thread->gmail_thread_id}] Rescheduled Follow-up for {$thread->sender_email} (Step " . ($currentStep + 1) . ") -> Scheduled at: {$job->scheduled_at}\n";
            $rescheduledCount++;
        } else {
            $skippedCount++;
        }
    } catch (\Throwable $e) {
        echo "  ✗ [Thread {$thread->gmail_thread_id}] Error: " . $e->getMessage() . "\n";
    }
}

echo "\n========================================================\n";
echo " Summary:\n";
echo "  • Rescheduled & Active: {$rescheduledCount} follow-up(s)\n";
echo "  • Skipped (genuine replies / completed): {$skippedCount}\n";
echo "========================================================\n";
