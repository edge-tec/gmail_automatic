<?php
namespace App\Models;

use App\Core\Database;

class EmailOpenEvent {
    public int $id;
    public int $tracking_id;
    public string $opened_at;
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public ?string $device_type = 'Unknown';
    public ?string $operating_system = 'Unknown';
    public ?string $browser = 'Unknown';
    public ?string $mail_client = 'Unknown';
    public int $is_bot_or_prefetch = 0;
    public ?array $metadata = null;

    public function __get(string $name): mixed {
        if ($name === 'os') {
            return $this->operating_system;
        }
        if ($name === 'is_proxy') {
            return $this->is_bot_or_prefetch;
        }
        return null;
    }

    public function __isset(string $name): bool {
        return in_array($name, ['os', 'is_proxy']);
    }

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';
        $tableSql = "CREATE TABLE IF NOT EXISTS email_open_events (
            id {$autoInc},
            tracking_id {$intType} NOT NULL,
            opened_at {$dateDefault},
            ip_address VARCHAR(100) NULL,
            user_agent TEXT NULL,
            device_type VARCHAR(50) NULL,
            operating_system VARCHAR(100) NULL,
            browser VARCHAR(100) NULL,
            mail_client VARCHAR(100) NULL,
            is_bot_or_prefetch {$intType} NOT NULL DEFAULT 0,
            metadata TEXT NULL
        )";
        try {
            Database::execute($tableSql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function create(array $data): self {
        self::ensureSchema();

        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $metaJson = !empty($data['metadata']) ? json_encode($data['metadata']) : null;

        $sql = "INSERT INTO email_open_events (
            tracking_id, opened_at, ip_address, user_agent, device_type,
            operating_system, browser, mail_client, is_bot_or_prefetch, metadata
        ) VALUES (
            :tracking_id, {$now}, :ip_address, :user_agent, :device_type,
            :operating_system, :browser, :mail_client, :is_bot_or_prefetch, :metadata
        )";

        Database::execute($sql, [
            'tracking_id' => (int)$data['tracking_id'],
            'ip_address' => !empty($data['ip_address']) ? substr(trim($data['ip_address']), 0, 100) : null,
            'user_agent' => !empty($data['user_agent']) ? substr(trim($data['user_agent']), 0, 1000) : null,
            'device_type' => $data['device_type'] ?? 'Unknown',
            'operating_system' => $data['operating_system'] ?? 'Unknown',
            'browser' => $data['browser'] ?? 'Unknown',
            'mail_client' => $data['mail_client'] ?? 'Unknown',
            'is_bot_or_prefetch' => !empty($data['is_bot_or_prefetch']) ? 1 : 0,
            'metadata' => $metaJson,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM email_open_events WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByTrackingId(int $trackingId): array {
        self::ensureSchema();
        $rows = Database::query(
            "SELECT * FROM email_open_events WHERE tracking_id = :tid ORDER BY opened_at DESC, id DESC",
            ['tid' => $trackingId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function countByTrackingId(int $trackingId): int {
        self::ensureSchema();
        $row = Database::first(
            "SELECT COUNT(*) as cnt FROM email_open_events WHERE tracking_id = :tid",
            ['tid' => $trackingId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public static function fromRow(array $row): self {
        $e = new self();
        $e->id = (int)$row['id'];
        $e->tracking_id = (int)$row['tracking_id'];
        $e->opened_at = (string)($row['opened_at'] ?? date('Y-m-d H:i:s'));
        $e->ip_address = !empty($row['ip_address']) ? (string)$row['ip_address'] : null;
        $e->user_agent = !empty($row['user_agent']) ? (string)$row['user_agent'] : null;
        $e->device_type = (string)($row['device_type'] ?? 'Unknown');
        $e->operating_system = (string)($row['operating_system'] ?? 'Unknown');
        $e->browser = (string)($row['browser'] ?? 'Unknown');
        $e->mail_client = (string)($row['mail_client'] ?? 'Unknown');
        $e->is_bot_or_prefetch = (int)($row['is_bot_or_prefetch'] ?? 0);
        $e->metadata = !empty($row['metadata']) ? json_decode($row['metadata'], true) : null;
        return $e;
    }
}
