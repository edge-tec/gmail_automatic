<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Encryption;

class GmailAccount {
    public int $id;
    public int $user_id;
    public string $gmail_email;
    public ?string $google_user_id = null;
    public ?string $access_token = null;
    public ?string $refresh_token = null;
    public ?string $token_expires_at = null;
    public ?string $history_id = null;
    public ?string $connected_at = null;
    public int $initial_sync_completed = 0;
    public ?string $initial_history_id = null;
    public ?string $initial_sync_at = null;
    public ?string $baseline_message_date = null;
    public string $status;
    public int $bulk_daily_limit = 50;
    public int $campaign_enabled = 1;
    public ?string $temp_unavailable_until = null;
    public int $temp_failure_count = 0;
    public ?string $last_sync_at = null;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void {
        if (self::$schemaEnsured) return;
        self::$schemaEnsured = true;

        $driver = config('database.default', 'mysql');
        $accountCols = [
            'connected_at' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
            'initial_sync_completed' => ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
            'initial_history_id' => 'VARCHAR(191) NULL',
            'initial_sync_at' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
            'baseline_message_date' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
            'bulk_daily_limit' => ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 50' : 'INTEGER NOT NULL DEFAULT 50'),
            'campaign_enabled' => ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1'),
            'temp_unavailable_until' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
            'temp_failure_count' => ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
        ];
        \App\Core\DatabaseSanitizer::ensureTableColumns('gmail_accounts', $accountCols);
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM gmail_accounts WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM gmail_accounts WHERE gmail_email = :email LIMIT 1", ['email' => $email]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId): array {
        self::ensureSchema();
        $rows = Database::query("SELECT * FROM gmail_accounts WHERE user_id = :uid ORDER BY id DESC", ['uid' => $userId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function allActive(): array {
        self::ensureSchema();
        $rows = Database::query("SELECT * FROM gmail_accounts WHERE status = 'connected' ORDER BY id ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        return self::createOrUpdate($data);
    }

    public static function createOrUpdate(array $data): self {
        self::ensureSchema();
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $nowDate = date('Y-m-d H:i:s');

        $existing = self::findByEmail($data['gmail_email']);
        $encryptedRefresh = isset($data['refresh_token']) ? Encryption::encrypt($data['refresh_token']) : null;
        $encryptedAccess = isset($data['access_token']) ? Encryption::encrypt($data['access_token']) : null;

        if ($existing) {
            $updateData = [
                'user_id' => $data['user_id'],
                'google_user_id' => $data['google_user_id'] ?? $existing->google_user_id,
                'token_expires_at' => $data['token_expires_at'] ?? $existing->token_expires_at,
                'status' => $data['status'] ?? 'connected',
                'last_error' => $data['last_error'] ?? null,
                'connected_at' => $existing->connected_at ?: $nowDate,
            ];

            if ($encryptedAccess) {
                $updateData['access_token'] = $encryptedAccess;
            }
            if ($encryptedRefresh) {
                $updateData['refresh_token'] = $encryptedRefresh;
            }

            $existing->update($updateData);
            return self::find($existing->id);
        }

        $sql = "INSERT INTO gmail_accounts (user_id, gmail_email, google_user_id, access_token, refresh_token, token_expires_at, status, connected_at, initial_sync_completed, created_at)
                VALUES (:user_id, :gmail_email, :google_user_id, :access_token, :refresh_token, :token_expires_at, :status, {$now}, 0, {$now})";

        try {
            Database::execute($sql, [
                'user_id' => $data['user_id'],
                'gmail_email' => $data['gmail_email'],
                'google_user_id' => $data['google_user_id'] ?? null,
                'access_token' => $encryptedAccess,
                'refresh_token' => $encryptedRefresh,
                'token_expires_at' => $data['token_expires_at'] ?? null,
                'status' => $data['status'] ?? 'connected',
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Unknown column') || str_contains($e->getMessage(), 'no such column')) {
                self::$schemaEnsured = false;
                self::ensureSchema();
                Database::execute($sql, [
                    'user_id' => $data['user_id'],
                    'gmail_email' => $data['gmail_email'],
                    'google_user_id' => $data['google_user_id'] ?? null,
                    'access_token' => $encryptedAccess,
                    'refresh_token' => $encryptedRefresh,
                    'token_expires_at' => $data['token_expires_at'] ?? null,
                    'status' => $data['status'] ?? 'connected',
                ]);
            } else {
                throw $e;
            }
        }

        $id = (int)Database::lastInsertId();

        // Create default automation settings
        AutomationSetting::createDefault($id);

        return self::find($id);
    }

    public function update(array $data): bool {
        self::ensureSchema();
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
        $sql = "UPDATE `gmail_accounts` SET " . implode(', ', $fields) . ", `updated_at` = {$now} WHERE `id` = :id";
        
        try {
            return Database::execute($sql, $params);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Unknown column') || str_contains($e->getMessage(), 'no such column')) {
                self::$schemaEnsured = false;
                self::ensureSchema();
                return Database::execute($sql, $params);
            }
            throw $e;
        }
    }

    public function getDecryptedAccessToken(): ?string {
        return Encryption::decrypt($this->access_token);
    }

    public function getDecryptedRefreshToken(): ?string {
        return Encryption::decrypt($this->refresh_token);
    }

    public function getSettings(): ?AutomationSetting {
        return AutomationSetting::findByAccountId($this->id);
    }

    public function getTodayUsage(): array {
        return DailyUsage::getOrCreate($this->id);
    }

    public function getCampaignTodayUsage(): array {
        return GmailCampaignDailyUsage::getAccountUsage($this->id);
    }

    public function isCampaignEligible(?string $date = null): bool {
        if ($this->status !== 'connected' || empty($this->refresh_token)) {
            return false;
        }

        if ($this->campaign_enabled !== 1) {
            return false;
        }

        // Check failure cooldown
        if ($this->temp_unavailable_until !== null) {
            if (strtotime($this->temp_unavailable_until) > time()) {
                return false;
            }
        }

        // Check per-account daily limit
        $usage = $this->getCampaignTodayUsage();
        $limit = $this->bulk_daily_limit > 0 ? $this->bulk_daily_limit : 50;
        return $usage['emails_sent'] < $limit;
    }

    public function markTemporaryFailure(int $cooldownMinutes = 10): void {
        $cooldown = date('Y-m-d H:i:s', time() + ($cooldownMinutes * 60));
        $this->update([
            'temp_unavailable_until' => $cooldown,
            'temp_failure_count' => $this->temp_failure_count + 1,
        ]);
    }

    public function clearTemporaryFailure(): void {
        if ($this->temp_unavailable_until !== null || $this->temp_failure_count > 0) {
            $this->update([
                'temp_unavailable_until' => null,
                'temp_failure_count' => 0,
            ]);
        }
    }

    public static function findCampaignEligibleByUserId(int $userId): array {
        $accounts = self::findByUserId($userId);
        return array_values(array_filter($accounts, fn(GmailAccount $a) => $a->isCampaignEligible()));
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM gmail_accounts WHERE id = :id", ['id' => $this->id]);
    }

    public static function fromRow(array $row): self {
        $acc = new self();
        $acc->id = (int)$row['id'];
        $acc->user_id = (int)$row['user_id'];
        $acc->gmail_email = $row['gmail_email'];
        $acc->google_user_id = $row['google_user_id'] ?? null;
        $acc->access_token = $row['access_token'] ?? null;
        $acc->refresh_token = $row['refresh_token'] ?? null;
        $acc->token_expires_at = $row['token_expires_at'] ?? null;
        $acc->history_id = $row['history_id'] ?? null;
        $acc->connected_at = $row['connected_at'] ?? null;
        $acc->initial_sync_completed = (int)($row['initial_sync_completed'] ?? 0);
        $acc->initial_history_id = $row['initial_history_id'] ?? null;
        $acc->initial_sync_at = $row['initial_sync_at'] ?? null;
        $acc->baseline_message_date = $row['baseline_message_date'] ?? null;
        $acc->status = $row['status'] ?? 'connected';
        $acc->bulk_daily_limit = isset($row['bulk_daily_limit']) ? (int)$row['bulk_daily_limit'] : 50;
        $acc->campaign_enabled = isset($row['campaign_enabled']) ? (int)$row['campaign_enabled'] : 1;
        $acc->temp_unavailable_until = $row['temp_unavailable_until'] ?? null;
        $acc->temp_failure_count = isset($row['temp_failure_count']) ? (int)$row['temp_failure_count'] : 0;
        $acc->last_sync_at = $row['last_sync_at'] ?? null;
        $acc->last_error = $row['last_error'] ?? null;
        $acc->created_at = $row['created_at'] ?? null;
        $acc->updated_at = $row['updated_at'] ?? null;
        return $acc;
    }
}
