<?php
namespace App\Models;

use App\Core\Database;

class LeadDeletionOperation {
    public int $id;
    public string $operation_uuid;
    public int $user_id;
    public int $actor_id;
    public string $action_type;
    public ?string $scope = null;
    public ?array $scope_filter = null;
    public int $requested_count = 0;
    public int $completed_count = 0;
    public int $failed_count = 0;
    public int $files_deleted = 0;
    public int $files_pending = 0;
    public int $storage_freed = 0;
    public string $status = 'pending';
    public ?string $error_category = null;
    public ?string $last_error = null;
    public int $current_batch = 0;
    public int $total_batches = 0;
    public ?string $started_at = null;
    public ?string $completed_at = null;
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

        $sql = "CREATE TABLE IF NOT EXISTS lead_deletion_operations (
            id {$autoInc},
            operation_uuid VARCHAR(64) NOT NULL UNIQUE,
            user_id {$intType} NOT NULL,
            actor_id {$intType} NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            scope VARCHAR(100) NULL,
            scope_filter TEXT NULL,
            requested_count {$intType} NOT NULL DEFAULT 0,
            completed_count {$intType} NOT NULL DEFAULT 0,
            failed_count {$intType} NOT NULL DEFAULT 0,
            files_deleted {$intType} NOT NULL DEFAULT 0,
            files_pending {$intType} NOT NULL DEFAULT 0,
            storage_freed {$bigintType} NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            error_category VARCHAR(50) NULL,
            last_error TEXT NULL,
            current_batch {$intType} NOT NULL DEFAULT 0,
            total_batches {$intType} NOT NULL DEFAULT 0,
            started_at {$dateDefault} NULL,
            completed_at {$dateDefault} NULL,
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

        $uuid = $data['operation_uuid'] ?? bin2hex(random_bytes(16));
        $filterJson = !empty($data['scope_filter']) ? json_encode($data['scope_filter']) : null;

        $sql = "INSERT INTO lead_deletion_operations (
            operation_uuid, user_id, actor_id, action_type, scope, scope_filter,
            requested_count, completed_count, failed_count, files_deleted, files_pending,
            storage_freed, status, error_category, last_error, current_batch, total_batches,
            started_at, created_at, updated_at
        ) VALUES (
            :uuid, :uid, :aid, :act, :scope, :filter,
            :req, 0, 0, 0, 0, 0, :status, :err_cat, :err_msg, 0, :tot_batch,
            {$now}, {$now}, {$now}
        )";

        Database::execute($sql, [
            'uuid' => $uuid,
            'uid' => (int)$data['user_id'],
            'aid' => (int)$data['actor_id'],
            'act' => (string)$data['action_type'],
            'scope' => $data['scope'] ?? null,
            'filter' => $filterJson,
            'req' => (int)($data['requested_count'] ?? 0),
            'status' => $data['status'] ?? 'processing',
            'err_cat' => $data['error_category'] ?? null,
            'err_msg' => $data['last_error'] ?? null,
            'tot_batch' => (int)($data['total_batches'] ?? 1),
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_deletion_operations WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUuid(string $uuid): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_deletion_operations WHERE operation_uuid = :u LIMIT 1", ['u' => $uuid]);
        return $row ? self::fromRow($row) : null;
    }

    public function update(array $data): bool {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $fields = [];
        $params = ['id' => $this->id];

        foreach ($data as $key => $val) {
            if ($key === 'scope_filter' && is_array($val)) {
                $val = json_encode($val);
            }
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;

            if (property_exists($this, $key)) {
                $this->$key = ($key === 'scope_filter' && is_string($val)) ? json_decode($val, true) : $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = {$now}";
        $sql = "UPDATE lead_deletion_operations SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getProgressPercentage(): float {
        if ($this->requested_count <= 0) {
            return ($this->status === 'completed' || $this->status === 'partial') ? 100.0 : 0.0;
        }
        $processed = $this->completed_count + $this->failed_count;
        return min(100.0, round(($processed / $this->requested_count) * 100, 1));
    }

    public static function fromRow(array $row): self {
        $op = new self();
        $op->id = (int)$row['id'];
        $op->operation_uuid = (string)$row['operation_uuid'];
        $op->user_id = (int)$row['user_id'];
        $op->actor_id = (int)$row['actor_id'];
        $op->action_type = (string)$row['action_type'];
        $op->scope = !empty($row['scope']) ? (string)$row['scope'] : null;
        $op->scope_filter = !empty($row['scope_filter']) ? json_decode($row['scope_filter'], true) : null;
        $op->requested_count = (int)($row['requested_count'] ?? 0);
        $op->completed_count = (int)($row['completed_count'] ?? 0);
        $op->failed_count = (int)($row['failed_count'] ?? 0);
        $op->files_deleted = (int)($row['files_deleted'] ?? 0);
        $op->files_pending = (int)($row['files_pending'] ?? 0);
        $op->storage_freed = (int)($row['storage_freed'] ?? 0);
        $op->status = (string)($row['status'] ?? 'pending');
        $op->error_category = !empty($row['error_category']) ? (string)$row['error_category'] : null;
        $op->last_error = !empty($row['last_error']) ? (string)$row['last_error'] : null;
        $op->current_batch = (int)($row['current_batch'] ?? 0);
        $op->total_batches = (int)($row['total_batches'] ?? 0);
        $op->started_at = !empty($row['started_at']) ? (string)$row['started_at'] : null;
        $op->completed_at = !empty($row['completed_at']) ? (string)$row['completed_at'] : null;
        $op->created_at = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        $op->updated_at = (string)($row['updated_at'] ?? date('Y-m-d H:i:s'));
        return $op;
    }
}
