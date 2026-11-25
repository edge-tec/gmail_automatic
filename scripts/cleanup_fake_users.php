<?php
/**
 * Remove fake / spam sign-up accounts, keep real ones.
 *
 *   php scripts/cleanup_fake_users.php                              # DRY RUN (default): only lists
 *   php scripts/cleanup_fake_users.php --execute --confirm=DELETE   # really deletes (after CSV backup)
 *
 * A user is a deletion CANDIDATE only if their name looks like spam (links, promo/casino text,
 * emoji, absurd length) AND they are NOT protected. Protected (never deleted):
 *   - admins, email-verified users, active/paid subscriptions
 *   - users with connected Gmail accounts, payments or bulk campaigns
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\SpamGuard;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

new App();

$execute = in_array('--execute', $argv, true);
$confirmed = in_array('--confirm=DELETE', $argv, true);

function tableCount(string $sql, int $uid): int {
    try {
        return (int)(Database::first($sql, ['uid' => $uid])['c'] ?? 0);
    } catch (\Throwable $e) {
        return 0; // table missing on this install
    }
}

function protectionReason(array $u): ?string {
    $uid = (int)$u['id'];
    if (($u['role'] ?? 'user') === 'admin') return 'admin';
    if (!empty($u['email_verified_at'])) return 'email verified';
    if (($u['subscription_status'] ?? '') === 'active') return 'active subscription';
    if (tableCount("SELECT COUNT(*) c FROM gmail_accounts WHERE user_id = :uid", $uid) > 0) return 'has Gmail account';
    if (tableCount("SELECT COUNT(*) c FROM payments WHERE user_id = :uid", $uid) > 0) return 'has payments';
    if (tableCount("SELECT COUNT(*) c FROM email_campaigns WHERE user_id = :uid", $uid) > 0) return 'has campaigns';
    return null;
}

$total = 0;
$candidates = [];
$protectedSpam = 0;
$lastId = 0;
while (true) {
    $rows = Database::query("SELECT * FROM users WHERE id > :last ORDER BY id ASC LIMIT 1000", ['last' => $lastId]);
    if (!$rows) break;
    foreach ($rows as $u) {
        $lastId = (int)$u['id'];
        $total++;
        $reasons = SpamGuard::nameReasons((string)($u['name'] ?? ''));
        if (!$reasons) continue;
        if (protectionReason($u) !== null) {
            $protectedSpam++;
            continue;
        }
        $u['_reasons'] = implode(', ', $reasons);
        $candidates[] = $u;
    }
}

echo "Total users:                    {$total}\n";
echo "Fake accounts (to delete):      " . count($candidates) . "\n";
echo "Spam-looking but PROTECTED:     {$protectedSpam} (kept)\n";
echo "Real accounts kept:             " . ($total - count($candidates)) . "\n\n";

foreach (array_slice($candidates, 0, 15) as $u) {
    printf("  #%-6d %-32s %s\n", $u['id'], $u['email'], mb_strimwidth(preg_replace('/\s+/', ' ', $u['name']), 0, 60, '...'));
}
if (count($candidates) > 15) echo "  ... and " . (count($candidates) - 15) . " more\n";

if (!$execute) {
    echo "\nDRY RUN - nothing was deleted. Review the list above, take a database backup, then run:\n";
    echo "  php scripts/cleanup_fake_users.php --execute --confirm=DELETE\n";
    exit(0);
}
if (!$confirmed) {
    exit("\nRefusing to delete: add --confirm=DELETE to proceed.\n");
}
if (!$candidates) {
    exit("\nNothing to delete.\n");
}

// 1. CSV backup of everything about to be deleted
$backupDir = storage_path('backups');
if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
$backupFile = $backupDir . '/deleted_fake_users_' . date('Ymd_His') . '.csv';
$fh = fopen($backupFile, 'w');
if (!$fh) exit("Cannot write backup file {$backupFile}; aborting, nothing deleted.\n");
fputcsv($fh, array_keys(array_diff_key($candidates[0], ['_reasons' => 1])) );
foreach ($candidates as $u) {
    unset($u['_reasons']);
    fputcsv($fh, $u);
}
fclose($fh);
echo "\nBackup written: {$backupFile}\n";

// 2. Delete in batches (no goodbye e-mails are sent to spam addresses)
$childTables = ['activity_logs', 'email_messages', 'automation_settings', 'scheduled_jobs', 'followup_jobs', 'skipped_email_logs'];
$deleted = 0;
foreach (array_chunk(array_column($candidates, 'id'), 200) as $batch) {
    $ids = array_map('intval', $batch);
    $in = implode(',', $ids);
    Database::beginTransaction();
    try {
        foreach ($childTables as $t) {
            try { Database::execute("DELETE FROM {$t} WHERE user_id IN ({$in})"); } catch (\Throwable $e) {}
        }
        Database::execute("DELETE FROM users WHERE id IN ({$in})");
        Database::commit();
        $deleted += count($ids);
        echo "Deleted {$deleted}/" . count($candidates) . "\r";
    } catch (\Throwable $e) {
        Database::rollBack();
        echo "\nBatch failed and was rolled back: " . $e->getMessage() . "\n";
    }
}
echo "\nDone. Removed {$deleted} fake accounts.\n";
