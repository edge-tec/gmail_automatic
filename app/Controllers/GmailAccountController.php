<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\SystemSetting;
use App\Services\GmailService;
use Exception;

class GmailAccountController {
    public function index(): string {
        $user = Auth::user();
        $accounts = GmailAccount::findByUserId($user->id);

        $accountsData = [];
        foreach ($accounts as $acc) {
            $settings = $acc->getSettings();
            $usage = $acc->getTodayUsage();
            $accountsData[] = [
                'account' => $acc,
                'settings' => $settings,
                'usage' => $usage,
            ];
        }

        return View::render('accounts/index', [
            'accountsData' => $accountsData,
        ]);
    }

    public function connect(): void {
        $clientId = SystemSetting::get('google_client_id') ?: config('google.client_id', '');
        $clientSecret = SystemSetting::get('google_client_secret') ?: config('google.client_secret', '');

        if (empty($clientId) || empty($clientSecret)) {
            flash('error', 'Google OAuth credentials are not configured yet. Please configure them in Admin Settings or .env file.');
            redirect('/accounts');
            return;
        }

        $service = new GmailService();
        $state = bin2hex(random_bytes(16));
        Session::set('oauth_state', $state);

        $authUrl = $service->getAuthUrl($state);
        redirect($authUrl);
    }

    public function callback(Request $request): void {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');

        if ($error) {
            flash('error', 'Google Authentication was cancelled or denied: ' . htmlspecialchars($error));
            redirect('/accounts');
            return;
        }

        $savedState = Session::get('oauth_state');
        if (!$savedState || !hash_equals($savedState, (string)$state)) {
            flash('error', 'OAuth state verification failed. Please try again.');
            redirect('/accounts');
            return;
        }
        Session::remove('oauth_state');

        if (!$code) {
            flash('error', 'Missing authorization code from Google.');
            redirect('/accounts');
            return;
        }

        try {
            $service = new GmailService();
            $tokenData = $service->handleCallback($code);

            $user = Auth::user();

            $account = GmailAccount::createOrUpdate([
                'user_id' => $user->id,
                'gmail_email' => $tokenData['email'],
                'google_user_id' => $tokenData['google_user_id'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_expires_at' => $tokenData['token_expires_at'],
            ]);

            logger("Connected Gmail Account: {$tokenData['email']}", 'success', $user->id, $account->id);

            flash('success', "Gmail account [{$tokenData['email']}] connected successfully!");
            redirect('/accounts');

        } catch (\Throwable $e) {
            flash('error', 'Failed to connect Gmail account: ' . $e->getMessage());
            logger("Failed to connect Gmail: " . $e->getMessage(), 'error', Auth::id());
            redirect('/accounts');
        }
    }

    public function disconnect(Request $request, int $id): void {
        $account = GmailAccount::find($id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Account not found.');
            redirect('/accounts');
            return;
        }

        try {
            $service = new GmailService($account);
            $service->disconnect();
        } catch (\Throwable $e) {
            // continue deletion
        }

        $email = $account->gmail_email;
        $account->delete();

        logger("Disconnected Gmail Account: {$email}", 'info', Auth::id());
        flash('success', "Gmail account [{$email}] has been disconnected and removed.");
        redirect('/accounts');
    }

    public function toggleAutoReply(Request $request, int $id): void {
        $account = GmailAccount::find($id);
        if (!$account || $account->user_id !== Auth::id()) {
            Response::json(['error' => 'Account not found'], 404);
        }

        $settings = $account->getSettings();
        if ($settings) {
            $newState = !$settings->auto_reply_enabled;
            $settings->update(['auto_reply_enabled' => $newState ? 1 : 0]);
            if (!$newState) {
                \App\Models\ScheduledJob::cancelPendingJobsByAccountAndType($account->id, 'auto_reply', 'Auto reply toggle turned off');
            }
            flash('success', "Auto Reply " . ($newState ? "Enabled" : "Disabled") . " for {$account->gmail_email}");
        }

        redirect('/accounts');
    }

    public function toggleFollowup(Request $request, int $id): void {
        $account = GmailAccount::find($id);
        if (!$account || $account->user_id !== Auth::id()) {
            Response::json(['error' => 'Account not found'], 404);
        }

        $settings = $account->getSettings();
        if ($settings) {
            $newState = !$settings->followup_enabled;
            $settings->update(['followup_enabled' => $newState ? 1 : 0]);
            if (!$newState) {
                \App\Models\ScheduledJob::cancelPendingJobsByAccountAndType($account->id, 'follow_up', 'Follow-up toggle turned off');
            }
            flash('success', "Follow-up Automation " . ($newState ? "Enabled" : "Disabled") . " for {$account->gmail_email}");
        }

        redirect('/accounts');
    }

    public function syncNow(Request $request, int $id): void {
        $account = GmailAccount::find($id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Account not found.');
            redirect('/accounts');
            return;
        }

        try {
            $service = new GmailService($account);
            $engine = new \App\Services\AutomationEngine($account);

            $messages = $service->listInboxMessages(15, 'label:INBOX');
            $newCount = 0;

            foreach ($messages as $msgItem) {
                $msgId = is_object($msgItem) ? $msgItem->getId() : ($msgItem['id'] ?? null);
                if (!$msgId) continue;

                $exists = \App\Models\EmailMessage::findByAccountAndMessageId($account->id, $msgId);
                if ($exists) continue;

                $msgData = $service->getMessage($msgId);
                if ($msgData) {
                    $engine->processIncomingMessage($msgData);
                    $newCount++;
                }
            }

            $account->update([
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);

            flash('success', "Sync complete! Found and processed {$newCount} new email(s).");

        } catch (\Throwable $e) {
            flash('error', "Sync error: " . $e->getMessage());
            $account->update(['last_error' => $e->getMessage()]);
        }

        redirect('/accounts');
    }
}
