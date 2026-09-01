<?php
namespace App\Models;

use App\Core\Database;

class Plan {
    public int $id;
    public string $slug;
    public string $name;
    public float $price;
    public string $billing_period;
    public int $gmail_limit;
    public ?string $features = null;
    public ?string $stripe_price_id = null;
    public bool $is_popular = false;
    public bool $is_active = true;
    public int $display_order = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM plans WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findBySlug(string $slug): ?self {
        $row = Database::first("SELECT * FROM plans WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
        return $row ? self::fromRow($row) : null;
    }

    public static function getActivePlans(): array {
        $rows = Database::query("SELECT * FROM plans WHERE is_active = 1 ORDER BY display_order ASC, price ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function allActive(): array {
        return self::getActivePlans();
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM plans ORDER BY display_order ASC, price ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO plans (slug, name, price, billing_period, gmail_limit, features, stripe_price_id, is_popular, is_active, display_order, created_at)
                VALUES (:slug, :name, :price, :period, :limit, :feat, :stripe, :pop, :act, :ord, {$now})";

        Database::execute($sql, [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'price' => (float)$data['price'],
            'period' => $data['billing_period'] ?? 'monthly',
            'limit' => (int)$data['gmail_limit'],
            'feat' => is_array($data['features'] ?? null) ? json_encode($data['features']) : ($data['features'] ?? null),
            'stripe' => $data['stripe_price_id'] ?? null,
            'pop' => !empty($data['is_popular']) ? 1 : 0,
            'act' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'ord' => (int)($data['display_order'] ?? 0),
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            if ($key === 'features' && is_array($val)) {
                $val = json_encode($val);
            }
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE plans SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function getFeaturesList(): array {
        if (empty($this->features)) {
            return [];
        }
        $decoded = json_decode($this->features, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_filter(array_map('trim', explode("\n", $this->features)));
    }

    public static function fromRow(array $row): self {
        $p = new self();
        $p->id = (int)$row['id'];
        $p->slug = $row['slug'];
        $p->name = $row['name'];
        $p->price = (float)$row['price'];
        $p->billing_period = $row['billing_period'];
        $p->gmail_limit = (int)$row['gmail_limit'];
        $p->features = $row['features'] ?? null;
        $p->stripe_price_id = $row['stripe_price_id'] ?? null;
        $p->is_popular = (bool)($row['is_popular'] ?? false);
        $p->is_active = (bool)($row['is_active'] ?? true);
        $p->display_order = (int)($row['display_order'] ?? 0);
        $p->created_at = $row['created_at'] ?? null;
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }
}
