<?php
namespace App\Models;

use App\Core\Database;
use DateTime;
use DateTimeZone;

class EmailCampaign {
    public int $id;
    public int $user_id;
    public string $name;
    public string $status; // draft, active, paused, completed, cancelled
    public int $daily_campaign_limit;
    public int $sending_interval; // in seconds
    public string $start_time;
    public string $end_time;
    public string $timezone;
    public ?int $last_used_gmail_account_id = null;
    public ?string $last_sent_at = null;
    public int $total_recipients = 0;
    public int $sent_count = 0;
    public int $failed_count = 0;
    public int $skipped_count = 0;
    public int $cancelled_count = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_campaigns WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserAndId(int $userId, int $id): ?self {
        $row = Database::first("SELECT * FROM email_campaigns WHERE id = :id AND user_id = :uid LIMIT 1", [
            'id' => $id,
            'uid' => $userId,
        ]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId): array {
        $rows = Database::query("SELECT * FROM email_campaigns WHERE user_id = :uid ORDER BY id DESC", ['uid' => $userId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function allActive(): array {
        $rows = Database::query("SELECT * FROM email_campaigns WHERE status = 'active' ORDER BY id ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM email_campaigns ORDER BY id DESC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_campaigns (
                    user_id, name, status, daily_campaign_limit, sending_interval,
                    start_time, end_time, timezone, last_used_gmail_account_id, last_sent_at,
                    total_recipients, sent_count, failed_count, skipped_count, cancelled_count,
                    created_at, updated_at
                ) VALUES (
                    :user_id, :name, :status, :daily_campaign_limit, :sending_interval,
                    :start_time, :end_time, :timezone, :last_used_gmail_account_id, :last_sent_at,
                    :total_recipients, 0, 0, 0, 0,
                    {$now}, {$now}
                )";

        Database::execute($sql, [
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'status' => $data['status'] ?? 'draft',
            'daily_campaign_limit' => $data['daily_campaign_limit'] ?? 300,
            'sending_interval' => $data['sending_interval'] ?? 60,
            'start_time' => $data['start_time'] ?? '00:00',
            'end_time' => $data['end_time'] ?? '23:59',
            'timezone' => $data['timezone'] ?? 'Asia/Dhaka',
            'last_used_gmail_account_id' => $data['last_used_gmail_account_id'] ?? null,
            'last_sent_at' => $data['last_sent_at'] ?? null,
            'total_recipients' => $data['total_recipients'] ?? 0,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "`{$key}` = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE `email_campaigns` SET " . implode(', ', $fields) . ", `updated_at` = {$now} WHERE `id` = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM email_campaigns WHERE id = :id", ['id' => $this->id]);
    }

    public function isWithinSendingSchedule(?string $customTime = null): bool {
        try {
            $tz = new DateTimeZone($this->timezone ?: 'Asia/Dhaka');
        } catch (\Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $now = new DateTime($customTime ?? 'now', $tz);
        $currentHhMm = $now->format('H:i');

        $start = trim($this->start_time ?: '00:00');
        $end = trim($this->end_time ?: '23:59');

        if ($start <= $end) {
            return ($currentHhMm >= $start && $currentHhMm <= $end);
        } else {
            // Overrides midnight (e.g. 22:00 to 06:00)
            return ($currentHhMm >= $start || $currentHhMm <= $end);
        }
    }

    public function getSendsCountToday(?string $date = null): int {
        $date = $date ?? date('Y-m-d');
        $driver = config('database.default', 'mysql');
        $dateFunc = $driver === 'mysql' ? "DATE(sent_at) = :dt" : "date(sent_at) = :dt";
        $row = Database::first(
            "SELECT COUNT(*) as c FROM email_campaign_sends WHERE campaign_id = :cid AND status = 'sent' AND {$dateFunc}",
            ['cid' => $this->id, 'dt' => $date]
        );
        return (int)($row['c'] ?? 0);
    }

    public function recalculateStats(): void {
        $row = Database::first("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM email_campaign_recipients 
            WHERE campaign_id = :cid
        ", ['cid' => $this->id]);

        $this->update([
            'total_recipients' => (int)($row['total'] ?? 0),
            'sent_count' => (int)($row['sent'] ?? 0),
            'failed_count' => (int)($row['failed'] ?? 0),
            'skipped_count' => (int)($row['skipped'] ?? 0),
            'cancelled_count' => (int)($row['cancelled'] ?? 0),
        ]);
    }

    public function getMessages(): array {
        return EmailCampaignMessage::findByCampaignId($this->id);
    }

    public function getActiveMessages(): array {
        return EmailCampaignMessage::findActiveByCampaignId($this->id);
    }

    public function getRemainingCount(): int {
        $row = Database::first("
            SELECT COUNT(*) as c 
            FROM email_campaign_recipients 
            WHERE campaign_id = :cid AND status IN ('pending', 'queued')
        ", ['cid' => $this->id]);
        return (int)($row['c'] ?? 0);
    }

    public function getProgressPercentage(): float {
        if ($this->total_recipients <= 0) {
            return 0.0;
        }
        $processed = $this->sent_count + $this->failed_count + $this->skipped_count + $this->cancelled_count;
        return round(($processed / $this->total_recipients) * 100, 1);
    }

    public static function fromRow(array $row): self {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->user_id = (int)$row['user_id'];
        $c->name = $row['name'];
        $c->status = $row['status'] ?? 'draft';
        $c->daily_campaign_limit = (int)($row['daily_campaign_limit'] ?? 300);
        $c->sending_interval = (int)($row['sending_interval'] ?? 60);
        $c->start_time = $row['start_time'] ?? '00:00';
        $c->end_time = $row['end_time'] ?? '23:59';
        $c->timezone = $row['timezone'] ?? 'Asia/Dhaka';
        $c->last_used_gmail_account_id = isset($row['last_used_gmail_account_id']) ? (int)$row['last_used_gmail_account_id'] : null;
        $c->last_sent_at = $row['last_sent_at'] ?? null;
        $c->total_recipients = (int)($row['total_recipients'] ?? 0);
        $c->sent_count = (int)($row['sent_count'] ?? 0);
        $c->failed_count = (int)($row['failed_count'] ?? 0);
        $c->skipped_count = (int)($row['skipped_count'] ?? 0);
        $c->cancelled_count = (int)($row['cancelled_count'] ?? 0);
        $c->created_at = $row['created_at'] ?? null;
        $c->updated_at = $row['updated_at'] ?? null;
        return $c;
    }
}
