<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\EmailTemplate;
use App\Models\EmailJob;
use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Services\MailService;

class AdminController {
    public function index(): string {
        $totalUsers = (int)(Database::first("SELECT COUNT(*) as c FROM users")['c'] ?? 0);
        $totalAccounts = (int)(Database::first("SELECT COUNT(*) as c FROM gmail_accounts")['c'] ?? 0);
        $activeAccounts = (int)(Database::first("SELECT COUNT(*) as c FROM gmail_accounts WHERE status = 'connected'")['c'] ?? 0);
        
        $totalThreads = (int)(Database::first("SELECT COUNT(*) as c FROM email_threads")['c'] ?? 0);
        $totalReplies = (int)(Database::first("SELECT SUM(reply_count) as c FROM daily_usage")['c'] ?? 0);
        $totalFollowups = (int)(Database::first("SELECT SUM(followup_count) as c FROM daily_usage")['c'] ?? 0);
        
        $pendingJobs = (int)(Database::first("SELECT COUNT(*) as c FROM scheduled_jobs WHERE status = 'pending'")['c'] ?? 0);
        $failedJobs = (int)(Database::first("SELECT COUNT(*) as c FROM scheduled_jobs WHERE status = 'failed'")['c'] ?? 0);

        $totalRevenue = (float)(Database::first("SELECT SUM(amount) as s FROM payments WHERE status = 'paid'")['s'] ?? 0);
        $activeSubscriptions = (int)(Database::first("SELECT COUNT(*) as c FROM users WHERE subscription_status = 'active'")['c'] ?? 0);
        $activeTrials = (int)(Database::first("SELECT COUNT(*) as c FROM users WHERE trial_status = 'active'")['c'] ?? 0);

        $recentLogs = ActivityLog::getLatest(20);
        $recentPayments = Payment::all(10);
        $globalAutomation = SystemSetting::get('global_automation_enabled', '1');
        $cronLastRun = SystemSetting::get('cron_last_run', 'Never');

        return View::render('admin/index', [
            'totalUsers' => $totalUsers,
            'totalAccounts' => $totalAccounts,
            'activeAccounts' => $activeAccounts,
            'totalThreads' => $totalThreads,
            'totalReplies' => $totalReplies,
            'totalFollowups' => $totalFollowups,
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'totalRevenue' => $totalRevenue,
            'activeSubscriptions' => $activeSubscriptions,
            'activeTrials' => $activeTrials,
            'recentLogs' => $recentLogs,
            'recentPayments' => $recentPayments,
            'globalAutomation' => $globalAutomation,
            'cronLastRun' => $cronLastRun,
        ]);
    }

    public function users(): string {
        $users = User::all();
        $plans = Plan::all();
        return View::render('admin/users', ['users' => $users, 'plans' => $plans]);
    }

