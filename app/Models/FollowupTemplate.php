<?php
namespace App\Models;

use App\Core\Database;

class FollowupTemplate {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public int $step_number;
    public string $name;
    public string $message;
    public int $delay_value;
    public string $delay_unit; // minutes, hours, days
    public string $status;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM followup_templates WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountId(int $accountId): array {
        $rows = Database::query(
            "SELECT * FROM followup_templates WHERE gmail_account_id = :acc ORDER BY step_number ASC",
            ['acc' => $accountId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function findNextStep(int $accountId, int $currentStep): ?self {
        $row = Database::first(
            "SELECT * FROM followup_templates 
             WHERE gmail_account_id = :acc AND step_number > :cur AND status = 'active' 
             ORDER BY step_number ASC LIMIT 1",
            ['acc' => $accountId, 'cur' => $currentStep]
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO followup_templates 
                (user_id, gmail_account_id, step_number, name, message, delay_value, delay_unit, status, created_at)
                VALUES 
                (:uid, :acc, :step, :name, :msg, :dv, :du, :status, {$now})";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'acc' => $data['gmail_account_id'],
            'step' => $data['step_number'] ?? 1,
            'name' => $data['name'],
            'msg' => $data['message'],
            'dv' => $data['delay_value'] ?? 2,
            'du' => $data['delay_unit'] ?? 'days',
            'status' => $data['status'] ?? 'active',
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
        $sql = "UPDATE followup_templates SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM followup_templates WHERE id = :id", ['id' => $this->id]);
    }

    public function calculateDelaySeconds(): int {
        return match ($this->delay_unit) {
            'minutes' => $this->delay_value * 60,
            'hours' => $this->delay_value * 3600,
            'days' => $this->delay_value * 86400,
            default => $this->delay_value * 86400,
        };
    }

    public static function fromRow(array $row): self {
        $f = new self();
        $f->id = (int)$row['id'];
        $f->user_id = (int)$row['user_id'];
        $f->gmail_account_id = (int)$row['gmail_account_id'];
        $f->step_number = (int)($row['step_number'] ?? 1);
        $f->name = $row['name'];
        $f->message = $row['message'];
        $f->delay_value = (int)($row['delay_value'] ?? 2);
        $f->delay_unit = $row['delay_unit'] ?? 'days';
        $f->status = $row['status'] ?? 'active';
        $f->created_at = $row['created_at'] ?? null;
        $f->updated_at = $row['updated_at'] ?? null;
        return $f;
    }
}
