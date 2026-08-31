<?php
namespace App\Models;

use App\Core\Database;

class ActivityLog {
    public int $id;
    public ?int $user_id = null;
    public ?int $gmail_account_id = null;
    public string $log_type;
    public string $message;
    public ?string $context_json = null;
    public ?string $ip_address = null;
    public ?string $created_at = null;

    public static function createLog(
        string $log_type,
        string $message,
        ?int $user_id = null,
        ?int $gmail_account_id = null,
        array $context = [],
        ?string $ip_address = null
    ): void {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO activity_logs (user_id, gmail_account_id, log_type, message, context_json, ip_address, created_at)
                VALUES (:user_id, :gmail_account_id, :log_type, :message, :context_json, :ip_address, {$now})";

        try {
            Database::execute($sql, [
                'user_id' => $user_id,
                'gmail_account_id' => $gmail_account_id,
                'log_type' => $log_type,
                'message' => $message,
                'context_json' => !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to write activity log: " . $e->getMessage());
        }
    }

    public static function getLatest(int $limit = 50, ?int $userId = null, ?string $filterType = null, ?int $accountId = null): array {
        $sql = "SELECT a.*, g.gmail_email, u.name as user_name 
                FROM activity_logs a 
                LEFT JOIN gmail_accounts g ON a.gmail_account_id = g.id
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE 1=1 ";
        $params = [];

        if ($userId !== null) {
            $sql .= "AND (a.user_id = :uid OR g.user_id = :uid2) ";
            $params['uid'] = $userId;
            $params['uid2'] = $userId;
        }

        if ($accountId !== null) {
            $sql .= "AND a.gmail_account_id = :acc ";
            $params['acc'] = $accountId;
        }

        if ($filterType) {
            if ($filterType === 'reply') {
                $sql .= "AND (a.message LIKE '%reply%' OR a.message LIKE '%Auto-Reply%') ";
            } elseif ($filterType === 'followup') {
                $sql .= "AND (a.message LIKE '%followup%' OR a.message LIKE '%Follow-up%') ";
            } elseif ($filterType === 'error') {
                $sql .= "AND a.log_type = 'error' ";
            } elseif ($filterType === 'success') {
                $sql .= "AND a.log_type = 'success' ";
            }
        }

        $sql .= "ORDER BY a.id DESC LIMIT {$limit}";
        return Database::query($sql, $params);
    }

    public static function deleteByUserId(int $userId, ?int $accountId = null): int {
        if ($accountId) {
            return Database::execute("DELETE FROM activity_logs WHERE (user_id = :uid OR gmail_account_id = :acc)", [
                'uid' => $userId,
                'acc' => $accountId
            ]);
        }

        // Get user's account IDs
        $accounts = Database::query("SELECT id FROM gmail_accounts WHERE user_id = :uid", ['uid' => $userId]);
        $accIds = array_column($accounts, 'id');

        if (!empty($accIds)) {
            $placeholders = implode(',', array_fill(0, count($accIds), '?'));
            return Database::execute("DELETE FROM activity_logs WHERE user_id = ? OR gmail_account_id IN ($placeholders)", array_merge([$userId], $accIds));
        }

        return Database::execute("DELETE FROM activity_logs WHERE user_id = :uid", ['uid' => $userId]);
    }
}
