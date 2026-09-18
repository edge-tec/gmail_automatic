<?php
namespace App\Services;

use App\Core\Database;
use App\Models\EmailOpenTracking;
use App\Models\EmailOpenEvent;
use App\Models\GmailAccount;

class EmailOpenTrackingService {

    /**
     * Generate a cryptographically secure 64-character hex token
     */
    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Inject an invisible 1x1 tracking pixel into HTML email content
     */
    public static function injectPixel(string $htmlBody, string $token): string {
        $trackingUrl = url('/tracking/open/' . $token);
        
        $pixelTag = '<img src="' . htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:none !important; width:1px !important; height:1px !important; max-height:1px !important; max-width:1px !important; opacity:0 !important; border:0 !important; outline:none !important; mso-hide:all !important;" />';

        // If body has closing </body> tag, inject right before it
        if (preg_match('/(<\/body\s*>)/i', $htmlBody)) {
            return preg_replace('/(<\/body\s*>)/i', $pixelTag . '$1', $htmlBody, 1);
        }

        // If body has closing </html> tag without </body>, inject before </html>
        if (preg_match('/(<\/html\s*>)/i', $htmlBody)) {
            return preg_replace('/(<\/html\s*>)/i', $pixelTag . '$1', $htmlBody, 1);
        }

        // Otherwise append at the end
        return $htmlBody . $pixelTag;
    }

    /**
     * Prepare tracking before outgoing send.
     * Guaranteed safe: Never throws exceptions to prevent interrupting email send.
     * 
     * @return array [string $trackedHtmlBody, ?EmailOpenTracking $trackingRecord]
     */
    public static function prepareTracking(string $body, array $metadata): array {
        try {
            $token = self::generateToken();

            $trackingRecord = EmailOpenTracking::create([
                'tracking_token' => $token,
                'message_id' => $metadata['message_id'] ?? null,
                'gmail_account_id' => (int)$metadata['gmail_account_id'],
                'user_id' => (int)($metadata['user_id'] ?? 0),
                'recipient_email' => $metadata['recipient_email'],
                'thread_id' => $metadata['thread_id'] ?? null,
                'campaign_id' => $metadata['campaign_id'] ?? null,
                'source_type' => $metadata['source_type'] ?? 'auto_reply',
                'scheduled_job_id' => $metadata['scheduled_job_id'] ?? null,
                'subject' => $metadata['subject'] ?? null,
            ]);

            $trackedBody = self::injectPixel($body, $token);

            return [$trackedBody, $trackingRecord];
        } catch (\Throwable $e) {
            error_log("EmailOpenTrackingService prepareTracking notice: " . $e->getMessage());
            return [$body, null];
        }
    }

    /**
     * Finalize tracking after successful send by attaching confirmed Gmail Message ID
     */
    public static function finalizeTracking(mixed $trackingOrId, ?string $gmailMessageId, ?string $sentAt = null): void {
        if (!$gmailMessageId) return;
        try {
            $record = ($trackingOrId instanceof EmailOpenTracking) 
                ? $trackingOrId 
                : (is_numeric($trackingOrId) ? EmailOpenTracking::find((int)$trackingOrId) : null);

            if ($record) {
                $updates = ['message_id' => $gmailMessageId];
                if ($sentAt) {
                    $updates['created_at'] = $sentAt;
                }
                $record->update($updates);
            }
        } catch (\Throwable $e) {
            error_log("EmailOpenTrackingService finalizeTracking notice: " . $e->getMessage());
        }
    }

