<?php
namespace App\Models;

use App\Core\Database;

class ReplyTemplate {
    public int $id;
    public int $user_id;
    public string $name;
    public string $message;
    public string $status;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM reply_templates WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId): array {
        $rows = Database::query("SELECT * FROM reply_templates WHERE user_id = :uid ORDER BY id DESC", ['uid' => $userId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO reply_templates (user_id, name, message, status, created_at)
                VALUES (:uid, :name, :msg, :status, {$now})";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'name' => $data['name'],
            'msg' => $data['message'],
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
        $sql = "UPDATE reply_templates SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM reply_templates WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->user_id = (int)$row['user_id'];
        $r->name = $row['name'];
        $r->message = $row['message'];
        $r->status = $row['status'] ?? 'active';
        $r->created_at = $row['created_at'] ?? null;
        $r->updated_at = $row['updated_at'] ?? null;
        return $r;
    }
}
