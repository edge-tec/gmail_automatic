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
    public string $status;
    public ?string $last_sync_at = null;
    public ?string $last_error = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM gmail_accounts WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self {
        $row = Database::first("SELECT * FROM gmail_accounts WHERE gmail_email = :email LIMIT 1", ['email' => $email]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId): array {
        $rows = Database::query("SELECT * FROM gmail_accounts WHERE user_id = :uid ORDER BY id DESC", ['uid' => $userId]);
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function allActive(): array {
        $rows = Database::query("SELECT * FROM gmail_accounts WHERE status = 'connected' ORDER BY id ASC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function createOrUpdate(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $existing = self::findByEmail($data['gmail_email']);
        $encryptedRefresh = isset($data['refresh_token']) ? Encryption::encrypt($data['refresh_token']) : null;
        $encryptedAccess = isset($data['access_token']) ? Encryption::encrypt($data['access_token']) : null;

        if ($existing) {
            $updateData = [
                'user_id' => $data['user_id'],
                'google_user_id' => $data['google_user_id'] ?? $existing->google_user_id,
                'token_expires_at' => $data['token_expires_at'] ?? $existing->token_expires_at,
                'status' => 'connected',
                'last_error' => null,
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

        $sql = "INSERT INTO gmail_accounts (user_id, gmail_email, google_user_id, access_token, refresh_token, token_expires_at, status, created_at)
                VALUES (:user_id, :gmail_email, :google_user_id, :access_token, :refresh_token, :token_expires_at, 'connected', {$now})";

        Database::execute($sql, [
            'user_id' => $data['user_id'],
            'gmail_email' => $data['gmail_email'],
            'google_user_id' => $data['google_user_id'] ?? null,
            'access_token' => $encryptedAccess,
            'refresh_token' => $encryptedRefresh,
            'token_expires_at' => $data['token_expires_at'] ?? null,
        ]);

        $id = (int)Database::lastInsertId();

        // Create default automation settings
        AutomationSetting::createDefault($id);

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
        $sql = "UPDATE gmail_accounts SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
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
        $acc->status = $row['status'] ?? 'connected';
        $acc->last_sync_at = $row['last_sync_at'] ?? null;
        $acc->last_error = $row['last_error'] ?? null;
        $acc->created_at = $row['created_at'] ?? null;
        $acc->updated_at = $row['updated_at'] ?? null;
        return $acc;
    }
}
