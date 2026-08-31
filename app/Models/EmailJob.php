<?php
namespace App\Models;

use App\Core\Database;

class EmailJob {
    public int $id;
    public ?int $user_id = null;
    public string $recipient_email;
    public ?string $recipient_name = null;
    public ?string $template_slug = null;
    public ?string $event_key = null;
    public string $subject;
    public string $body;
    public string $status; // pending, processing, sent, failed, cancelled
    public int $attempts;
    public int $max_attempts;
    public string $scheduled_at;
    public ?string $sent_at = null;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_jobs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEventKey(string $eventKey): ?self {
        $row = Database::first("SELECT * FROM email_jobs WHERE event_key = :key LIMIT 1", ['key' => $eventKey]);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Dispatch an email notification job with deduplication protection
     */
    public static function dispatch(array $data): ?self {
        // If event_key provided, prevent duplicate queueing
        if (!empty($data['event_key'])) {
            $existing = self::findByEventKey($data['event_key']);
            if ($existing) {
                return $existing;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sched = $data['scheduled_at'] ?? date('Y-m-d H:i:s');

        $sql = "INSERT INTO email_jobs 
                (user_id, recipient_email, recipient_name, template_slug, event_key, subject, body, status, attempts, max_attempts, scheduled_at, created_at)
                VALUES 
                (:uid, :email, :name, :slug, :key, :sub, :body, 'pending', 0, :max_att, :sched, {$now})";

        try {
            Database::execute($sql, [
                'uid' => $data['user_id'] ?? null,
                'email' => $data['recipient_email'],
                'name' => $data['recipient_name'] ?? null,
                'slug' => $data['template_slug'] ?? null,
                'key' => $data['event_key'] ?? null,
                'sub' => $data['subject'],
                'body' => $data['body'],
                'max_att' => $data['max_attempts'] ?? 3,
                'sched' => $sched,
            ]);

            $id = (int)Database::lastInsertId();
            return self::find($id);
        } catch (\Throwable $e) {
            if (!empty($data['event_key'])) {
                return self::findByEventKey($data['event_key']);
            }
            logger("Failed to dispatch EmailJob: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Dispatch notification using an existing EmailTemplate by slug
     */
    public static function dispatchTemplate(string $slug, string $recipientEmail, array $vars = [], ?string $eventKey = null, ?int $userId = null, ?string $recipientName = null): ?self {
        $tpl = EmailTemplate::findBySlug($slug);
        if (!$tpl) {
            // Slug fallbacks for legacy/alternative naming
            if ($slug === 'payment_approved') {
                $tpl = EmailTemplate::findBySlug('purchase_confirmation');
            } elseif ($slug === 'purchase_confirmation') {
                $tpl = EmailTemplate::findBySlug('payment_approved');
            }
        }

        if (!$tpl || !$tpl->is_enabled) {
            logger("EmailTemplate [{$slug}] is missing or disabled. Email to {$recipientEmail} was skipped.", 'warning', $userId);
            return null;
        }

        $rendered = $tpl->render(array_merge($vars, [
            'name' => $recipientName ?? $recipientEmail,
            'email' => $recipientEmail,
        ]));

        $job = self::dispatch([
            'user_id' => $userId,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'template_slug' => $slug,
            'event_key' => $eventKey,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ]);

        // Attempt immediate real-time delivery if SMTP is enabled
        if ($job && $job->status === 'pending') {
            try {
                \App\Services\MailService::processEmailJob($job);
            } catch (\Throwable $e) {
                // If immediate dispatch fails, keep in queue for background retry
            }
        }

        return $job;
    }

    public static function getReadyJobs(int $limit = 20): array {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT * FROM email_jobs 
                WHERE status = 'pending' AND scheduled_at <= :now 
                ORDER BY scheduled_at ASC LIMIT {$limit}";
        $rows = Database::query($sql, ['now' => $now]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function all(int $limit = 100, ?string $status = null): array {
        if ($status && in_array($status, ['pending', 'processing', 'sent', 'failed', 'cancelled'])) {
            $rows = Database::query("SELECT * FROM email_jobs WHERE status = :status ORDER BY created_at DESC LIMIT {$limit}", ['status' => $status]);
        } else {
            $rows = Database::query("SELECT * FROM email_jobs ORDER BY created_at DESC LIMIT {$limit}");
        }
        return array_map([self::class, 'fromRow'], $rows);
    }

    public function getUser(): ?User {
        return $this->user_id ? User::find($this->user_id) : null;
    }

    public function resend(): bool {
        return $this->update([
            'status' => 'pending',
            'attempts' => 0,
            'scheduled_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
    }

    public function cancel(): bool {
        return $this->update([
            'status' => 'cancelled',
        ]);
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
        $sql = "UPDATE email_jobs SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public static function fromRow(array $row): self {
        $j = new self();
        $j->id = (int)$row['id'];
        $j->user_id = isset($row['user_id']) ? (int)$row['user_id'] : null;
        $j->recipient_email = $row['recipient_email'];
        $j->recipient_name = $row['recipient_name'] ?? null;
        $j->template_slug = $row['template_slug'] ?? null;
        $j->event_key = $row['event_key'] ?? null;
        $j->subject = $row['subject'];
        $j->body = $row['body'];
        $j->status = $row['status'] ?? 'pending';
        $j->attempts = (int)($row['attempts'] ?? 0);
        $j->max_attempts = (int)($row['max_attempts'] ?? 3);
        $j->scheduled_at = $row['scheduled_at'];
        $j->sent_at = $row['sent_at'] ?? null;
        $j->last_error = $row['last_error'] ?? null;
        $j->created_at = $row['created_at'] ?? null;
        $j->updated_at = $row['updated_at'] ?? null;
        return $j;
    }
}
