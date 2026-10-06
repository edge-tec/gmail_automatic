<?php
namespace App\Models;

use App\Core\Database;

class Lead {
    public int $id;
    public int $user_id;
    public ?string $first_name = null;
    public ?string $last_name = null;
    public string $email;
    public ?string $phone = null;
    public ?string $company = null;
    public string $status = 'new';
    public string $source = 'manual';
    public ?string $tags = null;
    public ?string $notes = null;
    public ?array $custom_fields = null;
    public int $is_archived = 0;
    public ?string $archived_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';
        
        $sql = "CREATE TABLE IF NOT EXISTS leads (
            id {$autoInc},
            user_id {$intType} NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NULL,
            company VARCHAR(200) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'new',
            source VARCHAR(100) NOT NULL DEFAULT 'manual',
            tags VARCHAR(500) NULL,
            notes TEXT NULL,
            custom_fields TEXT NULL,
            is_archived {$intType} NOT NULL DEFAULT 0,
            archived_at {$dateDefault} NULL,
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
        $row = Database::first("SELECT * FROM leads WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserAndId(int $userId, int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM leads WHERE id = :id AND user_id = :uid LIMIT 1", [
            'id' => $id,
            'uid' => $userId,
        ]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findForUser(int $id, int $userId): ?self {
        return self::findByUserAndId($userId, $id);
    }

    public static function findByUserAndEmail(int $userId, string $email): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM leads WHERE user_id = :uid AND LOWER(email) = :e LIMIT 1", [
            'uid' => $userId,
            'e' => strtolower(trim($email)),
        ]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email, ?int $userId = null): ?self {
        self::ensureSchema();
        $sql = "SELECT * FROM leads WHERE LOWER(email) = :e" . ($userId ? " AND user_id = :uid" : "") . " LIMIT 1";
        $params = ['e' => strtolower(trim($email))];
        if ($userId) $params['uid'] = $userId;
        $row = Database::first($sql, $params);
        return $row ? self::fromRow($row) : null;
    }

    public static function countForUser(int $userId, bool $includeArchived = false): int {
        self::ensureSchema();
        $sql = "SELECT COUNT(*) as c FROM leads WHERE user_id = :uid" . ($includeArchived ? "" : " AND is_archived = 0");
        $row = Database::first($sql, ['uid' => $userId]);
        return (int)($row['c'] ?? 0);
    }

    public static function create(array $data): self {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $customJson = !empty($data['custom_fields']) ? (is_array($data['custom_fields']) ? json_encode($data['custom_fields']) : (string)$data['custom_fields']) : null;

        $sql = "INSERT INTO leads (
            user_id, first_name, last_name, email, phone, company, status, source, tags, notes, custom_fields, is_archived, created_at, updated_at
        ) VALUES (
            :user_id, :first_name, :last_name, :email, :phone, :company, :status, :source, :tags, :notes, :custom_fields, 0, {$now}, {$now}
        )";

        Database::execute($sql, [
            'user_id' => (int)$data['user_id'],
            'first_name' => !empty($data['first_name']) ? trim($data['first_name']) : null,
            'last_name' => !empty($data['last_name']) ? trim($data['last_name']) : null,
            'email' => strtolower(trim($data['email'])),
            'phone' => !empty($data['phone']) ? trim($data['phone']) : null,
            'company' => !empty($data['company']) ? trim($data['company']) : null,
            'status' => $data['status'] ?? 'new',
            'source' => $data['source'] ?? 'manual',
            'tags' => !empty($data['tags']) ? trim($data['tags']) : null,
            'notes' => !empty($data['notes']) ? trim($data['notes']) : null,
            'custom_fields' => $customJson,
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
            if ($key === 'custom_fields' && is_array($val)) {
                $val = json_encode($val);
            }
            if ($key === 'email' && is_string($val)) {
                $val = strtolower(trim($val));
            }
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;

            if (property_exists($this, $key)) {
                $this->$key = ($key === 'custom_fields' && is_string($val)) ? json_decode($val, true) : $val;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = {$now}";
        $sql = "UPDATE leads SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function archive(): bool {
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";
        $this->is_archived = 1;
        $this->archived_at = date('Y-m-d H:i:s');
        return Database::execute(
            "UPDATE leads SET is_archived = 1, status = 'archived', archived_at = {$now}, updated_at = {$now} WHERE id = :id",
            ['id' => $this->id]
        );
    }

    public function restore(): bool {
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";
        $this->is_archived = 0;
        $this->status = 'active';
        $this->archived_at = null;
        return Database::execute(
            "UPDATE leads SET is_archived = 0, status = 'active', archived_at = NULL, updated_at = {$now} WHERE id = :id",
            ['id' => $this->id]
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'status' => $this->status,
            'source' => $this->source,
            'tags' => $this->tags,
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields,
            'is_archived' => $this->is_archived,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'file_ids' => $this->file_ids ?? [],
        ];
    }

    public function getFiles(): array {
        LeadFile::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM lead_files WHERE lead_id = :lid AND status = 'active' ORDER BY id DESC",
            ['lid' => $this->id]
        );
        return array_map([LeadFile::class, 'fromRow'], $rows);
    }

    public function getFullName(): string {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return !empty($parts) ? implode(' ', $parts) : ($this->company ?: $this->email);
    }

    public static function fromRow(array $row): self {
        $lead = new self();
        $lead->id = (int)$row['id'];
        $lead->user_id = (int)$row['user_id'];
        $lead->first_name = !empty($row['first_name']) ? (string)$row['first_name'] : null;
        $lead->last_name = !empty($row['last_name']) ? (string)$row['last_name'] : null;
        $lead->email = (string)$row['email'];
        $lead->phone = !empty($row['phone']) ? (string)$row['phone'] : null;
        $lead->company = !empty($row['company']) ? (string)$row['company'] : null;
        $lead->status = (string)($row['status'] ?? 'new');
        $lead->source = (string)($row['source'] ?? 'manual');
        $lead->tags = !empty($row['tags']) ? (string)$row['tags'] : null;
        $lead->notes = !empty($row['notes']) ? (string)$row['notes'] : null;
        $lead->custom_fields = !empty($row['custom_fields']) ? (is_array($row['custom_fields']) ? $row['custom_fields'] : json_decode($row['custom_fields'], true)) : null;
        $lead->is_archived = (int)($row['is_archived'] ?? 0);
        $lead->archived_at = !empty($row['archived_at']) ? (string)$row['archived_at'] : null;
        $lead->created_at = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        $lead->updated_at = (string)($row['updated_at'] ?? date('Y-m-d H:i:s'));
        return $lead;
    }
}
