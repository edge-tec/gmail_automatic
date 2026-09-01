<?php
namespace App\Models;

use App\Core\Database;

class SeoRedirect {
    public int $id;
    public string $old_url;
    public string $new_url;
    public int $status_code = 301;
    public int $hits = 0;
    public bool $is_active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM seo_redirects WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByOldUrl(string $url): ?self {
        $cleanUrl = '/' . trim($url, '/');
        $row = Database::first("SELECT * FROM seo_redirects WHERE old_url = :u AND is_active = 1 LIMIT 1", ['u' => $cleanUrl]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        try {
            $rows = Database::query("SELECT * FROM seo_redirects ORDER BY id DESC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function create(array $data): self {
        $old = '/' . trim($data['old_url'], '/');
        $new = str_starts_with($data['new_url'], 'http') ? $data['new_url'] : '/' . trim($data['new_url'], '/');

        // Loop protection
        if ($old === $new) {
            throw new \Exception("Old URL and New URL cannot be identical (redirect loop).");
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO seo_redirects (old_url, new_url, status_code, hits, is_active, created_at)
                VALUES (:old, :new, :code, 0, :active, {$now})";

        Database::execute($sql, [
            'old' => $old,
            'new' => $new,
            'code' => (int)($data['status_code'] ?? 301),
            'active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        if (isset($data['old_url']) && isset($data['new_url']) && $data['old_url'] === $data['new_url']) {
            throw new \Exception("Old URL and New URL cannot be identical.");
        }

        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE seo_redirects SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM seo_redirects WHERE id = :id", ['id' => $this->id]);
    }

    public function incrementHit(): void {
        Database::execute("UPDATE seo_redirects SET hits = hits + 1 WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->old_url = $row['old_url'];
        $r->new_url = $row['new_url'];
        $r->status_code = (int)($row['status_code'] ?? 301);
        $r->hits = (int)($row['hits'] ?? 0);
        $r->is_active = (bool)($row['is_active'] ?? true);
        $r->created_at = $row['created_at'] ?? null;
        $r->updated_at = $row['updated_at'] ?? null;
        return $r;
    }
}
