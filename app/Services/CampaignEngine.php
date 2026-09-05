<?php
namespace App\Services;

use App\Core\Database;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaignMessage;
use App\Models\EmailCampaignSuppression;
use App\Models\GmailCampaignDailyUsage;
use App\Models\GmailAccount;
use App\Services\GmailService;
use Exception;

class CampaignEngine {

    /**
     * Process a batch of ready campaign emails.
     * Called by QueueWorker and Cron runner.
     * 
     * @param int $maxSends Maximum number of campaign emails to attempt in this run
     * @return int Number of successfully sent emails
     */
    public static function processBatch(int $maxSends = 25): int {
        $activeCampaigns = EmailCampaign::allActive();
        if (empty($activeCampaigns)) {
            return 0;
        }

        $totalSent = 0;

        foreach ($activeCampaigns as $campaign) {
            if ($totalSent >= $maxSends) {
                break;
            }

            try {
                $sentFromCampaign = self::processCampaign($campaign, $maxSends - $totalSent);
                $totalSent += $sentFromCampaign;
            } catch (\Throwable $e) {
                logger("Error processing Campaign #{$campaign->id} ({$campaign->name}): " . $e->getMessage(), 'error', $campaign->user_id);
            }
        }

        return $totalSent;
    }

    /**
     * Process sending for a specific campaign
     */
    public static function processCampaign(EmailCampaign $campaign, int $limit = 10, bool $bypassInterval = false): int {
        $sentCount = 0;

        for ($i = 0; $i < $limit; $i++) {
            // 1. Re-validate Campaign is active
            $freshCampaign = EmailCampaign::find($campaign->id);
            if (!$freshCampaign || $freshCampaign->status !== 'active') {
                break;
            }
            $campaign = $freshCampaign;

            // 2. Check Schedule & Timezone
            if (!$campaign->isWithinSendingSchedule()) {
                break;
            }

            // 3. Check Sending Interval (throttle)
            if (!$bypassInterval && $campaign->last_sent_at) {
                $elapsed = time() - strtotime($campaign->last_sent_at);
                if ($elapsed < $campaign->sending_interval) {
                    // Interval has not passed yet
                    break;
                }
            }

            // 4. Check Global Campaign Daily Limit
            $sendsToday = $campaign->getSendsCountToday();
            if ($sendsToday >= $campaign->daily_campaign_limit) {
                break; // Daily limit reached for this campaign
            }

            // 5. Select Next Eligible Gmail Account via True Round-Robin
            $selectedAccount = self::getNextRoundRobinAccount($campaign);
            if (!$selectedAccount) {
                // No eligible Gmail account available right now (all reached limit, cooling down, or none connected)
                break;
            }

            // 6. Atomically Claim Next Pending Recipient
            $recipient = EmailCampaignRecipient::claimNextPending($campaign->id);
            if (!$recipient) {
                // No more pending recipients; check if campaign should be marked completed
                $remaining = $campaign->getRemainingCount();
                if ($remaining === 0 && $campaign->total_recipients > 0) {
                    $campaign->update(['status' => 'completed']);
                    $campaign->recalculateStats();
                }
                break;
            }

            // 7. Final Pre-Send Revalidation
            // 7a. Check Suppression (Unsubscribe / Hard Bounce)
            if (EmailCampaignSuppression::isSuppressed($campaign->user_id, $recipient->email, $campaign->id)) {
                $recipient->markSkipped('Recipient is suppressed or unsubscribed');
                $campaign->recalculateStats();
                self::recordAuditSend($campaign->id, $recipient->id, $selectedAccount->id, null, 'skipped', null, 'SUPPRESSED');
                continue;
            }

            // 7b. Reload & Select Random Message Variation
            $message = EmailCampaignMessage::getRandomActiveForCampaign($campaign->id);
            if (!$message) {
                // STRICT ZERO FALLBACK POLICY: Do not call Gmail API if no valid message configured!
                $recipient->resetToPending();
                logger("Campaign #{$campaign->id} has no valid active messages. Sending aborted. Zero fallback enforced.", 'warning', $campaign->user_id);
                break;
            }

            // 8. Personalize Message
            $unsubUrl = url("/unsubscribe?email=" . urlencode($recipient->email) . "&cid=" . $campaign->id . "&t=" . md5($recipient->email . config('app.key', 'secret')));
            $renderedBody = self::renderVariables($message->body, $recipient, $unsubUrl);
            $renderedSubject = self::renderVariables($message->subject, $recipient, $unsubUrl);

            // Double check rendered body is non-empty
            $cleanBody = trim(strip_tags($renderedBody));
            if (empty($cleanBody)) {
                $recipient->resetToPending();
                logger("Rendered message body is empty for recipient {$recipient->email}. Send aborted.", 'warning', $campaign->user_id);
                break;
            }

            // 9. Send via Gmail API
            $now = date('Y-m-d H:i:s');
            $queuedAt = $recipient->claimed_at ?? $now;

            try {
                $gmailService = new GmailService($selectedAccount);
                $extraHeaders = [
                    'List-Unsubscribe' => "<{$unsubUrl}>",
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ];

                $sendResult = $gmailService->sendNewEmail(
                    $recipient->email,
                    $renderedSubject,
                    $renderedBody,
                    $extraHeaders
                );

                $sentMessageId = $sendResult['id'] ?? 'msg_' . uniqid();

                // 10. Record Success
                $recipient->markSent($selectedAccount->id, $sentMessageId);
                GmailCampaignDailyUsage::incrementSent($selectedAccount->id, $campaign->user_id, $selectedAccount->bulk_daily_limit);
                $message->incrementUsage();

                // Advance Round-Robin Pointer & Timestamp
                $campaign->update([
                    'last_used_gmail_account_id' => $selectedAccount->id,
                    'last_sent_at' => $now,
                    'sent_count' => $campaign->sent_count + 1,
                ]);

                // Audit logging
                self::recordAuditSend(
                    $campaign->id,
                    $recipient->id,
                    $selectedAccount->id,
                    $message->id,
                    'sent',
                    $sentMessageId,
                    null,
                    $queuedAt,
                    $recipient->claimed_at,
                    $now
                );

                $selectedAccount->clearTemporaryFailure();
                $sentCount++;

            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $recipient->markFailed($errorMsg);

                GmailCampaignDailyUsage::incrementFailed($selectedAccount->id, $campaign->user_id, $selectedAccount->bulk_daily_limit);
                $campaign->update(['failed_count' => $campaign->failed_count + 1]);

                // Determine error classification
                $isHardBounce = self::isHardBounceError($errorMsg);
                $errorCode = $isHardBounce ? 'HARD_BOUNCE' : 'API_ERROR';

                if ($isHardBounce) {
                    EmailCampaignSuppression::suppress($campaign->user_id, $recipient->email, 'hard_bounce', $campaign->id);
                }

                // If transient API failure or rate limit, put Gmail account on cooldown
                if (str_contains($errorMsg, 'Rate Limit') || str_contains($errorMsg, 'Quota') || str_contains($errorMsg, '429') || str_contains($errorMsg, '503') || str_contains($errorMsg, '500') || str_contains($errorMsg, 'userRateLimitExceeded')) {
                    $selectedAccount->markTemporaryFailure(10);
                }

                self::recordAuditSend(
                    $campaign->id,
                    $recipient->id,
                    $selectedAccount->id,
                    $message->id,
                    'failed',
                    null,
                    $errorCode,
                    $queuedAt,
                    $recipient->claimed_at,
                    null
                );

                logger("Campaign #{$campaign->id} send failure to {$recipient->email} via {$selectedAccount->gmail_email}: {$errorMsg}", 'error', $campaign->user_id, $selectedAccount->id);
            }
        }

        if ($sentCount > 0) {
            $campaign->recalculateStats();
        }

        return $sentCount;
    }

