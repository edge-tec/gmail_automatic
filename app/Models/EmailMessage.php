<?php
namespace App\Models;

use App\Core\Database;

class EmailMessage {
    public int $id;
    public int $thread_id;
    public int $gmail_account_id;
    public string $gmail_message_id;
    public string $direction; // incoming, outgoing
    public string $sender;
    public string $recipient;
    public ?string $subject = null;
    public ?string $snippet = null;
    public ?string $message_body = null;
    public ?string $received_at = null;
    public ?string $sent_at = null;
    public string $status;
    public int $is_historical = 0;
    public ?string $created_at = null;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $messageCols = [
            'is_historical' => ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
        ];
        \App\Core\DatabaseSanitizer::ensureTableColumns('email_messages', $messageCols);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM email_messages WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountAndMessageId(int $accountId, string $msgId): ?self {
        self::ensureSchema();
        $row = Database::first(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND gmail_message_id = :mid LIMIT 1",
            ['acc' => $accountId, 'mid' => $msgId]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function findByThreadId(int $threadId): array {
        self::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM email_messages WHERE thread_id = :tid ORDER BY COALESCE(received_at, sent_at, created_at) ASC",
            ['tid' => $threadId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): ?self {
        self::ensureSchema();
        // Prevent duplicate creation
        $existing = self::findByAccountAndMessageId($data['gmail_account_id'], $data['gmail_message_id']);
        if ($existing) {
            return $existing;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_messages 
                (thread_id, gmail_account_id, gmail_message_id, direction, sender, recipient, subject, snippet, message_body, received_at, sent_at, status, is_historical, created_at)
                VALUES 
                (:tid, :acc, :mid, :dir, :sender, :recipient, :subject, :snippet, :body, :rec_at, :sent_at, :status, :is_hist, {$now})";

        try {
            Database::execute($sql, [
                'tid' => $data['thread_id'],
                'acc' => $data['gmail_account_id'],
                'mid' => $data['gmail_message_id'],
                'dir' => $data['direction'] ?? 'incoming',
                'sender' => $data['sender'],
                'recipient' => $data['recipient'],
                'subject' => $data['subject'] ?? null,
                'snippet' => $data['snippet'] ?? null,
                'body' => $data['message_body'] ?? null,
                'rec_at' => $data['received_at'] ?? null,
                'sent_at' => $data['sent_at'] ?? null,
                'status' => $data['status'] ?? 'processed',
                'is_hist' => !empty($data['is_historical']) ? 1 : 0,
            ]);

            $id = (int)Database::lastInsertId();
            return self::find($id);
        } catch (\Throwable $e) {
            error_log("Failed to insert email_message: " . $e->getMessage());
            return self::findByAccountAndMessageId($data['gmail_account_id'], $data['gmail_message_id']);
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

        $sql = "UPDATE email_messages SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->thread_id = (int)$row['thread_id'];
        $m->gmail_account_id = (int)$row['gmail_account_id'];
        $m->gmail_message_id = $row['gmail_message_id'];
        $m->direction = $row['direction'] ?? 'incoming';
        $m->sender = $row['sender'];
        $m->recipient = $row['recipient'];
        $m->subject = $row['subject'] ?? null;
        $m->snippet = $row['snippet'] ?? null;
        $m->message_body = $row['message_body'] ?? null;
        $m->received_at = $row['received_at'] ?? null;
        $m->sent_at = $row['sent_at'] ?? null;
        $m->status = $row['status'] ?? 'processed';
        $m->is_historical = (int)($row['is_historical'] ?? 0);
        $m->created_at = $row['created_at'] ?? null;
        return $m;
    }
}
