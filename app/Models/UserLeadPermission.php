<?php
namespace App\Models;

use App\Core\Database;

class UserLeadPermission {
    public const PERMISSIONS = [
        'leads.view' => 'View Leads, search, filter, and view lead details',
        'leads.create' => 'Create new leads manually',
        'leads.edit' => 'Edit lead details, tags, status, notes, and custom fields',
        'leads.delete' => 'Permanently delete individual leads and lead files',
        'leads.bulk_delete' => 'Select multiple leads and permanently delete them',
        'leads.import' => 'Import leads from CSV, TXT, or Excel spreadsheets',
        'leads.export' => 'Export leads to CSV files',
        'leads.clear' => 'Execute high-risk Clear Lead Data operations',
        'leads.manage' => 'Full administrative management of leads',
        'lead_files.view' => 'View lead files and storage attachments',
        'lead_files.delete' => 'Delete individual lead files and attachments',
        'lead_files.cleanup' => 'Execute storage scan and cleanup operations',
        'lead_storage.view' => 'View storage statistics, breakdown, and usage metrics',
        'lead_audit.view' => 'View immutable lead audit trail and destructive action logs',
    ];

    public const PRESETS = [
        'lead_viewer' => [
            'leads.view',
            'lead_files.view',
            'lead_storage.view',
        ],
        'lead_editor' => [
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.import',
            'lead_files.view',
            'lead_storage.view',
        ],
        'lead_manager' => [
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.import',
            'leads.export',
            'leads.delete',
            'leads.bulk_delete',
            'lead_files.view',
            'lead_storage.view',
        ],
        'lead_admin' => [
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.delete',
            'leads.bulk_delete',
            'leads.import',
            'leads.export',
            'leads.clear',
            'leads.manage',
            'lead_files.view',
            'lead_files.delete',
            'lead_files.cleanup',
            'lead_storage.view',
            'lead_audit.view',
        ],
    ];

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $intType = ($driver === 'mysql') ? 'INT' : 'INTEGER';
        $autoInc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateDefault = ($driver === 'mysql') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';

        $sql = "CREATE TABLE IF NOT EXISTS user_lead_permissions (
            id {$autoInc},
            user_id {$intType} NOT NULL,
            permission_key VARCHAR(100) NOT NULL,
            granted {$intType} NOT NULL DEFAULT 1,
            granted_by {$intType} NULL,
            created_at {$dateDefault},
            updated_at {$dateDefault},
            UNIQUE (user_id, permission_key)
        )";

        try {
            Database::execute($sql);
        } catch (\Throwable $e) {
            // Ignore if exists
        }
    }

    public static function hasPermission(int $userId, string $permissionKey): bool {
        self::ensureSchema();

        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // Platform administrator has all permissions unconditionally
        if ($user->role === 'admin') {
            return true;
        }

        // Check if an explicit record exists in user_lead_permissions
        $row = Database::first(
            "SELECT granted FROM user_lead_permissions WHERE user_id = :uid AND permission_key = :pk LIMIT 1",
            ['uid' => $userId, 'pk' => $permissionKey]
        );

        if ($row !== null) {
            return (bool)(int)$row['granted'];
        }

        // Default permission matrix for active users without custom overrides
        $defaultPermissions = [
            'leads.view' => true,
            'leads.create' => true,
            'leads.edit' => true,
            'leads.import' => true,
            'leads.export' => true,
            'leads.delete' => false,
            'leads.bulk_delete' => false,
            'lead_files.view' => true,
            'lead_storage.view' => true,
            // Dangerous operations are OFF by default for standard users:
            'leads.clear' => false,
            'leads.manage' => false,
            'lead_files.delete' => false,
            'lead_files.cleanup' => false,
            'lead_audit.view' => false,
        ];

        return $defaultPermissions[$permissionKey] ?? false;
    }

    public static function getPermissionsForUser(int $userId): array {
        self::ensureSchema();

        $rows = Database::query(
            "SELECT permission_key, granted FROM user_lead_permissions WHERE user_id = :uid",
            ['uid' => $userId]
        );

        $customMap = [];
        foreach ($rows as $r) {
            $customMap[$r['permission_key']] = (bool)(int)$r['granted'];
        }

        $result = [];
        foreach (self::PERMISSIONS as $key => $description) {
            $granted = $customMap[$key] ?? self::hasPermission($userId, $key);
            $result[$key] = [
                'key' => $key,
                'description' => $description,
                'granted' => $granted,
                'is_dangerous' => in_array($key, ['leads.clear', 'leads.manage', 'lead_files.cleanup', 'lead_files.delete']),
            ];
        }

        return $result;
    }

    public static function setPermission(int $userId, string $permissionKey, bool $granted, ?int $grantedBy = null): bool {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = ($driver === 'mysql') ? 'NOW()' : "datetime('now')";

        $existing = Database::first(
            "SELECT id FROM user_lead_permissions WHERE user_id = :uid AND permission_key = :pk LIMIT 1",
            ['uid' => $userId, 'pk' => $permissionKey]
        );

        if ($existing) {
            return Database::execute(
                "UPDATE user_lead_permissions SET granted = :g, granted_by = :gb, updated_at = {$now} WHERE id = :id",
                [
                    'g' => $granted ? 1 : 0,
                    'gb' => $grantedBy,
                    'id' => (int)$existing['id'],
                ]
            );
        }

        return Database::execute(
            "INSERT INTO user_lead_permissions (user_id, permission_key, granted, granted_by, created_at, updated_at) 
             VALUES (:uid, :pk, :g, :gb, {$now}, {$now})",
            [
                'uid' => $userId,
                'pk' => $permissionKey,
                'g' => $granted ? 1 : 0,
                'gb' => $grantedBy,
            ]
        );
    }

    public static function applyPreset(int $userId, string $preset, ?int $grantedBy = null): void {
        self::ensureSchema();
        if (!isset(self::PRESETS[$preset])) {
            return;
        }

        $presetKeys = self::PRESETS[$preset];
        foreach (array_keys(self::PERMISSIONS) as $permKey) {
            $isGranted = in_array($permKey, $presetKeys, true);
            self::setPermission($userId, $permKey, $isGranted, $grantedBy);
        }
    }
}
