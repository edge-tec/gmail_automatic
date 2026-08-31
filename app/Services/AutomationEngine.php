<?php
namespace App\Services;

use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\AutomationRule;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\FollowupTemplate;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Models\SystemSetting;
use DateTime;
use DateTimeZone;
use Exception;

class AutomationEngine {
    private GmailAccount $account;
    private ?AutomationSetting $settings = null;

    public function __construct(GmailAccount $account) {
        $this->account = $account;
        $this->settings = $this->account->getSettings();
    }

    /**
     * Process an incoming email parsed array
     */
    public function processIncomingMessage(array $msgData): array {
        $msgId = $msgData['message_id'];
        $threadId = $msgData['thread_id'];
        $senderEmail = strtolower(trim($msgData['sender_email']));
        $senderName = $msgData['sender_name'];
        $subject = $msgData['subject'];
        $body = $msgData['body'] ?: $msgData['snippet'];
        $date = $msgData['date'];

        // Ignore messages sent by the account itself
        if (strtolower(trim($this->account->gmail_email)) === $senderEmail) {
            return ['status' => 'skipped', 'reason' => 'Self sent message'];
        }

        // 1. Idempotency Check
        $existingMsg = EmailMessage::findByAccountAndMessageId($this->account->id, $msgId);
        if ($existingMsg) {
            return ['status' => 'duplicate', 'message_id' => $msgId];
        }

        // 2. Find or create EmailThread
        $thread = EmailThread::createOrGet($this->account->id, $threadId, [
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'subject' => $subject,
        ]);

        // 3. Save incoming EmailMessage
        $savedMsg = EmailMessage::create([
            'thread_id' => $thread->id,
            'gmail_account_id' => $this->account->id,
            'gmail_message_id' => $msgId,
            'direction' => 'incoming',
            'sender' => $senderEmail,
            'recipient' => $this->account->gmail_email,
            'subject' => $subject,
            'snippet' => $msgData['snippet'] ?? '',
            'message_body' => $body,
            'received_at' => $date,
            'status' => 'processed',
        ]);

        // Update thread last incoming timestamp
        $thread->update([
            'last_incoming_at' => $date,
            'last_processed_message_id' => $msgId,
        ]);

        // 4. Critical Requirement #8: If recipient replied, cancel pending follow-up jobs!
        if ($thread->reply_count > 0 || $thread->followup_count > 0) {
            $thread->update([
                'automation_status' => 'replied',
                'next_followup_at' => null,
            ]);
            ScheduledJob::cancelPendingJobsForThread($thread->id, 'Recipient replied to email');
            logger("Recipient replied in thread {$threadId}. Cancelled all pending follow-ups.", 'info', $this->account->user_id, $this->account->id);
            return ['status' => 'replied_detected', 'thread_id' => $thread->id];
        }

        // Check if thread automation is stopped manually
        if ($thread->automation_status === 'stopped') {
            return ['status' => 'skipped', 'reason' => 'Thread automation manually stopped'];
        }

        // 5. Check Global Automation switch
        if (SystemSetting::get('global_automation_enabled', '1') !== '1') {
            return ['status' => 'skipped', 'reason' => 'Global automation is disabled'];
        }

        // 6. Check Account Auto Reply Settings
        if (!$this->settings || !$this->settings->auto_reply_enabled) {
            return ['status' => 'skipped', 'reason' => 'Auto reply disabled for account'];
        }

        // 7. Check Custom Automation Rules (sender/subject/body filters)
        $ruleDecision = $this->evaluateRules($senderEmail, $subject, $body);
        if ($ruleDecision['action'] === 'skip') {
            return ['status' => 'skipped', 'reason' => 'Skipped by custom automation rule'];
        }

        // 8. Check Per-Thread Reply Limit
        if ($thread->reply_count >= $this->settings->max_reply_per_thread) {
            return ['status' => 'limit_reached', 'reason' => 'Max reply per thread reached'];
        }

        // 9. Check Daily Reply Limit
        $usage = $this->account->getTodayUsage();
        if ($usage['reply_count'] >= $this->settings->daily_reply_limit) {
            // Schedule for next day beginning of working hour
            $scheduledAt = $this->calculateNextAllowedSendTime(true);
        } else {
            $scheduledAt = $this->calculateNextAllowedSendTime(false, $this->settings->reply_delay);
        }

        // 10. Prepare Reply Message with Template Variables
        $templateMessage = $ruleDecision['custom_message'] ?? $this->settings->reply_message;
        $renderedMessage = $this->renderVariables($templateMessage, [
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'subject' => $subject,
            'date' => $date,
        ]);

        // 11. Schedule the Auto Reply Job
        $job = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => [
                'recipient_email' => $senderEmail,
                'recipient_name' => $senderName,
                'subject' => $subject,
                'reply_body' => $renderedMessage,
                'in_reply_to' => $msgData['message_id_header'] ?? null,
                'references' => $msgData['references'] ?? null,
                'gmail_message_id' => $msgId,
            ],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        logger("Scheduled auto-reply for thread {$threadId} to {$senderEmail} at {$scheduledAt}", 'info', $this->account->user_id, $this->account->id);

        return [
            'status' => 'scheduled',
            'job_id' => $job->id,
            'scheduled_at' => $scheduledAt,
        ];
    }

