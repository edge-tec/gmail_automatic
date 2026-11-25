<?php
/**
 * Delete EVERY user account except one admin (and all of their data).
 *
 *   php scripts/delete_all_except.php --keep-email=admin@example.com
 *       -> DRY RUN (default): shows what would be deleted, deletes nothing
 *
 *   php scripts/delete_all_except.php --keep-email=admin@example.com --execute --confirm=DELETE-ALL-EXCEPT-admin@example.com
 *       -> really deletes (after writing a CSV backup of the removed users)
 *
 * IRREVERSIBLE. Take a database backup (mysqldump) before running with --execute.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

new App();

$keepEmail = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--keep-email=')) $keepEmail = strtolower(trim(substr($a, 13)));
}
$execute = in_array('--execute', $argv, true);
if (!$keepEmail) exit("Usage: php scripts/delete_all_except.php --keep-email=you@example.com [--execute --confirm=DELETE-ALL-EXCEPT-you@example.com]\n");
$expectedConfirm = '--confirm=DELETE-ALL-EXCEPT-' . $keepEmail;

$keep = Database::first("SELECT id, email, name, role FROM users WHERE LOWER(email) = :e LIMIT 1", ['e' => $keepEmail]);
if (!$keep) exit("ABORT: no user with email {$keepEmail} exists. Nothing deleted.\n");
if (($keep['role'] ?? '') !== 'admin') exit("ABORT: {$keepEmail} is not an admin. Nothing deleted.\n");
$keepId = (int)$keep['id'];

$driver = config('database.default', 'mysql');

/** All tables (except users) that have the given column. */
function tablesWithColumn(string $driver, string $column): array {
    $found = [];
    if ($driver === 'sqlite') {
        foreach (Database::query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $t) {
            foreach (Database::query("PRAGMA table_info(`{$t['name']}`)") as $c) {
                if (strtolower($c['name']) === $column) $found[] = $t['name'];
            }
        }
    } else {
        foreach (Database::query("SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = :c", ['c' => $column]) as $r) {
            $found[] = $r['t'] ?? $r['TABLE_NAME'];
        }
    }
    return array_values(array_unique(array_diff($found, ['users'])));
}

$total = (int)Database::first("SELECT COUNT(*) c FROM users")['c'];
$toDelete = $total - 1;
$stat = fn(string $sql) => (int)(Database::first($sql, ['k' => $keepId])['c'] ?? 0);
echo "KEEP:   #{$keepId} {$keep['email']} ({$keep['name']})\n";
echo "Total users:                         {$total}\n";
echo "Users that will be DELETED:          {$toDelete}\n";
try {
    echo "  - other admins:                    " . $stat("SELECT COUNT(*) c FROM users WHERE role='admin' AND id <> :k") . "\n";
    echo "  - email-verified users:            " . $stat("SELECT COUNT(*) c FROM users WHERE email_verified_at IS NOT NULL AND id <> :k") . "\n";
    echo "  - active subscriptions:            " . $stat("SELECT COUNT(*) c FROM users WHERE subscription_status='active' AND id <> :k") . "\n";
    echo "  - connected Gmail accounts:        " . $stat("SELECT COUNT(*) c FROM gmail_accounts WHERE user_id <> :k") . "\n";
    echo "  - payment records:                 " . $stat("SELECT COUNT(*) c FROM payments WHERE user_id <> :k") . "\n";
} catch (\Throwable $e) {}

if (!$execute) {
    echo "\nDRY RUN - nothing was deleted.\nTo delete for real (take a mysqldump backup first!):\n";
    echo "  php scripts/delete_all_except.php --keep-email={$keepEmail} --execute {$expectedConfirm}\n";
    exit(0);
}
if (!in_array($expectedConfirm, $argv, true)) {
    exit("\nREFUSING: add exactly  {$expectedConfirm}  to proceed.\n");
}
if ($toDelete <= 0) exit("\nNothing to delete.\n");

// 1. CSV backup of the users being removed
$dir = storage_path('backups');
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$file = $dir . '/deleted_all_users_' . date('Ymd_His') . '.csv';
$fh = fopen($file, 'w');
if (!$fh) exit("Cannot write {$file}; aborting, nothing deleted.\n");
fputcsv($fh, ['id', 'email', 'name', 'role', 'status', 'plan_type', 'email_verified_at', 'created_at']);
$last = 0;
while ($rows = Database::query("SELECT id,email,name,role,status,plan_type,email_verified_at,created_at FROM users WHERE id > :l AND id <> :k ORDER BY id LIMIT 1000", ['l' => $last, 'k' => $keepId])) {
    foreach ($rows as $r) { fputcsv($fh, $r); $last = (int)$r['id']; }
}
fclose($fh);
echo "\nBackup written: {$file}\n";

// 2. Delete: gmail-account children first, then all user_id children, then users
$byAccount = tablesWithColumn($driver, 'gmail_account_id');
$byUser = tablesWithColumn($driver, 'user_id');

if ($driver === 'sqlite') {
    Database::execute("PRAGMA foreign_keys = OFF");
} else {
    Database::execute("SET FOREIGN_KEY_CHECKS = 0");
}
Database::beginTransaction();
try {
    foreach ($byAccount as $t) {
        if ($t === 'gmail_accounts') continue;
        $n = Database::executeUpdate("DELETE FROM `{$t}` WHERE gmail_account_id IN (SELECT id FROM gmail_accounts WHERE user_id <> :k)", ['k' => $keepId]);
        if ($n) echo "  {$t}: {$n} rows\n";
    }
    foreach ($byUser as $t) {
        $n = Database::executeUpdate("DELETE FROM `{$t}` WHERE user_id <> :k", ['k' => $keepId]);
        if ($n) echo "  {$t}: {$n} rows\n";
    }
    $n = Database::executeUpdate("DELETE FROM users WHERE id <> :k", ['k' => $keepId]);
    echo "  users: {$n} rows\n";
    Database::commit();
    echo "\nDone. Only {$keep['email']} remains.\n";
} catch (\Throwable $e) {
    Database::rollBack();
    echo "\nFAILED and rolled back, nothing deleted: " . $e->getMessage() . "\n";
}
if ($driver !== 'sqlite') Database::execute("SET FOREIGN_KEY_CHECKS = 1");
