<?php
namespace App\Models;

use App\Core\Database;

class DailyUsage {
    public int $id;
    public int $gmail_account_id;
    public string $usage_date;
    public int $reply_count;
    public int $followup_count;
    public int $total_sent;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function getOrCreate(int $accountId, ?string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $row = Database::first(
            "SELECT * FROM daily_usage WHERE gmail_account_id = :acc AND usage_date = :dt LIMIT 1",
            ['acc' => $accountId, 'dt' => $date]
        );

        if ($row) {
            return $row;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO daily_usage (gmail_account_id, usage_date, reply_count, followup_count, total_sent, created_at)
                VALUES (:acc, :dt, 0, 0, 0, {$now})";

        try {
            Database::execute($sql, ['acc' => $accountId, 'dt' => $date]);
        } catch (\Throwable $e) {
            // Already inserted concurrently
        }

        return Database::first(
            "SELECT * FROM daily_usage WHERE gmail_account_id = :acc AND usage_date = :dt LIMIT 1",
            ['acc' => $accountId, 'dt' => $date]
        ) ?? [
            'id' => 0,
            'gmail_account_id' => $accountId,
            'usage_date' => $date,
            'reply_count' => 0,
            'followup_count' => 0,
            'total_sent' => 0,
        ];
    }

    public static function incrementReply(int $accountId, ?string $date = null): void {
        $date = $date ?? date('Y-m-d');
        self::getOrCreate($accountId, $date);

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        Database::execute(
            "UPDATE daily_usage 
             SET reply_count = reply_count + 1, total_sent = total_sent + 1, updated_at = {$now} 
             WHERE gmail_account_id = :acc AND usage_date = :dt",
            ['acc' => $accountId, 'dt' => $date]
        );
    }

    public static function incrementFollowup(int $accountId, ?string $date = null): void {
        $date = $date ?? date('Y-m-d');
        self::getOrCreate($accountId, $date);

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        Database::execute(
            "UPDATE daily_usage 
             SET followup_count = followup_count + 1, total_sent = total_sent + 1, updated_at = {$now} 
             WHERE gmail_account_id = :acc AND usage_date = :dt",
            ['acc' => $accountId, 'dt' => $date]
        );
    }

    public static function getStatsForUser(int $userId, ?string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $sql = "SELECT 
                    COALESCE(SUM(d.reply_count), 0) as total_replies,
                    COALESCE(SUM(d.followup_count), 0) as total_followups,
                    COALESCE(SUM(d.total_sent), 0) as total_sent
                FROM daily_usage d
                JOIN gmail_accounts g ON d.gmail_account_id = g.id
                WHERE g.user_id = :uid AND d.usage_date = :dt";
        return Database::first($sql, ['uid' => $userId, 'dt' => $date]) ?? [
            'total_replies' => 0,
            'total_followups' => 0,
            'total_sent' => 0,
        ];
    }
}
