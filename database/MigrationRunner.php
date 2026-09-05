<?php
namespace Database;

use App\Core\Database;
use Database\SeedData;
use PDO;

class MigrationRunner {
    public static function run(): void {
        $db = Database::getConnection();
        $driver = config('database.default', 'mysql');

        $schemaFile = __DIR__ . '/migrations/schema.sql';
        $sql = file_get_contents($schemaFile);

        if ($driver === 'sqlite') {
            $statements = self::convertMysqlToSqlite($sql);
            foreach ($statements as $stmt) {
                if (trim($stmt)) {
                    try {
                        $db->exec($stmt);
                    } catch (\Throwable $e) {
                        error_log("MigrationRunner SQLite statement notice: " . $e->getMessage());
                    }
                }
            }
        } else {
            // Strip SQL line comments
            $cleanSql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
            foreach ($statements as $stmt) {
                if ($stmt) {
                    try {
                        $db->exec($stmt);
                    } catch (\Throwable $e) {
                        error_log("MigrationRunner statement notice: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Run seeders
        try {
            SeedData::run();
        } catch (\Throwable $e) {
            error_log("MigrationRunner SeedData notice: " . $e->getMessage());
        }
    }

    private static function convertMysqlToSqlite(string $sql): array {
        // Strip SQL comments first
        $sql = preg_replace('/--.*$/m', '', $sql);

        // Strip MySQL specific table options
        $sql = preg_replace('/ENGINE\s*=\s*InnoDB\s*/i', '', $sql);
        $sql = preg_replace('/DEFAULT\s*CHARSET\s*=\s*[a-zA-Z0-9_]+\s*/i', '', $sql);
        $sql = preg_replace('/COLLATE\s*=\s*[a-zA-Z0-9_]+\s*/i', '', $sql);
        $sql = preg_replace('/ON\s*UPDATE\s*CURRENT_TIMESTAMP/i', '', $sql);

        // Convert primary keys
        $sql = preg_replace('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/TINYINT\([0-9]+\)/i', 'INTEGER', $sql);
        $sql = preg_replace('/INT\([0-9]+\)/i', 'INTEGER', $sql);
        $sql = preg_replace('/\bINT\b/i', 'INTEGER', $sql);
        $sql = preg_replace('/LONGTEXT/i', 'TEXT', $sql);
        $sql = preg_replace('/DECIMAL\([0-9]+,[0-9]+\)/i', 'REAL', $sql);
        $sql = preg_replace('/\bJSON\b/i', 'TEXT', $sql);
        $sql = preg_replace('/DATETIME\s+DEFAULT\s+CURRENT_TIMESTAMP/i', 'TEXT DEFAULT CURRENT_TIMESTAMP', $sql);
        $sql = preg_replace('/DATETIME/i', 'TEXT', $sql);

        // Remove inline MySQL INDEX definitions: ", INDEX idx_name (col)" or ",\n INDEX idx_name (col)"
        $sql = preg_replace('/,\s*INDEX\s+[a-zA-Z0-9_]+\s*\([^)]+\)/i', '', $sql);
        $sql = preg_replace('/UNIQUE\s+KEY\s+[a-zA-Z0-9_]+\s*\(/i', 'UNIQUE (', $sql);

        // Separate statements
        $rawStatements = explode(';', $sql);
        $statements = [];
        foreach ($rawStatements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed) {
                $statements[] = $trimmed;
            }
        }
        return $statements;
    }
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'MigrationRunner.php') {
    new \App\Core\App();
    MigrationRunner::run();
}