    /**
     * Render Template Variables
     * Supported: {{first_name}}, {{last_name}}, {{sender_email}}, {{subject}}, {{date}}
     */
    public function renderVariables(string $template, array $data): string {
        $name = trim($data['sender_name'] ?? '');
        $nameParts = explode(' ', $name);
        $firstName = $nameParts[0] ?? 'Friend';
        $lastName = count($nameParts) > 1 ? end($nameParts) : '';

        $cleanSubject = preg_replace('/^Re:\s*/i', '', $data['subject'] ?? '');

        $replacements = [
            '{{first_name}}' => $firstName ?: 'There',
            '{{last_name}}' => $lastName,
            '{{sender_email}}' => $data['sender_email'] ?? '',
            '{{subject}}' => $cleanSubject,
            '{{date}}' => date('F j, Y', strtotime($data['date'] ?? 'now')),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Calculate allowed send time considering delay, timezone, working days, and working hours
     */
    public function calculateNextAllowedSendTime(bool $forceTomorrow = false, int $delaySeconds = 0): string {
        $timezoneStr = $this->settings->timezone ?? 'Asia/Dhaka';
        $tz = new DateTimeZone($timezoneStr);
        $now = new DateTime('now', $tz);

        if ($forceTomorrow) {
            $now->modify('+1 day');
        }

        if ($delaySeconds > 0) {
            $now->modify("+{$delaySeconds} seconds");
        }

        $workingDays = array_map('trim', explode(',', $this->settings->working_days ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'));
        $startParts = explode(':', $this->settings->working_start ?? '00:00');
        $endParts = explode(':', $this->settings->working_end ?? '23:59');

        $startHour = (int)($startParts[0] ?? 0);
        $startMin = (int)($startParts[1] ?? 0);
        $endHour = (int)($endParts[0] ?? 23);
        $endMin = (int)($endParts[1] ?? 59);

        // Adjust if current time is outside allowed working window
        $maxAttempts = 14;
        while ($maxAttempts-- > 0) {
            $dayName = $now->format('l');
            if (!in_array($dayName, $workingDays)) {
                $now->modify('+1 day');
                $now->setTime($startHour, $startMin, 0);
                continue;
            }

            $currentHour = (int)$now->format('G');
            $currentMin = (int)$now->format('i');
            $currentTotalMin = ($currentHour * 60) + $currentMin;
            $startTotalMin = ($startHour * 60) + $startMin;
            $endTotalMin = ($endHour * 60) + $endMin;

            if ($currentTotalMin < $startTotalMin) {
                $now->setTime($startHour, $startMin, 0);
                break;
            } elseif ($currentTotalMin > $endTotalMin) {
                $now->modify('+1 day');
                $now->setTime($startHour, $startMin, 0);
                continue;
            } else {
                break;
            }
        }

        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Evaluate custom automation rules
     */
    private function evaluateRules(string $senderEmail, string $subject, string $body): array {
        $rules = AutomationRule::findByAccountId($this->account->id);
        foreach ($rules as $rule) {
            $match = false;
            $val = strtolower($rule->rule_value);

            if ($rule->rule_type === 'sender_contains' && str_contains(strtolower($senderEmail), $val)) {
                $match = true;
            } elseif ($rule->rule_type === 'subject_contains' && str_contains(strtolower($subject), $val)) {
                $match = true;
            } elseif ($rule->rule_type === 'body_contains' && str_contains(strtolower($body), $val)) {
                $match = true;
            }

            if ($match) {
                if ($rule->action === 'skip') {
                    return ['action' => 'skip'];
                }
                if ($rule->action === 'custom_reply' && $rule->template_id) {
                    $tpl = \App\Models\ReplyTemplate::find($rule->template_id);
                    if ($tpl) {
                        return ['action' => 'custom_reply', 'custom_message' => $tpl->message];
                    }
                }
            }
        }

        return ['action' => 'reply'];
    }

    /**
     * Schedule next follow-up step after a reply has been sent
     */
    public function scheduleNextFollowupStep(EmailThread $thread, int $completedStepNumber = 0): ?ScheduledJob {
        if (!$this->settings || !$this->settings->followup_enabled) {
            return null;
        }

        // If recipient replied or automation stopped, do not schedule follow-up
        if ($thread->automation_status === 'replied' || $thread->automation_status === 'stopped') {
            return null;
        }

        $nextTemplate = FollowupTemplate::findNextStep($this->account->id, $completedStepNumber);
        if (!$nextTemplate) {
            // Sequence completed
            $thread->update(['automation_status' => 'completed', 'next_followup_at' => null]);
            return null;
        }

        $delaySeconds = $nextTemplate->calculateDelaySeconds();
        $scheduledAt = $this->calculateNextAllowedSendTime(false, $delaySeconds);

        $renderedMessage = $this->renderVariables($nextTemplate->message, [
            'sender_email' => $thread->sender_email,
            'sender_name' => $thread->sender_name,
            'subject' => $thread->subject,
            'date' => $thread->last_incoming_at ?? 'now',
        ]);

        $job = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => [
                'recipient_email' => $thread->sender_email,
                'recipient_name' => $thread->sender_name,
                'subject' => $thread->subject,
                'reply_body' => $renderedMessage,
                'step_number' => $nextTemplate->step_number,
                'template_name' => $nextTemplate->name,
            ],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        $thread->update(['next_followup_at' => $scheduledAt]);

        logger("Scheduled Follow-up Step #{$nextTemplate->step_number} for thread {$thread->gmail_thread_id} at {$scheduledAt}", 'info', $this->account->user_id, $this->account->id);

        return $job;
    }
}
