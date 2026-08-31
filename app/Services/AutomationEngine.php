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

        // 4. If recipient replied, cancel any pending unanswered follow-up sequences
        if ($thread->reply_count > 0 || $thread->followup_count > 0) {
            ScheduledJob::cancelPendingJobsForThread($thread->id, 'Recipient replied to email');
            $thread->update([
                'next_followup_at' => null,
            ]);
            logger("Recipient replied in thread {$threadId}. Preparing next reply step.", 'info', $this->account->user_id, $this->account->id);
        }

        // Check if thread automation is stopped manually
        if ($thread->automation_status === 'stopped') {
            return ['status' => 'skipped', 'reason' => 'Thread automation manually stopped'];
        }

        // 5. Check Global Automation switch
        if (SystemSetting::get('global_automation_enabled', '1') !== '1') {
            return ['status' => 'skipped', 'reason' => 'Global automation is disabled'];
        }

        // 6. Check Global Admin Blacklist (Emails, Domains, and Content/Keywords)
        $blacklistDecision = $this->checkAdminBlacklist($senderEmail, $subject, $body);
        if ($blacklistDecision['action'] === 'skip') {
            logger("Skipped incoming email from {$senderEmail}: {$blacklistDecision['reason']}", 'warning', $this->account->user_id, $this->account->id);
            return ['status' => 'skipped', 'reason' => $blacklistDecision['reason']];
        }

        // 7. Check Account Auto Reply Settings
        if (!$this->settings || !$this->settings->auto_reply_enabled) {
            return ['status' => 'skipped', 'reason' => 'Auto reply disabled for account'];
        }

        // 8. Check Custom Automation Rules (sender/subject/body filters)
        $ruleDecision = $this->evaluateRules($senderEmail, $subject, $body);
        if ($ruleDecision['action'] === 'skip') {
            return ['status' => 'skipped', 'reason' => 'Skipped by custom automation rule'];
        }

        // 9. Check Per-Thread Reply Limit
        $nextReplyStep = $thread->reply_count + 1;
        if ($thread->reply_count >= $this->settings->max_reply_per_thread) {
            $thread->update(['automation_status' => 'completed']);
            return ['status' => 'limit_reached', 'reason' => "Max reply per thread reached ({$this->settings->max_reply_per_thread})"];
        }

        // 9. Check Daily Reply Limit (Applies to new leads/traffic; multiple replies to the same lead count as 1)
        $usage = $this->account->getTodayUsage();
        $stepDelay = $this->settings->getReplyDelaySecondsForStep($nextReplyStep);

        if ($thread->reply_count === 0 && $usage['reply_count'] >= $this->settings->daily_reply_limit) {
            // Schedule new lead reply for next day beginning of working hour
            $scheduledAt = $this->calculateNextAllowedSendTime(true);
        } else {
            $scheduledAt = $this->calculateNextAllowedSendTime(false, $stepDelay);
        }

        // 10. Prepare Reply Message for Step #$nextReplyStep with Template Variables
        $templateMessage = $ruleDecision['custom_message'] ?? $this->settings->getReplyMessageForStep($nextReplyStep);
        $renderedMessage = $this->renderVariables($templateMessage, [
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'subject' => $subject,
            'date' => $date,
        ]);

        // 11. Schedule the Auto Reply Job (Step #$nextReplyStep)
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
                'reply_step' => $nextReplyStep,
            ],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        $thread->update(['automation_status' => 'active']);

        logger("Scheduled Auto-Reply Step #{$nextReplyStep} for thread {$threadId} to {$senderEmail} at {$scheduledAt}", 'info', $this->account->user_id, $this->account->id);

        return [
            'status' => 'scheduled',
            'job_id' => $job->id,
            'reply_step' => $nextReplyStep,
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
     * Check global admin blacklist rules (Email, Domain, and Content)
     */
    private function checkAdminBlacklist(string $senderEmail, string $subject, string $body): array {
        $senderEmailClean = strtolower(trim($senderEmail));
        
        // 1. Blacklisted Emails
        $blacklistEmailsRaw = SystemSetting::get('blacklist_emails', '');
        if (!empty($blacklistEmailsRaw)) {
            $blacklistedEmails = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($blacklistEmailsRaw))));
            if (in_array($senderEmailClean, $blacklistedEmails)) {
                return ['action' => 'skip', 'reason' => "Sender email '{$senderEmailClean}' is blacklisted by admin"];
            }
        }

        // 2. Blacklisted Domains
        $blacklistDomainsRaw = SystemSetting::get('blacklist_domains', '');
        if (!empty($blacklistDomainsRaw)) {
            $blacklistedDomains = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($blacklistDomainsRaw))));
            $senderParts = explode('@', $senderEmailClean);
            $senderDomain = isset($senderParts[1]) ? trim($senderParts[1]) : '';
            if (!empty($senderDomain)) {
                foreach ($blacklistedDomains as $bDomain) {
                    $bDomain = ltrim($bDomain, '@');
                    if ($senderDomain === $bDomain || str_ends_with($senderDomain, '.' . $bDomain)) {
                        return ['action' => 'skip', 'reason' => "Sender domain '@{$senderDomain}' is blacklisted by admin"];
                    }
                }
            }
        }

        // 3. Blacklisted Content / Keywords
        $blacklistKeywordsRaw = SystemSetting::get('blacklist_keywords', '');
        if (!empty($blacklistKeywordsRaw)) {
            $blacklistedKeywords = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($blacklistKeywordsRaw))));
            $combinedContent = strtolower($subject . ' ' . strip_tags($body));
            foreach ($blacklistedKeywords as $keyword) {
                if (!empty($keyword) && str_contains($combinedContent, $keyword)) {
                    return ['action' => 'skip', 'reason' => "Content matches blacklisted keyword '{$keyword}'"];
                }
            }
        }

        return ['action' => 'pass'];
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
