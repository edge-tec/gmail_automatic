<?php
namespace App\Models;

use App\Core\Database;

class EmailCampaignRecipient {
    public int $id;
    public int $campaign_id;
    public int $user_id;
    public string $email;
    public ?string $first_name = null;
    public ?string $last_name = null;
    public ?string $company = null;
    public ?string $custom_field_1 = null;
    public ?string $custom_field_2 = null;
    public ?string $custom_data = null;
    public string $status; // pending, queued, sending, sent, failed, skipped, cancelled
    public ?string $claimed_at = null;
    public ?string $sent_at = null;
    public ?int $sent_gmail_account_id = null;
    public ?string $sent_message_id = null;
    public ?string $skip_reason = null;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM email_campaign_recipients WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByCampaignAndEmail(int $campaignId, string $email): ?self {
        $row = Database::first("SELECT * FROM email_campaign_recipients WHERE campaign_id = :cid AND email = :em LIMIT 1", [
            'cid' => $campaignId,
            'em' => strtolower(trim($email)),
        ]);
        return $row ? self::fromRow($row) : null;
    }

    public static function countByCampaign(int $campaignId, ?string $status = null): int {
        $sql = "SELECT COUNT(*) as c FROM email_campaign_recipients WHERE campaign_id = :cid";
        $params = ['cid' => $campaignId];
        if ($status) {
            $sql .= " AND status = :st";
            $params['st'] = $status;
        }
        $row = Database::first($sql, $params);
        return (int)($row['c'] ?? 0);
    }

