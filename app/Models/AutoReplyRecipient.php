<?php
namespace App\Models;

use App\Core\Database;

class AutoReplyRecipient {
    public int $id;
    public int $user_id;
    public int $gmail_account_id;
    public string $normalized_sender_email;
    public ?string $first_message_id = null;
    public ?string $first_thread_id = null;
    public int $reply_sequence_step = 0;
    public int $reply_sequence_total = 1;
    public string $reply_sequence_status = 'active'; // active, completed
    public ?string $reply_sequence_completed_at = null;
    public ?string $reply_sent_at = null;
    public int $daily_counted = 0;
    public ?string $counted_date = null;
    public int $recipient_replied_for_step = 0;
    public ?string $last_recipient_reply_at = null;
    public string $reply_status = 'pending'; // pending, processing, active, completed, replied, cancelled, failed
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function normalizeEmail(string $email): string {
        $clean = trim($email);
        if (preg_match('/<([^>]+)>/', $clean, $matches)) {
            $clean = $matches[1];
        }
        return strtolower(trim($clean));
    }

    /**
     * Ensure database columns, reconcile legacy duplicate records, and maintain user-scoped unique index
     */
    public static function ensureSchema(): void {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        $driver = config('database.default', 'mysql');
        $recipientCols = [
            'reply_sequence_step' => ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
            'reply_sequence_total' => ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1'),
            'reply_sequence_status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
            'reply_sequence_completed_at' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
            'daily_counted' => ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
            'counted_date' => ($driver === 'mysql' ? 'DATE NULL' : 'TEXT NULL'),
            'recipient_replied_for_step' => ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'),
            'last_recipient_reply_at' => ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL'),
        ];
        \App\Core\DatabaseSanitizer::ensureTableColumns('auto_reply_recipients', $recipientCols);

        // Reconcile any existing duplicate rows before enforcing unique constraint
        self::reconcileExistingDuplicates();

        // Ensure user-scoped unique constraint uk_user_sender_reply exists
        try {
            if ($driver === 'mysql') {
                // Drop legacy per-account unique key if present
                $indexes = Database::query("SHOW INDEX FROM auto_reply_recipients");
                $indexNames = array_map(fn($i) => $i['Key_name'] ?? $i['key_name'] ?? '', $indexes);
                if (in_array('uk_acc_sender_reply', $indexNames)) {
                    try {
                        Database::execute("ALTER TABLE auto_reply_recipients DROP INDEX uk_acc_sender_reply");
                    } catch (\Throwable $t) {}
                }
                if (!in_array('uk_user_sender_reply', $indexNames)) {
                    try {
                        Database::execute("ALTER TABLE auto_reply_recipients ADD UNIQUE KEY uk_user_sender_reply (user_id, normalized_sender_email)");
                    } catch (\Throwable $t) {}
                }
            } else {
                try {
                    Database::execute("CREATE UNIQUE INDEX IF NOT EXISTS uk_user_sender_reply ON auto_reply_recipients (user_id, normalized_sender_email)");
                } catch (\Throwable $t) {}
            }
        } catch (\Throwable $t) {}
    }

