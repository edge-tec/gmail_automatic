<?php
namespace App\Models;

use App\Core\Database;

class LeadStorageCleanup {
    public int $id;
    public ?int $operation_id = null;
    public ?int $lead_file_id = null;
    public int $user_id;
    public string $file_path;
    public ?string $file_hash = null;
    public int $file_size = 0;
    public string $status = 'pending';
    public int $retry_count = 0;
    public int $max_retries = 5;
    public ?string $next_retry_at = null;
    public ?string $last_error = null;
    public ?string $error_category = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $bigintType = ($driver === 'mysql') ? 'BIGINT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';

        $sql = "CREATE TABLE IF NOT EXISTS lead_storage_cleanups (
            id {$autoInc},
            operation_id {$intType} NULL,
            lead_file_id {$intType} NULL,
            user_id {$intType} NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_hash VARCHAR(64) NULL,
            file_size {$bigintType} NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            retry_count {$intType} NOT NULL DEFAULT 0,
            max_retries {$intType} NOT NULL DEFAULT 5,
            next_retry_at {$dateDefault} NULL,
            last_error TEXT NULL,
            error_category VARCHAR(50) NULL,
            created_at {$dateDefault},
            updated_at {$dateDefault}
        )";

        try {
            Database::execute($sql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function create(array $data): self {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO lead_storage_cleanups (
            operation_id, lead_file_id, user_id, file_path, file_hash, file_size, status,
            retry_count, max_retries, next_retry_at, created_at, updated_at
        ) VALUES (
            :op_id, :file_id, :uid, :path, :hash, :size, :status,
            0, :max_ret, {$now}, {$now}, {$now}
        )";

        Database::execute($sql, [
            'op_id' => !empty($data['operation_id']) ? (int)$data['operation_id'] : null,
            'file_id' => !empty($data['lead_file_id']) ? (int)$data['lead_file_id'] : null,
            'uid' => (int)$data['user_id'],
            'path' => (string)$data['file_path'],
            'hash' => $data['file_hash'] ?? null,
            'size' => (int)($data['file_size'] ?? 0),
            'status' => $data['status'] ?? 'pending',
            'max_ret' => (int)($data['max_retries'] ?? 5),
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_storage_cleanups WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function getPendingCleanups(int $limit = 100): array {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $rows = Database::query(
            "SELECT * FROM lead_storage_cleanups 
             WHERE status = 'pending' 
               AND (next_retry_at IS NULL OR next_retry_at <= {$now})
               AND retry_count < max_retries 
             ORDER BY id ASC 
             LIMIT {$limit}"
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function getPending(int $limit = 100): array {
        return self::getPendingCleanups($limit);
    }

    public static function getRecent(int $limit = 50): array {
        self::ensureSchema();
        $rows = Database::query("SELECT * FROM lead_storage_cleanups ORDER BY id DESC LIMIT {$limit}");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function getFailedCleanups(int $limit = 100): array {
        self::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM lead_storage_cleanups WHERE status = 'failed' ORDER BY id DESC LIMIT {$limit}"
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public function update(array $data): bool {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $fields = [];
        $params = ['id' => $this->id];

        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;

            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = {$now}";
        $sql = "UPDATE lead_storage_cleanups SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getAbsolutePath(): string {
        if (str_starts_with($this->file_path, '/') || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-zA-Z]:\\\\/', $this->file_path))) {
            return $this->file_path;
        }
        return storage_path($this->file_path);
    }

    public static function fromRow(array $row): self {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->operation_id = !empty($row['operation_id']) ? (int)$row['operation_id'] : null;
        $c->lead_file_id = !empty($row['lead_file_id']) ? (int)$row['lead_file_id'] : null;
        $c->user_id = (int)$row['user_id'];
        $c->file_path = (string)$row['file_path'];
        $c->file_hash = !empty($row['file_hash']) ? (string)$row['file_hash'] : null;
        $c->file_size = (int)($row['file_size'] ?? 0);
        $c->status = (string)($row['status'] ?? 'pending');
        $c->retry_count = (int)($row['retry_count'] ?? 0);
        $c->max_retries = (int)($row['max_retries'] ?? 5);
        $c->next_retry_at = !empty($row['next_retry_at']) ? (string)$row['next_retry_at'] : null;
        $c->last_error = !empty($row['last_error']) ? (string)$row['last_error'] : null;
        $c->error_category = !empty($row['error_category']) ? (string)$row['error_category'] : null;
        $c->created_at = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        $c->updated_at = (string)($row['updated_at'] ?? date('Y-m-d H:i:s'));
        return $c;
    }
}