    public static function paginateByCampaign(int $campaignId, int $limit = 50, int $offset = 0, ?string $status = null, ?string $search = null): array {
        $sql = "SELECT * FROM email_campaign_recipients WHERE campaign_id = :cid";
        $params = ['cid' => $campaignId];

        if ($status && in_array($status, ['pending', 'queued', 'sending', 'sent', 'failed', 'skipped', 'cancelled'])) {
            $sql .= " AND status = :st";
            $params['st'] = $status;
        }

        if ($search) {
            $sql .= " AND (email LIKE :sq OR first_name LIKE :sq OR last_name LIKE :sq OR company LIKE :sq)";
            $params['sq'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}";
        $rows = Database::query($sql, $params);
        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Atomically claim the next eligible pending recipient for a campaign.
     * Prevents race conditions across multiple concurrent workers.
     */
    public static function claimNextPending(int $campaignId): ?self {
        $driver = config('database.default', 'mysql');
        $now = date('Y-m-d H:i:s');

        // Check if there are stale 'sending' claims older than 10 minutes and reset them to pending
        $staleTime = date('Y-m-d H:i:s', time() - 600);
        Database::execute(
            "UPDATE email_campaign_recipients 
             SET status = 'pending', claimed_at = NULL 
             WHERE campaign_id = :cid AND status = 'sending' AND claimed_at <= :stale",
            ['cid' => $campaignId, 'stale' => $staleTime]
        );

        // Find candidate row
        $candidate = Database::first(
            "SELECT id FROM email_campaign_recipients 
             WHERE campaign_id = :cid AND status = 'pending' 
             ORDER BY id ASC LIMIT 1",
            ['cid' => $campaignId]
        );

        if (!$candidate) {
            return null;
        }

        $candId = (int)$candidate['id'];

        // Atomic lock: claim only if status is STILL 'pending'
        $claimed = Database::execute(
            "UPDATE email_campaign_recipients 
             SET status = 'sending', claimed_at = :now 
             WHERE id = :id AND status = 'pending'",
            ['id' => $candId, 'now' => $now]
        );

        if ($claimed) {
            return self::find($candId);
        }

        return null;
    }

    public function markSent(int $gmailAccountId, string $messageId): bool {
        $now = date('Y-m-d H:i:s');
        return $this->update([
            'status' => 'sent',
            'sent_at' => $now,
            'sent_gmail_account_id' => $gmailAccountId,
            'sent_message_id' => $messageId,
            'last_error' => null,
        ]);
    }

    public function markFailed(string $error): bool {
        return $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }

    public function markSkipped(string $reason): bool {
        return $this->update([
            'status' => 'skipped',
            'skip_reason' => $reason,
        ]);
    }

    public function markCancelled(): bool {
        return $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function resetToPending(): bool {
        return $this->update([
            'status' => 'pending',
            'claimed_at' => null,
        ]);
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
        $sql = "UPDATE `email_campaign_recipients` SET " . implode(', ', $fields) . ", `updated_at` = {$now} WHERE `id` = :id";
        return Database::execute($sql, $params);
    }

    /**
     * Batch insert recipients in chunks using INSERT IGNORE or INSERT OR IGNORE
     */
    public static function insertBatch(int $campaignId, int $userId, array $recipients): int {
        if (empty($recipients)) {
            return 0;
        }

        $driver = config('database.default', 'mysql');
        $insertVerb = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $inserted = 0;
        $chunks = array_chunk($recipients, 250);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $params = [];
            $i = 0;

            foreach ($chunk as $recip) {
                $email = strtolower(trim($recip['email'] ?? ''));
                if (!$email) continue;

                $placeholders[] = "(:cid{$i}, :uid{$i}, :em{$i}, :fn{$i}, :ln{$i}, :cp{$i}, :cf1_{$i}, :cf2_{$i}, :cd{$i}, 'pending', {$now}, {$now})";
                $params["cid{$i}"] = $campaignId;
                $params["uid{$i}"] = $userId;
                $params["em{$i}"] = $email;
                $params["fn{$i}"] = !empty($recip['first_name']) ? substr(trim($recip['first_name']), 0, 100) : null;
                $params["ln{$i}"] = !empty($recip['last_name']) ? substr(trim($recip['last_name']), 0, 100) : null;
                $params["cp{$i}"] = !empty($recip['company']) ? substr(trim($recip['company']), 0, 200) : null;
                $params["cf1_{$i}"] = !empty($recip['custom_field_1']) ? substr(trim($recip['custom_field_1']), 0, 255) : null;
                $params["cf2_{$i}"] = !empty($recip['custom_field_2']) ? substr(trim($recip['custom_field_2']), 0, 255) : null;
                $params["cd{$i}"] = !empty($recip['custom_data']) ? (is_string($recip['custom_data']) ? $recip['custom_data'] : json_encode($recip['custom_data'])) : null;
                $i++;
            }

            if (empty($placeholders)) continue;

            $sql = "{$insertVerb} INTO email_campaign_recipients (
                        campaign_id, user_id, email, first_name, last_name, company,
                        custom_field_1, custom_field_2, custom_data, status, created_at, updated_at
                    ) VALUES " . implode(', ', $placeholders);

            Database::execute($sql, $params);
            $inserted += $i;
        }

        return $inserted;
    }

    public static function fromRow(array $row): self {
        $r = new self();
        $r->id = (int)$row['id'];
        $r->campaign_id = (int)$row['campaign_id'];
        $r->user_id = (int)$row['user_id'];
        $r->email = $row['email'];
        $r->first_name = $row['first_name'] ?? null;
        $r->last_name = $row['last_name'] ?? null;
        $r->company = $row['company'] ?? null;
        $r->custom_field_1 = $row['custom_field_1'] ?? null;
        $r->custom_field_2 = $row['custom_field_2'] ?? null;
        $r->custom_data = $row['custom_data'] ?? null;
        $r->status = $row['status'] ?? 'pending';
        $r->claimed_at = $row['claimed_at'] ?? null;
        $r->sent_at = $row['sent_at'] ?? null;
        $r->sent_gmail_account_id = isset($row['sent_gmail_account_id']) ? (int)$row['sent_gmail_account_id'] : null;
        $r->sent_message_id = $row['sent_message_id'] ?? null;
        $r->skip_reason = $row['skip_reason'] ?? null;
        $r->last_error = $row['last_error'] ?? null;
        $r->created_at = $row['created_at'] ?? null;
        $r->updated_at = $row['updated_at'] ?? null;
        return $r;
    }
}
