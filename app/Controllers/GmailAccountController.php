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
        $user = Auth::user();
        $connectedCount = count(GmailAccount::findByUserId($user->id));
        $maxAllowed = $user->getMaxGmailAccounts();

        if ($connectedCount >= $maxAllowed) {
            flash('warning', "You have reached your limit of {$maxAllowed} connected Gmail account(s) for your current plan. Please upgrade your subscription to connect more accounts.");
            redirect('/billing');
            return;
        }

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

            $grantedScopes = $tokenData['raw_token']['scope'] ?? '';
            $hasMailboxAccess = str_contains($grantedScopes, 'mail.google.com')
                || str_contains($grantedScopes, 'gmail.modify')
                || str_contains($grantedScopes, 'gmail.readonly');

            $status = 'connected';
            $lastError = null;
            if (!$hasMailboxAccess && !empty($grantedScopes)) {
                $status = 'needs_reauth';
                $lastError = 'Action Required: Gmail permissions were not granted during Google sign-in. Please reconnect and check all Gmail permission checkboxes on Google\'s consent screen.';
            }

            $account = GmailAccount::createOrUpdate([
                'user_id' => $user->id,
                'gmail_email' => $tokenData['email'],
                'google_user_id' => $tokenData['google_user_id'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_expires_at' => $tokenData['token_expires_at'],
                'status' => $status,
                'last_error' => $lastError,
            ]);

            logger("Connected Gmail Account: {$tokenData['email']} (Status: {$status})", 'success', $user->id, $account->id);

            if ($status === 'connected') {
                try {
                    $service = new GmailService($account);
                    $service->initializeBaselineSync();
                } catch (\Throwable $baseEx) {
                    logger("Initial baseline sync error: " . $baseEx->getMessage(), 'warning', $user->id, $account->id);
                }
            }

            if ($status === 'needs_reauth') {
                flash('warning', "Gmail account [{$tokenData['email']}] connected, but Gmail mailbox permissions were unchecked during sign-in. Please click 'Reconnect & Grant Permissions' and check all permission checkboxes on Google's consent screen.");
            } else {
                flash('success', "Gmail account [{$tokenData['email']}] connected successfully! Existing inbox messages indexed as historical baseline. Auto-replies will trigger on new incoming emails.");
            }
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

            if ($account->initial_sync_completed === 0) {
                $baseResult = $service->initializeBaselineSync();
                flash('success', "Initial baseline established! Indexed {$baseResult['indexed']} existing inbox email(s) as historical. Auto-replies will now run for new incoming emails.");
                redirect('/accounts');
                return;
            }

            $engine = new \App\Services\AutomationEngine($account);

            $messages = $service->fetchNewIncomingMessages(20);
            if (empty($messages)) {
                $rawList = $service->listInboxMessages(15, 'label:INBOX');
                foreach ($rawList as $msgItem) {
                    $msgId = is_object($msgItem) ? $msgItem->getId() : ($msgItem['id'] ?? null);
                    if (!$msgId || \App\Models\EmailMessage::findByAccountAndMessageId($account->id, $msgId)) continue;
                    $mD = $service->getMessage($msgId);
                    if ($mD) $messages[] = $mD;
                }
            }

            $newCount = 0;

            foreach ($messages as $msgData) {
                $res = $engine->processIncomingMessage($msgData);
                if ($res['status'] !== 'skipped' || ($res['reason'] ?? '') !== 'Historical email received before Gmail account connection') {
                    $newCount++;
                }
            }

            $account->update([
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);

            // Immediately execute any scheduled jobs that are ready
            try {
                $worker = new \App\Services\QueueWorker();
                $worker->run(true, 50);
            } catch (\Throwable $workerEx) {
                // Log worker exception without crashing sync response
                logger("Queue execution after sync: " . $workerEx->getMessage(), 'warning', $account->user_id, $account->id);
            }

            flash('success', "Sync complete! Found and processed {$newCount} new email(s).");

        } catch (\Throwable $e) {
            flash('error', "Sync error: " . $e->getMessage());
            $account->update(['last_error' => $e->getMessage()]);
        }

        redirect('/accounts');
    }
}
