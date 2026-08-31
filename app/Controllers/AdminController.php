<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Models\ActivityLog;
use App\Models\SystemSetting;

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

        $recentLogs = ActivityLog::getLatest(20);
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
            'recentLogs' => $recentLogs,
            'globalAutomation' => $globalAutomation,
            'cronLastRun' => $cronLastRun,
        ]);
    }

    public function users(): string {
        $users = User::all();
        return View::render('admin/users', ['users' => $users]);
    }

    public function createUser(Request $request): void {
        $name = trim($request->input('name', ''));
        $email = strtolower(trim($request->input('email', '')));
        $password = $request->input('password', '');
        $role = $request->input('role', 'user');
        $status = $request->input('status', 'active');

        if (empty($name) || strlen($name) < 2) {
            flash('error', 'Please enter a valid user name (at least 2 characters).');
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

        if (!in_array($role, ['user', 'admin'])) {
            $role = 'user';
        }

        if (!in_array($status, ['active', 'suspended'])) {
            $status = 'active';
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            flash('error', "A user with email {$email} already exists.");
            redirect('/admin/users');
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role,
            'status' => $status,
        ]);

        logger("Admin created new user account: {$email} (Role: {$role})", 'info', Auth::id());
        flash('success', "User [{$name}] ({$email}) created successfully!");
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

        if (empty($name) || strlen($name) < 2) {
            flash('error', 'Please enter a valid name.');
            redirect('/admin/users');
            return;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('/admin/users');
            return;
        }

        // Email uniqueness check if changed
        if ($email !== strtolower($user->email)) {
            $existing = User::findByEmail($email);
            if ($existing) {
                flash('error', "Email {$email} is already in use by another account.");
                redirect('/admin/users');
                return;
            }
        }

        // Protect current admin from revoking own admin or suspending self
        if ($user->id === Auth::id()) {
            $role = 'admin';
            $status = 'active';
        }

        $dataToUpdate = [
            'name' => $name,
            'email' => $email,
            'role' => in_array($role, ['user', 'admin']) ? $role : 'user',
            'status' => in_array($status, ['active', 'suspended']) ? $status : 'active',
        ];

        // If password is provided, change it
        if (!empty($password)) {
            if (strlen($password) < 6) {
                flash('error', 'New password must be at least 6 characters.');
                redirect('/admin/users');
                return;
            }
            $dataToUpdate['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $user->update($dataToUpdate);
        logger("Admin updated user account ID: {$user->id} ({$email})", 'info', Auth::id());
        flash('success', "User [{$name}] updated successfully!");
        redirect('/admin/users');
    }

    public function deleteUser(Request $request, int $id): void {
        $user = User::find($id);
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            return;
        }

        if ($user->id === Auth::id()) {
            flash('error', 'You cannot delete your own admin account.');
            redirect('/admin/users');
            return;
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        logger("Admin deleted user account: {$email}", 'warning', Auth::id());
        flash('success', "User [{$name}] ({$email}) and all related data have been deleted.");
        redirect('/admin/users');
    }

    public function toggleUserStatus(Request $request, int $id): void {
        $user = User::find($id);
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            return;
        }

        if ($user->id === Auth::id()) {
            flash('error', 'Cannot suspend your own admin account.');
            redirect('/admin/users');
            return;
        }

        $newStatus = $user->status === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);
        flash('success', "User [{$user->name}] status changed to {$newStatus}.");
        redirect('/admin/users');
    }

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

        SystemSetting::set('google_client_id', $clientId);
        SystemSetting::set('google_client_secret', $clientSecret);
        SystemSetting::set('google_redirect_uri', $redirectUri);
        SystemSetting::set('google_pubsub_topic', $pubsubTopic);
        SystemSetting::set('google_pubsub_token', $pubsubToken);
        SystemSetting::set('global_automation_enabled', $globalAutomation ? '1' : '0');

        flash('success', 'System and Google OAuth settings saved successfully.');
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

        logger("Admin updated system-wide blacklist filters (Emails, Domains, Keywords)", 'info', Auth::id());
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
