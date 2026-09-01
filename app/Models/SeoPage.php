<?php
namespace App\Models;

use App\Core\Database;

class SeoPage {
    public int $id;
    public string $route_path;
    public string $page_name;
    public ?string $seo_title = null;
    public ?string $meta_description = null;
    public ?string $focus_keyword = null;
    public ?string $secondary_keywords = null;
    public ?string $canonical_url = null;
    public bool $is_indexable = true;
    public bool $is_followable = true;
    public ?string $og_title = null;
    public ?string $og_description = null;
    public ?string $og_image = null;
    public string $twitter_card = 'summary_large_image';
    public string $schema_type = 'WebPage';
    public ?string $custom_schema_json = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM seo_pages WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByRoute(string $routePath): ?self {
        $cleanRoute = '/' . trim($routePath, '/');
        if ($cleanRoute !== '/' && str_ends_with($cleanRoute, '/')) {
            $cleanRoute = rtrim($cleanRoute, '/');
        }
        $row = Database::first("SELECT * FROM seo_pages WHERE route_path = :rp LIMIT 1", ['rp' => $cleanRoute]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        try {
            $rows = Database::query("SELECT * FROM seo_pages ORDER BY id ASC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function getIndexablePages(): array {
        try {
            $rows = Database::query("SELECT * FROM seo_pages WHERE is_indexable = 1 ORDER BY id ASC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function update(array $data): bool {
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
        $sql = "UPDATE seo_pages SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO seo_pages 
                (route_path, page_name, seo_title, meta_description, focus_keyword, secondary_keywords, canonical_url, is_indexable, is_followable, og_title, og_description, og_image, twitter_card, schema_type, custom_schema_json, created_at)
                VALUES 
                (:rp, :pn, :st, :md, :fk, :sk, :canon, :idx, :fol, :ogt, :ogd, :ogi, :tc, :stype, :csj, {$now})";

        Database::execute($sql, [
            'rp' => '/' . trim($data['route_path'], '/'),
            'pn' => $data['page_name'],
            'st' => $data['seo_title'] ?? null,
            'md' => $data['meta_description'] ?? null,
            'fk' => $data['focus_keyword'] ?? null,
            'sk' => $data['secondary_keywords'] ?? null,
            'canon' => $data['canonical_url'] ?? null,
            'idx' => !empty($data['is_indexable']) ? 1 : 0,
            'fol' => !empty($data['is_followable']) ? 1 : 0,
            'ogt' => $data['og_title'] ?? null,
            'ogd' => $data['og_description'] ?? null,
            'ogi' => $data['og_image'] ?? null,
            'tc' => $data['twitter_card'] ?? 'summary_large_image',
            'stype' => $data['schema_type'] ?? 'WebPage',
            'csj' => $data['custom_schema_json'] ?? null,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public static function fromRow(array $row): self {
        $p = new self();
        $p->id = (int)$row['id'];
        $p->route_path = $row['route_path'];
        $p->page_name = $row['page_name'];
        $p->seo_title = $row['seo_title'] ?? null;
        $p->meta_description = $row['meta_description'] ?? null;
        $p->focus_keyword = $row['focus_keyword'] ?? null;
        $p->secondary_keywords = $row['secondary_keywords'] ?? null;
        $p->canonical_url = $row['canonical_url'] ?? null;
        $p->is_indexable = (bool)($row['is_indexable'] ?? true);
        $p->is_followable = (bool)($row['is_followable'] ?? true);
        $p->og_title = $row['og_title'] ?? null;
        $p->og_description = $row['og_description'] ?? null;
        $p->og_image = $row['og_image'] ?? null;
        $p->twitter_card = $row['twitter_card'] ?? 'summary_large_image';
        $p->schema_type = $row['schema_type'] ?? 'WebPage';
        $p->custom_schema_json = $row['custom_schema_json'] ?? null;
        $p->created_at = $row['created_at'] ?? null;
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }
}
