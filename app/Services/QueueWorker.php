<?php
namespace App\Services;

use App\Core\Database;
use App\Models\ScheduledJob;
use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\DailyUsage;
use App\Models\SystemSetting;
use App\Models\FollowupTemplate;
use App\Models\FollowupCampaign;
use App\Models\FollowupJob;
use App\Models\AutoReplyRecipient;
use Exception;

class QueueWorker {
    private bool $stopRequested = false;

    /**
     * Recover stuck 'processing' jobs that may have been abandoned due to unexpected crashes or timeouts.
     */
    public static function recoverStaleJobs(int $timeoutMinutes = 5): int {
        $driver = config('database.default', 'mysql');
        $now = date('Y-m-d H:i:s');
        $staleThreshold = date('Y-m-d H:i:s', time() - ($timeoutMinutes * 60));

        $sql = "UPDATE scheduled_jobs 
                SET status = 'pending', last_error = 'Auto-recovered from interrupted processing', updated_at = :now 
                WHERE status = 'processing' AND (updated_at <= :thresh OR updated_at IS NULL)";
        return Database::execute($sql, ['now' => $now, 'thresh' => $staleThreshold]);
    }

    public function run(bool $once = false, int $batchSize = 25): void {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] Gmail Automation Queue Worker started...\n";

        // Update heartbeat
        SystemSetting::set('worker_last_heartbeat', $timestamp);
        SystemSetting::set('worker_status', 'running');

        // 0. Recover any stale processing jobs
        self::recoverStaleJobs();

        // 1. Process asynchronous email notification jobs (SMTP)
        $this->processEmailJobs($batchSize);

        // 2. Check expiring subscriptions and trials and send reminders
        $this->checkExpiringSubscriptionsAndTrials();

        // 3. Process scheduled Gmail automation jobs in continuous or multi-batch mode
        $iterations = 0;
        $maxOnceIterations = 10;

