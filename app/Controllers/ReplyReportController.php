<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Core\Database;
use App\Models\GmailAccount;
use App\Models\DailyUsage;
use App\Models\ScheduledJob;

class ReplyReportController {
    /**
     * Render comprehensive Reply & Follow-up Report with last 7 days default
     */
    public function index(Request $request): string {
        $user = Auth::user();

        // 1. Parse Filters
        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;
        $type = $request->input('type', 'all'); // all, auto_reply, follow_up
        $status = $request->input('status', 'all'); // all, completed, pending, failed, cancelled
        $dateRange = $request->input('date_range', '7days'); // default: 7days as requested
        $search = trim((string)$request->input('search', ''));
        $page = max(1, (int)$request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        // Calculate Date Range Bounds
        [$startDate, $endDate, $rangeLabel] = $this->calculateDateBounds($dateRange, $request->input('start_date'), $request->input('end_date'));

        $filters = [
            'account_id' => $accountId,
            'type' => $type,
            'status' => $status,
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'range_label' => $rangeLabel,
            'search' => $search,
        ];

        // 2. Fetch User Accounts
        $accounts = GmailAccount::findByUserId($user->id);
        if (empty($accounts) && $user->role === 'admin') {
            $accounts = GmailAccount::allActive();
        }
        $accountIds = array_map(fn($a) => (int)$a->id, $accounts);

        if (empty($accountIds)) {
            return View::render('reports/replies', [
                'accounts' => [],
                'logs' => [],
                'stats' => [
                    'range_replies' => 0,
                    'range_followups' => 0,
                    'range_total' => 0,
                    'all_replies' => 0,
                    'all_followups' => 0,
                    'all_total' => 0,
                    'pending_count' => 0,
                    'unique_leads' => 0,
                ],
                'dailyBreakdown' => [],
                'filters' => $filters,
                'currentPage' => 1,
                'totalPages' => 1,
                'totalItems' => 0,
            ]);
        }

        // 3. Calculate Summary Stats (Both active range & all time)
        $stats = $this->calculateSummaryStats($user->id, $accountIds, $accountId, $startDate, $endDate);

        // 4. Calculate 7-Day Day-by-Day Breakdown
        $dailyBreakdown = $this->calculateDailyBreakdown($user->id, $accountIds, $accountId, $startDate, $endDate, $dateRange);

        // 5. Query Filtered Jobs for Detailed Activity Table
        [$logs, $totalItems] = $this->queryDetailedJobs($user->id, $accountIds, $filters, $limit, $offset);

        $totalPages = max(1, (int)ceil($totalItems / $limit));

        return View::render('reports/replies', [
            'accounts' => $accounts,
            'logs' => $logs,
            'stats' => $stats,
            'dailyBreakdown' => $dailyBreakdown,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    /**
     * Export Filtered Report to CSV
     */
    public function exportCsv(Request $request): void {
        $user = Auth::user();

        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;
        $type = $request->input('type', 'all');
        $status = $request->input('status', 'all');
        $dateRange = $request->input('date_range', '7days');
        $search = trim((string)$request->input('search', ''));

        [$startDate, $endDate, $rangeLabel] = $this->calculateDateBounds($dateRange, $request->input('start_date'), $request->input('end_date'));

        $filters = [
            'account_id' => $accountId,
            'type' => $type,
            'status' => $status,
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'range_label' => $rangeLabel,
            'search' => $search,
        ];

        $accounts = GmailAccount::findByUserId($user->id);
        if (empty($accounts) && $user->role === 'admin') {
            $accounts = GmailAccount::allActive();
        }
        $accountIds = array_map(fn($a) => (int)$a->id, $accounts);

        [$logs] = $this->queryDetailedJobs($user->id, $accountIds, $filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=replies_and_followups_report_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'Job ID',
            'Type',
            'Step',
            'Connected Gmail Account',
            'Recipient Email',
            'Recipient Name',
            'Subject',
            'Status',
            'Scheduled At',
            'Sent / Processed At',
            'Attempts',
            'Error Details',
        ]);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['job_type'] === 'auto_reply' ? 'Auto-Reply' : 'Follow-up',
                $log['step_label'],
                $log['gmail_email'] ?? '',
                $log['recipient_email'],
                $log['recipient_name'],
                $log['subject'],
                ucfirst($log['status']),
                $log['scheduled_at'],
                $log['processed_at'] ?? 'N/A',
                $log['attempts'] ?? 0,
                $log['last_error'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Compute Start and End date bounds
     */
    private function calculateDateBounds(string $dateRange, ?string $customStart, ?string $customEnd): array {
        $today = date('Y-m-d');

        switch ($dateRange) {
            case 'today':
                return [$today, $today, 'Today (' . date('M j, Y') . ')'];

            case 'yesterday':
                $y = date('Y-m-d', strtotime('-1 day'));
                return [$y, $y, 'Yesterday (' . date('M j, Y', strtotime('-1 day')) . ')'];

            case '7days':
                $s = date('Y-m-d', strtotime('-6 days'));
                return [$s, $today, 'Last 7 Days (' . date('M j', strtotime('-6 days')) . ' - ' . date('M j, Y') . ')'];

            case '14days':
                $s = date('Y-m-d', strtotime('-13 days'));
                return [$s, $today, 'Last 14 Days (' . date('M j', strtotime('-13 days')) . ' - ' . date('M j, Y') . ')'];

            case '30days':
                $s = date('Y-m-d', strtotime('-29 days'));
                return [$s, $today, 'Last 30 Days (' . date('M j', strtotime('-29 days')) . ' - ' . date('M j, Y') . ')'];

            case 'custom':
                $s = $customStart ?: date('Y-m-d', strtotime('-6 days'));
                $e = $customEnd ?: $today;
                return [$s, $e, 'Custom (' . $s . ' to ' . $e . ')'];

            case 'all':
            default:
                return [null, null, 'All Time'];
        }
    }

    /**
     * Compute Summary Metric Cards
     */
    private function calculateSummaryStats(int $userId, array $accountIds, ?int $singleAccountId, ?string $startDate, ?string $endDate): array {
        $inAccounts = $singleAccountId ? [$singleAccountId] : $accountIds;
        if (empty($inAccounts)) {
            return [
                'range_replies' => 0,
                'range_followups' => 0,
                'range_total' => 0,
                'all_replies' => 0,
                'all_followups' => 0,
                'all_total' => 0,
                'pending_count' => 0,
                'unique_leads' => 0,
            ];
        }

        $accPlaceholders = implode(',', array_fill(0, count($inAccounts), '?'));

        // 1. All-time stats from daily_usage
        $allUsage = Database::first(
            "SELECT 
                COALESCE(SUM(reply_messages_count), 0) as total_replies,
                COALESCE(SUM(followup_messages_count), 0) as total_followups,
                COALESCE(SUM(total_sent), 0) as total_sent
             FROM daily_usage 
             WHERE gmail_account_id IN ({$accPlaceholders})",
            $inAccounts
        );

        // Also check completed jobs directly in case daily_usage isn't seeded
        $allJobs = Database::first(
            "SELECT 
                COALESCE(SUM(CASE WHEN job_type = 'auto_reply' AND status = 'completed' THEN 1 ELSE 0 END), 0) as total_replies,
                COALESCE(SUM(CASE WHEN job_type = 'follow_up' AND status = 'completed' THEN 1 ELSE 0 END), 0) as total_followups,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as total_sent,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_jobs
             FROM scheduled_jobs 
             WHERE gmail_account_id IN ({$accPlaceholders})
               AND job_type IN ('auto_reply', 'follow_up')",
            $inAccounts
        );

        $allReplies = max((int)($allUsage['total_replies'] ?? 0), (int)($allJobs['total_replies'] ?? 0));
        $allFollowups = max((int)($allUsage['total_followups'] ?? 0), (int)($allJobs['total_followups'] ?? 0));
        $allTotal = max((int)($allUsage['total_sent'] ?? 0), (int)($allJobs['total_sent'] ?? 0), ($allReplies + $allFollowups));
        $pendingCount = (int)($allJobs['pending_jobs'] ?? 0);

        // 2. Active Range stats
        if ($startDate && $endDate) {
            $rangeParams = array_merge($inAccounts, [$startDate, $endDate]);
            $rangeUsage = Database::first(
                "SELECT 
                    COALESCE(SUM(reply_messages_count), 0) as total_replies,
                    COALESCE(SUM(followup_messages_count), 0) as total_followups,
                    COALESCE(SUM(total_sent), 0) as total_sent
                 FROM daily_usage 
                 WHERE gmail_account_id IN ({$accPlaceholders})
                   AND usage_date >= ? AND usage_date <= ?",
                $rangeParams
            );

            $rangeJobParams = array_merge($inAccounts, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $rangeJobs = Database::first(
                "SELECT 
                    COALESCE(SUM(CASE WHEN job_type = 'auto_reply' AND status = 'completed' THEN 1 ELSE 0 END), 0) as total_replies,
                    COALESCE(SUM(CASE WHEN job_type = 'follow_up' AND status = 'completed' THEN 1 ELSE 0 END), 0) as total_followups,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as total_sent
                 FROM scheduled_jobs 
                 WHERE gmail_account_id IN ({$accPlaceholders})
                   AND job_type IN ('auto_reply', 'follow_up')
                   AND COALESCE(processed_at, scheduled_at, created_at) >= ?
                   AND COALESCE(processed_at, scheduled_at, created_at) <= ?",
                $rangeJobParams
            );

            $rangeReplies = max((int)($rangeUsage['total_replies'] ?? 0), (int)($rangeJobs['total_replies'] ?? 0));
            $rangeFollowups = max((int)($rangeUsage['total_followups'] ?? 0), (int)($rangeJobs['total_followups'] ?? 0));
            $rangeTotal = max((int)($rangeUsage['total_sent'] ?? 0), (int)($rangeJobs['total_sent'] ?? 0), ($rangeReplies + $rangeFollowups));
        } else {
            $rangeReplies = $allReplies;
            $rangeFollowups = $allFollowups;
            $rangeTotal = $allTotal;
        }

        // 3. Unique Leads / Senders Contacted
        $uniqueLeadsRow = Database::first(
            "SELECT COUNT(DISTINCT normalized_sender_email) as c 
             FROM auto_reply_recipients 
             WHERE user_id = :uid",
            ['uid' => $userId]
        );
        $uniqueLeads = (int)($uniqueLeadsRow['c'] ?? 0);

        return [
            'range_replies' => $rangeReplies,
            'range_followups' => $rangeFollowups,
            'range_total' => $rangeTotal,
            'all_replies' => $allReplies,
            'all_followups' => $allFollowups,
            'all_total' => $allTotal,
            'pending_count' => $pendingCount,
            'unique_leads' => $uniqueLeads,
        ];
    }

    /**
     * Compute Day-by-Day breakdown for the specified period (default last 7 days)
     */
    private function calculateDailyBreakdown(int $userId, array $accountIds, ?int $singleAccountId, ?string $startDate, ?string $endDate, string $dateRange): array {
        $inAccounts = $singleAccountId ? [$singleAccountId] : $accountIds;
        if (empty($inAccounts)) {
            return [];
        }

        // Determine day range to display
        $effectiveStart = $startDate ?: date('Y-m-d', strtotime('-6 days'));
        $effectiveEnd = $endDate ?: date('Y-m-d');

        // Generate list of all calendar dates in this window (up to 31 days)
        $dates = [];
        $current = strtotime($effectiveEnd);
        $startLimit = strtotime($effectiveStart);
        $maxDays = 31;
        $counter = 0;

        while ($current >= $startLimit && $counter < $maxDays) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('-1 day', $current);
            $counter++;
        }

        $accPlaceholders = implode(',', array_fill(0, count($inAccounts), '?'));

        // Query daily_usage for accounts in range
        $usageRows = Database::query(
            "SELECT 
                usage_date,
                SUM(reply_messages_count) as auto_replies,
                SUM(reply_count) as unique_replies,
                SUM(followup_messages_count) as followups,
                SUM(followup_count) as unique_followups,
                SUM(total_sent) as total_sent
             FROM daily_usage 
             WHERE gmail_account_id IN ({$accPlaceholders})
               AND usage_date >= ? AND usage_date <= ?
             GROUP BY usage_date",
            array_merge($inAccounts, [$effectiveStart, $effectiveEnd])
        );

        $usageMap = [];
        foreach ($usageRows as $r) {
            $usageMap[$r['usage_date']] = [
                'auto_replies' => (int)$r['auto_replies'],
                'unique_replies' => (int)$r['unique_replies'],
                'followups' => (int)$r['followups'],
                'unique_followups' => (int)$r['unique_followups'],
                'total_sent' => (int)$r['total_sent'],
            ];
        }

        // Also query completed scheduled_jobs per day to cover all edge-cases
        $jobsRows = Database::query(
            "SELECT 
                DATE(COALESCE(processed_at, scheduled_at, created_at)) as jdate,
                SUM(CASE WHEN job_type = 'auto_reply' AND status = 'completed' THEN 1 ELSE 0 END) as replies_cnt,
                SUM(CASE WHEN job_type = 'follow_up' AND status = 'completed' THEN 1 ELSE 0 END) as followups_cnt,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_cnt
             FROM scheduled_jobs 
             WHERE gmail_account_id IN ({$accPlaceholders})
               AND job_type IN ('auto_reply', 'follow_up')
               AND COALESCE(processed_at, scheduled_at, created_at) >= ?
               AND COALESCE(processed_at, scheduled_at, created_at) <= ?
             GROUP BY DATE(COALESCE(processed_at, scheduled_at, created_at))",
            array_merge($inAccounts, [$effectiveStart . ' 00:00:00', $effectiveEnd . ' 23:59:59'])
        );

        $jobsMap = [];
        foreach ($jobsRows as $jr) {
            $jobsMap[$jr['jdate']] = [
                'replies_cnt' => (int)$jr['replies_cnt'],
                'followups_cnt' => (int)$jr['followups_cnt'],
                'completed_cnt' => (int)$jr['completed_cnt'],
            ];
        }

        // Build composite day-by-day table
        $todayStr = date('Y-m-d');
        $yesterdayStr = date('Y-m-d', strtotime('-1 day'));
        $breakdown = [];

        foreach ($dates as $d) {
            $u = $usageMap[$d] ?? ['auto_replies' => 0, 'unique_replies' => 0, 'followups' => 0, 'unique_followups' => 0, 'total_sent' => 0];
            $j = $jobsMap[$d] ?? ['replies_cnt' => 0, 'followups_cnt' => 0, 'completed_cnt' => 0];

            $replies = max($u['auto_replies'], $j['replies_cnt']);
            $followups = max($u['followups'], $j['followups_cnt']);
            $total = max($u['total_sent'], $j['completed_cnt'], ($replies + $followups));

            $time = strtotime($d);
            $dayLabel = date('D, M j', $time);
            if ($d === $todayStr) {
                $dayLabel .= ' (Today)';
            } elseif ($d === $yesterdayStr) {
                $dayLabel .= ' (Yesterday)';
            }

            $breakdown[] = [
                'date' => $d,
                'day_label' => $dayLabel,
                'auto_replies' => $replies,
                'followups' => $followups,
                'total_sent' => $total,
            ];
        }

        return $breakdown;
    }

    /**
     * Query detailed individual jobs matching all filters
     */
    private function queryDetailedJobs(int $userId, array $accountIds, array $filters, int $limit, int $offset): array {
        $inAccounts = !empty($filters['account_id']) ? [(int)$filters['account_id']] : $accountIds;
        if (empty($inAccounts)) {
            return [[], 0];
        }

        $where = ["sj.job_type IN ('auto_reply', 'follow_up')"];
        $params = [];

        // Account filter
        $accPlaceholders = implode(',', array_fill(0, count($inAccounts), '?'));
        $where[] = "sj.gmail_account_id IN ({$accPlaceholders})";
        foreach ($inAccounts as $aid) {
            $params[] = $aid;
        }

        // Type filter
        if ($filters['type'] && $filters['type'] !== 'all') {
            $where[] = "sj.job_type = ?";
            $params[] = $filters['type'];
        }

        // Status filter
        if ($filters['status'] && $filters['status'] !== 'all') {
            $where[] = "sj.status = ?";
            $params[] = $filters['status'];
        }

        // Date Range filter
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = "COALESCE(sj.processed_at, sj.scheduled_at, sj.created_at) >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';

            $where[] = "COALESCE(sj.processed_at, sj.scheduled_at, sj.created_at) <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        // Search query filter
        if (!empty($filters['search'])) {
            $sTerm = '%' . $filters['search'] . '%';
            $where[] = "(sj.payload LIKE ? OR th.subject LIKE ? OR th.sender_email LIKE ? OR th.sender_name LIKE ?)";
            $params[] = $sTerm;
            $params[] = $sTerm;
            $params[] = $sTerm;
            $params[] = $sTerm;
        }

        $whereSql = implode(' AND ', $where);

        // Count total items
        $countSql = "SELECT COUNT(*) as c 
                     FROM scheduled_jobs sj
                     JOIN gmail_accounts ga ON sj.gmail_account_id = ga.id
                     LEFT JOIN email_threads th ON sj.thread_id = th.id
                     WHERE {$whereSql}";

        $countRow = Database::first($countSql, $params);
        $totalItems = (int)($countRow['c'] ?? 0);

        if ($totalItems === 0) {
            return [[], 0];
        }

        // Fetch paginated rows
        $querySql = "SELECT 
                        sj.id,
                        sj.gmail_account_id,
                        sj.thread_id,
                        sj.job_type,
                        sj.payload,
                        sj.scheduled_at,
                        sj.processed_at,
                        sj.status,
                        sj.attempts,
                        sj.max_attempts,
                        sj.last_error,
                        sj.created_at,
                        ga.gmail_email,
                        th.subject as thread_subject,
                        th.sender_email as thread_sender_email,
                        th.sender_name as thread_sender_name,
                        th.gmail_thread_id
                     FROM scheduled_jobs sj
                     JOIN gmail_accounts ga ON sj.gmail_account_id = ga.id
                     LEFT JOIN email_threads th ON sj.thread_id = th.id
                     WHERE {$whereSql}
                     ORDER BY COALESCE(sj.processed_at, sj.scheduled_at, sj.created_at) DESC
                     LIMIT {$limit} OFFSET {$offset}";

        $rawRows = Database::query($querySql, $params);

        // Format and unpack rows
        $formatted = [];
        foreach ($rawRows as $r) {
            $payload = [];
            if (!empty($r['payload'])) {
                $payload = is_array($r['payload']) ? $r['payload'] : (json_decode($r['payload'], true) ?? []);
            }

            $recipientEmail = $payload['recipient_email'] ?? $r['thread_sender_email'] ?? 'Unknown';
            $recipientName = $payload['recipient_name'] ?? $r['thread_sender_name'] ?? '';
            $subject = $payload['subject'] ?? $r['thread_subject'] ?? '(No Subject)';
            $messageBody = $payload['reply_body'] ?? $payload['message'] ?? '';

            $stepNum = (int)($payload['reply_step'] ?? $payload['step_number'] ?? 1);
            $totalSteps = (int)($payload['total_steps'] ?? 0);

            if ($r['job_type'] === 'auto_reply') {
                $stepLabel = $totalSteps > 1 ? "Auto-Reply #{$stepNum}/{$totalSteps}" : "Auto-Reply #{$stepNum}";
            } else {
                $stepLabel = "Follow-up #{$stepNum}";
            }

            $displayDate = $r['processed_at'] ?: $r['scheduled_at'] ?: $r['created_at'];

            $formatted[] = [
                'id' => (int)$r['id'],
                'gmail_account_id' => (int)$r['gmail_account_id'],
                'gmail_email' => $r['gmail_email'] ?? '',
                'thread_id' => (int)$r['thread_id'],
                'gmail_thread_id' => $r['gmail_thread_id'] ?? '',
                'job_type' => $r['job_type'],
                'step_number' => $stepNum,
                'total_steps' => $totalSteps,
                'step_label' => $stepLabel,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'snippet' => substr(strip_tags($messageBody), 0, 160),
                'message_body' => $messageBody,
                'scheduled_at' => $r['scheduled_at'],
                'processed_at' => $r['processed_at'],
                'display_date' => $displayDate,
                'status' => $r['status'] ?? 'pending',
                'attempts' => (int)($r['attempts'] ?? 0),
                'last_error' => $r['last_error'] ?? null,
            ];
        }

        return [$formatted, $totalItems];
    }
}
