<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\SkippedEmailLog;
use App\Models\GmailAccount;
use App\Models\AutoReplyRecipient;

class SkippedEmailController {
    public function index(Request $request): string {
        $user = Auth::user();

        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;
        $skipType = $request->input('type');
        $dateRange = $request->input('date_range', 'all');
        $search = $request->input('search');
        $page = max(1, (int)$request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'account_id' => $accountId,
            'skip_type' => $skipType,
            'date_range' => $dateRange,
            'search' => $search,
        ];

        $accounts = GmailAccount::findByUserId($user->id);
        $totalItems = SkippedEmailLog::countByUserId($user->id, $filters);
        $logs = SkippedEmailLog::findByUserId($user->id, $filters, $limit, $offset);
        $stats = SkippedEmailLog::getStatsByUserId($user->id);
        $totalPages = max(1, (int)ceil($totalItems / $limit));

        return View::render('reports/skipped', [
            'logs' => $logs,
            'accounts' => $accounts,
            'stats' => $stats,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    public function exportCsv(Request $request): void {
        $user = Auth::user();

        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;
        $skipType = $request->input('type');
        $dateRange = $request->input('date_range', 'all');
        $search = $request->input('search');

        $filters = [
            'account_id' => $accountId,
            'skip_type' => $skipType,
            'date_range' => $dateRange,
            'search' => $search,
        ];

        $logs = SkippedEmailLog::findByUserId($user->id, $filters, 5000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=skipped_emails_report_' . date('Y-m-d_His') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'ID',
            'Connected Gmail Account',
            'Sender Email',
            'Sender Name',
            'Subject',
            'Skip Category',
            'Skip Reason',
            'Initial Reply Sent Date',
            'Skipped Email Received Date',
            'Logged At',
        ]);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['gmail_email'] ?? '',
                $log['sender_email'],
                $log['sender_name'] ?? '',
                $log['subject'] ?? '(No Subject)',
                $log['skip_type'],
                $log['skip_reason'],
                $log['first_reply_sent_at'] ?? 'N/A',
                $log['received_at'] ?? $log['created_at'],
                $log['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    public function clear(Request $request): void {
        $user = Auth::user();
        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;

        SkippedEmailLog::clearByUser($user->id, $accountId);

        flash('success', 'Skipped and duplicate email report logs have been cleared.');
        redirect('/skipped-emails');
    }
}
