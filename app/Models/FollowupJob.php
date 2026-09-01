<?php
namespace App\Models;

use App\Core\Database;

class FollowupJob {
    public int $id;
    public int $campaign_id;
    public int $gmail_account_id;
    public int $thread_id;
    public int $followup_step;
    public ?int $template_id = null;
    public ?string $message = null;
    public string $scheduled_at;
    public ?string $sent_at = null;
    public string $status; // pending, processing, sent, failed, cancelled
    public int $attempts = 0;
    public int $max_attempts = 3;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM followup_jobs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCampaignAndStep(int $campaignId, int $step): ?self {
        $row = Database::first(
            "SELECT * FROM followup_jobs WHERE campaign_id = :cid AND followup_step = :step LIMIT 1",
            ['cid' => $campaignId, 'step' => $step]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO followup_jobs 
                (campaign_id, gmail_account_id, thread_id, followup_step, template_id, message, scheduled_at, status, attempts, max_attempts, created_at)
                VALUES 
                (:cid, :acc, :tid, :step, :tpl, :msg, :sched, :status, :att, :max_att, {$now})";

        Database::execute($sql, [
            'cid' => $data['campaign_id'],
            'acc' => $data['gmail_account_id'],
            'tid' => $data['thread_id'],
            'step' => $data['followup_step'] ?? 1,
            'tpl' => $data['template_id'] ?? null,
            'msg' => $data['message'] ?? null,
            'sched' => $data['scheduled_at'],
            'status' => $data['status'] ?? 'pending',
            'att' => $data['attempts'] ?? 0,
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
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE followup_jobs SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public static function fromRow(array $row): self {
        $j = new self();
        $j->id = (int)$row['id'];
        $j->campaign_id = (int)$row['campaign_id'];
        $j->gmail_account_id = (int)$row['gmail_account_id'];
        $j->thread_id = (int)$row['thread_id'];
        $j->followup_step = (int)$row['followup_step'];
        $j->template_id = isset($row['template_id']) ? (int)$row['template_id'] : null;
        $j->message = $row['message'] ?? null;
        $j->scheduled_at = $row['scheduled_at'];
        $j->sent_at = $row['sent_at'] ?? null;
        $j->status = $row['status'] ?? 'pending';
        $j->attempts = (int)($row['attempts'] ?? 0);
        $j->max_attempts = (int)($row['max_attempts'] ?? 3);
        $j->last_error = $row['last_error'] ?? null;
        $j->created_at = $row['created_at'] ?? null;
        $j->updated_at = $row['updated_at'] ?? null;
        return $j;
    }
}