    /**
     * Reconcile legacy duplicate records for the same traffic identity (user_id, normalized_sender_email)
     */
    public static function reconcileExistingDuplicates(): void {
        try {
            $duplicates = Database::query(
                "SELECT user_id, normalized_sender_email, COUNT(*) as cnt 
                 FROM auto_reply_recipients 
                 GROUP BY user_id, normalized_sender_email 
                 HAVING cnt > 1"
            );

            foreach ($duplicates as $dup) {
                $uid = (int)$dup['user_id'];
                $sender = $dup['normalized_sender_email'];

                $records = Database::query(
                    "SELECT * FROM auto_reply_recipients 
                     WHERE user_id = :uid AND normalized_sender_email = :sender 
                     ORDER BY reply_sequence_step DESC, reply_sent_at DESC, id ASC",
                    ['uid' => $uid, 'sender' => $sender]
                );

                if (count($records) <= 1) continue;

                $primary = $records[0];
                $maxStep = 0;
                $maxTotal = 1;
                $latestSentAt = null;
                $latestCompletedAt = null;
                $hasCompleted = false;
                $idsToDelete = [];

                foreach ($records as $idx => $r) {
                    $s = (int)($r['reply_sequence_step'] ?? 0);
                    $tot = (int)($r['reply_sequence_total'] ?? 1);
                    if ($s > $maxStep) $maxStep = $s;
                    if ($tot > $maxTotal) $maxTotal = $tot;
                    if (!empty($r['reply_sent_at']) && (!$latestSentAt || $r['reply_sent_at'] > $latestSentAt)) {
                        $latestSentAt = $r['reply_sent_at'];
                    }
                    if (!empty($r['reply_sequence_completed_at']) && (!$latestCompletedAt || $r['reply_sequence_completed_at'] > $latestCompletedAt)) {
                        $latestCompletedAt = $r['reply_sequence_completed_at'];
                    }
                    if (($r['reply_sequence_status'] ?? '') === 'completed' || $s >= $tot) {
                        $hasCompleted = true;
                    }

                    if ($idx > 0) {
                        $idsToDelete[] = (int)$r['id'];
                    }
                }

                $finalStatus = ($hasCompleted || $maxStep >= $maxTotal) ? 'completed' : 'active';
                $finalReplyStatus = ($finalStatus === 'completed') ? 'replied' : ($maxStep > 0 ? 'active' : 'pending');

                Database::execute(
                    "UPDATE auto_reply_recipients SET 
                        reply_sequence_step = :step,
                        reply_sequence_total = :tot,
                        reply_sequence_status = :seq_status,
                        reply_status = :rep_status,
                        reply_sent_at = :sent_at,
                        reply_sequence_completed_at = :comp_at
                     WHERE id = :id",
                    [
                        'step' => $maxStep,
                        'tot' => $maxTotal,
                        'seq_status' => $finalStatus,
                        'rep_status' => $finalReplyStatus,
                        'sent_at' => $latestSentAt,
                        'comp_at' => $latestCompletedAt,
                        'id' => (int)$primary['id'],
                    ]
                );

                if (!empty($idsToDelete)) {
                    $idList = implode(',', $idsToDelete);
                    Database::execute("DELETE FROM auto_reply_recipients WHERE id IN ({$idList})");
                }
            }
        } catch (\Throwable $t) {}
    }

    public static function find(int $id): ?self {
        self::ensureSchema();
        $row = Database::first("SELECT * FROM auto_reply_recipients WHERE id = :id LIMIT 1", ['id' => $id]);
        return $row ? self::fromRow($row) : null;
    }