    /**
     * Record a real open event from the tracking pixel endpoint
     */
    public static function recordOpen(string $token, mixed $requestMeta = []): ?EmailOpenEvent {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        $tracking = EmailOpenTracking::findByToken($token);
        if (!$tracking) {
            return null;
        }

        if ($requestMeta instanceof \App\Core\Request) {
            $ua = $requestMeta->server('HTTP_USER_AGENT') ?? '';
            $ip = $requestMeta->ip();
            $headers = [
                'accept' => (string)$requestMeta->server('HTTP_ACCEPT', ''),
                'x-forwarded-for' => (string)$requestMeta->server('HTTP_X_FORWARDED_FOR', ''),
            ];
        } else {
            $ua = $requestMeta['user_agent'] ?? '';
            $ip = $requestMeta['ip'] ?? '';
            $headers = $requestMeta['headers'] ?? [];
        }

        $parsed = self::parseUserAgent($ua, $headers);

        try {
            $event = $tracking->recordOpen([
                'ip_address' => $ip,
                'user_agent' => $ua,
                'device_type' => $parsed['device_type'],
                'operating_system' => $parsed['operating_system'],
                'browser' => $parsed['browser'],
                'mail_client' => $parsed['mail_client'],
                'is_bot_or_prefetch' => $parsed['is_bot_or_prefetch'],
                'metadata' => [
                    'recipient_email' => $tracking->recipient_email,
                    'gmail_account_id' => $tracking->gmail_account_id,
                    'message_id' => $tracking->message_id,
                    'source_type' => $tracking->source_type,
                    'headers' => array_intersect_key($headers, array_flip(['accept-language', 'sec-ch-ua', 'sec-ch-ua-platform'])),
                ],
            ]);

            return $event;
        } catch (\Throwable $e) {
            error_log("EmailOpenTrackingService recordOpen error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse User-Agent and headers to extract device, OS, browser, mail client, and bot/proxy detection
     */
    public static function parseUserAgent(string $ua, array $headers = []): array {
        $uaLower = strtolower($ua);
        
        $deviceType = 'Desktop';
        $os = 'Unknown';
        $browser = 'Unknown';
        $mailClient = 'Unknown';
        $isBotOrPrefetch = 0;

        // Check prefetch headers
        $purpose = strtolower($headers['purpose'] ?? $headers['sec-purpose'] ?? $headers['x-purpose'] ?? '');
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview')) {
            $isBotOrPrefetch = 1;
        }

        // 1. Mail Client & Proxy Detection
        if (str_contains($ua, 'GoogleImageProxy')) {
            $mailClient = 'Google Image Proxy';
            $deviceType = 'Bot/Proxy';
            $isBotOrPrefetch = 1;
            $browser = 'Google Proxy';
            $os = 'Google Cloud';
        } elseif (str_contains($ua, 'YahooMailProxy')) {
            $mailClient = 'Yahoo Image Proxy';
            $deviceType = 'Bot/Proxy';
            $isBotOrPrefetch = 1;
        } elseif (str_contains($ua, 'Thunderbird')) {
            $mailClient = 'Mozilla Thunderbird';
            $browser = 'Thunderbird';
        } elseif (str_contains($ua, 'Outlook') || str_contains($ua, 'Microsoft Office')) {
            $mailClient = 'Microsoft Outlook';
            $browser = 'Outlook';
        } elseif (str_contains($ua, 'AppleWebKit') && (str_contains($ua, 'Mobile') || str_contains($ua, 'CFNetwork') || str_contains($uaLower, 'mail'))) {
            $mailClient = 'Apple Mail';
        } elseif (str_contains($uaLower, 'superhuman')) {
            $mailClient = 'Superhuman';
        }

        // If not recognized as dedicated mail client proxy, parse OS and Browser
        if ($os === 'Unknown') {
            if (preg_match('/iPhone/i', $ua)) {
                $os = 'iOS';
                $deviceType = 'Mobile';
            } elseif (preg_match('/iPad/i', $ua)) {
                $os = 'iPadOS';
                $deviceType = 'Tablet';
            } elseif (preg_match('/Android/i', $ua)) {
                $os = 'Android';
                $deviceType = preg_match('/Mobile/i', $ua) ? 'Mobile' : 'Tablet';
            } elseif (preg_match('/Windows NT 10.0/i', $ua)) {
                $os = 'Windows 10/11';
                $deviceType = 'Desktop';
            } elseif (preg_match('/Windows/i', $ua)) {
                $os = 'Windows';
                $deviceType = 'Desktop';
            } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
                $os = 'macOS';
                $deviceType = 'Desktop';
            } elseif (preg_match('/Linux/i', $ua)) {
                $os = 'Linux';
                $deviceType = 'Desktop';
            }
        }

        // Browser Detection
        if ($browser === 'Unknown') {
            if (preg_match('/Edg\/([0-9.]+)/i', $ua, $m)) {
                $browser = 'Microsoft Edge ' . explode('.', $m[1])[0];
            } elseif (preg_match('/Chrome\/([0-9.]+)/i', $ua, $m) && !str_contains($ua, 'Edg')) {
                $browser = 'Google Chrome ' . explode('.', $m[1])[0];
            } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua, $m)) {
                $browser = 'Mozilla Firefox ' . explode('.', $m[1])[0];
            } elseif (preg_match('/Version\/([0-9.]+).*Safari/i', $ua, $m)) {
                $browser = 'Apple Safari ' . explode('.', $m[1])[0];
            } elseif (preg_match('/Safari/i', $ua)) {
                $browser = 'Apple Safari';
            }
        }