    public function createUser(Request $request): void {
        $name = trim($request->input('name', ''));
        $email = strtolower(trim($request->input('email', '')));
        $password = $request->input('password', '');
        $role = $request->input('role', 'user');
        $status = $request->input('status', 'active');
        $planId = (int)$request->input('plan_id', 0);

        if (empty($name) || strlen($name) < 2) {
            flash('error', 'Please enter a valid user name.');
            redirect('/admin/users');
            return;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('/admin/users');
            return;
        }

        if (empty($password) || strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            redirect('/admin/users');
            return;
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            flash('error', "A user with email {$email} already exists.");
            redirect('/admin/users');
            return;
        }

        $plan = $planId ? Plan::find($planId) : null;

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => in_array($role, ['user', 'admin']) ? $role : 'user',
            'status' => in_array($status, ['active', 'suspended']) ? $status : 'active',
            'plan_id' => $plan ? $plan->id : null,
            'plan_type' => $plan ? $plan->slug : 'free',
            'subscription_status' => $plan ? 'active' : 'inactive',
            'gmail_limit' => $plan ? $plan->gmail_limit : 1,
            'subscription_started_at' => $plan ? date('Y-m-d H:i:s') : null,
            'subscription_expires_at' => $plan ? date('Y-m-d H:i:s', strtotime('+1 month')) : null,
        ]);

        logger("Admin created user account: {$email}", 'info', Auth::id());
        flash('success', "User [{$name}] created successfully!");
        redirect('/admin/users');
    }

    public function updateUser(Request $request, int $id): void {
        $user = User::find($id);
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            return;
        }

        $name = trim($request->input('name', ''));
        $email = strtolower(trim($request->input('email', '')));
        $password = $request->input('password', '');
        $role = $request->input('role', $user->role);
        $status = $request->input('status', $user->status);
        $planId = (int)$request->input('plan_id', 0);
        $gmailLimit = (int)$request->input('gmail_limit', $user->gmail_limit);
        $subscriptionStatus = $request->input('subscription_status', $user->subscription_status);
        $trialStatus = $request->input('trial_status', $user->trial_status);
        $resetTrial = $request->input('reset_trial', '0') === '1';

        $plan = $planId ? Plan::find($planId) : null;

        $dataToUpdate = [
            'name' => $name,
            'email' => $email,
            'role' => ($user->id === Auth::id()) ? 'admin' : (in_array($role, ['user', 'admin']) ? $role : 'user'),
            'status' => ($user->id === Auth::id()) ? 'active' : (in_array($status, ['active', 'suspended']) ? $status : 'active'),
            'plan_id' => $plan ? $plan->id : null,
            'plan_type' => $plan ? $plan->slug : 'free',
            'subscription_status' => $subscriptionStatus,
            'gmail_limit' => $plan ? $plan->gmail_limit : max($gmailLimit, 1),
            'trial_status' => $trialStatus,
        ];

        if ($resetTrial) {
            $dataToUpdate['trial_used'] = 0;
            $dataToUpdate['trial_status'] = 'not_started';
            $dataToUpdate['trial_started_at'] = null;
            $dataToUpdate['trial_ends_at'] = null;
        }

        if (!empty($password)) {
            $dataToUpdate['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $user->update($dataToUpdate);
        logger("Admin updated user ID #{$user->id} subscription & settings", 'info', Auth::id());
        flash('success', "User [{$name}] updated successfully!");
        redirect('/admin/users');
    }

    public function deleteUser(Request $request, int $id): void {
        $user = User::find($id);
        if (!$user || $user->id === Auth::id()) {
            flash('error', 'Cannot delete this user.');
            redirect('/admin/users');
            return;
        }

        $user->delete();
        flash('success', 'User and all related accounts deleted.');
        redirect('/admin/users');
    }

    public function toggleUserStatus(Request $request, int $id): void {
        $user = User::find($id);
        if (!$user || $user->id === Auth::id()) {
            flash('error', 'Cannot change status of this user.');
            redirect('/admin/users');
            return;
        }

        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);
        flash('success', "User status updated to {$newStatus}.");
        redirect('/admin/users');
    }

    // --- SMTP Settings ---
    public function smtp(): string {
        $config = MailService::getConfig();
        return View::render('admin/smtp', ['config' => $config]);
    }

    public function updateSmtp(Request $request): void {
        SystemSetting::set('smtp_enabled', $request->input('smtp_enabled') ? '1' : '0');
        SystemSetting::set('smtp_host', trim($request->input('smtp_host', '')));
        SystemSetting::set('smtp_port', trim($request->input('smtp_port', '587')));
        SystemSetting::set('smtp_username', trim($request->input('smtp_username', '')));
        
        $password = $request->input('smtp_password');
        if (!empty($password)) {
            SystemSetting::set('smtp_password', $password);
        }

        SystemSetting::set('smtp_encryption', $request->input('smtp_encryption', 'tls'));
        SystemSetting::set('smtp_from_name', trim($request->input('smtp_from_name', 'Gmail Automation')));
        SystemSetting::set('smtp_from_email', trim($request->input('smtp_from_email', 'support@2xbets.net')));

        logger("Admin updated SMTP Server settings", 'info', Auth::id());
        flash('success', 'SMTP server settings saved successfully.');
        redirect('/admin/smtp');
    }

    public function testSmtpConnection(Request $request): void {
        $host = trim($request->input('smtp_host', ''));
        $port = trim($request->input('smtp_port', ''));
        $username = trim($request->input('smtp_username', ''));
        $password = $request->input('smtp_password');
        $encryption = $request->input('smtp_encryption', 'tls');

        $cfg = MailService::getConfig();
        if (!empty($host)) {
            $cfg['host'] = $host;
            $cfg['port'] = (int)$port;
            $cfg['username'] = $username;
            if (!empty($password)) {
                $cfg['password'] = $password;
            }
            $cfg['encryption'] = $encryption;
        }

        $testResult = MailService::testConnection($cfg);
        if ($testResult['success']) {
            flash('success', $testResult['message']);
        } else {
            flash('error', $testResult['message']);
        }

        redirect('/admin/smtp');
    }

    public function testSmtp(Request $request): void {
        $testEmail = trim($request->input('test_email', ''));
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid recipient email for test message.');
            redirect('/admin/smtp');
            return;
        }

        $cfg = MailService::getConfig();
        $testResult = MailService::testConnection($cfg);

        if (!$testResult['success']) {
            flash('error', $testResult['message']);
            redirect('/admin/smtp');
            return;
        }

        try {
            $html = "<h3>SMTP Test Connection Successful</h3><p>This is a test notification email sent from your <strong>Gmail Automation SaaS Platform</strong>.</p><p>Time: " . date('Y-m-d H:i:s') . "</p>";
            MailService::send($testEmail, "SMTP Connection Test - Gmail Automation", $html, "Test Recipient", $cfg);
            flash('success', "SMTP Connection verified and test email sent to {$testEmail} successfully!");
        } catch (\Throwable $e) {
            flash('error', 'SMTP Connection succeeded, but sending test email failed: ' . $e->getMessage());
        }

        redirect('/admin/smtp');
    }

    // --- Trial Settings ---
    public function trial(): string {
        $enabled = (bool)(int)SystemSetting::get('trial_enabled', '1');
        $duration = (int)SystemSetting::get('trial_duration_days', '14');
        $limit = (int)SystemSetting::get('trial_gmail_limit', '5');
        $onePerUser = (bool)(int)SystemSetting::get('trial_one_per_user', '1');

        return View::render('admin/trial', [
            'trialEnabled' => $enabled,
            'trialDuration' => $duration,
            'trialLimit' => $limit,
            'trialOnePerUser' => $onePerUser,
        ]);
    }

    public function updateTrial(Request $request): void {
        $enabled = $request->input('trial_enabled') ? '1' : '0';
        $duration = max(1, (int)$request->input('trial_duration_days', 14));
        $limit = max(1, (int)$request->input('trial_gmail_limit', 5));
        $onePerUser = $request->input('trial_one_per_user') ? '1' : '0';

        SystemSetting::set('trial_enabled', $enabled);
        SystemSetting::set('trial_duration_days', (string)$duration);
        SystemSetting::set('trial_gmail_limit', (string)$limit);
        SystemSetting::set('trial_one_per_user', $onePerUser);

        logger("Admin updated Free Trial parameters ({$duration} days, {$limit} accounts)", 'info', Auth::id());
        flash('success', 'Free trial configuration updated successfully.');
        redirect('/admin/trial');
    }

    // --- Subscription Plans ---
    public function plans(): string {
        $plans = Plan::all();
        return View::render('admin/plans', ['plans' => $plans]);
    }

    public function updatePlan(Request $request, int $id): void {
        $plan = Plan::find($id);
        if (!$plan) {
            flash('error', 'Plan not found.');
            redirect('/admin/plans');
            return;
        }

        $name = trim($request->input('name', $plan->name));
        $price = (float)$request->input('price', $plan->price);
        $period = $request->input('billing_period', $plan->billing_period);
        $gmailLimit = (int)$request->input('gmail_limit', $plan->gmail_limit);
        $stripePriceId = trim($request->input('stripe_price_id', ''));
        $isPopular = $request->input('is_popular') ? 1 : 0;
        $isActive = $request->input('is_active') ? 1 : 0;
        $featuresText = trim($request->input('features', ''));

        $featuresArray = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $featuresText))));

        $plan->update([
            'name' => $name,
            'price' => $price,
            'billing_period' => $period,
            'gmail_limit' => $gmailLimit,
            'stripe_price_id' => $stripePriceId ?: null,
            'is_popular' => $isPopular,
            'is_active' => $isActive,
            'features' => json_encode($featuresArray, JSON_UNESCAPED_UNICODE),
        ]);

        logger("Admin updated subscription plan {$plan->slug} ($ {$price})", 'info', Auth::id());
        flash('success', "Plan [{$name}] updated successfully!");
        redirect('/admin/plans');
    }

    // --- Email Templates ---
    public function emailTemplates(): string {
        $templates = EmailTemplate::all();
        return View::render('admin/email_templates', ['templates' => $templates]);
    }

    public function updateEmailTemplate(Request $request, int $id): void {
        $tpl = EmailTemplate::find($id);
        if (!$tpl) {
            flash('error', 'Template not found.');
            redirect('/admin/email-templates');
            return;
        }

        $subject = trim($request->input('subject', $tpl->subject));
        $body = $request->input('body', $tpl->body);
        $isEnabled = $request->input('is_enabled') ? 1 : 0;

        $tpl->update([
            'subject' => $subject,
            'body' => $body,
            'is_enabled' => $isEnabled,
        ]);

        logger("Admin updated email template {$tpl->slug}", 'info', Auth::id());
        flash('success', "Email Template [{$tpl->name}] updated successfully!");
        redirect('/admin/email-templates');
    }

    // --- Payment Gateways (Stripe, bKash, Nagad) ---
    public function gateways(): string {
        $stripeEnabled = (bool)(int)SystemSetting::get('stripe_enabled', '1');
        $stripePubKey = SystemSetting::get('stripe_publishable_key', '');
        $stripeSecKey = SystemSetting::get('stripe_secret_key', '');
        $stripeWebhook = SystemSetting::get('stripe_webhook_secret', '');

        // bKash
        $bkashEnabled = (bool)(int)SystemSetting::get('bkash_enabled', '1');
        $bkashType = SystemSetting::get('bkash_type', 'manual_number');
        $bkashNumber = SystemSetting::get('bkash_number', '01611195794');
        $bkashAccType = SystemSetting::get('bkash_account_type', 'Personal');
        $bkashRate = SystemSetting::get('bkash_exchange_rate', '120');
        $bkashInstructions = SystemSetting::get('bkash_instructions', 'Send Money to bKash Personal Number: 01611195794. Enter your Sender Phone Number & TrxID below to submit verification.');
        $bkashAppKey = SystemSetting::get('bkash_app_key', '');
        $bkashAppSecret = SystemSetting::get('bkash_app_secret', '');
        $bkashUsername = SystemSetting::get('bkash_username', '');
        $bkashPassword = SystemSetting::get('bkash_password', '');

        // Nagad
        $nagadEnabled = (bool)(int)SystemSetting::get('nagad_enabled', '1');
        $nagadType = SystemSetting::get('nagad_type', 'manual_number');
        $nagadNumber = SystemSetting::get('nagad_number', '01611195794');
        $nagadAccType = SystemSetting::get('nagad_account_type', 'Personal');
        $nagadRate = SystemSetting::get('nagad_exchange_rate', '120');
        $nagadInstructions = SystemSetting::get('nagad_instructions', 'Send Money to Nagad Personal Number: 01611195794. Enter your Sender Phone Number & TrxID below to submit verification.');
        $nagadMerchantId = SystemSetting::get('nagad_merchant_id', '');
        $nagadPublicKey = SystemSetting::get('nagad_public_key', '');
        $nagadPrivateKey = SystemSetting::get('nagad_private_key', '');

        return View::render('admin/gateways', [
            'stripe' => [
                'enabled' => $stripeEnabled,
                'publishable_key' => $stripePubKey,
                'secret_key' => $stripeSecKey,
                'webhook_secret' => $stripeWebhook,
            ],
            'bkash' => [
                'enabled' => $bkashEnabled,
                'type' => $bkashType,
                'number' => $bkashNumber,
                'account_type' => $bkashAccType,
                'rate' => $bkashRate,
                'instructions' => $bkashInstructions,
                'app_key' => $bkashAppKey,
                'app_secret' => $bkashAppSecret,
                'username' => $bkashUsername,
                'password' => $bkashPassword,
            ],
            'nagad' => [
                'enabled' => $nagadEnabled,
                'type' => $nagadType,
                'number' => $nagadNumber,
                'account_type' => $nagadAccType,
                'rate' => $nagadRate,
                'instructions' => $nagadInstructions,
                'merchant_id' => $nagadMerchantId,
                'public_key' => $nagadPublicKey,
                'private_key' => $nagadPrivateKey,
            ],
        ]);
    }

    public function updateGateways(Request $request): void {
        // Stripe
        SystemSetting::set('stripe_enabled', $request->input('stripe_enabled') ? '1' : '0');
        SystemSetting::set('stripe_publishable_key', trim($request->input('stripe_publishable_key', '')));
        $secKey = $request->input('stripe_secret_key');
        if (!empty($secKey)) {
            SystemSetting::set('stripe_secret_key', trim($secKey));
        }
        $whSec = $request->input('stripe_webhook_secret');
        if (!empty($whSec)) {
            SystemSetting::set('stripe_webhook_secret', trim($whSec));
        }

        // bKash
        SystemSetting::set('bkash_enabled', $request->input('bkash_enabled') ? '1' : '0');
        SystemSetting::set('bkash_type', $request->input('bkash_type', 'manual_number'));
        SystemSetting::set('bkash_number', trim($request->input('bkash_number', '01611195794')));
        SystemSetting::set('bkash_account_type', $request->input('bkash_account_type', 'Personal'));
        SystemSetting::set('bkash_exchange_rate', trim($request->input('bkash_exchange_rate', '120')));
        SystemSetting::set('bkash_instructions', trim($request->input('bkash_instructions', '')));
        SystemSetting::set('bkash_app_key', trim($request->input('bkash_app_key', '')));
        $bkashSec = $request->input('bkash_app_secret');
        if (!empty($bkashSec)) {
            SystemSetting::set('bkash_app_secret', trim($bkashSec));
        }
        SystemSetting::set('bkash_username', trim($request->input('bkash_username', '')));
        $bkashPass = $request->input('bkash_password');
        if (!empty($bkashPass)) {
            SystemSetting::set('bkash_password', trim($bkashPass));
        }

        // Nagad
        SystemSetting::set('nagad_enabled', $request->input('nagad_enabled') ? '1' : '0');
        SystemSetting::set('nagad_type', $request->input('nagad_type', 'manual_number'));
        SystemSetting::set('nagad_number', trim($request->input('nagad_number', '01611195794')));
        SystemSetting::set('nagad_account_type', $request->input('nagad_account_type', 'Personal'));
        SystemSetting::set('nagad_exchange_rate', trim($request->input('nagad_exchange_rate', '120')));
        SystemSetting::set('nagad_instructions', trim($request->input('nagad_instructions', '')));
        SystemSetting::set('nagad_merchant_id', trim($request->input('nagad_merchant_id', '')));
        $nagadPub = $request->input('nagad_public_key');
        if (!empty($nagadPub)) {
            SystemSetting::set('nagad_public_key', trim($nagadPub));
        }
        $nagadPriv = $request->input('nagad_private_key');
        if (!empty($nagadPriv)) {
            SystemSetting::set('nagad_private_key', trim($nagadPriv));
        }

        logger("Admin updated Payment Gateway settings (Stripe, bKash, Nagad)", 'info', Auth::id());
        flash('success', 'Payment gateway configurations saved successfully.');
        redirect('/admin/gateways');
    }

    // --- Payments & Billing History ---
    public function payments(): string {
        $payments = Payment::all(100);
        return View::render('admin/payments', ['payments' => $payments]);
    }

    public function approvePayment(Request $request, int $id): void {
        $payment = Payment::find($id);
        if (!$payment) {
            flash('error', 'Payment record not found.');
            redirect('/admin/payments');
            return;
        }

        $notes = trim($request->input('admin_notes', 'Manually verified and approved by Admin.'));
        $payment->approve(Auth::id(), $notes);

        flash('success', "Payment #{$payment->id} approved and subscription activated successfully!");
        redirect('/admin/payments');
    }

    public function rejectPayment(Request $request, int $id): void {
        $payment = Payment::find($id);
        if (!$payment) {
            flash('error', 'Payment record not found.');
            redirect('/admin/payments');
            return;
        }

        $reason = trim($request->input('admin_notes', 'Payment verification rejected.'));
        $payment->reject($reason);

        flash('warning', "Payment #{$payment->id} has been marked as rejected.");
        redirect('/admin/payments');
    }

    // --- General Settings ---
    public function settings(): string {
        $settings = SystemSetting::all();
        return View::render('admin/settings', ['settings' => $settings]);
    }

    public function updateSettings(Request $request): void {
        $clientId = trim($request->input('google_client_id', ''));
        $clientSecret = trim($request->input('google_client_secret', ''));
        $redirectUri = trim($request->input('google_redirect_uri', ''));
        $pubsubTopic = trim($request->input('google_pubsub_topic', ''));
        $pubsubToken = trim($request->input('google_pubsub_token', ''));
        $globalAutomation = $request->input('global_automation_enabled', '1');
        
        $stripePubKey = trim($request->input('stripe_publishable_key', ''));
        $stripeSecKey = trim($request->input('stripe_secret_key', ''));
        $stripeWebhook = trim($request->input('stripe_webhook_secret', ''));

        SystemSetting::set('google_client_id', $clientId);
        SystemSetting::set('google_client_secret', $clientSecret);
        SystemSetting::set('google_redirect_uri', $redirectUri);
        SystemSetting::set('google_pubsub_topic', $pubsubTopic);
        SystemSetting::set('google_pubsub_token', $pubsubToken);
        SystemSetting::set('global_automation_enabled', $globalAutomation ? '1' : '0');

        SystemSetting::set('stripe_publishable_key', $stripePubKey);
        if (!empty($stripeSecKey)) {
            SystemSetting::set('stripe_secret_key', $stripeSecKey);
        }
        if (!empty($stripeWebhook)) {
            SystemSetting::set('stripe_webhook_secret', $stripeWebhook);
        }

        flash('success', 'System, Google OAuth & Stripe settings saved successfully.');
        redirect('/admin/settings');
    }

    public function logs(): string {
        $logs = ActivityLog::getLatest(100);
        return View::render('admin/logs', ['logs' => $logs]);
    }

    public function filters(): string {
        $blacklistEmails = SystemSetting::get('blacklist_emails', '');
        $blacklistDomains = SystemSetting::get('blacklist_domains', '');
        $blacklistKeywords = SystemSetting::get('blacklist_keywords', '');

        return View::render('admin/filters', [
            'blacklistEmails' => $blacklistEmails,
            'blacklistDomains' => $blacklistDomains,
            'blacklistKeywords' => $blacklistKeywords,
        ]);
    }

    public function updateFilters(Request $request): void {
        $emails = trim($request->input('blacklist_emails', ''));
        $domains = trim($request->input('blacklist_domains', ''));
        $keywords = trim($request->input('blacklist_keywords', ''));

        SystemSetting::set('blacklist_emails', $emails);
        SystemSetting::set('blacklist_domains', $domains);
        SystemSetting::set('blacklist_keywords', $keywords);

        logger("Admin updated system-wide blacklist filters", 'info', Auth::id());
        flash('success', 'Blacklist filters and skip rules updated successfully!');
        redirect('/admin/filters');
    }

    public function toggleGlobalAutomation(Request $request): void {
        $current = SystemSetting::get('global_automation_enabled', '1');
        $new = $current === '1' ? '0' : '1';
        SystemSetting::set('global_automation_enabled', $new);
        flash('success', 'Global Automation ' . ($new === '1' ? 'Enabled' : 'Disabled') . ' system-wide.');
        redirect('/admin');
    }
}
