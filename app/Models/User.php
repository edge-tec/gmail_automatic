<?php
namespace App\Models;

use App\Core\Database;

class User {
    public int $id;
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public string $status;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM users WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self {
        $row = Database::first("SELECT * FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM users ORDER BY id DESC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $sql = "INSERT INTO users (name, email, password, role, status, created_at) 
                VALUES (:name, :email, :password, :role, :status, datetime('now'))";
        
        $driver = config('database.default', 'mysql');
        if ($driver === 'mysql') {
            $sql = "INSERT INTO users (name, email, password, role, status, created_at) 
                    VALUES (:name, :email, :password, :role, :status, NOW())";
        }

        Database::execute($sql, [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'user',
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
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM users WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $user = new self();
        $user->id = (int)$row['id'];
        $user->name = $row['name'];
        $user->email = $row['email'];
        $user->password = $row['password'];
        $user->role = $row['role'] ?? 'user';
        $user->status = $row['status'] ?? 'active';
        $user->created_at = $row['created_at'] ?? null;
        $user->updated_at = $row['updated_at'] ?? null;
        return $user;
    }
}
