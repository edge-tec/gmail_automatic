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

// 2. Fetch all active connected Gmail accounts
$accounts = GmailAccount::allActive();
echo "[{$timestamp}] Found " . count($accounts) . " active Gmail account(s).\n";

foreach ($accounts as $account) {
    echo "Processing account: {$account->gmail_email} (ID: {$account->id})...\n";

    try {
        $gmailService = new GmailService($account);
        $engine = new AutomationEngine($account);

        // Fetch recent unread or inbox messages
        $messages = $gmailService->listInboxMessages(20, 'label:INBOX');
        echo "  ↳ Found " . count($messages) . " inbox message(s) to inspect.\n";

        foreach ($messages as $msgItem) {
            $msgId = is_object($msgItem) ? $msgItem->getId() : ($msgItem['id'] ?? null);
            if (!$msgId) continue;

            // Check if already processed in database
            $exists = \App\Models\EmailMessage::findByAccountAndMessageId($account->id, $msgId);
            if ($exists) {
                continue; // already recorded
            }

            // Fetch full message details
            $msgData = $gmailService->getMessage($msgId);
            if (!$msgData) continue;

            // Process message through Automation Engine
            $result = $engine->processIncomingMessage($msgData);
            echo "  ↳ Processed message {$msgId}: Result = {$result['status']}\n";
        }

        // Update last sync timestamp
        $account->update([
            'last_sync_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        echo "  ✗ Error syncing {$account->gmail_email}: {$errorMsg}\n";
        $account->update([
            'last_error' => "Sync error: {$errorMsg}",
        ]);
        logger("Sync error for account {$account->gmail_email}: {$errorMsg}", 'error', $account->user_id, $account->id);
    }
}

// 3. Process any pending queue jobs ready for sending
echo "[{$timestamp}] Triggering queue worker batch...\n";
$worker = new QueueWorker();
$worker->run(true, 50);

$elapsed = round(microtime(true) - $startTime, 2);
echo "[" . date('Y-m-d H:i:s') . "] Cron run finished in {$elapsed}s.\n";
