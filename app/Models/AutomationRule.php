<?php
namespace App\Models;

use App\Core\Database;

class AutomationRule {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public string $rule_type; // sender_contains, subject_contains, body_contains
    public string $rule_value;
    public ?int $template_id = null;
    public string $action; // reply, skip, custom_reply
    public string $status;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM automation_rules WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByAccountId(int $accountId): array {
        $rows = Database::query(
            "SELECT * FROM automation_rules WHERE gmail_account_id = :acc AND status = 'active' ORDER BY id ASC",
            ['acc' => $accountId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function findByAccountIdAll(int $accountId): array {
        $rows = Database::query(
            "SELECT * FROM automation_rules WHERE gmail_account_id = :acc ORDER BY id DESC",
            ['acc' => $accountId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public function getTemplate(): ?ReplyTemplate {
        return $this->template_id ? ReplyTemplate::find($this->template_id) : null;
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO automation_rules (user_id, gmail_account_id, rule_type, rule_value, template_id, action, status, created_at)
                VALUES (:uid, :acc, :type, :val, :tid, :act, :status, {$now})";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'acc' => $data['gmail_account_id'],
            'type' => $data['rule_type'],
            'val' => $data['rule_value'],
            'tid' => $data['template_id'] ?? null,
            'act' => $data['action'] ?? 'reply',
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
        $sql = "UPDATE automation_rules SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM automation_rules WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->user_id = (int)$row['user_id'];
        $r->gmail_account_id = (int)$row['gmail_account_id'];
        $r->rule_type = $row['rule_type'];
        $r->rule_value = $row['rule_value'];
        $r->template_id = isset($row['template_id']) ? (int)$row['template_id'] : null;
        $r->action = $row['action'] ?? 'reply';
        $r->status = $row['status'] ?? 'active';
        $r->created_at = $row['created_at'] ?? null;
        $r->updated_at = $row['updated_at'] ?? null;
        return $r;
    }
}
