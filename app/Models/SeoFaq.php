<?php
namespace App\Models;

use App\Core\Database;

class SeoFaq {
    public int $id;
    public string $question;
    public string $answer;
    public string $category = 'General';
    public int $sort_order = 0;
    public bool $is_active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM seo_faqs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        try {
            $rows = Database::query("SELECT * FROM seo_faqs ORDER BY sort_order ASC, id ASC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function allActive(): array {
        try {
            $rows = Database::query("SELECT * FROM seo_faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
            return array_map([self::class, 'fromRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO seo_faqs (question, answer, category, sort_order, is_active, created_at)
                VALUES (:q, :a, :cat, :sort, :active, {$now})";

        Database::execute($sql, [
            'q' => trim($data['question']),
            'a' => trim($data['answer']),
            'cat' => trim($data['category'] ?? 'General'),
            'sort' => (int)($data['sort_order'] ?? 0),
            'active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
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
        $sql = "UPDATE seo_faqs SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM seo_faqs WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $f = new self();
        $f->id = (int)$row['id'];
        $f->question = $row['question'];
        $f->answer = $row['answer'];
        $f->category = $row['category'] ?? 'General';
        $f->sort_order = (int)($row['sort_order'] ?? 0);
        $f->is_active = (bool)($row['is_active'] ?? true);
        $f->created_at = $row['created_at'] ?? null;
        $f->updated_at = $row['updated_at'] ?? null;
        return $f;
    }
}
