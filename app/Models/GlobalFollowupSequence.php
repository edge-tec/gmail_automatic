<?php
namespace App\Models;

use App\Core\Database;

class GlobalFollowupSequence {
    public ?int $id = null;
    public int $user_id = 0;
    public int $step_number = 1;
    public string $name = '';
    public int $delay_value = 1;
    public string $delay_unit = 'days'; // minutes, hours, days
    public string $status = 'active';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct(array $data = []) {
        if (!empty($data)) {
            if (isset($data['id'])) $this->id = (int)$data['id'];
            if (isset($data['user_id'])) $this->user_id = (int)$data['user_id'];
            if (isset($data['step_number'])) $this->step_number = (int)$data['step_number'];
            if (isset($data['name'])) $this->name = $data['name'];
            if (isset($data['delay_value'])) $this->delay_value = (int)$data['delay_value'];
            if (isset($data['delay_unit'])) $this->delay_unit = $data['delay_unit'];
            if (isset($data['status'])) $this->status = $data['status'];
            if (isset($data['is_active'])) {
                $this->status = $data['is_active'] ? 'active' : 'inactive';
            }
        }
    }

    public function __get(string $name) {
        if ($name === 'is_active') return $this->status === 'active';
        return null;
    }

    public function __set(string $name, $value) {
        if ($name === 'is_active') {
            $this->status = $value ? 'active' : 'inactive';
        } else {
            $this->$name = $value;
        }
    }

    public function __isset(string $name): bool {
        return $name === 'is_active' || isset($this->$name);
    }

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM global_followup_sequences WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId): array {
        $rows = Database::query(
            "SELECT * FROM global_followup_sequences WHERE user_id = :uid ORDER BY step_number ASC",
            ['uid' => $userId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function getForUser(int $userId): array {
        return self::findByUserId($userId);
    }

    public static function findByStep(int $userId, int $stepNumber): ?self {
        $row = Database::first(
            "SELECT * FROM global_followup_sequences WHERE user_id = :uid AND step_number = :step LIMIT 1",
            ['uid' => $userId, 'step' => $stepNumber]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function deleteStep(int $userId, int $stepNumber): bool {
        return Database::execute(
            "DELETE FROM global_followup_sequences WHERE user_id = :uid AND step_number = :step",
            ['uid' => $userId, 'step' => $stepNumber]
        );
    }

    public static function findNextStep(int $userId, int $currentStep): ?self {
        $row = Database::first(
            "SELECT * FROM global_followup_sequences 
             WHERE user_id = :uid AND step_number > :cur AND status = 'active' 
             ORDER BY step_number ASC LIMIT 1",
            ['uid' => $userId, 'cur' => $currentStep]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function create(array $data): self {
        $model = new self($data);
        $model->save();
        return $model;
    }

    public function save(): bool {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        if (empty($this->name)) {
            $this->name = 'Follow-up #' . $this->step_number;
        }

        if (isset($this->id) && $this->id > 0) {
            $sql = "UPDATE global_followup_sequences 
                    SET step_number = :step, name = :name, delay_value = :dv, delay_unit = :du, status = :status, updated_at = {$now} 
                    WHERE id = :id";
            return Database::execute($sql, [
                'id' => $this->id,
                'step' => $this->step_number,
                'name' => $this->name,
                'dv' => $this->delay_value,
                'du' => $this->delay_unit,
                'status' => $this->status,
            ]);
        }

        $sql = "INSERT INTO global_followup_sequences 
                (user_id, step_number, name, delay_value, delay_unit, status, created_at)
                VALUES 
                (:uid, :step, :name, :dv, :du, :status, {$now})";

        $ok = Database::execute($sql, [
            'uid' => $this->user_id,
            'step' => $this->step_number,
            'name' => $this->name,
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
            return Database::execute("DELETE FROM global_followup_sequences WHERE id = :id", ['id' => $this->id]);
        }
        return false;
    }

    public static function fromRow(array $row): self {
        $s = new self();
        $s->id = (int)$row['id'];
        $s->user_id = (int)$row['user_id'];
        $s->step_number = (int)($row['step_number'] ?? 1);
        $s->name = $row['name'] ?? 'Follow-up #' . $s->step_number;
        $s->delay_value = (int)($row['delay_value'] ?? 1);
        $s->delay_unit = $row['delay_unit'] ?? 'days';
        $s->status = $row['status'] ?? 'active';
        $s->created_at = $row['created_at'] ?? null;
        $s->updated_at = $row['updated_at'] ?? null;
        return $s;
    }
}
