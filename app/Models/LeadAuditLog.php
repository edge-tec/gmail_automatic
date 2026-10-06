<?php
namespace App\Models;

use App\Core\Database;

class LeadAuditLog {
    public int $id;
    public ?string $operation_uuid = null;
    public int $actor_id;
    public string $actor_email;
    public int $target_user_id;
    public string $action;
    public int $requested_count = 0;
    public int $deleted_count = 0;
    public int $failed_count = 0;
    public int $files_deleted = 0;
    public int $files_pending = 0;
    public int $storage_deleted = 0;
    public string $result; // SUCCESS, PARTIAL, FAILED, ALREADY_DELETED, AUTHORIZATION_FAILED
    public ?string $error_category = null;
    public ?array $metadata = null;
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public string $created_at;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $bigintType = ($driver === 'mysql') ? 'BIGINT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';

        $sql = "CREATE TABLE IF NOT EXISTS lead_audit_logs (
            id {$autoInc},
            operation_uuid VARCHAR(64) NULL,
            actor_id {$intType} NOT NULL,
            actor_email VARCHAR(255) NOT NULL,
            target_user_id {$intType} NOT NULL,
            action VARCHAR(100) NOT NULL,
            requested_count {$intType} NOT NULL DEFAULT 0,
            deleted_count {$intType} NOT NULL DEFAULT 0,
            failed_count {$intType} NOT NULL DEFAULT 0,
            files_deleted {$intType} NOT NULL DEFAULT 0,
            files_pending {$intType} NOT NULL DEFAULT 0,
            storage_deleted {$bigintType} NOT NULL DEFAULT 0,
            result VARCHAR(50) NOT NULL,
            error_category VARCHAR(50) NULL,
            metadata_json TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at {$dateDefault}
        )";

        try {
            Database::execute($sql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function record(array $data): self {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $metaJson = !empty($data['metadata']) ? json_encode($data['metadata']) : null;

        $sql = "INSERT INTO lead_audit_logs (
            operation_uuid, actor_id, actor_email, target_user_id, action,
            requested_count, deleted_count, failed_count, files_deleted, files_pending,
            storage_deleted, result, error_category, metadata_json, ip_address, user_agent, created_at
        ) VALUES (
            :uuid, :aid, :aemail, :tuid, :action,
            :req, :del, :fail, :fdel, :fpend,
            :sdel, :result, :err_cat, :meta, :ip, :ua, {$now}
        )";

        Database::execute($sql, [
            'uuid' => $data['operation_uuid'] ?? null,
            'aid' => (int)$data['actor_id'],
            'aemail' => (string)$data['actor_email'],
            'tuid' => (int)$data['target_user_id'],
            'action' => (string)$data['action'],
            'req' => (int)($data['requested_count'] ?? 0),
            'del' => (int)($data['deleted_count'] ?? 0),
            'fail' => (int)($data['failed_count'] ?? 0),
            'fdel' => (int)($data['files_deleted'] ?? 0),
            'fpend' => (int)($data['files_pending'] ?? 0),
            'sdel' => (int)($data['storage_deleted'] ?? 0),
            'result' => (string)($data['result'] ?? 'SUCCESS'),
            'err_cat' => $data['error_category'] ?? null,
            'meta' => $metaJson,
            'ip' => !empty($data['ip_address']) ? substr(trim($data['ip_address']), 0, 45) : null,
            'ua' => !empty($data['user_agent']) ? substr(trim($data['user_agent']), 0, 500) : null,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM lead_audit_logs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function queryLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        self::ensureSchema();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = "(target_user_id = :uid OR actor_id = :uid)";
            $params['uid'] = (int)$filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = "action = :act";
            $params['act'] = $filters['action'];
        }

        if (!empty($filters['result'])) {
            $where[] = "result = :res";
            $params['res'] = $filters['result'];
        }

        $whereSql = implode(' AND ', $where);
        $totalRow = Database::first("SELECT COUNT(*) as c FROM lead_audit_logs WHERE {$whereSql}", $params);
        $total = (int)($totalRow['c'] ?? 0);

        $rows = Database::query(
            "SELECT * FROM lead_audit_logs WHERE {$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return [array_map([self::class, 'fromRow'], $rows), $total];
    }

    public static function forUser(int $userId, int $limit = 50): array {
        [$items, $total] = self::queryLogs(['user_id' => $userId], $limit, 0);
        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public static function fromRow(array $row): self {
        $log = new self();
        $log->id = (int)$row['id'];
        $log->operation_uuid = !empty($row['operation_uuid']) ? (string)$row['operation_uuid'] : null;
        $log->actor_id = (int)$row['actor_id'];
        $log->actor_email = (string)$row['actor_email'];
        $log->target_user_id = (int)$row['target_user_id'];
        $log->action = (string)$row['action'];
        $log->requested_count = (int)($row['requested_count'] ?? 0);
        $log->deleted_count = (int)($row['deleted_count'] ?? 0);
        $log->failed_count = (int)($row['failed_count'] ?? 0);
        $log->files_deleted = (int)($row['files_deleted'] ?? 0);
        $log->files_pending = (int)($row['files_pending'] ?? 0);
        $log->storage_deleted = (int)($row['storage_deleted'] ?? 0);
        $log->result = (string)$row['result'];
        $log->error_category = !empty($row['error_category']) ? (string)$row['error_category'] : null;
        $log->metadata = !empty($row['metadata_json']) ? json_decode($row['metadata_json'], true) : null;
        $log->ip_address = !empty($row['ip_address']) ? (string)$row['ip_address'] : null;
        $log->user_agent = !empty($row['user_agent']) ? (string)$row['user_agent'] : null;
        $log->created_at = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        return $log;
    }
}
