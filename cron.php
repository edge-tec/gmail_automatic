<?php
/**
 * Gmail Automation Cron Runner
 * Run every minute via aaPanel Cron: * * * * * cd /www/wwwroot/your-domain.com && php cron.php >> storage/logs/cron.log 2>&1
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;
use App\Models\GmailAccount;
use App\Models\SystemSetting;
use App\Services\GmailService;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;

new App();
\App\Core\DatabaseSanitizer::runOnce();

// Non-blocking file lock to prevent overlapping cron runs
$lockDir = __DIR__ . '/storage/framework';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/cron.lock';
$lockFp = @fopen($lockFile, 'c+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    $nowStr = date('Y-m-d H:i:s');
    echo "[{$nowStr}] Another Gmail Automation Cron process is already running. Exiting gracefully to prevent overlap.\n";
    exit(0);
}

register_shutdown_function(function() use ($lockFp) {
    if ($lockFp) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
});

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Gmail Automation Cron Poller starting...\n";

// Update system setting last run
SystemSetting::set('cron_last_run', $timestamp);

// 1. Check Global Automation Switch
if (SystemSetting::get('global_automation_enabled', '1') !== '1') {
    echo "[{$timestamp}] Global automation is disabled. Exiting.\n";
    exit(0);
}

// 2. Fetch active connected Gmail accounts ready for sync (throttled and prioritized for 20-50+ users)
$isTesting = config('app.env') === 'testing' || getenv('APP_ENV') === 'testing' || ($_ENV['APP_ENV'] ?? '') === 'testing';
$accounts = $isTesting ? GmailAccount::allActive() : GmailAccount::getReadyForSync(45, 50);
echo "[{$timestamp}] Found " . count($accounts) . " active Gmail account(s) ready for sync.\n";

foreach ($accounts as $account) {
    echo "Processing account: {$account->gmail_email} (ID: {$account->id})...\n";

    try {
        $gmailService = new GmailService($account);

        // If baseline has not been established yet for this account, run initial baseline synchronization
        if ($account->initial_sync_completed === 0) {
            echo "  ↳ Initial sync baseline not found. Establishing baseline for {$account->gmail_email}...\n";
            $baseResult = $gmailService->initializeBaselineSync();
            echo "  ✓ Baseline established: {$baseResult['indexed']} historical message(s) indexed. Auto-replies strictly ignored for pre-existing emails.\n";
            continue;
        }

        $engine = new AutomationEngine($account);

        // Fetch new incoming messages via History API delta (with auto-recovery & baseline protection)
        $newMessages = $gmailService->fetchNewIncomingMessages(50);

        // In test environments or fallback mode, check inbox with AutomationEngine baseline filters
        if (empty($newMessages) && (config('app.env') === 'testing' || getenv('APP_ENV') === 'testing')) {
            $rawList = $gmailService->listInboxMessages(50, 'label:INBOX');
            foreach ($rawList as $msgItem) {
                $msgId = is_object($msgItem) ? $msgItem->getId() : ($msgItem['id'] ?? null);
                if (!$msgId || \App\Models\EmailMessage::findByAccountAndMessageId($account->id, $msgId)) continue;
                $mD = $gmailService->getMessage($msgId);
                if ($mD) $newMessages[] = $mD;
            }
        }

        echo "  ↳ Found " . count($newMessages) . " incoming message(s) to inspect.\n";

        foreach ($newMessages as $msgData) {
            $msgId = $msgData['message_id'] ?? $msgData['id'] ?? 'unknown';
            $result = $engine->processIncomingMessage($msgData);
            $reasonInfo = !empty($result['reason']) ? " (Reason: {$result['reason']})" : "";
            echo "  ↳ Processed message {$msgId}: Result = {$result['status']}{$reasonInfo}\n";
        }

        // Update last sync timestamp
        $account->update([
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        echo "  ✗ Error syncing {$account->gmail_email}: {$errorMsg}\n";

        // If rate limit or quota exceeded, place account in cooldown
        if (str_contains($errorMsg, 'Rate Limit') || str_contains($errorMsg, 'Quota') || str_contains($errorMsg, '429') || str_contains($errorMsg, 'userRateLimitExceeded') || str_contains($errorMsg, 'rateLimitExceeded')) {
            $account->markTemporaryFailure(10);
            echo "  ↳ Rate limit hit. Account {$account->gmail_email} placed on 10m cooldown.\n";
        }

        $account->update([
            'last_error' => "Sync error: {$errorMsg}",
        ]);
        logger("Sync error for account {$account->gmail_email}: {$errorMsg}", 'error', $account->user_id, $account->id);
    }
}

// 3. Process any pending queue jobs ready for sending
echo "[{$timestamp}] Triggering queue worker batch...\n";
$worker = new QueueWorker();
$worker->run(true, 25);

// 4. Process bulk email campaigns
echo "[{$timestamp}] Triggering bulk email campaign engine...\n";
$sentCount = \App\Services\CampaignEngine::processBatch(25);
echo "  ↳ Campaign Engine sent {$sentCount} campaign email(s).\n";

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Cron run finished in {$elapsed}s.\n";

