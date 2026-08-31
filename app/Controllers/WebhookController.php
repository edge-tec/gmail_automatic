<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\GmailAccount;
use App\Models\SystemSetting;
use App\Services\GmailService;
use App\Services\AutomationEngine;

class WebhookController {
    /**
     * Handle Google Cloud Pub/Sub Webhook Push Notification
     */
    public function handlePubSub(Request $request): void {
        $token = $request->input('token');
        $expectedToken = SystemSetting::get('google_pubsub_token');

        if ($expectedToken && $token !== $expectedToken) {
            Response::json(['error' => 'Unauthorized token'], 403);
        }

        $input = $request->all();
        $message = $input['message'] ?? null;
        if (!$message || !isset($message['data'])) {
            Response::json(['status' => 'acknowledged_empty'], 200);
        }

        $decoded = base64_decode($message['data']);
        $payload = json_decode($decoded, true);

        if (!$payload || !isset($payload['emailAddress'])) {
            Response::json(['status' => 'invalid_payload'], 200);
        }

        $emailAddress = $payload['emailAddress'];
        $historyId = $payload['historyId'] ?? null;

        $account = GmailAccount::findByEmail($emailAddress);
        if (!$account || $account->status !== 'connected') {
            Response::json(['status' => 'account_not_found'], 200);
        }

        try {
            $service = new GmailService($account);
            $engine = new AutomationEngine($account);

            // Fetch recent inbox messages
            $messages = $service->listInboxMessages(10, 'label:INBOX');
            foreach ($messages as $msgItem) {
                $msgId = is_object($msgItem) ? $msgItem->getId() : ($msgItem['id'] ?? null);
                if (!$msgId) continue;

                $exists = \App\Models\EmailMessage::findByAccountAndMessageId($account->id, $msgId);
                if ($exists) continue;

                $msgData = $service->getMessage($msgId);
                if ($msgData) {
                    $engine->processIncomingMessage($msgData);
                }
            }

            $account->update([
                'history_id' => $historyId,
                'last_sync_at' => date('Y-m-d H:i:s'),
            ]);

            logger("Processed Pub/Sub push notification for {$emailAddress}", 'info', $account->user_id, $account->id);

        } catch (\Throwable $e) {
            logger("PubSub webhook error for {$emailAddress}: " . $e->getMessage(), 'error', $account->user_id, $account->id);
        }

        Response::json(['status' => 'success'], 200);
    }
}
