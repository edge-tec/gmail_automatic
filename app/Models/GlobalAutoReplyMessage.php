<?php
namespace App\Models;

use App\Core\Database;

class GlobalAutoReplyMessage {
    public ?int $id = null;
    public int $user_id = 0;
    public int $step_number = 1;
    public string $variation_name = 'Variation A';
    public string $message = '';
    public int $delay_value = 0;
    public string $delay_unit = 'minutes';
    public string $status = 'active';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct(array $data = []) {
        if (!empty($data)) {
            if (isset($data['id'])) $this->id = (int)$data['id'];
            if (isset($data['user_id'])) $this->user_id = (int)$data['user_id'];
            if (isset($data['step_number'])) $this->step_number = (int)$data['step_number'];
            if (isset($data['variation_name'])) $this->variation_name = $data['variation_name'];
            if (isset($data['message'])) $this->message = $data['message'];
            if (isset($data['body_html'])) $this->message = $data['body_html'];
            if (isset($data['delay_value'])) $this->delay_value = (int)$data['delay_value'];
            if (isset($data['delay_minutes'])) {
                $this->delay_value = (int)$data['delay_minutes'];
                $this->delay_unit = 'minutes';
            }
            if (isset($data['delay_unit'])) $this->delay_unit = $data['delay_unit'];
            if (isset($data['status'])) $this->status = $data['status'];
            if (isset($data['is_active'])) {
                $this->status = $data['is_active'] ? 'active' : 'inactive';
            }
        }
    }

    public function __get(string $name) {
        if ($name === 'body_html') return $this->message;
        if ($name === 'delay_minutes') return $this->delay_value;
        if ($name === 'is_active') return $this->status === 'active';
        return null;
    }

    public function __set(string $name, $value) {
        if ($name === 'body_html') {
            $this->message = (string)$value;
        } elseif ($name === 'delay_minutes') {
            $this->delay_value = (int)$value;
            $this->delay_unit = 'minutes';
        } elseif ($name === 'is_active') {
            $this->status = $value ? 'active' : 'inactive';
        } else {
            $this->$name = $value;
        }
    }

