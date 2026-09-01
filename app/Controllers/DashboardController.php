<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\DailyUsage;
use App\Models\ActivityLog;

class DashboardController {
    public function index(): string {
        $user = Auth::user();
        $accounts = GmailAccount::findByUserId($user->id);
        $accountIds = array_map(fn($a) => $a->id, $accounts);

        // Stats
        $todayUsage = DailyUsage::getStatsForUser($user->id);

        $pendingJobsCount = 0;
        $failedJobsCount = 0;
        $totalThreadsCount = 0;

        if (!empty($accountIds)) {
            $inClause = implode(',', $accountIds);
            $pendingRow = Database::first("SELECT COUNT(*) as c FROM scheduled_jobs WHERE gmail_account_id IN ({$inClause}) AND status = 'pending'");
            $pendingJobsCount = (int)($pendingRow['c'] ?? 0);

            $failedRow = Database::first("SELECT COUNT(*) as c FROM scheduled_jobs WHERE gmail_account_id IN ({$inClause}) AND status = 'failed'");
            $failedJobsCount = (int)($failedRow['c'] ?? 0);

            $threadsRow = Database::first("SELECT COUNT(*) as c FROM email_threads WHERE gmail_account_id IN ({$inClause})");
            $totalThreadsCount = (int)($threadsRow['c'] ?? 0);
        }

        // Recent conversations
        $recentThreads = EmailThread::findByUserId($user->id, 10);

        // Recent activity logs
        $recentLogs = ActivityLog::getLatest(10, $user->id);

        $uniqueTrafficReplied = (int)(Database::first(
            "SELECT COUNT(*) as c FROM auto_reply_recipients WHERE user_id = :uid AND reply_status = 'replied'",
            ['uid' => $user->id]
        )['c'] ?? 0);

        $duplicateSkippedCount = (int)(Database::first(
            "SELECT COUNT(*) as c FROM activity_logs WHERE user_id = :uid AND (message LIKE '%Duplicate%' OR message LIKE '%duplicate%')",
            ['uid' => $user->id]
        )['c'] ?? 0);

        return View::render('dashboard/index', [
            'user' => $user,
            'accounts' => $accounts,
            'todayUsage' => $todayUsage,
            'pendingJobsCount' => $pendingJobsCount,
            'failedJobsCount' => $failedJobsCount,
            'totalThreadsCount' => $totalThreadsCount,
            'recentThreads' => $recentThreads,
            'recentLogs' => $recentLogs,
            'uniqueTrafficReplied' => $uniqueTrafficReplied,
            'duplicateSkippedCount' => $duplicateSkippedCount,
        ]);
    }
}
