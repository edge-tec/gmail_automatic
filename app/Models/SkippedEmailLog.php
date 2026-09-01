<?php
namespace App\Models;

use App\Core\Database;

class SkippedEmailLog {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public ?int $thread_id = null;
    public ?string $gmail_thread_id = null;
    public ?string $gmail_message_id = null;
    public string $sender_email;
    public ?string $sender_name = null;
    public ?string $recipient_email = null;
    public ?string $subject = null;
    public ?string $snippet = null;
    public string $skip_reason;
    public string $skip_type; // duplicate_traffic, blacklist, spam_filter, limit_reached, rule_skip, disabled
    public ?string $first_reply_sent_at = null;
    public ?string $received_at = null;
    public ?string $created_at = null;

    public static function create(array $data): self {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";

        $sql = "INSERT INTO skipped_email_logs 
                (user_id, gmail_account_id, thread_id, gmail_thread_id, gmail_message_id, sender_email, sender_name, recipient_email, subject, snippet, skip_reason, skip_type, first_reply_sent_at, received_at, created_at)
                VALUES 
                (:uid, :acc, :tid, :gtid, :mid, :sender, :name, :recipient, :subject, :snippet, :reason, :stype, :first_sent, :received_at, {$now})";

        Database::execute($sql, [
            'uid' => $data['user_id'],
            'acc' => $data['gmail_account_id'],
            'tid' => $data['thread_id'] ?? null,
            'gtid' => $data['gmail_thread_id'] ?? null,
            'mid' => $data['gmail_message_id'] ?? null,
            'sender' => $data['sender_email'],
            'name' => $data['sender_name'] ?? null,
            'recipient' => $data['recipient_email'] ?? null,
            'subject' => $data['subject'] ?? null,
            'snippet' => $data['snippet'] ?? null,
            'reason' => $data['skip_reason'],
            'stype' => $data['skip_type'] ?? 'duplicate_traffic',
            'first_sent' => $data['first_reply_sent_at'] ?? null,
            'received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
        ]);

        $id = (int)Database::lastInsertId();
        return self::find($id) ?? new self();
    }

