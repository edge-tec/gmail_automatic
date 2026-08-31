<?php
namespace App\Models;

use App\Core\Database;

class Payment {
    public int $id;
    public int $user_id;
    public ?int $plan_id = null;
    public ?string $stripe_session_id = null;
    public ?string $stripe_payment_intent_id = null;
    public ?string $stripe_invoice_id = null;
    public float $amount;
    public string $currency;
    public string $status; // pending, paid, failed, cancelled, refunded
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

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO payments (user_id, plan_id, stripe_session_id, stripe_payment_intent_id, stripe_invoice_id, amount, currency, status, paid_at, created_at)
                VALUES (:uid, :pid, :sess, :pi, :inv, :amount, :curr, :status, :paid, {$now})";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'pid' => $data['plan_id'] ?? null,
            'sess' => $data['stripe_session_id'] ?? null,
            'pi' => $data['stripe_payment_intent_id'] ?? null,
            'inv' => $data['stripe_invoice_id'] ?? null,
            'amount' => (float)($data['amount'] ?? 0.00),
            'curr' => strtolower($data['currency'] ?? 'usd'),
            'status' => $data['status'] ?? 'pending',
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
        $p->stripe_session_id = $row['stripe_session_id'] ?? null;
        $p->stripe_payment_intent_id = $row['stripe_payment_intent_id'] ?? null;
        $p->stripe_invoice_id = $row['stripe_invoice_id'] ?? null;
        $p->amount = (float)$row['amount'];
        $p->currency = $row['currency'] ?? 'usd';
        $p->status = $row['status'] ?? 'pending';
        $p->paid_at = $row['paid_at'] ?? null;
        $p->created_at = $row['created_at'] ?? null;
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }
}
