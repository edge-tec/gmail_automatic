<?php
namespace App\Models;

use App\Core\Database;

class BlogPost {
    public int $id;
    public string $title;
    public string $slug;
    public ?string $excerpt = null;
    public string $content;
    public ?string $featured_image = null;
    public string $author_name = 'Team';
    public string $category = 'Guides';
    public ?string $tags = null;
    public ?string $seo_title = null;
    public ?string $meta_description = null;
    public ?string $focus_keyword = null;
    public ?string $canonical_url = null;
    public ?string $og_image = null;
    public string $status = 'published';
    public int $views = 0;
    public ?string $published_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM blog_posts WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findBySlug(string $slug): ?self {
        $row = Database::first("SELECT * FROM blog_posts WHERE slug = :s LIMIT 1", ['s' => $slug]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        try {
            $rows = Database::query("SELECT * FROM blog_posts ORDER BY published_at DESC, id DESC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function allPublished(int $limit = 20): array {
        try {
            $rows = Database::query("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC, id DESC LIMIT {$limit}");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function create(array $data): self {
        $slug = self::generateUniqueSlug($data['slug'] ?: $data['title']);
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $pubDate = !empty($data['published_at']) ? $data['published_at'] : date('Y-m-d H:i:s');

        $sql = "INSERT INTO blog_posts 
                (title, slug, excerpt, content, featured_image, author_name, category, tags, seo_title, meta_description, focus_keyword, canonical_url, og_image, status, views, published_at, created_at)
                VALUES 
                (:t, :s, :ex, :cnt, :img, :auth, :cat, :tags, :st, :md, :fk, :canon, :ogi, :stt, 0, :pub, {$now})";

        Database::execute($sql, [
            't' => trim($data['title']),
            's' => $slug,
            'ex' => trim($data['excerpt'] ?? ''),
            'cnt' => $data['content'],
            'img' => $data['featured_image'] ?? null,
            'auth' => trim($data['author_name'] ?? 'Team'),
            'cat' => trim($data['category'] ?? 'Guides'),
            'tags' => trim($data['tags'] ?? ''),
            'st' => $data['seo_title'] ?? null,
            'md' => $data['meta_description'] ?? null,
            'fk' => $data['focus_keyword'] ?? null,
            'canon' => $data['canonical_url'] ?? null,
            'ogi' => $data['og_image'] ?? null,
            'stt' => $data['status'] ?? 'published',
            'pub' => $pubDate,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        if (isset($data['slug']) && $data['slug'] !== $this->slug) {
            $data['slug'] = self::generateUniqueSlug($data['slug'], $this->id);
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
        $sql = "UPDATE blog_posts SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM blog_posts WHERE id = :id", ['id' => $this->id]);
    }

    public function incrementViews(): void {
        Database::execute("UPDATE blog_posts SET views = views + 1 WHERE id = :id", ['id' => $this->id]);
    }

    public static function generateUniqueSlug(string $text, int $excludeId = 0): string {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        if (empty($slug)) {
            $slug = 'post-' . uniqid();
        }

        $original = $slug;
        $counter = 1;

        while (true) {
            $existing = Database::first(
                "SELECT id FROM blog_posts WHERE slug = :s AND id != :exId LIMIT 1",
                ['s' => $slug, 'exId' => $excludeId]
            );
            if (!$existing) {
                break;
            }
            $slug = $original . '-' . (++$counter);
        }

        return $slug;
    }

    public static function fromRow(array $row): self {
        $b = new self();
        $b->id = (int)$row['id'];
        $b->title = $row['title'];
        $b->slug = $row['slug'];
        $b->excerpt = $row['excerpt'] ?? null;
        $b->content = $row['content'];
        $b->featured_image = $row['featured_image'] ?? null;
        $b->author_name = $row['author_name'] ?? 'Team';
        $b->category = $row['category'] ?? 'Guides';
        $b->tags = $row['tags'] ?? null;
        $b->seo_title = $row['seo_title'] ?? null;
        $b->meta_description = $row['meta_description'] ?? null;
        $b->focus_keyword = $row['focus_keyword'] ?? null;
        $b->canonical_url = $row['canonical_url'] ?? null;
        $b->og_image = $row['og_image'] ?? null;
        $b->status = $row['status'] ?? 'published';
        $b->views = (int)($row['views'] ?? 0);
        $b->published_at = $row['published_at'] ?? null;
        $b->created_at = $row['created_at'] ?? null;
        $b->updated_at = $row['updated_at'] ?? null;
        return $b;
    }
}
