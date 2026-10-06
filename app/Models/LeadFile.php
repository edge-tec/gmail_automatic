<?php
namespace App\Models;

use App\Core\Database;

class LeadFile {
    public int $id;
    public int $user_id;
    public ?int $lead_id = null;
    public string $file_name;
    public string $original_name;
    public string $file_path;
    public int $file_size = 0;
    public ?string $mime_type = null;
    public ?string $file_hash = null;
    public int $is_orphaned = 0;
    public string $status = 'active'; // active, marked_for_deletion, deleted, cleanup_pending, cleanup_failed
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

        $sql = "CREATE TABLE IF NOT EXISTS lead_files (
            id {$autoInc},
            user_id {$intType} NOT NULL,
            lead_id {$intType} NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size {$bigintType} NOT NULL DEFAULT 0,
            mime_type VARCHAR(100) NULL,
            file_hash VARCHAR(64) NULL,
            is_orphaned {$intType} NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at {$dateDefault},
            updated_at {$dateDefault}
        )";

        try {
            Database::execute($sql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_files WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserAndId(int $userId, int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_files WHERE id = :id AND user_id = :uid LIMIT 1", [
            'id' => $id,
            'uid' => $userId,
        ]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findForUser(int $id, int $userId): ?self {
        return self::findByUserAndId($userId, $id);
    }

    public static function forUser(int $userId): array {
        self::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM lead_files WHERE user_id = :uid AND status = 'active' ORDER BY id DESC",
            ['uid' => $userId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public function getFormattedSize(): string {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lead_id' => $this->lead_id,
            'file_name' => $this->file_name,
            'original_name' => $this->original_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'file_hash' => $this->file_hash,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }

    public static function create(array $data): self {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO lead_files (
            user_id, lead_id, file_name, original_name, file_path, file_size, mime_type, file_hash, is_orphaned, status, created_at, updated_at
        ) VALUES (
            :user_id, :lead_id, :file_name, :original_name, :file_path, :file_size, :mime_type, :file_hash, :is_orphaned, :status, {$now}, {$now}
        )";

        Database::execute($sql, [
            'user_id' => (int)$data['user_id'],
            'lead_id' => !empty($data['lead_id']) ? (int)$data['lead_id'] : null,
            'file_name' => (string)$data['file_name'],
            'original_name' => (string)$data['original_name'],
            'file_path' => (string)$data['file_path'],
            'file_size' => (int)($data['file_size'] ?? 0),
            'mime_type' => $data['mime_type'] ?? null,
            'file_hash' => $data['file_hash'] ?? null,
            'is_orphaned' => !empty($data['is_orphaned']) ? 1 : 0,
            'status' => $data['status'] ?? 'active',
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
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
        $sql = "UPDATE lead_files SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getAbsolutePath(): string {
        if (str_starts_with($this->file_path, '/') || (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[a-zA-Z]:\\\\/', $this->file_path))) {
            return $this->file_path;
        }
        return storage_path($this->file_path);
    }

    public function existsOnDisk(): bool {
        return file_exists($this->getAbsolutePath());
    }

    /**
     * Count active references sharing this file hash across the platform.
     * Prevents deleting shared files referenced by other valid entities.
     */
    public static function countActiveReferencesByHash(string $hash, ?int $excludeFileId = null): int {
        self::ensureSchema();
        if (empty($hash)) {
            return 0;
        }
        $sql = "SELECT COUNT(*) as c FROM lead_files WHERE file_hash = :h AND status = 'active'";
        $params = ['h' => $hash];

        if ($excludeFileId) {
            $sql .= " AND id != :exId";
            $params['exId'] = $excludeFileId;
        }

        $row = Database::first($sql, $params);
        return (int)($row['c'] ?? 0);
    }

    public static function fromRow(array $row): self {
        $file = new self();
        $file->id = (int)$row['id'];
        $file->user_id = (int)$row['user_id'];
        $file->lead_id = !empty($row['lead_id']) ? (int)$row['lead_id'] : null;
        $file->file_name = (string)$row['file_name'];
        $file->original_name = (string)$row['original_name'];
        $file->file_path = (string)$row['file_path'];
        $file->file_size = (int)($row['file_size'] ?? 0);
        $file->mime_type = !empty($row['mime_type']) ? (string)$row['mime_type'] : null;
        $file->file_hash = !empty($row['file_hash']) ? (string)$row['file_hash'] : null;
        $file->is_orphaned = (int)($row['is_orphaned'] ?? 0);
        $file->status = (string)($row['status'] ?? 'active');
        $file->created_at = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        $file->updated_at = (string)($row['updated_at'] ?? date('Y-m-d H:i:s'));
        return $file;
    }
}
