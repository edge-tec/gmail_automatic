<?php
namespace App\Models;

use App\Core\Database;

class EmailTemplate {
    public int $id;
    public string $slug;
    public string $name;
    public string $subject;
    public string $body;
    public bool $is_enabled = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_templates WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findBySlug(string $slug): ?self {
        $row = Database::first("SELECT * FROM email_templates WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM email_templates ORDER BY id ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_templates (slug, name, subject, body, is_enabled, created_at)
                VALUES (:slug, :name, :subject, :body, :enabled, {$now})";

        Database::execute($sql, [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'enabled' => isset($data['is_enabled']) ? (int)$data['is_enabled'] : 1,
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
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE email_templates SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    /**
     * Render subject and body with provided dynamic variables
     */
    public function render(array $vars): array {
        $renderedSubject = $this->subject;
        $renderedBody = $this->body;

        // Default variables
        $vars['app_name'] = $vars['app_name'] ?? SystemSetting::get('app_name', 'Gmail Auto Reply & Follow-up');
        $vars['support_email'] = $vars['support_email'] ?? SystemSetting::get('smtp_from_email', 'support@2xbets.net');
        $vars['dashboard_url'] = $vars['dashboard_url'] ?? url('/dashboard');
        $vars['login_url'] = $vars['login_url'] ?? url('/login');
        $vars['billing_url'] = $vars['billing_url'] ?? url('/billing');

        foreach ($vars as $key => $val) {
            $placeholder = '{{' . $key . '}}';
            $valStr = (string)$val;
            $renderedSubject = str_replace($placeholder, $valStr, $renderedSubject);
            $renderedBody = str_replace($placeholder, $valStr, $renderedBody);
        }

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
        ];
    }

    public static function fromRow(array $row): self {
        $t = new self();
        $t->id = (int)$row['id'];
        $t->slug = $row['slug'];
        $t->name = $row['name'];
        $t->subject = $row['subject'];
        $t->body = $row['body'];
        $t->is_enabled = (bool)($row['is_enabled'] ?? true);
        $t->created_at = $row['created_at'] ?? null;
        $t->updated_at = $row['updated_at'] ?? null;
        return $t;
    }
}
