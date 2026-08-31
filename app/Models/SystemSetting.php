<?php
namespace App\Models;

use App\Core\Database;

class SystemSetting {
    public static function get(string $key, ?string $default = null): ?string {
        $row = Database::first("SELECT setting_value FROM system_settings WHERE setting_key = :k LIMIT 1", ['k' => $key]);
        return $row ? $row['setting_value'] : $default;
    }

    public static function set(string $key, ?string $value): void {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        
        $existing = self::get($key);
        if ($existing !== null) {
            Database::execute("UPDATE system_settings SET setting_value = :v, updated_at = {$now} WHERE setting_key = :k", [
                'v' => $value,
                'k' => $key,
            ]);
        } else {
            Database::execute("INSERT INTO system_settings (setting_key, setting_value, created_at) VALUES (:k, :v, {$now})", [
                'k' => $key,
                'v' => $value,
            ]);
        }
    }

    public static function all(): array {
        $rows = Database::query("SELECT setting_key, setting_value FROM system_settings");
        $result = [];
        foreach ($rows as $r) {
            $result[$r['setting_key']] = $r['setting_value'];
        }
        return $result;
    }
}
