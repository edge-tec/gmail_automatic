<?php
namespace App\Models;

use App\Core\Database;

class User {
    public int $id;
    public string $name;
    public string $email;
    public string $password;
    public string $role;
    public string $status;
    public ?int $plan_id = null;
    public string $plan_type = 'free';
    public string $subscription_status = 'inactive';
    public int $gmail_limit = 1;
    public string $trial_status = 'not_started';
    public ?string $trial_started_at = null;
    public ?string $trial_ends_at = null;
    public int $trial_days = 0;
    public bool $trial_used = false;
    public ?string $subscription_started_at = null;
    public ?string $subscription_expires_at = null;
    public ?string $stripe_customer_id = null;
    public ?string $stripe_subscription_id = null;
    public ?string $email_verified_at = null;
    public ?string $verification_token = null;
    public ?string $verification_token_expires_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM users WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self {
        $row = Database::first("SELECT * FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByVerificationToken(string $token): ?self {
        $row = Database::first("SELECT * FROM users WHERE verification_token = :tok LIMIT 1", ['tok' => $token]);
        return $row ? self::fromRow($row) : null;
    }

    public static function all(): array {
        $rows = Database::query("SELECT * FROM users ORDER BY id DESC");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO users 
                (name, email, password, role, status, plan_id, plan_type, subscription_status, gmail_limit, trial_status, trial_used, verification_token, created_at) 
                VALUES 
                (:name, :email, :password, :role, :status, :pid, :ptype, :sub_status, :limit, :t_status, :t_used, :v_tok, {$now})";

        Database::execute($sql, [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'user',
            'status' => $data['status'] ?? 'active',
            'pid' => $data['plan_id'] ?? null,
            'ptype' => $data['plan_type'] ?? 'free',
            'sub_status' => $data['subscription_status'] ?? 'inactive',
            'limit' => (int)($data['gmail_limit'] ?? 1),
            't_status' => $data['trial_status'] ?? 'not_started',
            't_used' => !empty($data['trial_used']) ? 1 : 0,
            'v_tok' => $data['verification_token'] ?? null,
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
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        $ok = Database::execute($sql, $params);
        if ($ok) {
            foreach ($data as $key => $val) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $val;
                }
            }
        }
        return $ok;
    }

    public function delete(): bool {
        return Database::execute("DELETE FROM users WHERE id = :id", ['id' => $this->id]);
    }

    /**
     * Check if user is eligible to start a free trial
     */
    public function canStartTrial(): bool {
        $trialEnabled = (bool)(int)SystemSetting::get('trial_enabled', '1');
        if (!$trialEnabled) {
            return false;
        }
        $onePerUser = (bool)(int)SystemSetting::get('trial_one_per_user', '1');
        if ($onePerUser && ($this->trial_used || $this->trial_status === 'active' || $this->trial_status === 'expired')) {
            return false;
        }
        if ($this->hasActiveSubscription()) {
            return false;
        }
        return true;
    }

    /**
     * Activate free trial for user
     */
    public function startTrial(?int $days = null, ?int $limit = null): bool {
        if (!$this->canStartTrial()) {
            return false;
        }

        $trialDays = $days ?? (int)SystemSetting::get('trial_duration_days', '14');
        $gmailLimit = $limit ?? (int)SystemSetting::get('trial_gmail_limit', '5');

        $start = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));

        return $this->update([
            'plan_type' => 'trial',
            'subscription_status' => 'trialing',
            'gmail_limit' => $gmailLimit,
            'trial_status' => 'active',
            'trial_started_at' => $start,
            'trial_ends_at' => $end,
            'trial_days' => $trialDays,
            'trial_used' => 1,
        ]);
    }

    /**
     * Check if user has an active paid subscription
     */
    public function hasActiveSubscription(): bool {
        if ($this->role === 'admin') {
            return true;
        }
        if ($this->subscription_status === 'active') {
            if ($this->subscription_expires_at) {
                return strtotime($this->subscription_expires_at) > time();
            }
            return true;
        }
        return false;
    }

    /**
     * Check if user's free trial is currently active
     */
    public function isTrialActive(): bool {
        if ($this->trial_status === 'active' && $this->trial_ends_at) {
            return strtotime($this->trial_ends_at) > time();
        }
        return false;
    }

    /**
     * Check if user has automation access (either active subscription, active trial, or admin)
     */
    public function hasAutomationAccess(): bool {
        return $this->role === 'admin' || $this->hasActiveSubscription() || $this->isTrialActive();
    }

    /**
     * Maximum allowed connected Gmail accounts
     */
    public function getMaxGmailAccounts(): int {
        if ($this->role === 'admin') {
            return 9999;
        }
        if ($this->hasActiveSubscription()) {
            return max($this->gmail_limit, 100);
        }
        if ($this->isTrialActive()) {
            return max($this->gmail_limit, (int)SystemSetting::get('trial_gmail_limit', '5'));
        }
        return max($this->gmail_limit, 1);
    }

    public function getPlan(): ?Plan {
        return $this->plan_id ? Plan::find($this->plan_id) : null;
    }

    public function getActivePlanName(): string {
        if ($this->role === 'admin') {
            return 'Administrator (Unlimited)';
        }
        if ($this->hasActiveSubscription()) {
            $plan = $this->getPlan();
            return $plan ? $plan->name . ' Plan' : ucfirst($this->plan_type) . ' Plan';
        }
        if ($this->isTrialActive()) {
            return 'Free Trial (' . $this->trial_days . ' Days)';
        }
        if ($this->trial_status === 'expired') {
            return 'Trial Expired';
        }
        return 'Free / Inactive';
    }

    public static function fromRow(array $row): self {
        $user = new self();
        $user->id = (int)$row['id'];
        $user->name = $row['name'];
        $user->email = $row['email'];
        $user->password = $row['password'];
        $user->role = $row['role'] ?? 'user';
        $user->status = $row['status'] ?? 'active';
        $user->plan_id = isset($row['plan_id']) ? (int)$row['plan_id'] : null;
        $user->plan_type = $row['plan_type'] ?? 'free';
        $user->subscription_status = $row['subscription_status'] ?? 'inactive';
        $user->gmail_limit = (int)($row['gmail_limit'] ?? 1);
        $user->trial_status = $row['trial_status'] ?? 'not_started';
        $user->trial_started_at = $row['trial_started_at'] ?? null;
        $user->trial_ends_at = $row['trial_ends_at'] ?? null;
        $user->trial_days = (int)($row['trial_days'] ?? 0);
        $user->trial_used = (bool)($row['trial_used'] ?? false);
        $user->subscription_started_at = $row['subscription_started_at'] ?? null;
        $user->subscription_expires_at = $row['subscription_expires_at'] ?? null;
        $user->stripe_customer_id = $row['stripe_customer_id'] ?? null;
        $user->stripe_subscription_id = $row['stripe_subscription_id'] ?? null;
        $user->email_verified_at = $row['email_verified_at'] ?? null;
        $user->verification_token = $row['verification_token'] ?? null;
        $user->verification_token_expires_at = $row['verification_token_expires_at'] ?? null;
        $user->created_at = $row['created_at'] ?? null;
        $user->updated_at = $row['updated_at'] ?? null;
        return $user;
    }
}