    public function refresh(): self {
        $reloaded = self::find($this->id);
        if ($reloaded) {
            foreach (get_object_vars($reloaded) as $key => $value) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    /**
     * Look up sequence state by traffic identity (user_id + normalized_sender_email)
     */
    public static function findByUserAndSender(int $userId, string $senderEmail): ?self {
        self::ensureSchema();
        $normalized = self::normalizeEmail($senderEmail);
        $row = Database::first(
            "SELECT * FROM auto_reply_recipients WHERE user_id = :uid AND normalized_sender_email = :sender LIMIT 1",
            ['uid' => $userId, 'sender' => $normalized]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Backward-compatible helper that resolves account to user
     */
    public static function findByAccountAndSender(int $accountId, string $senderEmail): ?self {
        self::ensureSchema();
        $normalized = self::normalizeEmail($senderEmail);
        $account = GmailAccount::find($accountId);
        if ($account) {
            return self::findByUserAndSender($account->user_id, $normalized);
        }
        $row = Database::first(
            "SELECT * FROM auto_reply_recipients WHERE gmail_account_id = :acc AND normalized_sender_email = :sender LIMIT 1",
            ['acc' => $accountId, 'sender' => $normalized]
        );
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Atomically claims or evaluates a sender's global Reply Sequence for the user's automation.
     * 
     * Rules:
     * - ONE sequence per traffic/sender across all connected Gmail accounts.
     * - Email #1 -> Step 1
     * - Email #2 -> Step 2
     * - Email #N -> Step N
     * - Email #(N+1) -> DUPLICATE TRAFFIC (SKIP)
     * - Successful delivery is required to advance the completed step counter.
     */
    public static function claimOrGetForSequence(
        int $userId,
        int $accountId,
        string $senderEmail,
        int $totalConfiguredSteps,
        ?string $msgId = null,
        ?string $threadId = null,
        bool $requireRecipientReply = false,
        bool $isRecipientReply = false
    ): array {
        self::ensureSchema();
        $normalized = self::normalizeEmail($senderEmail);
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $totalConfiguredSteps = max(1, $totalConfiguredSteps);

        // 1. Check existing record for this user and sender
        $existing = self::findByUserAndSender($userId, $normalized);

        if (!$existing) {
            try {
                Database::execute(
                    "INSERT INTO auto_reply_recipients 
                    (user_id, gmail_account_id, normalized_sender_email, first_message_id, first_thread_id, reply_sequence_step, reply_sequence_total, reply_sequence_status, reply_status, created_at)
                    VALUES 
                    (:uid, :acc, :sender, :mid, :tid, 0, :total, 'active', 'pending', {$now})",
                    [
                        'uid' => $userId,
                        'acc' => $accountId,
                        'sender' => $normalized,
                        'mid' => $msgId,
                        'tid' => $threadId,
                        'total' => $totalConfiguredSteps,
                    ]
                );

                $id = (int)Database::lastInsertId();
                $recipient = self::find($id);

                logger("Traffic: {$senderEmail} | User: {$userId} | Sequence ID: {$id} | Current Step: 0 | Selected Reply: #1 | Total: {$totalConfiguredSteps} | Decision: NEW_TRAFFIC_SEQUENCE", 'info', $userId, $accountId);

                return [
                    'recipient' => $recipient,
                    'next_step' => 1,
                    'is_eligible' => true,
                    'is_duplicate' => false,
                ];
            } catch (\Throwable $e) {
                // Concurrent insert happened, fetch existing
                $existing = self::findByUserAndSender($userId, $normalized);
            }
        }

        if (!$existing) {
            return [
                'recipient' => null,
                'next_step' => 0,
                'is_eligible' => false,
                'is_duplicate' => true,
            ];
        }

        // Update latest account ID on the recipient record for activity reference
        $existing->update(['gmail_account_id' => $accountId]);

        // Sync latest total steps if user increased sequence configuration
        if ($totalConfiguredSteps > $existing->reply_sequence_total) {
            $existing->update([
                'reply_sequence_total' => $totalConfiguredSteps,
                'reply_sequence_status' => ($existing->reply_sequence_step >= $totalConfiguredSteps) ? 'completed' : 'active',
            ]);
            $existing->reply_sequence_total = $totalConfiguredSteps;
        }

        // Determine highest step already scheduled in pending/processing jobs for this user across all accounts
        $queuedStep = self::getHighestQueuedStep($userId, $normalized);

        // Determine next step
        $highestStepInFlight = max($existing->reply_sequence_step, $queuedStep);
        $nextStep = $highestStepInFlight + 1;

        // Check if sequence is completed
        if ($nextStep > $totalConfiguredSteps || ($existing->reply_sequence_status === 'completed' && $existing->reply_sequence_step >= $totalConfiguredSteps)) {
            logger("Traffic: {$senderEmail} | User: {$userId} | Sequence ID: {$existing->id} | Status: COMPLETED | Completed Step: {$existing->reply_sequence_step}/{$totalConfiguredSteps} | Decision: DUPLICATE_TRAFFIC", 'info', $userId, $accountId);
            return [
                'recipient' => $existing,
                'next_step' => $nextStep,
                'is_eligible' => false,
                'is_duplicate' => true,
            ];
        }

        // Check Require Recipient Reply Before Next Auto-Reply setting:
        // If enabled and our system has already sent at least 1 reply ($highestStepInFlight >= 1),
        // we must wait for a genuine recipient reply before unlocking the next reply step.
        if ($requireRecipientReply && $highestStepInFlight >= 1 && !$isRecipientReply) {
            logger("Traffic: {$senderEmail} | User: {$userId} | Sequence ID: {$existing->id} | Current Step: {$existing->reply_sequence_step} | Status: WAITING_FOR_RECIPIENT_REPLY | Decision: SKIP_AWAITING_RECIPIENT_REPLY", 'info', $userId, $accountId);
            return [
                'recipient' => $existing,
                'next_step' => $existing->reply_sequence_step,
                'is_eligible' => false,
                'is_duplicate' => false,
                'skip_type' => 'awaiting_recipient_reply',
                'skip_reason' => "Waiting for recipient to reply to our previous auto-reply (Step #{$existing->reply_sequence_step}) before sending next reply",
            ];
        }

        // Sequence is still active & eligible!
        logger("Traffic: {$senderEmail} | User: {$userId} | Sequence ID: {$existing->id} | Current Step: {$existing->reply_sequence_step} | In-Flight: {$highestStepInFlight} | Selected Reply: #{$nextStep} | Total: {$totalConfiguredSteps} | Decision: ACTIVE_SEQUENCE", 'info', $userId, $accountId);

        return [
            'recipient' => $existing,
            'next_step' => $nextStep,
            'is_eligible' => true,
            'is_duplicate' => false,
        ];
    }

    /**
     * Backward-compatible claimOrGet method
     */
    public static function claimOrGet(int $userId, int $accountId, string $senderEmail, ?string $msgId = null, ?string $threadId = null): array {
        $res = self::claimOrGetForSequence($userId, $accountId, $senderEmail, 1, $msgId, $threadId);
        return [
            'recipient' => $res['recipient'],
            'is_new' => $res['next_step'] === 1 && $res['is_eligible'],
            'is_eligible' => $res['is_eligible'],
        ];
    }

    /**
     * Finds highest pending/queued step number in scheduled_jobs for this user and sender across all accounts
     */
    public static function getHighestQueuedStep(int $userId, string $normalizedSenderEmail): int {
        $jobs = Database::query(
            "SELECT sj.payload FROM scheduled_jobs sj
             JOIN gmail_accounts ga ON ga.id = sj.gmail_account_id
             WHERE ga.user_id = :uid AND sj.job_type = 'auto_reply' AND sj.status IN ('pending', 'processing')",
            ['uid' => $userId]
        );

        $maxStep = 0;
        foreach ($jobs as $j) {
            $payload = json_decode($j['payload'] ?? '', true);
            if (is_array($payload)) {
                $email = self::normalizeEmail($payload['recipient_email'] ?? '');
                if ($email === $normalizedSenderEmail) {
                    $step = (int)($payload['reply_step'] ?? 1);
                    if ($step > $maxStep) {
                        $maxStep = $step;
                    }
                }
            }
        }
        return $maxStep;
    }

    /**
     * Record a reply step successfully sent by worker
     * 
     * Rule:
     * - Daily Reply quota is counted ONCE per unique traffic sequence (1 per lead).
     * - Subsequent replies (Step 2, 3, 4, 5...) increment reply_messages_count without consuming additional daily traffic quota.
     */
    public function recordStepSent(int $step, int $totalSteps, string $sentAt, int $accountId = 0): bool {
        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $totalSteps = max(1, $totalSteps);
        $newStep = max($this->reply_sequence_step, $step);
        $isCompleted = ($newStep >= $totalSteps);
        $todayDate = date('Y-m-d');
        $accId = $accountId ?: $this->gmail_account_id;

        // Daily Reply Quota Counting: 1 traffic sequence = 1 Daily Reply count
        $shouldCountDaily = ($this->daily_counted === 0 || $this->counted_date !== $todayDate);

        if ($shouldCountDaily && $accId > 0) {
            DailyUsage::incrementReply($accId, $todayDate);
            $this->daily_counted = 1;
            $this->counted_date = $todayDate;
        }

        if ($accId > 0) {
            DailyUsage::incrementReplyMessage($accId, $todayDate);
        }

        $fields = [
            'reply_sequence_step' => $newStep,
            'reply_sequence_total' => $totalSteps,
            'reply_sequence_status' => $isCompleted ? 'completed' : 'active',
            'reply_status' => $isCompleted ? 'replied' : 'active',
            'reply_sent_at' => $sentAt,
            'daily_counted' => 1,
            'counted_date' => $todayDate,
            'updated_at' => $now,
        ];

        if ($isCompleted) {
            $fields['reply_sequence_completed_at'] = $sentAt;
        }

        $setSql = [];
        $params = ['id' => $this->id];
        foreach ($fields as $k => $v) {
            if ($k === 'updated_at') {
                $setSql[] = "updated_at = {$now}";
            } else {
                $setSql[] = "{$k} = :{$k}";
                $params[$k] = $v;
            }
        }

        $sql = "UPDATE auto_reply_recipients SET " . implode(', ', $setSql) . " WHERE id = :id";
        $ok = Database::execute($sql, $params);
        if ($ok) {
            $this->reply_sequence_step = $newStep;
            $this->reply_sequence_total = $totalSteps;
            $this->reply_sequence_status = $isCompleted ? 'completed' : 'active';
            $this->reply_status = $isCompleted ? 'replied' : 'active';
            $this->reply_sent_at = $sentAt;
            $this->daily_counted = 1;
            $this->counted_date = $todayDate;
            if ($isCompleted) {
                $this->reply_sequence_completed_at = $sentAt;
            }
        }
        return $ok;
    }

    public function markReplied(): bool {
        $sentAt = date('Y-m-d H:i:s');
        return $this->recordStepSent($this->reply_sequence_step ?: 1, $this->reply_sequence_total ?: 1, $sentAt);
    }

    public function update(array $data): bool {
        $fields = [];
        $params = ['id' => $this->id];
        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }

        $driver = config('database.default', 'mysql');
        $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
        $sql = "UPDATE auto_reply_recipients SET " . implode(', ', $fields) . ", updated_at = {$now} WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public function markProcessing(): bool {
        return $this->update(['reply_status' => 'processing']);
    }

    public function markCancelled(): bool {
        return $this->update(['reply_status' => 'cancelled']);
    }

    public function markFailed(): bool {
        return $this->update(['reply_status' => 'failed']);
    }

    public function isSequenceCompleted(): bool {
        return $this->reply_sequence_status === 'completed' || ($this->reply_sequence_step >= $this->reply_sequence_total && $this->reply_sequence_step > 0);
    }

    public static function countUniqueTrafficToday(int $accountId): int {
        $account = GmailAccount::find($accountId);
        $userId = $account ? $account->user_id : 0;
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";
        $row = Database::first(
            "SELECT COUNT(DISTINCT normalized_sender_email) as total FROM auto_reply_recipients WHERE user_id = :uid AND {$todayCond}",
            ['uid' => $userId]
        );
        return (int)($row['total'] ?? 0);
    }

    public static function countRepliedForAccount(int $accountId): int {
        $account = GmailAccount::find($accountId);
        $userId = $account ? $account->user_id : 0;
        $row = Database::first(
            "SELECT COUNT(*) as total FROM auto_reply_recipients WHERE user_id = :uid AND reply_status IN ('replied', 'active')",
            ['uid' => $userId]
        );
        return (int)($row['total'] ?? 0);
    }

    public static function countUniqueTrafficTodayAdmin(): int {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(created_at) = CURDATE()" : "date(created_at) = date('now')";
        $row = Database::first("SELECT COUNT(DISTINCT normalized_sender_email) as total FROM auto_reply_recipients WHERE {$todayCond}");
        return (int)($row['total'] ?? 0);
    }

    public static function countTotalRepliedTodayAdmin(): int {
        $driver = config('database.default', 'mysql');
        $todayCond = $driver === 'mysql' ? "DATE(reply_sent_at) = CURDATE()" : "date(reply_sent_at) = date('now')";
        $row = Database::first("SELECT COUNT(*) as total FROM auto_reply_recipients WHERE reply_sent_at IS NOT NULL AND {$todayCond}");
        return (int)($row['total'] ?? 0);
    }

    public static function countPendingAdmin(): int {
        $row = Database::first("SELECT COUNT(*) as total FROM auto_reply_recipients WHERE reply_status IN ('pending', 'processing')");
        return (int)($row['total'] ?? 0);
    }

    public function markRecipientReplied(int $step, string $replyAt): bool {
        return $this->update([
            'recipient_replied_for_step' => $step,
            'last_recipient_reply_at' => $replyAt,
        ]);
    }

    private static function fromRow(array $row): self {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->user_id = (int)$row['user_id'];
        $m->gmail_account_id = (int)($row['gmail_account_id'] ?? 0);
        $m->normalized_sender_email = $row['normalized_sender_email'];
        $m->first_message_id = $row['first_message_id'] ?? null;
        $m->first_thread_id = $row['first_thread_id'] ?? null;
        $m->reply_sequence_step = isset($row['reply_sequence_step']) ? (int)$row['reply_sequence_step'] : 0;
        $m->reply_sequence_total = isset($row['reply_sequence_total']) ? (int)$row['reply_sequence_total'] : 1;
        $m->reply_sequence_status = $row['reply_sequence_status'] ?? 'active';
        $m->reply_sequence_completed_at = $row['reply_sequence_completed_at'] ?? null;
        $m->reply_sent_at = $row['reply_sent_at'] ?? null;
        $m->daily_counted = isset($row['daily_counted']) ? (int)$row['daily_counted'] : 0;
        $m->counted_date = $row['counted_date'] ?? null;
        $m->recipient_replied_for_step = isset($row['recipient_replied_for_step']) ? (int)$row['recipient_replied_for_step'] : 0;
        $m->last_recipient_reply_at = $row['last_recipient_reply_at'] ?? null;
        $m->reply_status = $row['reply_status'] ?? 'pending';
        $m->created_at = $row['created_at'] ?? null;
        $m->updated_at = $row['updated_at'] ?? null;
        return $m;
    }
}