        // Check for common bots, crawlers, or previewers
        if (preg_match('/bot|spider|crawl|slurp|facebookexternalhit|whatsapp|telegrambot|preview/i', $ua)) {
            $isBotOrPrefetch = 1;
            $deviceType = 'Bot/Proxy';
        }

        return [
            'device_type' => $deviceType,
            'operating_system' => $os,
            'browser' => $browser,
            'mail_client' => $mailClient,
            'is_bot_or_prefetch' => $isBotOrPrefetch,
        ];
    }

    /**
     * Compute real aggregate open rate statistics for a user and optional filters
     */
    public static function getAggregateStats(int $userId, ?int $accountId = null, ?string $startDate = null, ?string $endDate = null): array {
        EmailOpenTracking::ensureSchema();

        $where = ["user_id = :uid"];
        $params = ['uid' => $userId];

        if ($accountId) {
            $where[] = "gmail_account_id = :acc";
            $params['acc'] = $accountId;
        }

        if ($startDate) {
            $where[] = "created_at >= :start";
            $params['start'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $where[] = "created_at <= :end";
            $params['end'] = $endDate . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        // 1. Total tracked emails sent
        $rowTotal = Database::first("SELECT COUNT(*) as total_tracked FROM email_open_tracking WHERE {$whereSql}", $params);
        $totalTracked = (int)($rowTotal['total_tracked'] ?? 0);

        // 2. Total unique recipients tracked (Delivered denominator)
        $rowRecipients = Database::first("SELECT COUNT(DISTINCT recipient_email) as total_recipients FROM email_open_tracking WHERE {$whereSql}", $params);
        $totalRecipients = (int)($rowRecipients['total_recipients'] ?? 0);

        // 3. Unique recipients who opened at least once
        $rowUniqueOpened = Database::first("SELECT COUNT(DISTINCT recipient_email) as unique_opened FROM email_open_tracking WHERE {$whereSql} AND open_count > 0", $params);
        $uniqueOpened = (int)($rowUniqueOpened['unique_opened'] ?? 0);

        // 4. Total recorded open events
        $rowTotalOpens = Database::first("SELECT COALESCE(SUM(open_count), 0) as total_opens FROM email_open_tracking WHERE {$whereSql}", $params);
        $totalOpens = (int)($rowTotalOpens['total_opens'] ?? 0);

        // 5. Unopened recipients
        $notOpened = max(0, $totalRecipients - $uniqueOpened);

        // 6. Open Rate % = (Unique Opened Recipients ÷ Successfully Delivered/Tracked Recipients) * 100
        $openRate = ($totalRecipients > 0) ? round(($uniqueOpened / $totalRecipients) * 100, 1) : 0.0;

        return [
            'total_tracked' => $totalTracked,
            'total_recipients' => $totalRecipients,
            'unique_opened' => $uniqueOpened,
            'total_opens' => $totalOpens,
            'not_opened' => $notOpened,
            'open_rate' => $openRate,
            // Aliases
            'unique_recipients_opened' => $uniqueOpened,
            'total_delivered' => $totalRecipients,
            'open_rate_percent' => $openRate,
            'total_open_events' => $totalOpens,
            'total_unopened' => $notOpened,
        ];
    }

    /**
     * Alias for getAggregateStats with filter array
     */
    public static function getOpenRateStats(int $userId, array $filters = []): array {
        $accountId = !empty($filters['account_id']) ? (int)$filters['account_id'] : null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        return self::getAggregateStats($userId, $accountId, $startDate, $endDate);
    }

    /**
     * Get real recipient-level open tracking records
     */
    public static function getRecipientStats(
        int $userId,
        ?int $accountId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null,
        int $limit = 25,
        int $offset = 0
    ): array {
        EmailOpenTracking::ensureSchema();

        $where = ["t.user_id = :uid"];
        $params = ['uid' => $userId];

        if ($accountId) {
            $where[] = "t.gmail_account_id = :acc";
            $params['acc'] = $accountId;
        }

        if ($startDate) {
            $where[] = "t.created_at >= :start";
            $params['start'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $where[] = "t.created_at <= :end";
            $params['end'] = $endDate . ' 23:59:59';
        }

        if ($search) {
            $where[] = "(t.recipient_email LIKE :search OR t.subject LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        // Count distinct recipients matching
        $countRow = Database::first("SELECT COUNT(DISTINCT t.recipient_email) as cnt FROM email_open_tracking t WHERE {$whereSql}", $params);
        $totalCount = (int)($countRow['cnt'] ?? 0);

        // Query grouped recipient records (compatible with both MySQL and SQLite)
        $sql = "SELECT 
                    t.recipient_email,
                    MAX(t.id) as latest_id,
                    COUNT(t.id) as emails_sent,
                    COALESCE(SUM(t.open_count), 0) as total_opens,
                    MIN(t.first_opened_at) as first_opened_at,
                    MAX(t.last_opened_at) as last_opened_at,
                    MAX(t.created_at) as last_sent_at
                FROM email_open_tracking t
                WHERE {$whereSql}
                GROUP BY t.recipient_email
                ORDER BY total_opens DESC, last_sent_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $rows = Database::query($sql, $params);

        $latestIds = array_filter(array_column($rows, 'latest_id'));
        $detailsMap = [];
        if (!empty($latestIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($latestIds), '?'));
            $detailRows = Database::query(
                "SELECT t.id, t.subject, t.source_type, a.gmail_email as sending_account 
                 FROM email_open_tracking t 
                 LEFT JOIN gmail_accounts a ON a.id = t.gmail_account_id 
                 WHERE t.id IN ({$inPlaceholders})",
                array_values($latestIds)
            );
            foreach ($detailRows as $dr) {
                $detailsMap[$dr['id']] = $dr;
            }
        }

        $results = [];
        foreach ($rows as $row) {
            $totOpens = (int)($row['total_opens'] ?? 0);
            $latestId = (int)$row['latest_id'];
            $detail = $detailsMap[$latestId] ?? [];
            $results[] = [
                'recipient_email' => $row['recipient_email'],
                'status' => ($totOpens > 0) ? 'Opened' : 'Not Opened',
                'first_opened' => !empty($row['first_opened_at']) ? $row['first_opened_at'] : null,
                'last_opened' => !empty($row['last_opened_at']) ? $row['last_opened_at'] : null,
                'total_opens' => $totOpens,
                'emails_sent' => (int)($row['emails_sent'] ?? 1),
                'last_subject' => $detail['subject'] ?? '(No Subject)',
                'sending_account' => $detail['sending_account'] ?? '',
                'source_type' => $detail['source_type'] ?? 'auto_reply',
                'last_sent_at' => $row['last_sent_at'],
                'latest_tracking_id' => $latestId,
            ];
        }

        return [$results, $totalCount];
    }
}