        while (!$this->stopRequested) {
            SystemSetting::set('worker_last_heartbeat', date('Y-m-d H:i:s'));
            $jobs = ScheduledJob::getReadyJobs($batchSize);
            
            if (empty($jobs)) {
                if ($once) {
                    echo "[" . date('Y-m-d H:i:s') . "] No pending jobs found. Exiting.\n";
                    break;
                }
                sleep(3);
                continue;
            }

            foreach ($jobs as $job) {
                try {
                    $this->processJob($job);
                } catch (\Throwable $e) {
                    logger("Fatal error in queue job #{$job->id}: " . $e->getMessage(), 'error');
                    echo "  ✗ Uncaught error in Job #{$job->id}: " . $e->getMessage() . "\n";
                }
            }

            if ($once) {
                $iterations++;
                if ($iterations >= $maxOnceIterations) {
                    break;
                }
            }
        }
    }

    public function processEmailJobs(int $limit = 25): void {
        $jobs = \App\Models\EmailJob::getReadyJobs($limit);
        if (!empty($jobs)) {
            echo "[" . date('Y-m-d H:i:s') . "] Processing " . count($jobs) . " email notification job(s)...\n";
            foreach ($jobs as $job) {
                MailService::processEmailJob($job);
            }
        }
    }

    public function checkExpiringSubscriptionsAndTrials(): void {
        try {
            EmailNotificationService::checkExpiringAndExpiredSubscriptions();
        } catch (\Throwable $e) {
            logger("Error in checkExpiringSubscriptionsAndTrials: " . $e->getMessage(), 'error');
        }
    }

    public function processJob(ScheduledJob $job): bool {
        // Attempt status lock to prevent concurrent worker race conditions
        $now = date('Y-m-d H:i:s');
        $locked = Database::execute(
            "UPDATE scheduled_jobs SET status = 'processing', attempts = attempts + 1, updated_at = :now 
             WHERE id = :id AND status = 'pending'",
            ['id' => $job->id, 'now' => $now]
        );

        if (!$locked) {
            return false;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Processing Job #{$job->id} (Type: {$job->job_type}, Thread: {$job->thread_id})...\n";

        try {
            $account = GmailAccount::find($job->gmail_account_id);
            if (!$account || $account->status !== 'connected') {
                throw new Exception("Gmail account not found or disconnected");
            }

            $thread = EmailThread::find($job->thread_id);
            if (!$thread) {
                throw new Exception("Email thread not found");
            }

            // Check if thread was replied by user or stopped manually
            if ($job->job_type === 'follow_up' && $thread->automation_status === 'replied') {
                $job->update([
                    'status' => 'cancelled',
                    'last_error' => 'Thread status is replied. Cancelled automatically.',
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);
                echo "  ↳ Thread already replied by recipient. Job cancelled.\n";
                return true;
            }

            if ($thread->automation_status === 'stopped') {
                $job->update([
                    'status' => 'cancelled',
                    'last_error' => 'Thread automation manually stopped.',
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);
                echo "  ↳ Thread automation stopped manually. Job cancelled.\n";
                return true;
            }

            // Check Global Automation Setting
            if (SystemSetting::get('global_automation_enabled', '1') !== '1') {
                throw new Exception("Global automation is temporarily disabled by admin");
            }

            $payload = $job->getPayloadArray();
            $recipientEmail = $payload['recipient_email'] ?? $thread->sender_email;
            $subject = $payload['subject'] ?? $thread->subject;
            
            if (empty($recipientEmail)) {
                throw new Exception("Missing recipient email address in job payload");
            }

            // Check Account Automation Settings
            $settings = $account->getSettings();
            $usage = $account->getTodayUsage();
            $engine = new AutomationEngine($account);

            $finalBody = '';

            if ($job->job_type === 'auto_reply') {
                if (!$settings || !$settings->auto_reply_enabled) {
                    $job->cancel('Auto-reply is currently turned off for this account');
                    logger("Skipped auto-reply job #{$job->id} because auto-reply is turned off for {$account->gmail_email}", 'info', $account->user_id, $account->id);
                    echo "  ↳ Skipped: Auto-reply is turned off for {$account->gmail_email}.\n";
                    return true;
                }

                // Strictly fetch latest live user-configured message from database for this step
                $stepNumber = (int)($payload['reply_step'] ?? 1);
                $totalSteps = (int)($payload['total_steps'] ?? $settings->getTotalConfiguredReplySteps());

                // Concurrency check: verify this step hasn't already been sent to this global traffic identity
                $replyRecipient = AutoReplyRecipient::findByUserAndSender($account->user_id, $recipientEmail);
                if ($replyRecipient && $replyRecipient->reply_sequence_step >= $stepNumber) {
                    $job->cancel("Auto-reply Step #{$stepNumber} already sent to this recipient.");
                    echo "  ↳ Skipped: Auto-reply Step #{$stepNumber} already sent to {$recipientEmail}.\n";
                    return true;
                }

                $liveTemplate = $settings->getReplyMessageForStep($stepNumber);

                $liveClean = trim(strip_tags($liveTemplate, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
                $liveIsPlaceholder = in_array(trim($liveTemplate), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
                if (empty($liveClean) || $liveIsPlaceholder) {
                    if ($replyRecipient) {
                        $replyRecipient->markCancelled();
                    }
                    $job->cancel('Message content is missing. Automated email was not sent.');
                    logger("Cancelled auto-reply job #{$job->id}: Message content is missing for Step #{$stepNumber}. Automated email was not sent.", 'warning', $account->user_id, $account->id);
                    echo "  ↳ Cancelled: Message content is missing for Step #{$stepNumber}.\n";
                    return true;
                }

                $finalBody = $engine->renderVariables($liveTemplate, [
                    'sender_email' => $recipientEmail,
                    'sender_name' => $payload['recipient_name'] ?? $thread->sender_name,
                    'subject' => $subject,
                    'date' => date('Y-m-d H:i:s'),
                ]);

                // Check daily limit for starting NEW traffic sequences (Step 1)
                // Existing active sequences (Step 2+) are already authorized and must complete their remaining replies
                $todayDate = date('Y-m-d');
                $isSequenceCountedToday = $replyRecipient && $replyRecipient->daily_counted === 1 && $replyRecipient->counted_date === $todayDate;
                $isNewTrafficSequence = ($stepNumber === 1 && !$isSequenceCountedToday);

                if ($isNewTrafficSequence && $usage['reply_count'] >= ($settings->daily_reply_limit ?? 100)) {
                    // Reschedule for tomorrow
                    $nextTime = $engine->calculateNextAllowedSendTime(true);
                    $job->update([
                        'status' => 'pending',
                        'scheduled_at' => $nextTime,
                        'last_error' => 'Daily reply limit for new traffic reached. Postponed to next day.',
                    ]);
                    echo "  ↳ Daily reply limit reached for new traffic. Postponed to {$nextTime}.\n";
                    return false;
                }
            } elseif ($job->job_type === 'follow_up') {
                if (!$settings || !$settings->followup_enabled) {
                    $job->cancel('Follow-up automation is currently turned off for this account');
                    logger("Skipped follow-up job #{$job->id} because follow-up is turned off for {$account->gmail_email}", 'info', $account->user_id, $account->id);
                    echo "  ↳ Skipped: Follow-up is turned off for {$account->gmail_email}.\n";
                    return true;
                }

                $campaignId = (int)($payload['campaign_id'] ?? 0);
                $campaign = $campaignId ? FollowupCampaign::find($campaignId) : FollowupCampaign::findByThreadId($thread->id);
                if ($campaign && in_array($campaign->campaign_status, ['replied', 'stopped', 'cancelled'])) {
                    $job->cancel("Campaign status is '{$campaign->campaign_status}'. Cancelled automatically.");
                    echo "  ↳ Campaign status is {$campaign->campaign_status}. Job cancelled.\n";
                    return true;
                }

                $templateId = (int)($payload['template_id'] ?? 0);
                $stepNumber = (int)($payload['step_number'] ?? 1);
                // Re-validate live template strictly from DB (Message edit/delete protection)
                $template = $templateId ? FollowupTemplate::find($templateId) : FollowupTemplate::findNextStep($account->id, $stepNumber - 1);
                $tplMsg = $template ? $template->message : '';
                $tplClean = trim(strip_tags($tplMsg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
                $tplIsPlaceholder = in_array(trim($tplMsg), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);

                if (!$template || $template->status !== 'active' || empty($tplClean) || $tplIsPlaceholder) {
                    $job->cancel('Follow-up template is missing, disabled, or deleted. Automated email was not sent.');
                    if ($campaign) {
                        $campaign->cancelPendingJobs('Follow-up template missing or deleted');
                    }
                    logger("Cancelled follow-up job #{$job->id}: Follow-up template is missing or disabled. Automated email was not sent.", 'warning', $account->user_id, $account->id);
                    echo "  ↳ Cancelled: Follow-up template is missing or disabled.\n";
                    return true;
                }

                $finalBody = $engine->renderVariables($template->message, [
                    'sender_email' => $recipientEmail,
                    'sender_name' => $payload['recipient_name'] ?? $thread->sender_name,
                    'subject' => $subject,
                    'date' => date('Y-m-d H:i:s'),
                ]);

                // Check Daily Follow limit only if this unique campaign has NOT been counted today
                $todayDate = date('Y-m-d');
                $isCampaignCountedToday = $campaign && $campaign->daily_follow_counted === 1 && $campaign->counted_date === $todayDate;

                if (!$isCampaignCountedToday && $usage['followup_count'] >= ($settings->daily_followup_limit ?? 100)) {
                    $nextTime = $engine->calculateNextAllowedSendTime(true);
                    $job->update([
                        'status' => 'pending',
                        'scheduled_at' => $nextTime,
                        'last_error' => 'Daily follow-up limit reached. Postponed to next day.',
                    ]);
                    echo "  ↳ Daily follow-up limit reached. Postponed to {$nextTime}.\n";
                    return false;
                }
            }

            $finalClean = trim(strip_tags($finalBody, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
            $finalIsPlaceholder = in_array(trim($finalBody), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
            if (empty($finalClean) || $finalIsPlaceholder) {
                $job->cancel('Message content is missing. Automated email was not sent.');
                logger("Blocked automated email: Message content is empty. Job #{$job->id} cancelled.", 'warning', $account->user_id, $account->id);
                return true;
            }

            // Send via Gmail API - Strictly User-Configured Content Only
            $gmailService = new GmailService($account);
            $sent = $gmailService->sendThreadReply(
                $recipientEmail,
                $subject,
                $finalBody,
                $thread->gmail_thread_id,
                $payload['in_reply_to'] ?? null,
                $payload['references'] ?? null
            );

            $sentMessageId = $sent['id'];
            $sentAt = date('Y-m-d H:i:s');

            // Record outgoing message
            EmailMessage::create([
                'thread_id' => $thread->id,
                'gmail_account_id' => $account->id,
                'gmail_message_id' => $sentMessageId,
                'direction' => 'outgoing',
                'sender' => $account->gmail_email,
                'recipient' => $recipientEmail,
                'subject' => 'Re: ' . preg_replace('/^Re:\s*/i', '', $subject),
                'snippet' => substr(strip_tags($finalBody), 0, 150),
                'message_body' => $finalBody,
                'sent_at' => $sentAt,
                'status' => 'sent',
            ]);

            // Update thread counters & timestamps
            if ($job->job_type === 'auto_reply') {
                $stepNumber = (int)($payload['reply_step'] ?? 1);
                $totalSteps = (int)($payload['total_steps'] ?? $settings->getTotalConfiguredReplySteps());

                $replyRecipient = AutoReplyRecipient::findByUserAndSender($account->user_id, $recipientEmail);
                if ($replyRecipient) {
                    $replyRecipient->recordStepSent($stepNumber, $totalSteps, $sentAt, $account->id);
                }

                logger("[QueueWorker] Traffic: {$recipientEmail} | Step #{$stepNumber} sent successfully via {$account->gmail_email} | Sequence: {$stepNumber}/{$totalSteps}", 'info', $account->user_id, $account->id);

                $thread->update([
                    'reply_count' => $thread->reply_count + 1,
                    'last_outgoing_at' => $sentAt,
                ]);

                // Schedule follow-up Step 1 if enabled
                $engine = new AutomationEngine($account);
                $engine->scheduleNextFollowupStep($thread, 0);

            } elseif ($job->job_type === 'follow_up') {
                $stepNumber = (int)($payload['step_number'] ?? 1);
                $campaignId = (int)($payload['campaign_id'] ?? 0);
                $campaign = $campaignId ? FollowupCampaign::find($campaignId) : FollowupCampaign::findByThreadId($thread->id);

                if ($campaign) {
                    // Mark unique campaign counted for daily follow quota (1 per conversation)
                    $campaign->markCountedForToday($account->id);
                    $campaign->update([
                        'current_step' => $stepNumber,
                        'last_sent_at' => $sentAt,
                    ]);

                    // Update corresponding followup_job status
                    $fJob = FollowupJob::findByCampaignAndStep($campaign->id, $stepNumber);
                    if ($fJob) {
                        $fJob->update(['status' => 'sent', 'sent_at' => $sentAt]);
                    }
                }

                // Always increment actual follow-up messages sent count
                DailyUsage::incrementFollowupMessage($account->id);

                $thread->update([
                    'followup_count' => $thread->followup_count + 1,
                    'last_outgoing_at' => $sentAt,
                ]);

                // Schedule subsequent follow-up step
                $engine = new AutomationEngine($account);
                $engine->scheduleNextFollowupStep($thread, $stepNumber);
            }

            // Mark job completed
            $job->update([
                'status' => 'completed',
                'processed_at' => $sentAt,
                'last_error' => null,
            ]);

            logger("Successfully sent {$job->job_type} to {$recipientEmail} in thread {$thread->gmail_thread_id}", 'success', $account->user_id, $account->id);
            echo "  ✓ Successfully sent {$job->job_type} (Message ID: {$sentMessageId})\n";
            return true;

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            echo "  ✗ Error: {$errorMsg}\n";

            $attempts = $job->attempts;
            $maxAttempts = $job->max_attempts;

            if ($attempts >= $maxAttempts) {
                $job->update([
                    'status' => 'failed',
                    'last_error' => $errorMsg,
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);
                logger("Job #{$job->id} failed permanently after {$attempts} attempts: {$errorMsg}", 'error', $job->gmail_account_id);
            } else {
                // Exponential backoff
                $backoffSeconds = pow(2, $attempts) * 60; // 2m, 4m, 8m
                $nextAttempt = date('Y-m-d H:i:s', time() + $backoffSeconds);
                $job->update([
                    'status' => 'pending',
                    'scheduled_at' => $nextAttempt,
                    'last_error' => "Attempt {$attempts} failed: {$errorMsg}",
                ]);
                logger("Job #{$job->id} attempt {$attempts} failed. Retrying at {$nextAttempt}", 'warning', $job->gmail_account_id);
            }

            return false;
        }
    }
}
