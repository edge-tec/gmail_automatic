<?php
namespace App\Services;

use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\AutomationRule;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\FollowupTemplate;
use App\Models\FollowupCampaign;
use App\Models\FollowupJob;
use App\Models\AutoReplyRecipient;
use App\Models\SkippedEmailLog;
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
        $this->settings = $this->account->getSettings();
        $msgId = (string)($msgData['message_id'] ?? $msgData['id'] ?? '');
        $threadId = (string)($msgData['thread_id'] ?? '');
        $senderEmail = strtolower(trim($msgData['sender_email']));
        $senderName = $msgData['sender_name'];
        $subject = $msgData['subject'];
        $body = $msgData['body'] ?: $msgData['snippet'];
        $date = $msgData['date'];

        // Ignore messages sent by the account itself
        if (strtolower(trim($this->account->gmail_email)) === $senderEmail) {
            return ['status' => 'skipped', 'reason' => 'Self sent message'];
        }

        // 0. Historical Pre-Connection Email Protection
        // Strictly prevent auto-replies, leads, and follow-ups on emails received before account connection baseline
        $connectedAt = $this->account->connected_at ?: ($this->account->initial_sync_at ?: $this->account->created_at);
        $baselineDate = $this->account->baseline_message_date ?: $connectedAt;
        $msgDateUnix = strtotime($date);
        $connectedUnix = $connectedAt ? strtotime($connectedAt) : 0;
        $baselineUnix = $baselineDate ? strtotime($baselineDate) : 0;

        $effectiveCutoff = min(array_filter([$connectedUnix, $baselineUnix]) ?: [time()]);
        $isHistorical = ($msgDateUnix > 0 && $effectiveCutoff > 0 && $msgDateUnix < ($effectiveCutoff - 10));

        // 1. Idempotency & Historical Check
        $existingMsg = EmailMessage::findByAccountAndMessageId($this->account->id, $msgId);
        if ($existingMsg) {
            if ($existingMsg->is_historical || $existingMsg->status === 'historical') {
                return ['status' => 'skipped', 'reason' => 'Historical email received before Gmail account connection'];
            }
            logger("Duplicate email prevented for account {$this->account->gmail_email} in thread {$threadId} (Message: {$msgId})", 'info', $this->account->user_id, $this->account->id);
            return ['status' => 'duplicate', 'message_id' => $msgId];
        }

        if ($isHistorical) {
            $hThread = EmailThread::createOrGet($this->account->id, $threadId, [
                'sender_email' => $senderEmail,
                'sender_name' => $senderName,
                'subject' => $subject,
                'automation_status' => 'historical',
            ]);

            EmailMessage::create([
                'thread_id' => $hThread->id,
                'gmail_account_id' => $this->account->id,
                'gmail_message_id' => $msgId,
                'direction' => 'incoming',
                'sender' => $senderEmail,
                'recipient' => $this->account->gmail_email,
                'subject' => $subject,
                'snippet' => $msgData['snippet'] ?? '',
                'message_body' => $body,
                'received_at' => $date,
                'status' => 'historical',
                'is_historical' => 1,
            ]);

            logger("Ignored pre-existing historical email from {$senderEmail} in thread {$threadId} (received at {$date} before account connection at {$connectedAt})", 'info', $this->account->user_id, $this->account->id);
            return [
                'status' => 'skipped',
                'reason' => 'Historical email received before Gmail account connection'
            ];
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

        // 4. If recipient wrote back AFTER an outgoing message was sent, mark thread as replied and stop pending follow-up campaigns
        $hasSentOutgoing = ($thread->reply_count > 0 || $thread->followup_count > 0) && !empty($thread->last_outgoing_at);
        $isReplyToUs = ($hasSentOutgoing && (strtotime($date) >= (strtotime($thread->last_outgoing_at) - 60)))
            || !empty($msgData['in_reply_to'])
            || (!empty($msgData['is_reply']) && $hasSentOutgoing);

        if ($isReplyToUs) {
            $campaign = FollowupCampaign::findByThreadId($thread->id);
            if ($campaign && $campaign->campaign_status === 'active') {
                $campaign->markReplied();
            }
            \App\Core\Database::execute(
                "UPDATE scheduled_jobs SET status = 'cancelled', last_error = 'Recipient replied to email', processed_at = :now WHERE thread_id = :tid AND job_type = 'follow_up' AND status = 'pending'",
                ['tid' => $thread->id, 'now' => date('Y-m-d H:i:s')]
            );
            $thread->update([
                'automation_status' => 'replied',
                'next_followup_at' => null,
            ]);
            logger("Recipient replied in thread {$threadId}. Follow-up campaign stopped.", 'info', $this->account->user_id, $this->account->id);
        }

        // Check if thread automation is stopped manually
        if ($thread->automation_status === 'stopped') {
            return ['status' => 'skipped', 'reason' => 'Thread automation manually stopped'];
        }

        // 5. Check Global Automation switch
        if (SystemSetting::get('global_automation_enabled', '1') !== '1') {
            return ['status' => 'skipped', 'reason' => 'Global automation is disabled'];
        }

        // 6. Check Blacklist Rules (Admin + Account Level: Emails, Domains, and Content/Keywords)
        $blacklistDecision = $this->checkBlacklistRules($senderEmail, $subject, $body);
        if ($blacklistDecision['action'] === 'skip') {
            $reason = $blacklistDecision['reason'];
            try {
                SkippedEmailLog::create([
                    'user_id' => $this->account->user_id,
                    'gmail_account_id' => $this->account->id,
                    'thread_id' => $thread->id,
                    'gmail_thread_id' => $threadId,
                    'gmail_message_id' => $msgId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'recipient_email' => $this->account->gmail_email,
                    'subject' => $subject,
                    'snippet' => $msgData['snippet'] ?? '',
                    'skip_reason' => $reason,
                    'skip_type' => 'blacklist',
                    'received_at' => $date,
                ]);
            } catch (\Throwable $t) {}
            logger("Skipped incoming email from {$senderEmail}: {$reason}", 'warning', $this->account->user_id, $this->account->id);
            return ['status' => 'skipped', 'reason' => $reason];
        }

        // 7. Anti-Spam / Multi-Recipient & Bulk Header Checks (Skip if multiple To, CC, BCC, or bulk spam)
        $spamCheck = $this->checkSpamAndMultiRecipients($msgData);
        if ($spamCheck['is_spam']) {
            $reason = $spamCheck['reason'];
            try {
                SkippedEmailLog::create([
                    'user_id' => $this->account->user_id,
                    'gmail_account_id' => $this->account->id,
                    'thread_id' => $thread->id,
                    'gmail_thread_id' => $threadId,
                    'gmail_message_id' => $msgId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'recipient_email' => $this->account->gmail_email,
                    'subject' => $subject,
                    'snippet' => $msgData['snippet'] ?? '',
                    'skip_reason' => $reason,
                    'skip_type' => 'spam_filter',
                    'received_at' => $date,
                ]);
            } catch (\Throwable $t) {}
            logger("Skipped spam/bulk incoming email from {$senderEmail}: {$reason}", 'warning', $this->account->user_id, $this->account->id);
            return ['status' => 'skipped', 'reason' => $reason];
        }

        // 8. Check Account Auto Reply Settings
        if (!$this->settings || !$this->settings->auto_reply_enabled) {
            // If auto-reply is disabled, but follow-up is enabled, schedule follow-up campaign step 1
            if ($this->settings && $this->settings->followup_enabled && $thread->reply_count === 0 && $thread->followup_count === 0) {
                $job = $this->scheduleNextFollowupStep($thread, 0);
                return [
                    'status' => 'followup_scheduled',
                    'job_id' => $job?->id,
                    'reason' => 'Auto reply disabled; follow-up sequence initiated'
                ];
            }
            return ['status' => 'skipped', 'reason' => 'Auto reply disabled for account'];
        }

        // 9. Check Custom Automation Rules (sender/subject/body filters)
        $ruleDecision = $this->evaluateRules($senderEmail, $subject, $body);
        if ($ruleDecision['action'] === 'skip') {
            $reason = $ruleDecision['reason'] ?? 'Skipped by custom automation rule';
            try {
                SkippedEmailLog::create([
                    'user_id' => $this->account->user_id,
                    'gmail_account_id' => $this->account->id,
                    'thread_id' => $thread->id,
                    'gmail_thread_id' => $threadId,
                    'gmail_message_id' => $msgId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'recipient_email' => $this->account->gmail_email,
                    'subject' => $subject,
                    'snippet' => $msgData['snippet'] ?? '',
                    'skip_reason' => $reason,
                    'skip_type' => 'rule_skip',
                    'received_at' => $date,
                ]);
            } catch (\Throwable $t) {}
            logger("Skipped incoming email from {$senderEmail}: {$reason}", 'warning', $this->account->user_id, $this->account->id);
            return ['status' => 'skipped', 'reason' => $reason];
        }

        // 9.5. Auto-Reply Sequence & Duplicate Traffic Protection
        $totalConfiguredSteps = $this->settings->getTotalConfiguredReplySteps();
        if ($totalConfiguredSteps <= 0) {
            return ['status' => 'skipped', 'reason' => 'Message content is missing. Automated email was not sent.'];
        }

        $requireRecipientReply = (bool)($this->settings->require_recipient_reply_before_next_reply ?? false);

        $claimResult = AutoReplyRecipient::claimOrGetForSequence(
            $this->account->user_id,
            $this->account->id,
            $senderEmail,
            $totalConfiguredSteps,
            $msgId,
            $threadId,
            $requireRecipientReply,
            $isReplyToUs
        );

        if (!$claimResult['is_eligible']) {
            if (($claimResult['skip_type'] ?? '') === 'awaiting_recipient_reply') {
                $reason = $claimResult['skip_reason'] ?? "Waiting for recipient to reply to previous auto-reply before sending next reply";
                try {
                    SkippedEmailLog::create([
                        'user_id' => $this->account->user_id,
                        'gmail_account_id' => $this->account->id,
                        'thread_id' => $thread->id,
                        'gmail_thread_id' => $threadId,
                        'gmail_message_id' => $msgId,
                        'sender_email' => $senderEmail,
                        'sender_name' => $senderName,
                        'recipient_email' => $this->account->gmail_email,
                        'subject' => $subject,
                        'snippet' => $msgData['snippet'] ?? '',
                        'skip_reason' => $reason,
                        'skip_type' => 'awaiting_recipient_reply',
                        'received_at' => $date,
                    ]);
                } catch (\Throwable $t) {}
                logger("Skipped incoming email from {$senderEmail}: {$reason}", 'info', $this->account->user_id, $this->account->id);
                return [
                    'status' => 'skipped',
                    'reason' => $reason,
                    'skip_type' => 'awaiting_recipient_reply',
                ];
            }

            if ($claimResult['is_duplicate']) {
                $reason = "Duplicate traffic: Auto-reply sequence ({$totalConfiguredSteps}/{$totalConfiguredSteps} steps) already completed for {$senderEmail} on this account";
                $firstReplySentAt = $claimResult['recipient']?->reply_sent_at;
                try {
                    SkippedEmailLog::create([
                        'user_id' => $this->account->user_id,
                        'gmail_account_id' => $this->account->id,
                        'thread_id' => $thread->id,
                        'gmail_thread_id' => $threadId,
                        'gmail_message_id' => $msgId,
                        'sender_email' => $senderEmail,
                        'sender_name' => $senderName,
                        'recipient_email' => $this->account->gmail_email,
                        'subject' => $subject,
                        'snippet' => $msgData['snippet'] ?? '',
                        'skip_reason' => $reason,
                        'skip_type' => 'duplicate_traffic',
                        'first_reply_sent_at' => $firstReplySentAt,
                        'received_at' => $date,
                    ]);
                } catch (\Throwable $t) {}
                logger("Skipped incoming email: {$reason}", 'info', $this->account->user_id, $this->account->id);
                return [
                    'status' => 'skipped',
                    'reason' => $reason,
                    'is_duplicate_traffic' => true,
                ];
            }
        }

        if ($isReplyToUs && isset($claimResult['recipient']) && $claimResult['recipient']) {
            $claimResult['recipient']->markRecipientReplied($claimResult['recipient']->reply_sequence_step, $date);
        }

        $nextReplyStep = (int)($claimResult['next_step'] ?? 1);

        // Check Daily Reply Limit for Starting New Traffic Sequences (Step 1)
        // Active sequences (Step 2, 3, 4, 5...) are not blocked by the daily new-traffic limit
        $usage = $this->account->getTodayUsage();
        $stepDelay = $this->settings->getReplyDelaySecondsForStep($nextReplyStep);

        $recipientObj = $claimResult['recipient'] ?? null;
        $isNewTrafficSequence = ($nextReplyStep === 1 && (!$recipientObj || $recipientObj->daily_counted === 0 || $recipientObj->counted_date !== date('Y-m-d')));

        if ($isNewTrafficSequence && $usage['reply_count'] >= ($this->settings->daily_reply_limit ?? 100)) {
            // Schedule reply for next day beginning of working hour
            $scheduledAt = $this->calculateNextAllowedSendTime(true);
        } else {
            $scheduledAt = $this->calculateNextAllowedSendTime(false, $stepDelay);
        }

        // 10. Prepare Reply Message for Step #$nextReplyStep with Template Variables
        $templateMessage = $ruleDecision['custom_message'] ?? $this->settings->getReplyMessageForStep($nextReplyStep);
        $cleanCheck = trim(strip_tags($templateMessage, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
        $isPlaceholder = in_array(trim($templateMessage), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
        if (empty($cleanCheck) || $isPlaceholder) {
            logger("Message content is missing for Step #{$nextReplyStep}. Automated email was not sent.", 'warning', $this->account->user_id, $this->account->id);
            return ['status' => 'skipped', 'reason' => "Message content is missing for Step #{$nextReplyStep}. Automated email was not sent."];
        }

        $renderedMessage = $this->renderVariables($templateMessage, [
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'subject' => $subject,
            'date' => $date,
        ]);

        logger("Prepared Auto-Reply Step #{$nextReplyStep} for {$senderEmail}: " . substr(strip_tags($renderedMessage), 0, 120), 'info', $this->account->user_id, $this->account->id);

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
                'total_steps' => $totalConfiguredSteps,
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
            '{{sender_name}}' => $name ?: 'Friend',
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
        $appTz = new \DateTimeZone(date_default_timezone_get());
        $userTzStr = $this->settings->timezone ?? date_default_timezone_get();
        if (empty($userTzStr)) {
            $userTzStr = date_default_timezone_get();
        }
        try {
            $userTz = new \DateTimeZone($userTzStr);
        } catch (\Throwable $e) {
            $userTz = $appTz;
        }

        $userNow = new \DateTime('now', $userTz);

        if ($forceTomorrow) {
            $userNow->modify('+1 day');
        }

        if ($delaySeconds > 0) {
            $userNow->modify("+{$delaySeconds} seconds");
        }

        $workingDays = array_map('trim', explode(',', $this->settings->working_days ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'));
        $workingDays = array_filter($workingDays);
        if (empty($workingDays)) {
            $workingDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        }

        $workingStart = !empty($this->settings->working_start) ? $this->settings->working_start : '00:00';
        $workingEnd = !empty($this->settings->working_end) ? $this->settings->working_end : '23:59';

        $startParts = explode(':', $workingStart);
        $endParts = explode(':', $workingEnd);

        $startHour = (int)($startParts[0] ?? 0);
        $startMin = (int)($startParts[1] ?? 0);
        $endHour = (int)($endParts[0] ?? 23);
        $endMin = (int)($endParts[1] ?? 59);

        // Adjust if current time is outside allowed working window
        $maxAttempts = 14;
        while ($maxAttempts-- > 0) {
            $dayName = $userNow->format('l');
            if (!in_array($dayName, $workingDays)) {
                $userNow->modify('+1 day');
                $userNow->setTime($startHour, $startMin, 0);
                continue;
            }

            $currentHour = (int)$userNow->format('G');
            $currentMin = (int)$userNow->format('i');
            $currentTotalMin = ($currentHour * 60) + $currentMin;
            $startTotalMin = ($startHour * 60) + $startMin;
            $endTotalMin = ($endHour * 60) + $endMin;

            if ($currentTotalMin < $startTotalMin) {
                $userNow->setTime($startHour, $startMin, 0);
                break;
            } elseif ($currentTotalMin > $endTotalMin) {
                $userNow->modify('+1 day');
                $userNow->setTime($startHour, $startMin, 0);
                continue;
            } else {
                break;
            }
        }

        $userNow->setTimezone($appTz);
        return $userNow->format('Y-m-d H:i:s');
    }

    /**
     * Check both Global Admin and Account-Level User Blacklist rules (Email, Domain, and Content)
     */
    private function checkBlacklistRules(string $senderEmail, string $subject, string $body): array {
        $senderEmailClean = strtolower(trim($senderEmail));
        $senderParts = explode('@', $senderEmailClean);
        $senderDomain = isset($senderParts[1]) ? trim($senderParts[1]) : '';
        $combinedContent = strtolower($subject . ' ' . strip_tags($body));

        // 1. Global Admin Blacklisted Emails
        $adminEmailsRaw = SystemSetting::get('blacklist_emails', '');
        $adminBlacklistedEmails = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($adminEmailsRaw))));
        if (in_array($senderEmailClean, $adminBlacklistedEmails)) {
            return ['action' => 'skip', 'reason' => "Sender email '{$senderEmailClean}' is blacklisted by admin"];
        }

        // 2. User Account Blacklisted Emails
        if ($this->settings) {
            $userEmailsRaw = $this->settings->getBlacklistEmails();
            $userBlacklistedEmails = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($userEmailsRaw))));
            if (in_array($senderEmailClean, $userBlacklistedEmails)) {
                return ['action' => 'skip', 'reason' => "Sender email '{$senderEmailClean}' is in account blacklist"];
            }
        }

        // 3. Global Admin Blacklisted Domains & Extensions (.net, .bi, .xyz, spamdomain.com)
        $adminDomainsRaw = SystemSetting::get('blacklist_domains', '');
        $adminBlacklistedDomains = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($adminDomainsRaw))));
        if (!empty($senderDomain)) {
            foreach ($adminBlacklistedDomains as $bDomain) {
                $bDomain = ltrim($bDomain, '@');
                if (empty($bDomain)) continue;
                if (str_starts_with($bDomain, '.')) {
                    if (str_ends_with($senderDomain, $bDomain)) {
                        return ['action' => 'skip', 'reason' => "Sender domain extension '{$bDomain}' is blacklisted by admin"];
                    }
                } elseif ($senderDomain === $bDomain || str_ends_with($senderDomain, '.' . $bDomain)) {
                    return ['action' => 'skip', 'reason' => "Sender domain '@{$senderDomain}' is blacklisted by admin (pattern: {$bDomain})"];
                }
            }
        }

        // 4. User Account Blacklisted Domains & Extensions
        if ($this->settings && !empty($senderDomain)) {
            $userDomainsRaw = $this->settings->getBlacklistDomains();
            $userBlacklistedDomains = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($userDomainsRaw))));
            foreach ($userBlacklistedDomains as $bDomain) {
                $bDomain = ltrim($bDomain, '@');
                if (empty($bDomain)) continue;
                if (str_starts_with($bDomain, '.')) {
                    if (str_ends_with($senderDomain, $bDomain)) {
                        return ['action' => 'skip', 'reason' => "Sender domain extension '{$bDomain}' is in account blacklist"];
                    }
                } elseif ($senderDomain === $bDomain || str_ends_with($senderDomain, '.' . $bDomain)) {
                    return ['action' => 'skip', 'reason' => "Sender domain '@{$senderDomain}' matches account blacklisted pattern '{$bDomain}'"];
                }
            }
        }

        // 5. Global Admin Blacklisted Content / Keywords
        $adminKeywordsRaw = SystemSetting::get('blacklist_keywords', '');
        $adminBlacklistedKeywords = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($adminKeywordsRaw))));
        foreach ($adminBlacklistedKeywords as $keyword) {
            if (!empty($keyword) && str_contains($combinedContent, $keyword)) {
                return ['action' => 'skip', 'reason' => "Content matches admin blacklisted keyword '{$keyword}'"];
            }
        }

        // 6. User Account Blacklisted Content / Keywords
        if ($this->settings) {
            $userKeywordsRaw = $this->settings->getBlacklistKeywords();
            $userBlacklistedKeywords = array_filter(array_map('trim', preg_split('/[\r\n,]+/', strtolower($userKeywordsRaw))));
            foreach ($userBlacklistedKeywords as $keyword) {
                if (!empty($keyword) && str_contains($combinedContent, $keyword)) {
                    return ['action' => 'skip', 'reason' => "Content matches account blacklisted keyword '{$keyword}'"];
                }
            }
        }

        return ['action' => 'pass'];
    }

    /**
     * Check if incoming message is a mass blast, spam, or contains multiple recipients (To / CC / BCC)
     */
    private function checkSpamAndMultiRecipients(array $msgData): array {
        $toHeader = $msgData['to'] ?? '';
        $ccHeader = $msgData['cc'] ?? '';
        $bccHeader = $msgData['bcc'] ?? '';
        $autoSubmitted = strtolower(trim($msgData['auto_submitted'] ?? ''));
        $precedence = strtolower(trim($msgData['precedence'] ?? ''));
        $subject = strtolower(trim($msgData['subject'] ?? ''));

        // 1. Multiple recipients in To header
        $toCount = $this->extractEmailCount($toHeader);
        if ($toCount > 1) {
            return [
                'is_spam' => true,
                'reason' => "Mass/Spam email skipped: Multiple recipients ({$toCount}) in 'To' header"
            ];
        }

        // 2. Check for CC / BCC recipients (bulk email)
        $ccCount = $this->extractEmailCount($ccHeader);
        if ($ccCount > 0) {
            return [
                'is_spam' => true,
                'reason' => "Mass/Spam email skipped: 'CC' recipients ({$ccCount}) detected"
            ];
        }

        $bccCount = $this->extractEmailCount($bccHeader);
        if ($bccCount > 0) {
            return [
                'is_spam' => true,
                'reason' => "Mass/Spam email skipped: 'BCC' recipients ({$bccCount}) detected"
            ];
        }

        // 3. Automated / Bulk / System Headers
        if (in_array($autoSubmitted, ['auto-generated', 'auto-replied', 'auto-notified'])) {
            return [
                'is_spam' => true,
                'reason' => "System/Bot email skipped: Auto-Submitted header ({$autoSubmitted})"
            ];
        }

        if (in_array($precedence, ['bulk', 'junk', 'list'])) {
            return [
                'is_spam' => true,
                'reason' => "Mass mailing skipped: Precedence header ({$precedence})"
            ];
        }

        // 4. Delivery status / Mailer-daemon failure notices
        $deliveryKeywords = ['delivery status notification', 'undelivered mail', 'mail delivery failed', 'failure notice', 'returned mail:'];
        foreach ($deliveryKeywords as $kw) {
            if (str_contains($subject, $kw)) {
                return [
                    'is_spam' => true,
                    'reason' => "Delivery failure notice skipped: subject contains '{$kw}'"
                ];
            }
        }

        return ['is_spam' => false, 'reason' => ''];
    }

    private function extractEmailCount(string $headerValue): int {
        if (empty(trim($headerValue))) {
            return 0;
        }
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $headerValue, $matches);
        if (empty($matches[0])) {
            return 0;
        }
        return count(array_unique(array_map('strtolower', $matches[0])));
    }

    /**
     * Evaluate custom automation rules
     */
    private function evaluateRules(string $senderEmail, string $subject, string $body): array {
        $rules = AutomationRule::findByAccountId($this->account->id);
        $senderEmailLower = strtolower($senderEmail);
        $senderParts = explode('@', $senderEmailLower);
        $senderDomain = isset($senderParts[1]) ? trim($senderParts[1]) : '';

        foreach ($rules as $rule) {
            $match = false;
            $val = strtolower(trim($rule->rule_value));

            if ($rule->rule_type === 'sender_contains' && str_contains($senderEmailLower, $val)) {
                $match = true;
            } elseif ($rule->rule_type === 'domain_extension' && str_ends_with($senderDomain, '.' . ltrim($val, '.'))) {
                $match = true;
            } elseif ($rule->rule_type === 'sender_domain') {
                $cleanVal = ltrim($val, '@');
                if (str_starts_with($cleanVal, '.')) {
                    if (str_ends_with($senderDomain, $cleanVal)) {
                        $match = true;
                    }
                } elseif ($senderDomain === $cleanVal || str_ends_with($senderDomain, '.' . $cleanVal)) {
                    $match = true;
                }
            } elseif ($rule->rule_type === 'subject_contains' && str_contains(strtolower($subject), $val)) {
                $match = true;
            } elseif ($rule->rule_type === 'body_contains' && str_contains(strtolower($body), $val)) {
                $match = true;
            }

            if ($match) {
                if ($rule->action === 'skip') {
                    return ['action' => 'skip', 'reason' => "Skipped by filter rule #{$rule->id} ({$rule->rule_type}: '{$rule->rule_value}')"];
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
     * Schedule next follow-up step after a reply or previous follow-up has been sent
     */
    public function scheduleNextFollowupStep(EmailThread $thread, int $completedStepNumber = 0): ?ScheduledJob {
        if (!$this->settings || !$this->settings->followup_enabled) {
            return null;
        }

        // If recipient replied or automation stopped, do not schedule follow-up
        if ($thread->automation_status === 'replied' || $thread->automation_status === 'stopped') {
            return null;
        }

        $allTemplates = FollowupTemplate::findByAccountId($this->account->id);

        // Get or create unique FollowupCampaign for this thread
        $campaign = FollowupCampaign::getOrCreate(
            $this->account->user_id,
            $this->account->id,
            $thread->id,
            $thread->gmail_thread_id,
            [
                'message_id' => $thread->last_processed_message_id,
                'sender_email' => $thread->sender_email,
                'recipient_email' => $this->account->gmail_email,
                'subject' => $thread->subject,
                'total_steps' => count($allTemplates),
            ]
        );

        if ($campaign->campaign_status === 'replied' || $campaign->campaign_status === 'stopped' || $campaign->campaign_status === 'cancelled') {
            return null;
        }

        $nextTemplate = FollowupTemplate::findNextStep($this->account->id, $completedStepNumber);
        $nextMsg = $nextTemplate ? $nextTemplate->message : '';
        $cleanCheck = trim(strip_tags($nextMsg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
        $isPlaceholder = in_array(trim($nextMsg), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
        if (!$nextTemplate || empty($cleanCheck) || $isPlaceholder) {
            // Sequence completed or template missing/empty
            $campaign->markCompleted();
            $thread->update(['automation_status' => 'completed', 'next_followup_at' => null]);
            return null;
        }

        $usage = $this->account->getTodayUsage();
        $delaySeconds = $nextTemplate->calculateDelaySeconds();

        // Check if daily follow quota was already counted for this campaign
        // New campaign (not counted yet today) vs Existing campaign in sequence
        if ($campaign->daily_follow_counted === 0 && $usage['followup_count'] >= ($this->settings->daily_followup_limit ?? 100)) {
            // Limit reached for NEW campaigns today -> postpone step 1 to next day allowed window
            $scheduledAt = $this->calculateNextAllowedSendTime(true);
        } else {
            $scheduledAt = $this->calculateNextAllowedSendTime(false, $delaySeconds);
        }

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
                'campaign_id' => $campaign->id,
                'recipient_email' => $thread->sender_email,
                'recipient_name' => $thread->sender_name,
                'subject' => $thread->subject,
                'reply_body' => $renderedMessage,
                'template_id' => $nextTemplate->id,
                'step_number' => $nextTemplate->step_number,
                'template_name' => $nextTemplate->name,
            ],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        FollowupJob::create([
            'campaign_id' => $campaign->id,
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'followup_step' => $nextTemplate->step_number,
            'template_id' => $nextTemplate->id,
            'message' => $renderedMessage,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        $campaign->update([
            'current_step' => $nextTemplate->step_number,
            'next_step_at' => $scheduledAt,
            'campaign_status' => 'active',
        ]);

        $thread->update(['next_followup_at' => $scheduledAt]);

        logger("Scheduled Follow-up Step #{$nextTemplate->step_number} (Campaign #{$campaign->id}) for thread {$thread->gmail_thread_id} at {$scheduledAt}", 'info', $this->account->user_id, $this->account->id);

        return $job;
    }
}