    /**
     * True Round-Robin Gmail Account Selection:
     * - Returns exactly 1 Gmail account per turn.
     * - Advances A -> B -> C -> D -> E -> A -> B...
     * - Accounts reaching daily bulk limit drop out of the pool for the day.
     * - Accounts in temporary failure cooldown drop out until recovered.
     * - Survives worker restart by persisting last_used_gmail_account_id.
     */
    public static function getNextRoundRobinAccount(EmailCampaign $campaign): ?GmailAccount {
        $allAccounts = GmailAccount::findByUserId($campaign->user_id);
        
        // Filter strictly eligible accounts
        $eligible = [];
        foreach ($allAccounts as $acc) {
            if ($acc->isCampaignEligible()) {
                $eligible[] = $acc;
            }
        }

        if (empty($eligible)) {
            return null;
        }

        // Sort ascending by ID for deterministic cyclic order
        usort($eligible, fn(GmailAccount $a, GmailAccount $b) => $a->id <=> $b->id);

        $lastId = $campaign->last_used_gmail_account_id;

        if ($lastId === null) {
            return $eligible[0];
        }

        // Find next account with id > lastId
        foreach ($eligible as $acc) {
            if ($acc->id > $lastId) {
                return $acc;
            }
        }

        // Wrap around to the first eligible account
        return $eligible[0];
    }

