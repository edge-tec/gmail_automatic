<?php
namespace App\Models;

use App\Core\Database;

class Payment {
    public int $id;
    public int $user_id;
    public ?int $plan_id = null;
    public string $gateway = 'stripe'; // stripe, bkash, nagad
    public string $payment_method_type = 'api'; // api, manual_number
    public ?string $sender_number = null;
    public ?string $transaction_id = null;
    public ?string $stripe_session_id = null;
    public ?string $stripe_payment_intent_id = null;
    public ?string $stripe_invoice_id = null;
    public float $amount = 0.00;
    public ?float $amount_bdt = null;
    public string $currency = 'usd';
    public string $status = 'pending'; // pending, paid, failed, rejected, cancelled, refunded
    public ?string $admin_notes = null;
    public ?string $paid_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM payments WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findBySessionId(string $sessionId): ?self {
        $row = Database::first("SELECT * FROM payments WHERE stripe_session_id = :sid LIMIT 1", ['sid' => $sessionId]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByTransactionId(string $trxId): ?self {
        $row = Database::first("SELECT * FROM payments WHERE transaction_id = :trx LIMIT 1", ['trx' => $trxId]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId, int $limit = 50): array {
        $rows = Database::query(
            "SELECT * FROM payments WHERE user_id = :uid ORDER BY created_at DESC LIMIT {$limit}",
            ['uid' => $userId]
        );
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function all(int $limit = 100): array {
        $rows = Database::query("SELECT * FROM payments ORDER BY created_at DESC LIMIT {$limit}");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function getPendingManual(int $limit = 50): array {
        $rows = Database::query("SELECT * FROM payments WHERE status = 'pending' ORDER BY created_at DESC LIMIT {$limit}");
        return array_map([self::class, 'fromRow'], $rows);
    }

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO payments (
                    user_id, plan_id, gateway, payment_method_type, sender_number, transaction_id, 
                    stripe_session_id, stripe_payment_intent_id, stripe_invoice_id, 
                    amount, amount_bdt, currency, status, admin_notes, paid_at, created_at
                ) VALUES (
                    :uid, :pid, :gw, :pmt, :sender, :trx, 
                    :sess, :pi, :inv, 
                    :amount, :bdt, :curr, :status, :notes, :paid, {$now}
                )";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'pid' => $data['plan_id'] ?? null,
            'gw' => $data['gateway'] ?? 'stripe',
            'pmt' => $data['payment_method_type'] ?? 'api',
            'sender' => $data['sender_number'] ?? null,
            'trx' => $data['transaction_id'] ?? null,
            'sess' => $data['stripe_session_id'] ?? null,
            'pi' => $data['stripe_payment_intent_id'] ?? null,
            'inv' => $data['stripe_invoice_id'] ?? null,
            'amount' => (float)($data['amount'] ?? 0.00),
            'bdt' => isset($data['amount_bdt']) ? (float)$data['amount_bdt'] : null,
            'curr' => strtolower($data['currency'] ?? 'usd'),
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['admin_notes'] ?? null,
            'paid' => $data['paid_at'] ?? null,
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
        $sql = "UPDATE payments SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    /**
     * Approve pending manual payment and activate user subscription
     */
    public function approve(int $adminId = 0, string $notes = 'Approved by Admin'): bool {
        $now = date('Y-m-d H:i:s');
        $this->update([
            'status' => 'paid',
            'paid_at' => $now,
            'admin_notes' => $notes,
        ]);

        $user = $this->getUser();
        $plan = $this->getPlan();

        if ($user && $plan) {
            $canBulkSend = ($plan->slug === 'professional') ? 1 : $user->can_bulk_send;

            $user->update([
                'plan_id' => $plan->id,
                'plan_type' => $plan->slug,
                'subscription_status' => 'active',
                'gmail_limit' => $plan->gmail_limit,
                'can_bulk_send' => $canBulkSend,
                'subscription_started_at' => $now,
                'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
            ]);

            logger("Payment #{$this->id} approved by admin. Activated {$plan->name} plan for user {$user->email}", 'info', $adminId, null);

            // Dispatch payment approved notification
            \App\Services\EmailNotificationService::notifyPaymentApproved($this);
        }

        return true;
    }

    /**
     * Reject pending manual payment
     */
    public function reject(string $reason = 'Payment verification rejected'): bool {
        $ok = $this->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
        ]);

        if ($ok) {
            \App\Services\EmailNotificationService::notifyPaymentRejected($this, $reason);
        }

        return $ok;
    }

    public function getPlan(): ?Plan {
        return $this->plan_id ? Plan::find($this->plan_id) : null;
    }

    public function getUser(): ?User {
        return User::find($this->user_id);
    }

    public static function fromRow(array $row): self {
        $p = new self();
        $p->id = (int)$row['id'];
        $p->user_id = (int)$row['user_id'];
        $p->plan_id = isset($row['plan_id']) ? (int)$row['plan_id'] : null;
        $p->gateway = $row['gateway'] ?? 'stripe';
        $p->payment_method_type = $row['payment_method_type'] ?? 'api';
        $p->sender_number = $row['sender_number'] ?? null;
        $p->transaction_id = $row['transaction_id'] ?? null;
        $p->stripe_session_id = $row['stripe_session_id'] ?? null;
        $p->stripe_payment_intent_id = $row['stripe_payment_intent_id'] ?? null;
        $p->stripe_invoice_id = $row['stripe_invoice_id'] ?? null;
        $p->amount = (float)$row['amount'];
        $p->amount_bdt = isset($row['amount_bdt']) ? (float)$row['amount_bdt'] : null;
        $p->currency = $row['currency'] ?? 'usd';
        $p->status = $row['status'] ?? 'pending';
        $p->admin_notes = $row['admin_notes'] ?? null;
        $p->paid_at = $row['paid_at'] ?? null;
        $p->created_at = $row['created_at'] ?? null;
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }
}
