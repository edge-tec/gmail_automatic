<?php
namespace App\Models;

use App\Core\Database;

class AutoReplyRecipient {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public string $normalized_sender_email;
    public ?string $first_message_id = null;
    public ?string $first_thread_id = null;
    public ?string $reply_sent_at = null;
    public string $reply_status; // pending, processing, replied, skipped_duplicate, failed, cancelled
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function normalizeEmail(string $email): string {
        $clean = trim($email);
        if (preg_match('/<([^>]+)>/', $clean, $matches)) {
            $clean = $matches[1];
        }
        return strtolower(trim($clean));
    }

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM auto_reply_recipients WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountAndSender(int $accountId, string $senderEmail): ?self {
        $normalized = self::normalizeEmail($senderEmail);
        $row = Database::first(
            "SELECT * FROM auto_reply_recipients WHERE gmail_account_id = :acc AND normalized_sender_email = :sender LIMIT 1",
            ['acc' => $accountId, 'sender' => $normalized]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Atomically claims or retrieves a recipient record to prevent race conditions.
     * Returns ['recipient' => AutoReplyRecipient, 'is_new' => bool, 'is_eligible' => bool]
     */
    public static function claimOrGet(int $userId, int $accountId, string $senderEmail, ?string $msgId = null, ?string $threadId = null): array {
        $normalized = self::normalizeEmail($senderEmail);
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        try {
            Database::execute(
                "INSERT INTO auto_reply_recipients 
                (user_id, gmail_account_id, normalized_sender_email, first_message_id, first_thread_id, reply_status, created_at)
                VALUES 
                (:uid, :acc, :sender, :mid, :tid, 'pending', {$now})",
                [
                    'uid' => $userId,
                    'acc' => $accountId,
                    'sender' => $normalized,
                    'mid' => $msgId,
                    'tid' => $threadId,
                ]
            );

            $id = (int)Database::lastInsertId();
            $recipient = self::find($id);
            return [
                'recipient' => $recipient,
                'is_new' => true,
                'is_eligible' => true,
            ];
        } catch (\Throwable $e) {
            // Record exists (unique constraint violation)
            $existing = self::findByAccountAndSender($accountId, $normalized);
            if (!$existing) {
                return [
                    'recipient' => null,
                    'is_new' => false,
                    'is_eligible' => false,
                ];
            }

            // Eligible only if previous attempt was cancelled
            $isEligible = ($existing->reply_status === 'cancelled');
            if ($isEligible) {
                $existing->update([
                    'reply_status' => 'pending',
                    'first_message_id' => $msgId ?? $existing->first_message_id,
                    'first_thread_id' => $threadId ?? $existing->first_thread_id,
                ]);
            }

            return [
                'recipient' => $existing,
                'is_new' => false,
                'is_eligible' => $isEligible,
            ];
        }
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE auto_reply_recipients SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function markReplied(): bool {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        return Database::execute(
            "UPDATE auto_reply_recipients SET reply_status = 'replied', reply_sent_at = {$now}, updated_at = {$now} WHERE id = :id",
            ['id' => $this->id]
        );
    }

    public function markProcessing(): bool {
        return $this->update(['reply_status' => 'processing']);
    }

    public function markCancelled(): bool {
        return $this->update(['reply_status' => 'cancelled']);
    }

    public function markFailed(): bool {
        return $this->update(['reply_status' => 'failed']);
    }

    public static function countUniqueTrafficToday(int $accountId): int {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";
        $row = Database::first(
            "SELECT COUNT(DISTINCT normalized_sender_email) as total FROM auto_reply_recipients WHERE gmail_account_id = :acc AND {$todayCond}",
            ['acc' => $accountId]
        );
        return (int)($row['total'] ?? 0);
    }

    public static function countRepliedForAccount(int $accountId): int {
        $row = Database::first(
            "SELECT COUNT(*) as total FROM auto_reply_recipients WHERE gmail_account_id = :acc AND reply_status = 'replied'",
            ['acc' => $accountId]
        );
        return (int)($row['total'] ?? 0);
    }

    public static function countUniqueTrafficTodayAdmin(): int {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";
        $row = Database::first("SELECT COUNT(DISTINCT normalized_sender_email) as total FROM auto_reply_recipients WHERE {$todayCond}");
        return (int)($row['total'] ?? 0);
    }

    public static function countTotalRepliedTodayAdmin(): int {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(reply_sent_at) = CURDATE()" : "date(reply_sent_at) = date('now')";
        $row = Database::first("SELECT COUNT(*) as total FROM auto_reply_recipients WHERE reply_status = 'replied' AND {$todayCond}");
        return (int)($row['total'] ?? 0);
    }

    public static function countPendingAdmin(): int {
        $row = Database::first("SELECT COUNT(*) as total FROM auto_reply_recipients WHERE reply_status IN ('pending', 'processing')");
        return (int)($row['total'] ?? 0);
    }

    public static function countFailedAdmin(): int {
        $row = Database::first("SELECT COUNT(*) as total FROM auto_reply_recipients WHERE reply_status = 'failed'");
        return (int)($row['total'] ?? 0);
    }

    private static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->user_id = (int)$row['user_id'];
        $m->gmail_account_id = (int)$row['gmail_account_id'];
        $m->normalized_sender_email = $row['normalized_sender_email'];
        $m->first_message_id = $row['first_message_id'] ?? null;
        $m->first_thread_id = $row['first_thread_id'] ?? null;
        $m->reply_sent_at = $row['reply_sent_at'] ?? null;
        $m->reply_status = $row['reply_status'] ?? 'pending';
        $m->created_at = $row['created_at'] ?? null;
        $m->updated_at = $row['updated_at'] ?? null;
        return $m;
    }
}