    public static function find(int $id): ?self {
        $row = Database::first("SELECT * FROM skipped_email_logs WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUserId(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "SELECT s.*, g.gmail_email 
                FROM skipped_email_logs s
                JOIN gmail_accounts g ON s.gmail_account_id = g.id
                WHERE s.user_id = :uid";
        $params = ['uid' => $userId];

        if (!empty($filters['account_id'])) {
            $sql .= " AND s.gmail_account_id = :acc";
            $params['acc'] = (int)$filters['account_id'];
        }

        if (!empty($filters['skip_type'])) {
            $sql .= " AND s.skip_type = :stype";
            $params['stype'] = $filters['skip_type'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.sender_email LIKE :search OR s.sender_name LIKE :search OR s.subject LIKE :search OR s.skip_reason LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_range'])) {
            $driver = config('database.default', 'mysql');
            if ($filters['date_range'] === 'today') {
                $sql .= $driver === 'mysql' ? " AND DATE(s.created_at) = CURDATE()" : " AND date(s.created_at) = date('now')";
            } elseif ($filters['date_range'] === '7days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " AND s.created_at >= datetime('now', '-7 days')";
            } elseif ($filters['date_range'] === '30days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : " AND s.created_at >= datetime('now', '-30 days')";
            }
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        return Database::query($sql, $params);
    }

    public static function countByUserId(int $userId, array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM skipped_email_logs s WHERE s.user_id = :uid";
        $params = ['uid' => $userId];

        if (!empty($filters['account_id'])) {
            $sql .= " AND s.gmail_account_id = :acc";
            $params['acc'] = (int)$filters['account_id'];
        }

        if (!empty($filters['skip_type'])) {
            $sql .= " AND s.skip_type = :stype";
            $params['stype'] = $filters['skip_type'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.sender_email LIKE :search OR s.sender_name LIKE :search OR s.subject LIKE :search OR s.skip_reason LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_range'])) {
            $driver = config('database.default', 'mysql');
            if ($filters['date_range'] === 'today') {
                $sql .= $driver === 'mysql' ? " AND DATE(s.created_at) = CURDATE()" : " AND date(s.created_at) = date('now')";
            } elseif ($filters['date_range'] === '7days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " AND s.created_at >= datetime('now', '-7 days')";
            } elseif ($filters['date_range'] === '30days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : " AND s.created_at >= datetime('now', '-30 days')";
            }
        }

        $row = Database::first($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    public static function getStatsByUserId(int $userId): array {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";

        $totalSkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs WHERE user_id = :uid", ['uid' => $userId])['c'] ?? 0);
        $dupSkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs WHERE user_id = :uid AND skip_type = 'duplicate_traffic'", ['uid' => $userId])['c'] ?? 0);
        $todaySkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs WHERE user_id = :uid AND {$todayCond}", ['uid' => $userId])['c'] ?? 0);
        
        $uniqueSendersProtected = (int)(Database::first(
            "SELECT COUNT(DISTINCT normalized_sender_email) as c FROM auto_reply_recipients WHERE user_id = :uid AND reply_status = 'replied'",
            ['uid' => $userId]
        )['c'] ?? 0);

        return [
            'total_skipped' => $totalSkipped,
            'duplicate_traffic_skipped' => $dupSkipped,
            'today_skipped' => $todaySkipped,
            'unique_senders_protected' => $uniqueSendersProtected,
        ];
    }

    public static function allAdmin(array $filters = [], int $limit = 50, int $offset = 0): array {
        $sql = "SELECT s.*, g.gmail_email, u.email as user_account_email, u.name as user_name 
                FROM skipped_email_logs s
                JOIN gmail_accounts g ON s.gmail_account_id = g.id
                JOIN users u ON s.user_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND s.user_id = :uid";
            $params['uid'] = (int)$filters['user_id'];
        }

        if (!empty($filters['skip_type'])) {
            $sql .= " AND s.skip_type = :stype";
            $params['stype'] = $filters['skip_type'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.sender_email LIKE :search OR s.sender_name LIKE :search OR s.subject LIKE :search OR s.skip_reason LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_range'])) {
            $driver = config('database.default', 'mysql');
            if ($filters['date_range'] === 'today') {
                $sql .= $driver === 'mysql' ? " AND DATE(s.created_at) = CURDATE()" : " AND date(s.created_at) = date('now')";
            } elseif ($filters['date_range'] === '7days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " AND s.created_at >= datetime('now', '-7 days')";
            } elseif ($filters['date_range'] === '30days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : " AND s.created_at >= datetime('now', '-30 days')";
            }
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        return Database::query($sql, $params);
    }

    public static function countAdmin(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM skipped_email_logs s JOIN users u ON s.user_id = u.id WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND s.user_id = :uid";
            $params['uid'] = (int)$filters['user_id'];
        }

        if (!empty($filters['skip_type'])) {
            $sql .= " AND s.skip_type = :stype";
            $params['stype'] = $filters['skip_type'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.sender_email LIKE :search OR s.sender_name LIKE :search OR s.subject LIKE :search OR s.skip_reason LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_range'])) {
            $driver = config('database.default', 'mysql');
            if ($filters['date_range'] === 'today') {
                $sql .= $driver === 'mysql' ? " AND DATE(s.created_at) = CURDATE()" : " AND date(s.created_at) = date('now')";
            } elseif ($filters['date_range'] === '7days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " AND s.created_at >= datetime('now', '-7 days')";
            } elseif ($filters['date_range'] === '30days') {
                $sql .= $driver === 'mysql' ? " AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : " AND s.created_at >= datetime('now', '-30 days')";
            }
        }

        $row = Database::first($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    public static function getStatsAdmin(): array {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";

        $totalSkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs")['c'] ?? 0);
        $dupSkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs WHERE skip_type = 'duplicate_traffic'")['c'] ?? 0);
        $todaySkipped = (int)(Database::first("SELECT COUNT(*) as c FROM skipped_email_logs WHERE {$todayCond}")['c'] ?? 0);
        $uniqueSendersProtected = (int)(Database::first("SELECT COUNT(DISTINCT normalized_sender_email) as c FROM auto_reply_recipients WHERE reply_status = 'replied'")['c'] ?? 0);

        return [
            'total_skipped' => $totalSkipped,
            'duplicate_traffic_skipped' => $dupSkipped,
            'today_skipped' => $todaySkipped,
            'unique_senders_protected' => $uniqueSendersProtected,
        ];
    }

    public static function clearByUser(int $userId, ?int $accountId = null): bool {
        if ($accountId) {
            return Database::execute("DELETE FROM skipped_email_logs WHERE user_id = :uid AND gmail_account_id = :acc", ['uid' => $userId, 'acc' => $accountId]);
        }
        return Database::execute("DELETE FROM skipped_email_logs WHERE user_id = :uid", ['uid' => $userId]);
    }

    public static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->user_id = (int)$row['user_id'];
        $m->gmail_account_id = (int)$row['gmail_account_id'];
        $m->thread_id = isset($row['thread_id']) ? (int)$row['thread_id'] : null;
        $m->gmail_thread_id = $row['gmail_thread_id'] ?? null;
        $m->gmail_message_id = $row['gmail_message_id'] ?? null;
        $m->sender_email = $row['sender_email'];
        $m->sender_name = $row['sender_name'] ?? null;
        $m->recipient_email = $row['recipient_email'] ?? null;
        $m->subject = $row['subject'] ?? null;
        $m->snippet = $row['snippet'] ?? null;
        $m->skip_reason = $row['skip_reason'];
        $m->skip_type = $row['skip_type'] ?? 'duplicate_traffic';
        $m->first_reply_sent_at = $row['first_reply_sent_at'] ?? null;
        $m->received_at = $row['received_at'] ?? null;
        $m->created_at = $row['created_at'] ?? null;
        return $m;
    }
}
