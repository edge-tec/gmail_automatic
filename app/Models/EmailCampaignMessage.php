<?php
namespace App\Models;

use App\Core\Database;

class EmailCampaignMessage {
    public int $id;
    public int $campaign_id;
    public int $user_id;
    public string $subject;
    public string $body;
    public int $usage_count = 0;
    public string $status; // active, inactive
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_campaign_messages WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCampaignId(int $campaignId): array {
        $rows = Database::query("SELECT * FROM email_campaign_messages WHERE campaign_id = :cid ORDER BY id ASC", ['cid' => $campaignId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function findActiveByCampaignId(int $campaignId): array {
        $rows = Database::query("SELECT * FROM email_campaign_messages WHERE campaign_id = :cid AND status = 'active' ORDER BY id ASC", ['cid' => $campaignId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Strictly reloads active messages from DB and picks one randomly.
     * Guaranteed ZERO hardcoded fallback if no messages exist.
     */
    public static function getRandomActiveForCampaign(int $campaignId): ?self {
        $messages = self::findActiveByCampaignId($campaignId);
        $valid = [];
        foreach ($messages as $m) {
            if (!$m->isEmptyOrPlaceholder()) {
                $valid[] = $m;
            }
        }

        if (empty($valid)) {
            return null; // NO FALLBACK!
        }

        $randomIndex = array_rand($valid);
        return $valid[$randomIndex];
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_campaign_messages (campaign_id, user_id, subject, body, usage_count, status, created_at, updated_at)
                VALUES (:cid, :uid, :subj, :body, 0, :st, {$now}, {$now})";

        Database::execute($sql, [
            'cid' => $data['campaign_id'],
            'uid' => $data['user_id'],
            'subj' => $data['subject'] ?? '(No Subject)',
            'body' => $data['body'] ?? '',
            'st' => $data['status'] ?? 'active',
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id);
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "`{$key}` = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE `email_campaign_messages` SET " . implode(', ', $fields) . ", `updated_at` = {$now} WHERE `id` = :id";
        return Database::execute($sql, $params);
    }

    public function incrementUsage(): void {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        Database::execute(
            "UPDATE email_campaign_messages SET usage_count = usage_count + 1, updated_at = {$now} WHERE id = :id",
            ['id' => $this->id]
        );
        $this->usage_count++;
    }

    public function isEmptyOrPlaceholder(): bool {
        $clean = trim(strip_tags($this->body, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
        return empty($clean) || in_array(trim($this->body), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM email_campaign_messages WHERE id = :id", ['id' => $this->id]);
    }

    public static function deleteByCampaignId(int $campaignId): bool {
        return Database::execute("DELETE FROM email_campaign_messages WHERE campaign_id = :cid", ['cid' => $campaignId]);
    }

    public function __get(string $name) {
        if ($name === 'sends_count') {
            return $this->usage_count;
        }
        return null;
    }

    public static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->campaign_id = (int)$row['campaign_id'];
        $m->user_id = (int)$row['user_id'];
        $m->subject = $row['subject'] ?? '';
        $m->body = $row['body'] ?? '';
        $m->usage_count = (int)($row['usage_count'] ?? 0);
        $m->status = $row['status'] ?? 'active';
        $m->created_at = $row['created_at'] ?? null;
        $m->updated_at = $row['updated_at'] ?? null;
        return $m;
    }
}
