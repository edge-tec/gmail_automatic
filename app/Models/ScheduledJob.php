<?php
namespace App\Models;

use App\Core\Database;

class ScheduledJob {
    public int $id;
    public int $gmail_account_id;
    public int $thread_id;
    public string $job_type; // auto_reply, follow_up, sync_account
    public ?string $payload = null;
    public string $scheduled_at;
    public string $status; // pending, processing, completed, failed, cancelled
    public int $attempts;
    public int $max_attempts;
    public ?string $last_error = null;
    public ?string $processed_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM scheduled_jobs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findPendingByThreadId(int $threadId): array {
        $rows = Database::query(
            "SELECT * FROM scheduled_jobs WHERE thread_id = :tid AND status = 'pending' ORDER BY scheduled_at ASC",
            ['tid' => $threadId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function cancelPendingJobsForThread(int $threadId, string $reason = 'Cancelled due to recipient reply'): int {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        Database::execute(
            "UPDATE scheduled_jobs 
             SET status = 'cancelled', last_error = :reason, updated_at = {$now} 
             WHERE thread_id = :tid AND status = 'pending'",
            ['tid' => $threadId, 'reason' => $reason]
        );

        return 1;
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO scheduled_jobs 
                (gmail_account_id, thread_id, job_type, payload, scheduled_at, status, attempts, max_attempts, created_at)
                VALUES 
                (:acc, :tid, :type, :payload, :sched, :status, 0, :max_att, {$now})";

        Database::execute($sql, [
            'acc' => $data['gmail_account_id'],
            'tid' => $data['thread_id'],
            'type' => $data['job_type'],
            'payload' => isset($data['payload']) ? (is_array($data['payload']) ? json_encode($data['payload']) : $data['payload']) : null,
            'sched' => $data['scheduled_at'],
            'status' => $data['status'] ?? 'pending',
            'max_att' => $data['max_attempts'] ?? 3,
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
        $sql = "UPDATE scheduled_jobs SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function cancel(string $reason = 'Cancelled'): bool {
        return $this->update([
            'status' => 'cancelled',
            'last_error' => $reason,
        ]);
    }

    public static function cancelPendingJobsByAccountAndType(int $accountId, string $jobType, string $reason = 'Automation turned off'): int {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE scheduled_jobs 
                SET status = 'cancelled', last_error = :reason, updated_at = {$now}
                WHERE gmail_account_id = :acc AND job_type = :type AND status = 'pending'";
        return Database::execute($sql, [
            'acc' => $accountId,
            'type' => $jobType,
            'reason' => $reason,
        ]);
    }

    public function getPayloadArray(): array {
        if (!$this->payload) {
            return [];
        }
        return json_decode($this->payload, true) ?? [];
    }

    public static function getReadyJobs(int $limit = 20): array {
        $now = date('Y-m-d H:i:s');

        $sql = "SELECT * FROM scheduled_jobs 
                WHERE status = 'pending' AND scheduled_at <= :now 
                ORDER BY scheduled_at ASC LIMIT {$limit}";
        $rows = Database::query($sql, ['now' => $now]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function fromRow(array $row): self {
        $j = new self();
        $j->id = (int)$row['id'];
        $j->gmail_account_id = (int)$row['gmail_account_id'];
        $j->thread_id = (int)$row['thread_id'];
        $j->job_type = $row['job_type'];
        $j->payload = $row['payload'] ?? null;
        $j->scheduled_at = $row['scheduled_at'];
        $j->status = $row['status'] ?? 'pending';
        $j->attempts = (int)($row['attempts'] ?? 0);
        $j->max_attempts = (int)($row['max_attempts'] ?? 3);
        $j->last_error = $row['last_error'] ?? null;
        $j->processed_at = $row['processed_at'] ?? null;
        $j->created_at = $row['created_at'] ?? null;
        $j->updated_at = $row['updated_at'] ?? null;
        return $j;
    }
}
