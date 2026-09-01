<?php
namespace App\Models;

use App\Core\Database;

class AutomationSetting {
    public int $id;
    public int $gmail_account_id;
    public bool $auto_reply_enabled;
    public ?string $reply_message = null;
    public int $max_reply_per_thread;
    public int $daily_reply_limit;
    public int $reply_delay;
    public bool $followup_enabled;
    public int $daily_followup_limit;
    public bool $require_recipient_reply_before_next_reply = false;
    public string $timezone;
    public string $working_days;
    public string $working_start;
    public string $working_end;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function findByAccountId(int $accountId): ?self {
        $row = Database::first("SELECT * FROM automation_settings WHERE gmail_account_id = :acc LIMIT 1", ['acc' => $accountId]);
        return $row ? self::fromRow($row) : null;
    }

    public static function createOrGet(int $accountId): self {
        return self::createDefault($accountId);
    }

    public static function createDefault(int $accountId): self {
        $existing = self::findByAccountId($accountId);
        if ($existing) {
            return $existing;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO automation_settings 
                (gmail_account_id, auto_reply_enabled, reply_message, max_reply_per_thread, daily_reply_limit, reply_delay, followup_enabled, daily_followup_limit, require_recipient_reply_before_next_reply, timezone, working_days, working_start, working_end, created_at)
                VALUES 
                (:acc, 0, NULL, 3, 100, 0, 0, 100, 0, 'Asia/Dhaka', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday', '00:00', '23:59', {$now})";

        Database::execute($sql, [
            'acc' => $accountId,
        ]);

        return self::findByAccountId($accountId);
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
        $sql = "UPDATE automation_settings SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getReplyStepsData(): array {
        if (!$this->reply_message) {
            return [];
        }

        // Detect any legacy hardcoded boilerplate and reject immediately
        if (str_contains($this->reply_message, 'Thank you for reaching out') || 
            str_contains($this->reply_message, 'Automated Support')) {
            return [];
        }

        $decoded = json_decode($this->reply_message, true);
        if (is_array($decoded) && !empty($decoded)) {
            $result = [];
            foreach ($decoded as $step => $data) {
                if ($step === '_blacklist') {
                    continue;
                }
                $stepNum = (int)$step;
                if ($stepNum <= 0) {
                    continue;
                }

                $msg = is_array($data) ? ($data['message'] ?? '') : (string)$data;
                if (str_contains($msg, 'Thank you for reaching out') || str_contains($msg, 'Automated Support')) {
                    $msg = '';
                }

                if (is_array($data)) {
                    $result[$stepNum] = [
                        'message' => $msg,
                        'delay_value' => (int)($data['delay_value'] ?? 0),
                        'delay_unit' => $data['delay_unit'] ?? 'seconds'
                    ];
                } else {
                    $result[$stepNum] = [
                        'message' => $msg,
                        'delay_value' => $stepNum === 1 ? (int)$this->reply_delay : 0,
                        'delay_unit' => 'seconds'
                    ];
                }
            }
            if (!empty($result)) {
                ksort($result);
                return $result;
            }
        } elseif (!empty(trim(strip_tags($this->reply_message)))) {
            // Raw text or HTML string stored directly
            return [
                1 => [
                    'message' => $this->reply_message,
                    'delay_value' => (int)$this->reply_delay,
                    'delay_unit' => 'seconds'
                ]
            ];
        }

        return [];
    }

    public function getTotalConfiguredReplySteps(): int {
        $steps = $this->getReplyStepsData();
        if (empty($steps)) {
            if (!empty($this->reply_message) && !empty(trim(strip_tags($this->reply_message)))) {
                return 1;
            }
            return 0;
        }
        return max(array_keys($steps));
    }

    public function getReplyMessages(): array {
        $steps = $this->getReplyStepsData();
        $msgs = [];
        foreach ($steps as $s => $item) {
            $msgs[$s] = $item['message'];
        }
        return $msgs;
    }

    public function getReplyMessageForStep(int $step): string {
        $steps = $this->getReplyStepsData();
        
        // 1. Direct step match
        if (isset($steps[$step])) {
            $msg = trim($steps[$step]['message'] ?? '');
            if (!empty($msg) && $msg !== '<p><br></p>') {
                return $steps[$step]['message'];
            }
        }

        // Return empty string if not configured by user. Never return hardcoded demo text.
        return '';
    }

    public function getReplyDelaySecondsForStep(int $step): int {
        $steps = $this->getReplyStepsData();
        $stepData = $steps[$step] ?? ($steps[1] ?? null);
        if (!$stepData) {
            return (int)$this->reply_delay;
        }

        $val = (int)($stepData['delay_value'] ?? 0);
        $unit = $stepData['delay_unit'] ?? 'seconds';

        switch ($unit) {
            case 'minutes': return $val * 60;
            case 'hours':   return $val * 3600;
            case 'days':    return $val * 86400;
            case 'seconds':
            default:        return $val;
        }
    }

    public function getBlacklistData(): array {
        if (!$this->reply_message) {
            return ['emails' => '', 'domains' => '', 'keywords' => ''];
        }

        $decoded = json_decode($this->reply_message, true);
        if (is_array($decoded) && isset($decoded['_blacklist']) && is_array($decoded['_blacklist'])) {
            return [
                'emails' => $decoded['_blacklist']['emails'] ?? '',
                'domains' => $decoded['_blacklist']['domains'] ?? '',
                'keywords' => $decoded['_blacklist']['keywords'] ?? '',
            ];
        }

        return ['emails' => '', 'domains' => '', 'keywords' => ''];
    }

    public function getBlacklistEmails(): string {
        return $this->getBlacklistData()['emails'] ?? '';
    }

    public function getBlacklistDomains(): string {
        return $this->getBlacklistData()['domains'] ?? '';
    }

    public function getBlacklistKeywords(): string {
        return $this->getBlacklistData()['keywords'] ?? '';
    }

    public static function fromRow(array $row): self {
        $setting = new self();
        $setting->id = (int)$row['id'];
        $setting->gmail_account_id = (int)$row['gmail_account_id'];
        $setting->auto_reply_enabled = (bool)$row['auto_reply_enabled'];
        $setting->reply_message = $row['reply_message'] ?? null;
        $setting->max_reply_per_thread = (int)($row['max_reply_per_thread'] ?? 3);
        $setting->daily_reply_limit = (int)($row['daily_reply_limit'] ?? 100);
        $setting->reply_delay = (int)($row['reply_delay'] ?? 0);
        $setting->followup_enabled = (bool)($row['followup_enabled'] ?? false);
        $setting->daily_followup_limit = (int)($row['daily_followup_limit'] ?? 100);
        $setting->require_recipient_reply_before_next_reply = (bool)($row['require_recipient_reply_before_next_reply'] ?? false);
        $setting->timezone = $row['timezone'] ?? 'Asia/Dhaka';
        $setting->working_days = $row['working_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
        $setting->working_start = $row['working_start'] ?? '00:00';
        $setting->working_end = $row['working_end'] ?? '23:59';
        $setting->created_at = $row['created_at'] ?? null;
        $setting->updated_at = $row['updated_at'] ?? null;
        return $setting;
    }
}
