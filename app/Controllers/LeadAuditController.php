<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\LeadAuditService;

class LeadAuditController {

    private function getAuthenticatedUser(): ?\App\Models\User {
        $user = Auth::user();
        if (!$user) {
            flash('error', 'Please log in to continue.');
            redirect('/login');
            exit;
        }
        return $user;
    }

    public function index(Request $request): string {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission('lead_audit.view')) {
            flash('error', 'You do not have permission to view lead audit logs.');
            redirect('/dashboard');
            exit;
        }

        $filters = [
            'action' => trim((string)$request->input('action', '')),
            'entity_type' => trim((string)$request->input('entity_type', '')),
        ];

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(100, max(10, (int)$request->input('per_page', 25)));

        // Admins can see all logs or filter by user; regular users only see their own
        $scopeUserId = ($user->role === 'admin' && $request->input('all_users') === '1') ? null : $user->id;

        $logData = LeadAuditService::getLogs($scopeUserId, $page, $perPage, $filters);

        if ($request->input('format') === 'json' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            Response::json($logData);
        }

        return View::render('leads/audit', [
            'user' => $user,
            'logData' => $logData,
            'filters' => $filters,
            'scopeUserId' => $scopeUserId,
        ]);
    }
}
