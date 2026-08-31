<?php
namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $pdo = null;

    public static function resetConnection(): void {
        self::$pdo = null;
    }

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $connection = config('database.default', 'mysql');
        $config = config("database.connections.{$connection}", []);

        try {
            if ($connection === 'sqlite') {
                $dbPath = $config['database'] ?? storage_path('database/database.sqlite');
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (!file_exists($dbPath)) {
                    touch($dbPath);
                }
                self::$pdo = new PDO("sqlite:{$dbPath}", null, null, $config['options'] ?? [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                // Enable foreign keys in SQLite
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                $host = $config['host'] ?? '127.0.0.1';
                $port = $config['port'] ?? 3306;
                $database = $config['database'] ?? 'gmail_automation';
                $charset = $config['charset'] ?? 'utf8mb4';
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

                self::$pdo = new PDO(
                    $dsn,
                    $config['username'] ?? 'root',
                    $config['password'] ?? '',
                    $config['options'] ?? [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            }
        } catch (PDOException $e) {
            // Log database connection error
            error_log("Database Connection Error: " . $e->getMessage());
            throw $e;
        }

        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function execute(string $sql, array $params = []): bool {
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function lastInsertId(): string|int {
        return self::getConnection()->lastInsertId();
    }

    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }
}
