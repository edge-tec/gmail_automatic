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

    public function toggleGlobalAutomation(Request $request): void {
        $current = SystemSetting::get('global_automation_enabled', '1');
        $new = $current === '1' ? '0' : '1';
        SystemSetting::set('global_automation_enabled', $new);
        flash('success', 'Global Automation ' . ($new === '1' ? 'Enabled' : 'Disabled') . ' system-wide.');
        redirect('/admin');
    }
}
