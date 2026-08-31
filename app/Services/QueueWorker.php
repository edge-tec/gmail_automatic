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
use Exception;

class QueueWorker {
    private bool $stopRequested = false;

    public function run(bool $once = false, int $batchSize = 25): void {
        echo "[" . date('Y-m-d H:i:s') . "] Gmail Automation Queue Worker started...\n";

        // 1. Process asynchronous email notification jobs (SMTP)
        $this->processEmailJobs($batchSize);

        // 2. Check expiring subscriptions and trials and send reminders
        $this->checkExpiringSubscriptionsAndTrials();

        // 3. Process scheduled Gmail automation jobs
        while (!$this->stopRequested) {
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
                $this->processJob($job);
            }

            if ($once) {
                break;
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
            if ($thread->automation_status === 'replied') {
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
                $liveTemplate = $settings->getReplyMessageForStep($stepNumber);

                if (empty(trim(strip_tags($liveTemplate)))) {
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

                // Check daily limit only for NEW leads/traffic (reply_count == 0); multi-turn replies to existing leads count as 1
                if ($thread->reply_count === 0 && $usage['reply_count'] >= ($settings->daily_reply_limit ?? 100)) {
                    // Reschedule for tomorrow
                    $nextTime = $engine->calculateNextAllowedSendTime(true);
                    $job->update([
                        'status' => 'pending',
                        'scheduled_at' => $nextTime,
                        'last_error' => 'Daily lead/traffic limit reached. Postponed to next day.',
                    ]);
                    echo "  ↳ Daily lead/traffic limit reached. Postponed to {$nextTime}.\n";
                    return false;
                }
            } elseif ($job->job_type === 'follow_up') {
                if (!$settings || !$settings->followup_enabled) {
                    $job->cancel('Follow-up automation is currently turned off for this account');
                    logger("Skipped follow-up job #{$job->id} because follow-up is turned off for {$account->gmail_email}", 'info', $account->user_id, $account->id);
                    echo "  ↳ Skipped: Follow-up is turned off for {$account->gmail_email}.\n";
                    return true;
                }

                $templateId = (int)($payload['template_id'] ?? 0);
                $stepNumber = (int)($payload['step_number'] ?? 1);
                $template = $templateId ? FollowupTemplate::find($templateId) : FollowupTemplate::findNextStep($account->id, $stepNumber - 1);

                if (!$template || $template->status !== 'active' || empty(trim(strip_tags($template->message)))) {
                    $job->cancel('Follow-up template is missing, disabled, or deleted. Automated email was not sent.');
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

                if ($usage['followup_count'] >= ($settings->daily_followup_limit ?? 100)) {
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

            if (empty(trim(strip_tags($finalBody)))) {
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
                'snippet' => substr($body, 0, 150),
                'message_body' => $body,
                'sent_at' => $sentAt,
                'status' => 'sent',
            ]);

            // Update thread counters & timestamps
            if ($job->job_type === 'auto_reply') {
                // Only increment daily quota when initiating reply with a lead (first reply)
                if ($thread->reply_count === 0) {
                    DailyUsage::incrementReply($account->id);
                }

                $thread->update([
                    'reply_count' => $thread->reply_count + 1,
                    'last_outgoing_at' => $sentAt,
                ]);

                // Schedule follow-up Step 1 if enabled
                $engine = new AutomationEngine($account);
                $engine->scheduleNextFollowupStep($thread, 0);

            } elseif ($job->job_type === 'follow_up') {
                $stepNumber = (int)($payload['step_number'] ?? 1);
                $thread->update([
                    'followup_count' => $thread->followup_count + 1,
                    'last_outgoing_at' => $sentAt,
                ]);
                DailyUsage::incrementFollowup($account->id);

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
