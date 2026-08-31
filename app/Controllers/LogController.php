<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\GmailAccount;

class LogController {
    public function index(Request $request): string {
        $user = Auth::user();
        $filterType = $request->input('type');
        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;

        $accounts = GmailAccount::findByUserId($user->id);
        $logs = ActivityLog::getLatest(100, $user->id, $filterType, $accountId);

        return View::render('logs/index', [
            'logs' => $logs,
            'accounts' => $accounts,
            'selectedType' => $filterType,
            'selectedAccountId' => $accountId,
        ]);
    }

    public function clear(Request $request): void {
        $user = Auth::user();
        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;

        ActivityLog::deleteByUserId($user->id, $accountId);

        flash('success', 'Activity and automation logs have been cleared.');
        redirect('/logs');
    }
}
