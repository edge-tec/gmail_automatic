<?php
namespace App\Models;

use App\Core\Database;

class SeoSetting {
    public int $id;
    public string $setting_key;
    public ?string $setting_value = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $row = Database::first(
                "SELECT setting_value FROM seo_settings WHERE setting_key = :k LIMIT 1",
                ['k' => $key]
            );
            if ($row) {
                self::$cache[$key] = $row['setting_value'];
                return $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // Table might not exist yet
        }

        return $default;
    }

    public static function set(string $key, ?string $value): void {
        self::$cache[$key] = $value;
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        if ($driver === 'mysql') {
            Database::execute(
                "INSERT INTO seo_settings (setting_key, setting_value, created_at, updated_at) 
                 VALUES (:k, :v, {$now}, {$now}) 
                 ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = {$now}",
                ['k' => $key, 'v' => $value, 'v2' => $value]
            );
        } else {
            // SQLite
            Database::execute(
                "INSERT INTO seo_settings (setting_key, setting_value, created_at, updated_at) 
                 VALUES (:k, :v, {$now}, {$now}) 
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = {$now}",
                ['k' => $key, 'v' => $value, 'v2' => $value]
            );
        }
    }

    public static function all(): array {
        try {
            $rows = Database::query("SELECT setting_key, setting_value FROM seo_settings");
            $res = [];
            foreach ($rows as $r) {
                $res[$r['setting_key']] = $r['setting_value'];
            }
            return $res;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
