<?php
namespace App\Models;

use App\Core\Database;
use App\Models\EmailOpenEvent;

class EmailOpenTracking {
    public int $id;
    public string $tracking_token;
    public ?string $message_id = null;
    public int $gmail_account_id;
    public int $user_id;
    public string $recipient_email;
    public ?int $thread_id = null;
    public ?int $campaign_id = null;
    public string $source_type = 'auto_reply';
    public ?int $scheduled_job_id = null;
    public ?string $subject = null;
    public int $open_count = 0;
    public ?string $first_opened_at = null;
    public ?string $last_opened_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';
        $tableSql = "CREATE TABLE IF NOT EXISTS email_open_tracking (
            id {$autoInc},
            tracking_token VARCHAR(128) NOT NULL,
            message_id VARCHAR(191) NULL,
            gmail_account_id {$intType} NOT NULL,
            user_id {$intType} NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            thread_id {$intType} NULL,
            campaign_id {$intType} NULL,
            source_type VARCHAR(50) NOT NULL DEFAULT 'auto_reply',
            scheduled_job_id {$intType} NULL,
            subject VARCHAR(500) NULL,
            open_count {$intType} NOT NULL DEFAULT 0,
            first_opened_at {$dateDefault} NULL,
            last_opened_at {$dateDefault} NULL,
            created_at {$dateDefault},
            updated_at {$dateDefault},
            UNIQUE (tracking_token)
        )";
        try {
            Database::execute($tableSql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function create(array $data): self {
        self::ensureSchema();

        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_open_tracking (
            tracking_token, message_id, gmail_account_id, user_id, recipient_email,
            thread_id, campaign_id, source_type, scheduled_job_id, subject,
            open_count, created_at, updated_at
        ) VALUES (
            :tracking_token, :message_id, :gmail_account_id, :user_id, :recipient_email,
            :thread_id, :campaign_id, :source_type, :scheduled_job_id, :subject,
            0, {$now}, {$now}
        )";

        Database::execute($sql, [
            'tracking_token' => $data['tracking_token'],
            'message_id' => $data['message_id'] ?? null,
            'gmail_account_id' => (int)$data['gmail_account_id'],
            'user_id' => (int)($data['user_id'] ?? 0),
            'recipient_email' => strtolower(trim($data['recipient_email'])),
            'thread_id' => !empty($data['thread_id']) ? (int)$data['thread_id'] : null,
            'campaign_id' => !empty($data['campaign_id']) ? (int)$data['campaign_id'] : null,
            'source_type' => $data['source_type'] ?? 'auto_reply',
            'scheduled_job_id' => !empty($data['scheduled_job_id']) ? (int)$data['scheduled_job_id'] : null,
            'subject' => $data['subject'] ?? null,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM email_open_tracking WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByToken(string $token): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM email_open_tracking WHERE tracking_token = :t LIMIT 1", ['t' => trim($token)]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByMessageId(string $messageId): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM email_open_tracking WHERE message_id = :m LIMIT 1", ['m' => trim($messageId)]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByThreadId(int $threadId): array {
        self::ensureSchema();
        $rows = Database::query("SELECT * FROM email_open_tracking WHERE thread_id = :t ORDER BY id ASC", ['t' => $threadId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function findByRecipient(int $userId, string $recipientEmail): array {
        self::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM email_open_tracking WHERE user_id = :u AND recipient_email = :r ORDER BY id DESC",
            ['u' => $userId, 'r' => strtolower(trim($recipientEmail))]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public function update(array $data): bool {
        self::ensureSchema();
        $sets = [];
        $params = ['id' => $this->id];
        foreach ($data as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[$k] = $v;
        }
        if (empty($sets)) return false;

        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";
        $sets[] = "updated_at = {$now}";

        $sql = "UPDATE email_open_tracking SET " . implode(', ', $sets) . " WHERE id = :id";
        $ok = Database::execute($sql, $params);
        if ($ok) {
            foreach ($data as $k => $v) {
                if (property_exists($this, $k)) {
                    $this->{$k} = $v;
                }
            }
        }
        return $ok;
    }

    public function recordOpen(array $eventData): EmailOpenEvent {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        // Create the event
        $event = EmailOpenEvent::create(array_merge($eventData, [
            'tracking_id' => $this->id,
        ]));

        // Atomically increment open count and update timestamps
        $firstOpenSql = $this->first_opened_at ? "" : ", first_opened_at = {$now}";
        $sql = "UPDATE email_open_tracking SET 
                    open_count = open_count + 1, 
                    last_opened_at = {$now}
                    {$firstOpenSql},
                    updated_at = {$now}
                WHERE id = :id";

        Database::execute($sql, ['id' => $this->id]);

        $this->open_count++;
        if (!$this->first_opened_at) {
            $this->first_opened_at = date('Y-m-d H:i:s');
        }
        $this->last_opened_at = date('Y-m-d H:i:s');

        return $event;
    }

    public function getEvents(): array {
        return EmailOpenEvent::findByTrackingId($this->id);
    }

    public function isOpened(): bool {
        return $this->open_count > 0;
    }

    public static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->tracking_token = (string)$row['tracking_token'];
        $m->message_id = !empty($row['message_id']) ? (string)$row['message_id'] : null;
        $m->gmail_account_id = (int)$row['gmail_account_id'];
        $m->user_id = (int)($row['user_id'] ?? 0);
        $m->recipient_email = (string)$row['recipient_email'];
        $m->thread_id = !empty($row['thread_id']) ? (int)$row['thread_id'] : null;
        $m->campaign_id = !empty($row['campaign_id']) ? (int)$row['campaign_id'] : null;
        $m->source_type = (string)($row['source_type'] ?? 'auto_reply');
        $m->scheduled_job_id = !empty($row['scheduled_job_id']) ? (int)$row['scheduled_job_id'] : null;
        $m->subject = !empty($row['subject']) ? (string)$row['subject'] : null;
        $m->open_count = (int)($row['open_count'] ?? 0);
        $m->first_opened_at = !empty($row['first_opened_at']) ? (string)$row['first_opened_at'] : null;
        $m->last_opened_at = !empty($row['last_opened_at']) ? (string)$row['last_opened_at'] : null;
        $m->created_at = !empty($row['created_at']) ? (string)$row['created_at'] : null;
        $m->updated_at = !empty($row['updated_at']) ? (string)$row['updated_at'] : null;
        return $m;
    }
}
