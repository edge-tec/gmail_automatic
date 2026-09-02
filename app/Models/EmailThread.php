<?php
namespace App\Models;

use App\Core\Database;

class EmailThread {
    public int $id;
    public int $gmail_account_id;
    public string $gmail_thread_id;
    public string $sender_email;
    public ?string $sender_name = null;
    public ?string $subject = null;
    public int $reply_count;
    public int $followup_count;
    public string $automation_status; // active, replied, stopped, completed
    public ?string $last_incoming_at = null;
    public ?string $last_outgoing_at = null;
    public ?string $next_followup_at = null;
    public ?string $last_processed_message_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_threads WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountAndThreadId(int $accountId, string $threadId): ?self {
        $row = Database::first(
            "SELECT * FROM email_threads WHERE gmail_account_id = :acc AND gmail_thread_id = :tid LIMIT 1",
            ['acc' => $accountId, 'tid' => $threadId]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountId(int $accountId, int $limit = 50): array {
        $rows = Database::query(
            "SELECT * FROM email_threads WHERE gmail_account_id = :acc ORDER BY COALESCE(last_incoming_at, created_at) DESC LIMIT {$limit}",
            ['acc' => $accountId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function findByUserId(int $userId, int $limit = 50, ?string $status = null): array {
        $sql = "SELECT t.*, g.gmail_email 
                FROM email_threads t
                JOIN gmail_accounts g ON t.gmail_account_id = g.id
                WHERE g.user_id = :uid ";
        $params = ['uid' => $userId];

        if ($status) {
            $sql .= "AND t.automation_status = :status ";
            $params['status'] = $status;
        }

        $sql .= "ORDER BY COALESCE(t.last_incoming_at, t.created_at) DESC LIMIT {$limit}";
        return Database::query($sql, $params);
    }

    public static function createOrGet(int $accountId, string $threadId, array $data): self {
        $existing = self::findByAccountAndThreadId($accountId, $threadId);
        if ($existing) {
            return $existing;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $status = $data['automation_status'] ?? 'active';

        $sql = "INSERT INTO email_threads 
                (gmail_account_id, gmail_thread_id, sender_email, sender_name, subject, reply_count, followup_count, automation_status, last_incoming_at, created_at)
                VALUES 
                (:acc, :tid, :email, :name, :subject, 0, 0, :status, {$now}, {$now})";

        Database::execute($sql, [
            'acc' => $accountId,
            'tid' => $threadId,
            'email' => $data['sender_email'],
            'name' => $data['sender_name'] ?? null,
            'subject' => $data['subject'] ?? null,
            'status' => $status,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE email_threads SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getMessages(): array {
        return EmailMessage::findByThreadId($this->id);
    }

    public function getPendingJobs(): array {
        return ScheduledJob::findPendingByThreadId($this->id);
    }

    public function getFollowupCampaign(): ?FollowupCampaign {
        return FollowupCampaign::findByThreadId($this->id);
    }

    public function delete(): bool {
        Database::execute("DELETE FROM followup_jobs WHERE thread_id = :id", ['id' => $this->id]);
        Database::execute("DELETE FROM followup_campaigns WHERE thread_id = :id", ['id' => $this->id]);
        Database::execute("DELETE FROM scheduled_jobs WHERE thread_id = :id", ['id' => $this->id]);
        Database::execute("DELETE FROM email_messages WHERE thread_id = :id", ['id' => $this->id]);
        return Database::execute("DELETE FROM email_threads WHERE id = :id", ['id' => $this->id]);
    }

    public static function deleteAllByUserId(int $userId, ?int $accountId = null): int {
        $where = "g.user_id = :uid";
        $params = ['uid' => $userId];
        if ($accountId) {
            $where .= " AND t.gmail_account_id = :acc";
            $params['acc'] = $accountId;
        }

        $threads = Database::query("SELECT t.id FROM email_threads t JOIN gmail_accounts g ON t.gmail_account_id = g.id WHERE {$where}", $params);
        if (empty($threads)) {
            return 0;
        }

        $ids = array_column($threads, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        Database::execute("DELETE FROM scheduled_jobs WHERE thread_id IN ($placeholders)", $ids);
        Database::execute("DELETE FROM email_messages WHERE thread_id IN ($placeholders)", $ids);
        Database::execute("DELETE FROM email_threads WHERE id IN ($placeholders)", $ids);

        return count($ids);
    }

    public static function fromRow(array $row): self {
        $t = new self();
        $t->id = (int)$row['id'];
        $t->gmail_account_id = (int)$row['gmail_account_id'];
        $t->gmail_thread_id = $row['gmail_thread_id'];
        $t->sender_email = $row['sender_email'];
        $t->sender_name = $row['sender_name'] ?? null;
        $t->subject = $row['subject'] ?? null;
        $t->reply_count = (int)($row['reply_count'] ?? 0);
        $t->followup_count = (int)($row['followup_count'] ?? 0);
        $t->automation_status = $row['automation_status'] ?? 'active';
        $t->last_incoming_at = $row['last_incoming_at'] ?? null;
        $t->last_outgoing_at = $row['last_outgoing_at'] ?? null;
        $t->next_followup_at = $row['next_followup_at'] ?? null;
        $t->last_processed_message_id = $row['last_processed_message_id'] ?? null;
        $t->created_at = $row['created_at'] ?? null;
        $t->updated_at = $row['updated_at'] ?? null;
        return $t;
    }
}
