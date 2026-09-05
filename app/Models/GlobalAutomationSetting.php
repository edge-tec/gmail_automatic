<?php
namespace App\Models;

use App\Core\Database;

class GlobalAutomationSetting {
    public int $id;
    public int $user_id;
    public bool $auto_reply_enabled = true;
    public bool $followup_enabled = true;
    public bool $require_recipient_reply_before_next_reply = false;
    public int $max_reply_per_thread = 3;
    public int $daily_reply_limit = 100;
    public int $daily_followup_limit = 100;
    public int $reply_delay = 0;
    public string $reply_time_type = 'instant';
    public string $timezone = 'Asia/Dhaka';
    public string $working_days = 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
    public string $working_start = '00:00';
    public string $working_end = '23:59';
    public int $version = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __get(string $name) {
        if ($name === 'daily_reply_limit_per_account') {
            return $this->daily_reply_limit;
        }
        if ($name === 'daily_followup_limit_per_account') {
            return $this->daily_followup_limit;
        }
        return null;
    }

    public function __set(string $name, $value) {
        if ($name === 'daily_reply_limit_per_account') {
            $this->daily_reply_limit = (int)$value;
        } elseif ($name === 'daily_followup_limit_per_account') {
            $this->daily_followup_limit = (int)$value;
        } else {
            $this->$name = $value;
        }
    }

    public static function findByUserId(int $userId): ?self {
        $row = Database::first("SELECT * FROM global_automation_settings WHERE user_id = :uid LIMIT 1", ['uid' => $userId]);
        return $row ? self::fromRow($row) : null;
    }

    public static function getOrCreate(int $userId): self {
        $existing = self::findByUserId($userId);
        if ($existing) {
            return $existing;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO global_automation_settings 
                (user_id, auto_reply_enabled, followup_enabled, require_recipient_reply_before_next_reply, max_reply_per_thread, daily_reply_limit, daily_followup_limit, reply_delay, timezone, working_days, working_start, working_end, version, created_at)
                VALUES 
                (:uid, 1, 1, 0, 3, 100, 100, 0, 'Asia/Dhaka', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday', '00:00', '23:59', 1, {$now})";

        try {
            Database::execute($sql, ['uid' => $userId]);
        } catch (\Throwable $e) {
            // Already created concurrently
        }

        return self::findByUserId($userId) ?? new self();
    }

    public static function getForUser(int $userId): self {
        return self::getOrCreate($userId);
    }

    public function bumpVersion(): void {
        $this->version++;
    }

    public function save(): bool {
        if (isset($this->id) && $this->id > 0) {
            return $this->update([
                'auto_reply_enabled' => $this->auto_reply_enabled ? 1 : 0,
                'followup_enabled' => $this->followup_enabled ? 1 : 0,
                'require_recipient_reply_before_next_reply' => $this->require_recipient_reply_before_next_reply ? 1 : 0,
                'max_reply_per_thread' => $this->max_reply_per_thread,
                'daily_reply_limit' => $this->daily_reply_limit,
                'daily_followup_limit' => $this->daily_followup_limit,
                'reply_delay' => $this->reply_delay,
                'timezone' => $this->timezone,
                'working_days' => $this->working_days,
                'working_start' => $this->working_start,
                'working_end' => $this->working_end,
                'version' => $this->version,
            ]);
        }

        return false;
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
        $sql = "UPDATE global_automation_settings SET " . implode(', ', $fields) . ", version = version + 1, updated_at = {$now} WHERE id = :id";
        $ok = Database::execute($sql, $params);
        if ($ok) {
            $this->version++;
        }
        return $ok;
    }

    public static function fromRow(array $row): self {
        $setting = new self();
        $setting->id = (int)$row['id'];
        $setting->user_id = (int)$row['user_id'];
        $setting->auto_reply_enabled = (bool)($row['auto_reply_enabled'] ?? true);
        $setting->followup_enabled = (bool)($row['followup_enabled'] ?? true);
        $setting->require_recipient_reply_before_next_reply = (bool)($row['require_recipient_reply_before_next_reply'] ?? false);
        $setting->max_reply_per_thread = (int)($row['max_reply_per_thread'] ?? 3);
        $setting->daily_reply_limit = (int)($row['daily_reply_limit'] ?? 100);
        $setting->daily_followup_limit = (int)($row['daily_followup_limit'] ?? 100);
        $setting->reply_delay = (int)($row['reply_delay'] ?? 0);
        $setting->timezone = $row['timezone'] ?? 'Asia/Dhaka';
        $setting->working_days = $row['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
        $setting->working_start = $row['working_start'] ?? '00:00';
        $setting->working_end = $row['working_end'] ?? '23:59';
        $setting->version = (int)($row['version'] ?? 1);
        $setting->created_at = $row['created_at'] ?? null;
        $setting->updated_at = $row['updated_at'] ?? null;
        return $setting;
    }
}
