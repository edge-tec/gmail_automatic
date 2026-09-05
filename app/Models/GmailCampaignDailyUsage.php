<?php
namespace App\Models;

use App\Core\Database;

class GmailCampaignDailyUsage {
    public int $id;
    public int $gmail_account_id;
    public int $user_id;
    public string $usage_date;
    public int $daily_limit = 50;
    public int $emails_sent = 0;
    public int $emails_failed = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function getOrCreate(int $accountId, int $userId, ?string $date = null, int $defaultLimit = 50): array {
        $date = $date ?? date('Y-m-d');
        $row = Database::first(
            "SELECT * FROM gmail_campaign_daily_usage WHERE gmail_account_id = :acc AND usage_date = :dt LIMIT 1",
            ['acc' => $accountId, 'dt' => $date]
        );

        if ($row) {
            return $row;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO gmail_campaign_daily_usage (gmail_account_id, user_id, usage_date, daily_limit, emails_sent, emails_failed, created_at, updated_at)
                VALUES (:acc, :uid, :dt, :lim, 0, 0, {$now}, {$now})";

        try {
            Database::execute($sql, [
                'acc' => $accountId,
                'uid' => $userId,
                'dt' => $date,
                'lim' => $defaultLimit,
            ]);
        } catch (\Throwable $e) {
            // Concurrently inserted
        }

        return Database::first(
            "SELECT * FROM gmail_campaign_daily_usage WHERE gmail_account_id = :acc AND usage_date = :dt LIMIT 1",
            ['acc' => $accountId, 'dt' => $date]
        ) ?? [
            'id' => 0,
            'gmail_account_id' => $accountId,
            'user_id' => $userId,
            'usage_date' => $date,
            'daily_limit' => $defaultLimit,
            'emails_sent' => 0,
            'emails_failed' => 0,
        ];
    }

    public static function canSend(int $accountId, int $userId, int $accountLimit = 50, ?string $date = null): bool {
        $usage = self::getOrCreate($accountId, $userId, $date, $accountLimit);
        $limit = max((int)$usage['daily_limit'], $accountLimit);
        return ((int)$usage['emails_sent']) < $limit;
    }

    public static function incrementSent(int $accountId, int $userId, int $accountLimit = 50, ?string $date = null): void {
        $date = $date ?? date('Y-m-d');
        self::getOrCreate($accountId, $userId, $date, $accountLimit);

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        Database::execute(
            "UPDATE gmail_campaign_daily_usage 
             SET emails_sent = emails_sent + 1, daily_limit = :lim, updated_at = {$now} 
             WHERE gmail_account_id = :acc AND usage_date = :dt",
            ['acc' => $accountId, 'dt' => $date, 'lim' => $accountLimit]
        );
    }

    public static function incrementFailed(int $accountId, int $userId, int $accountLimit = 50, ?string $date = null): void {
        $date = $date ?? date('Y-m-d');
        self::getOrCreate($accountId, $userId, $date, $accountLimit);

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        Database::execute(
            "UPDATE gmail_campaign_daily_usage 
             SET emails_failed = emails_failed + 1, daily_limit = :lim, updated_at = {$now} 
             WHERE gmail_account_id = :acc AND usage_date = :dt",
            ['acc' => $accountId, 'dt' => $date, 'lim' => $accountLimit]
        );
    }

    public static function getAccountUsage(int $accountId, ?string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $row = Database::first(
            "SELECT * FROM gmail_campaign_daily_usage WHERE gmail_account_id = :acc AND usage_date = :dt LIMIT 1",
            ['acc' => $accountId, 'dt' => $date]
        );
        if ($row) {
            $limit = (int)$row['daily_limit'];
            $sent = (int)$row['emails_sent'];
            return [
                'emails_sent' => $sent,
                'emails_failed' => (int)$row['emails_failed'],
                'daily_limit' => $limit,
                'remaining' => max(0, $limit - $sent),
            ];
        }
        return [
            'emails_sent' => 0,
            'emails_failed' => 0,
            'daily_limit' => 50,
            'remaining' => 50,
        ];
    }
}