    public function __isset(string $name): bool {
        return in_array($name, ['body_html', 'delay_minutes', 'is_active']) || isset($this->$name);
    }

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM global_auto_reply_messages WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function getActiveVariations(int $userId, int $stepNumber): array {
        $rows = Database::query(
            "SELECT * FROM global_auto_reply_messages 
             WHERE user_id = :uid AND step_number = :step AND status = 'active' 
             ORDER BY id ASC",
            ['uid' => $userId, 'step' => $stepNumber]
        );

        $valid = [];
        foreach ($rows as $r) {
            $msg = $r['message'] ?? '';
            $clean = trim(strip_tags($msg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
            $isPlaceholder = in_array(trim($msg), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
            if (!empty($clean) && !$isPlaceholder) {
                $valid[] = self::fromRow($r);
            }
        }
        return $valid;
    }

    public static function getRandomVariation(int $userId, int $stepNumber): ?self {
        $active = self::getActiveVariations($userId, $stepNumber);
        if (empty($active)) {
            return null;
        }
        $randomIndex = array_rand($active);
        return $active[$randomIndex];
    }

    public static function getAllSteps(int $userId): array {
        $rows = Database::query(
            "SELECT * FROM global_auto_reply_messages 
             WHERE user_id = :uid 
             ORDER BY step_number ASC, id ASC",
            ['uid' => $userId]
        );

        $grouped = [];
        foreach ($rows as $r) {
            $obj = self::fromRow($r);
            $grouped[$obj->step_number][] = $obj;
        }
        ksort($grouped);
        return $grouped;
    }

    public static function getForUserGroupedByStep(int $userId): array {
        return self::getAllSteps($userId);
    }

    public static function getForStep(int $userId, int $stepNumber): array {
        $rows = Database::query(
            "SELECT * FROM global_auto_reply_messages 
             WHERE user_id = :uid AND step_number = :step 
             ORDER BY id ASC",
            ['uid' => $userId, 'step' => $stepNumber]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function deleteStep(int $userId, int $stepNumber): bool {
        return Database::execute(
            "DELETE FROM global_auto_reply_messages WHERE user_id = :uid AND step_number = :step",
            ['uid' => $userId, 'step' => $stepNumber]
        );
    }

    public static function getTotalConfiguredSteps(int $userId): int {
        $steps = self::getAllSteps($userId);
        $activeSteps = [];
        foreach ($steps as $stepNum => $variations) {
            foreach ($variations as $v) {
                if ($v->status === 'active') {
                    $clean = trim(strip_tags($v->message, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
                    $isPlaceholder = in_array(trim($v->message), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
                    if (!empty($clean) && !$isPlaceholder) {
                        $activeSteps[$stepNum] = true;
                        break;
                    }
                }
            }
        }
        return count($activeSteps) > 0 ? max(array_keys($activeSteps)) : 0;
    }

    public static function getDelaySecondsForStep(int $userId, int $stepNumber): int {
        $row = Database::first(
            "SELECT delay_value, delay_unit FROM global_auto_reply_messages 
             WHERE user_id = :uid AND step_number = :step AND status = 'active' 
             ORDER BY id ASC LIMIT 1",
            ['uid' => $userId, 'step' => $stepNumber]
        );

        if (!$row) {
            return 0;
        }

        $val = (int)($row['delay_value'] ?? 0);
        $unit = $row['delay_unit'] ?? 'minutes';

        return match ($unit) {
            'minutes' => $val * 60,
            'hours' => $val * 3600,
            'days' => $val * 86400,
            default => $val,
        };
    }

    public static function create(array $data): self {
        $model = new self($data);
        $model->save();
        return $model;
    }

    public function save(): bool {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        if (isset($this->id) && $this->id > 0) {
            $sql = "UPDATE global_auto_reply_messages 
                    SET step_number = :step, variation_name = :name, message = :msg, 
                        delay_value = :dv, delay_unit = :du, status = :status, updated_at = {$now} 
                    WHERE id = :id";
            return Database::execute($sql, [
                'id' => $this->id,
                'step' => $this->step_number,
                'name' => $this->variation_name,
                'msg' => $this->message,
                'dv' => $this->delay_value,
                'du' => $this->delay_unit,
                'status' => $this->status,
            ]);
        }

        $sql = "INSERT INTO global_auto_reply_messages 
                (user_id, step_number, variation_name, message, delay_value, delay_unit, status, created_at)
                VALUES 
                (:uid, :step, :name, :msg, :dv, :du, :status, {$now})";

        $ok = Database::execute($sql, [
            'uid' => $this->user_id,
            'step' => $this->step_number,
            'name' => $this->variation_name,
            'msg' => $this->message,
            'dv' => $this->delay_value,
            'du' => $this->delay_unit,
            'status' => $this->status,
        ]);

        if ($ok) {
            $this->id = (int)Database::lastInsertId();
            return true;
        }
        return false;
    }

    public function update(array $data): bool {
        foreach ($data as $key => $val) {
            $this->$key = $val;
        }
        return $this->save();
    }

    public function delete(): bool {
        if (!empty($this->id)) {
            return Database::execute("DELETE FROM global_auto_reply_messages WHERE id = :id", ['id' => $this->id]);
        }
        return false;
    }

    public static function fromRow(array $row): self {
        $v = new self();
        $v->id = (int)$row['id'];
        $v->user_id = (int)$row['user_id'];
        $v->step_number = (int)($row['step_number'] ?? 1);
        $v->variation_name = $row['variation_name'] ?? 'Variation 1';
        $v->message = $row['message'] ?? '';
        $v->delay_value = (int)($row['delay_value'] ?? 0);
        $v->delay_unit = $row['delay_unit'] ?? 'minutes';
        $v->status = $row['status'] ?? 'active';
        $v->created_at = $row['created_at'] ?? null;
        $v->updated_at = $row['updated_at'] ?? null;
        return $v;
    }
}
