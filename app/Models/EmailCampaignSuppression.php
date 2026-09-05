<?php
namespace App\Models;

use App\Core\Database;

class EmailCampaignSuppression {
    public int $id;
    public int $user_id;
    public ?int $campaign_id = null;
    public string $email;
    public string $reason; // unsubscribed, hard_bounce, complaint, manual
    public ?string $created_at = null;

    public static function isSuppressed(int $userId, string $email, ?int $campaignId = null): bool {
        $normalized = strtolower(trim($email));
        if ($campaignId) {
            $row = Database::first(
                "SELECT id FROM email_campaign_suppressions 
                 WHERE user_id = :uid AND email = :em AND (campaign_id IS NULL OR campaign_id = :cid) 
                 LIMIT 1",
                ['uid' => $userId, 'em' => $normalized, 'cid' => $campaignId]
            );
        } else {
            $row = Database::first(
                "SELECT id FROM email_campaign_suppressions 
                 WHERE user_id = :uid AND email = :em 
                 LIMIT 1",
                ['uid' => $userId, 'em' => $normalized]
            );
        }
        return $row !== null;
    }

    public static function suppress(int $userId, string $email, string $reason = 'unsubscribed', ?int $campaignId = null): self {
        $normalized = strtolower(trim($email));
        $existing = Database::first(
            "SELECT * FROM email_campaign_suppressions 
             WHERE user_id = :uid AND email = :em AND (campaign_id = :cid OR (campaign_id IS NULL AND :cid2 IS NULL)) 
             LIMIT 1",
            ['uid' => $userId, 'em' => $normalized, 'cid' => $campaignId, 'cid2' => $campaignId]
        );

        if ($existing) {
            return self::fromRow($existing);
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO email_campaign_suppressions (user_id, campaign_id, email, reason, created_at)
                VALUES (:uid, :cid, :em, :reason, {$now})";

        Database::execute($sql, [
            'uid' => $userId,
            'cid' => $campaignId,
            'em' => $normalized,
            'reason' => $reason,
        ]);

        $id = (int)Database::lastInsertId();
        $row = Database::first("SELECT * FROM email_campaign_suppressions WHERE id = :id LIMIT 1", ['id' => $id]);
        return self::fromRow($row);
    }

    public static function findByUserId(int $userId): array {
        $rows = Database::query("SELECT * FROM email_campaign_suppressions WHERE user_id = :uid ORDER BY id DESC", ['uid' => $userId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM email_campaign_suppressions ORDER BY id DESC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function fromRow(array $row): self {
        $s = new self();
        $s->id = (int)$row['id'];
        $s->user_id = (int)$row['user_id'];
        $s->campaign_id = isset($row['campaign_id']) ? (int)$row['campaign_id'] : null;
        $s->email = $row['email'];
        $s->reason = $row['reason'] ?? 'unsubscribed';
        $s->created_at = $row['created_at'] ?? null;
        return $s;
    }
}
