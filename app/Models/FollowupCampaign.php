<?php
namespace App\Models;

use App\Core\Database;

class FollowupCampaign {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public int $thread_id;
    public string $gmail_thread_id;
    public ?string $message_id = null;
    public string $sender_email;
    public string $recipient_email;
    public ?string $normalized_subject = null;
    public string $campaign_status; // active, completed, cancelled, replied, stopped
    public int $daily_follow_counted = 0;
    public ?string $counted_date = null;
    public int $total_steps = 0;
    public int $current_step = 0;
    public ?string $last_sent_at = null;
    public ?string $next_step_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM followup_campaigns WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByThreadId(int $threadId): ?self {
        $row = Database::first("SELECT * FROM followup_campaigns WHERE thread_id = :tid LIMIT 1", ['tid' => $threadId]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountAndThread(int $accountId, string $gmailThreadId): ?self {
        $row = Database::first(
            "SELECT * FROM followup_campaigns WHERE gmail_account_id = :acc AND gmail_thread_id = :tid LIMIT 1",
            ['acc' => $accountId, 'tid' => $gmailThreadId]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Atomically find existing campaign or create a new unique campaign for account + thread
     */
    public static function getOrCreate(int $userId, int $accountId, int $threadId, string $gmailThreadId, array $details = []): self {
        $existing = self::findByAccountAndThread($accountId, $gmailThreadId);
        if ($existing) {
            return $existing;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $cleanSubject = preg_replace('/^Re:\s*/i', '', $details['subject'] ?? '');

        $sql = "INSERT INTO followup_campaigns 
                (user_id, gmail_account_id, thread_id, gmail_thread_id, message_id, sender_email, recipient_email, normalized_subject, campaign_status, daily_follow_counted, total_steps, current_step, created_at)
                VALUES 
                (:uid, :acc, :tid, :gtid, :mid, :sender, :recipient, :subject, 'active', 0, :total_steps, 0, {$now})";

        try {
            Database::execute($sql, [
                'uid' => $userId,
                'acc' => $accountId,
                'tid' => $threadId,
                'gtid' => $gmailThreadId,
                'mid' => $details['message_id'] ?? null,
                'sender' => $details['sender_email'] ?? '',
                'recipient' => $details['recipient_email'] ?? '',
                'subject' => $cleanSubject,
                'total_steps' => (int)($details['total_steps'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            // Already created concurrently by another worker
        }

        $created = self::findByAccountAndThread($accountId, $gmailThreadId);
        if ($created) {
            return $created;
        }

        $id = (int)Database::lastInsertId();
        return self::find($id) ?? new self();
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE followup_campaigns SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    /**
     * Mark campaign as counted for today's Daily Follow quota
     */
    public function markCountedForToday(int $accountId): void {
        $today = date('Y-m-d');
        if ($this->daily_follow_counted === 0 || $this->counted_date !== $today) {
            $this->update([
                'daily_follow_counted' => 1,
                'counted_date' => $today,
            ]);
            DailyUsage::incrementFollowup($accountId, $today);
        }
    }

    /**
     * Cancel all pending scheduled jobs for this campaign (e.g. When recipient replies or user deletes/stops)
     */
    public function cancelPendingJobs(string $reason = 'Campaign cancelled'): void {
        Database::execute(
            "UPDATE scheduled_jobs 
             SET status = 'cancelled', last_error = :reason, processed_at = :now 
             WHERE thread_id = :tid AND job_type = 'follow_up' AND status = 'pending'",
            [
                'tid' => $this->thread_id,
                'reason' => $reason,
                'now' => date('Y-m-d H:i:s'),
            ]
        );

        Database::execute(
            "UPDATE followup_jobs 
             SET status = 'cancelled', last_error = :reason 
             WHERE campaign_id = :cid AND status = 'pending'",
            [
                'cid' => $this->id,
                'reason' => $reason,
            ]
        );
    }

    public function markReplied(): void {
        $this->cancelPendingJobs('Recipient replied to email');
        $this->update([
            'campaign_status' => 'replied',
            'next_step_at' => null,
        ]);
    }

    public function markCompleted(): void {
        $this->update([
            'campaign_status' => 'completed',
            'next_step_at' => null,
        ]);
    }

    public function markStopped(string $reason = 'Stopped manually'): void {
        $this->cancelPendingJobs($reason);
        $this->update([
            'campaign_status' => 'stopped',
            'next_step_at' => null,
        ]);
    }

    public static function countActive(): int {
        return (int)(Database::first("SELECT COUNT(*) as c FROM followup_campaigns WHERE campaign_status = 'active'")['c'] ?? 0);
    }

    public static function countCancelled(): int {
        return (int)(Database::first("SELECT COUNT(*) as c FROM followup_campaigns WHERE campaign_status IN ('cancelled', 'replied', 'stopped')")['c'] ?? 0);
    }

    public static function countTodayCampaigns(?string $date = null): int {
        $date = $date ?? date('Y-m-d');
        return (int)(Database::first("SELECT COUNT(*) as c FROM followup_campaigns WHERE counted_date = :dt OR (DATE(created_at) = :dt2 AND daily_follow_counted = 1)", ['dt' => $date, 'dt2' => $date])['c'] ?? 0);
    }

    public static function fromRow(array $row): self {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->user_id = (int)$row['user_id'];
        $c->gmail_account_id = (int)$row['gmail_account_id'];
        $c->thread_id = (int)$row['thread_id'];
        $c->gmail_thread_id = $row['gmail_thread_id'];
        $c->message_id = $row['message_id'] ?? null;
        $c->sender_email = $row['sender_email'];
        $c->recipient_email = $row['recipient_email'];
        $c->normalized_subject = $row['normalized_subject'] ?? null;
        $c->campaign_status = $row['campaign_status'] ?? 'active';
        $c->daily_follow_counted = (int)($row['daily_follow_counted'] ?? 0);
        $c->counted_date = $row['counted_date'] ?? null;
        $c->total_steps = (int)($row['total_steps'] ?? 0);
        $c->current_step = (int)($row['current_step'] ?? 0);
        $c->last_sent_at = $row['last_sent_at'] ?? null;
        $c->next_step_at = $row['next_step_at'] ?? null;
        $c->created_at = $row['created_at'] ?? null;
        $c->updated_at = $row['updated_at'] ?? null;
        return $c;
    }
}