    /**
     * Personalize message templates safely. Missing variables become empty strings.
     */
    public static function renderVariables(string $template, EmailCampaignRecipient $recipient, string $unsubUrl = ''): string {
        $vars = [
            '{{email}}' => $recipient->email,
            '{{first_name}}' => $recipient->first_name ?? '',
            '{{last_name}}' => $recipient->last_name ?? '',
            '{{company}}' => $recipient->company ?? '',
            '{{custom_field_1}}' => $recipient->custom_field_1 ?? '',
            '{{custom_field_2}}' => $recipient->custom_field_2 ?? '',
            '{{date}}' => date('Y-m-d'),
            '{{unsubscribe_url}}' => $unsubUrl,
        ];

        // Also check custom_data JSON
        if (!empty($recipient->custom_data)) {
            $cData = is_array($recipient->custom_data) ? $recipient->custom_data : json_decode($recipient->custom_data, true);
            if (is_array($cData)) {
                foreach ($cData as $k => $v) {
                    $vars['{{' . $k . '}}'] = (string)$v;
                }
            }
        }

        $result = str_replace(array_keys($vars), array_values($vars), $template);

        // Safe empty handling for any remaining unmapped {{variable}} tags:
        // replace with empty string, do not inject unexpected text!
        $result = preg_replace('/\{\{[a-zA-Z0-9_\-]+\}\}/', '', $result);

        return $result;
    }

    /**
     * Check if an error message represents a permanent hard bounce
     */
    public static function isHardBounceError(string $error): bool {
        $lower = strtolower($error);
        $hardIndicators = [
            '550', '551', '552', '553', '554',
            'user unknown', 'does not exist', 'recipient address rejected',
            'no mailbox', 'mailbox not found', 'invalid recipient',
            'address rejected', 'undeliverable',
        ];

        foreach ($hardIndicators as $ind) {
            if (str_contains($lower, $ind)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record an audit trail log in email_campaign_sends
     */
    public static function recordAuditSend(
        int $campaignId,
        int $recipientId,
        int $gmailAccountId,
        ?int $messageVariationId,
        string $status,
        ?string $gmailMessageId = null,
        ?string $errorCode = null,
        ?string $queuedAt = null,
        ?string $claimedAt = null,
        ?string $sentAt = null
    ): void {
        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO email_campaign_sends (
                    campaign_id, recipient_id, gmail_account_id, message_variation_id,
                    queued_at, claimed_at, sent_at, status, gmail_message_id, error_code, created_at
                ) VALUES (
                    :cid, :rid, :gid, :mid,
                    :qa, :ca, :sa, :st, :gmid, :err, :now
                )";

        Database::execute($sql, [
            'cid' => $campaignId,
            'rid' => $recipientId,
            'gid' => $gmailAccountId,
            'mid' => $messageVariationId,
            'qa' => $queuedAt ?? $now,
            'ca' => $claimedAt ?? $now,
            'sa' => $sentAt,
            'st' => $status,
            'gmid' => $gmailMessageId,
            'err' => $errorCode,
            'now' => $now,
        ]);
    }
}
