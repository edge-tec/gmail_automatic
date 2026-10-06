<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Lead;
use App\Models\LeadFile;
use App\Models\LeadDeletionOperation;
use App\Services\LeadManagementService;
use App\Services\LeadStorageService;
use App\Services\LeadAuditService;
use Exception;

class LeadController {

    private function getAuthenticatedUser(): ?\App\Models\User {
        $user = Auth::user();
        if (!$user) {
            flash('error', 'Please log in to continue.');
            redirect('/login');
            exit;
        }
        return $user;
    }

    private function checkPermission(string $permission): bool {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission($permission)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                Response::json(['error' => 'Permission denied: ' . $permission], 403);
            }
            flash('error', "Access Denied: You lack the required permission [{$permission}].");
            redirect('/leads');
            return false;
        }
        return true;
    }

    public function index(Request $request): string {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission('leads.view')) {
            flash('error', 'You do not have permission to view leads.');
            redirect('/dashboard');
            exit;
        }

        $filters = [
            'search' => trim((string)$request->input('search', '')),
            'status' => (string)$request->input('status', 'active'),
            'source' => (string)$request->input('source', 'all'),
            'sort_by' => (string)$request->input('sort_by', 'created_at'),
            'sort_direction' => (string)$request->input('sort_direction', 'desc'),
            'page' => (int)$request->input('page', 1),
            'per_page' => (int)$request->input('per_page', 25),
        ];

        $kpis = LeadManagementService::getDashboardKpis($user->id);
        $leadData = LeadManagementService::queryLeads($user->id, $filters);
        $files = LeadFile::forUser($user->id);
        $recentOperations = LeadDeletionOperation::getRecentForUser($user->id, 5);

        if ($request->input('format') === 'json' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            Response::json([
                'kpis' => $kpis,
                'data' => $leadData,
            ]);
        }

        return View::render('leads/index', [
            'user' => $user,
            'kpis' => $kpis,
            'leadData' => $leadData,
            'filters' => $filters,
            'files' => $files,
            'recentOperations' => $recentOperations,
        ]);
    }

    public function show(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission('leads.view')) {
            Response::json(['error' => 'Permission denied'], 403);
        }

        $id = (int)($params['id'] ?? $request->input('id', 0));
        $lead = Lead::findForUser($id, $user->id);

        if (!$lead) {
            Response::json(['error' => 'Lead not found'], 404);
        }

        $leadFiles = [];
        if (!empty($lead->file_ids)) {
            foreach ($lead->file_ids as $fid) {
                $f = LeadFile::findForUser((int)$fid, $user->id);
                if ($f) {
                    $leadFiles[] = [
                        'id' => $f->id,
                        'file_name' => $f->file_name,
                        'file_size' => $f->file_size,
                        'file_type' => $f->file_type,
                    ];
                }
            }
        }

        Response::json([
            'lead' => $lead->toArray(),
            'files' => $leadFiles,
        ]);
    }

    public function store(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.create')) return;

        $email = strtolower(trim((string)$request->input('email', '')));
        $firstName = trim((string)$request->input('first_name', ''));
        $lastName = trim((string)$request->input('last_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $company = trim((string)$request->input('company', ''));
        $title = trim((string)$request->input('title', ''));
        $source = trim((string)$request->input('source', 'manual'));
        $status = trim((string)$request->input('status', 'active'));
        $notes = trim((string)$request->input('notes', ''));
        $fileId = (int)$request->input('file_id', 0);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please provide a valid email address.');
            redirect('/leads');
            return;
        }

        // Duplicate check within user scope
        $existing = Lead::findByEmail($email, $user->id);
        if ($existing) {
            flash('error', "A lead with email '{$email}' already exists in your account.");
            redirect('/leads');
            return;
        }

        $lead = Lead::create([
            'user_id' => $user->id,
            'email' => $email,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'phone' => $phone ?: null,
            'company' => $company ?: null,
            'title' => $title ?: null,
            'source' => $source ?: 'manual',
            'status' => $status ?: 'active',
            'notes' => $notes ?: null,
            'file_ids' => $fileId ? [$fileId] : [],
        ]);

        LeadAuditService::log(
            userId: $user->id,
            action: 'lead.create',
            entityType: 'lead',
            entityId: $lead->id,
            beforeState: null,
            afterState: [
                'email' => $lead->email,
                'name' => $lead->getFullName(),
                'company' => $lead->company,
                'status' => $lead->status,
            ],
            metadata: ['source' => $source],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Lead '{$lead->email}' successfully created.");
        redirect('/leads');
    }

    public function update(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.edit')) return;

        $id = (int)($params['id'] ?? $request->input('id', 0));
        $lead = Lead::findForUser($id, $user->id);
        if (!$lead) {
            flash('error', 'Lead not found.');
            redirect('/leads');
            return;
        }

        $email = strtolower(trim((string)$request->input('email', $lead->email)));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please provide a valid email address.');
            redirect('/leads');
            return;
        }

        // Duplicate check if email changed
        if ($email !== $lead->email) {
            $existing = Lead::findByEmail($email, $user->id);
            if ($existing && $existing->id !== $lead->id) {
                flash('error', "Another lead already exists with email '{$email}'.");
                redirect('/leads');
                return;
            }
        }

        $beforeState = [
            'email' => $lead->email,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'company' => $lead->company,
            'status' => $lead->status,
        ];

        $lead->update([
            'email' => $email,
            'first_name' => trim((string)$request->input('first_name', '')) ?: null,
            'last_name' => trim((string)$request->input('last_name', '')) ?: null,
            'phone' => trim((string)$request->input('phone', '')) ?: null,
            'company' => trim((string)$request->input('company', '')) ?: null,
            'title' => trim((string)$request->input('title', '')) ?: null,
            'source' => trim((string)$request->input('source', $lead->source)),
            'status' => trim((string)$request->input('status', $lead->status)),
            'notes' => trim((string)$request->input('notes', '')) ?: null,
        ]);

        LeadAuditService::log(
            userId: $user->id,
            action: 'lead.update',
            entityType: 'lead',
            entityId: $lead->id,
            beforeState: $beforeState,
            afterState: [
                'email' => $lead->email,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'company' => $lead->company,
                'status' => $lead->status,
            ],
            metadata: [],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Lead '{$lead->email}' updated successfully.");
        redirect('/leads');
    }

    public function archive(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.edit')) return;

        $id = (int)($params['id'] ?? $request->input('id', 0));
        $lead = Lead::findForUser($id, $user->id);
        if (!$lead) {
            flash('error', 'Lead not found.');
            redirect('/leads');
            return;
        }

        $lead->archive();

        LeadAuditService::log(
            userId: $user->id,
            action: 'lead.archive',
            entityType: 'lead',
            entityId: $lead->id,
            beforeState: ['status' => 'active'],
            afterState: ['status' => 'archived'],
            metadata: [],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Lead '{$lead->email}' archived.");
        redirect('/leads');
    }

    public function restore(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.edit')) return;

        $id = (int)($params['id'] ?? $request->input('id', 0));
        $lead = Lead::findForUser($id, $user->id);
        if (!$lead) {
            flash('error', 'Lead not found.');
            redirect('/leads');
            return;
        }

        $lead->restore();

        LeadAuditService::log(
            userId: $user->id,
            action: 'lead.restore',
            entityType: 'lead',
            entityId: $lead->id,
            beforeState: ['status' => 'archived'],
            afterState: ['status' => 'active'],
            metadata: [],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Lead '{$lead->email}' restored to active state.");
        redirect('/leads');
    }

    public function destroy(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.delete')) return;

        $id = (int)($params['id'] ?? $request->input('id', 0));
        
        $result = LeadManagementService::deleteLeadPermanently($user->id, $id, [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => $request->server('HTTP_USER_AGENT'),
            'reason' => 'User initiated single deletion',
        ]);

        if ($result['success']) {
            flash('success', "Lead #{$id} and its associated records have been permanently deleted.");
        } else {
            flash('error', "Deletion failed: " . ($result['message'] ?? 'Unknown error'));
        }

        redirect('/leads');
    }

    public function bulkDelete(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.bulk_delete')) return;

        $rawIds = $request->input('lead_ids', []);
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }
        $ids = array_filter(array_map('intval', (array)$rawIds));

        if (empty($ids)) {
            flash('warning', 'No leads selected for deletion.');
            redirect('/leads');
            return;
        }

        $result = LeadManagementService::bulkDeletePermanently($user->id, $ids, [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => $request->server('HTTP_USER_AGENT'),
            'reason' => 'User initiated bulk deletion',
        ]);

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            Response::json($result);
        }

        if ($result['success']) {
            flash('success', "Successfully deleted {$result['deleted_count']} leads permanently. {$result['queued_cleanups']} storage cleanups dispatched.");
        } else {
            flash('error', "Bulk deletion encountered an issue: " . ($result['message'] ?? 'Unknown error'));
        }

        redirect('/leads');
    }

    public function clear(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.clear')) return;

        $confirm = trim((string)$request->input('confirmation_text', ''));
        if ($confirm !== 'DELETE ALL LEADS') {
            flash('error', 'Clear leads failed: You must type the exact phrase "DELETE ALL LEADS" to confirm complete data deletion.');
            redirect('/leads');
            return;
        }

        $scope = [
            'status' => $request->input('scope_status', 'all'),
            'source' => $request->input('scope_source', 'all'),
            'file_id' => (int)$request->input('scope_file_id', 0),
        ];

        $result = LeadManagementService::clearAllLeads($user->id, [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => $request->server('HTTP_USER_AGENT'),
            'scope' => $scope,
        ]);

        if ($result['success']) {
            flash('success', "Clear completed: {$result['deleted_count']} leads permanently wiped from the system. Storage cleanups dispatched.");
        } else {
            flash('error', "Clear operation failed: " . ($result['message'] ?? 'Unknown error'));
        }

        redirect('/leads');
    }

    public function export(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.export')) return;

        $filters = [
            'search' => trim((string)$request->input('search', '')),
            'status' => (string)$request->input('status', 'all'),
            'source' => (string)$request->input('source', 'all'),
        ];

        LeadManagementService::exportCsv($user->id, $filters);
        exit;
    }

    public function import(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.import')) return;

        if (!isset($_FILES['lead_file']) || $_FILES['lead_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please upload a valid CSV or Excel file.');
            redirect('/leads');
            return;
        }

        $storedFile = LeadStorageService::storeUploadedLeadFile(
            $user->id,
            $_FILES['lead_file'],
            'lead_import',
            null
        );

        if (!$storedFile['success']) {
            flash('error', 'File upload failed: ' . $storedFile['message']);
            redirect('/leads');
            return;
        }

        $leadFile = $storedFile['file'];
        $filePath = $leadFile->getAbsolutePath();

        if (!file_exists($filePath)) {
            flash('error', 'Uploaded file could not be located on disk.');
            redirect('/leads');
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            flash('error', 'Failed to read uploaded file.');
            redirect('/leads');
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            flash('error', 'File is empty or not a valid CSV.');
            redirect('/leads');
            return;
        }

        // Map headers
        $colMap = [];
        foreach ($header as $idx => $col) {
            $c = strtolower(trim($col));
            if (in_array($c, ['email', 'email address', 'e-mail'])) $colMap['email'] = $idx;
            elseif (in_array($c, ['first name', 'first_name', 'fname'])) $colMap['first_name'] = $idx;
            elseif (in_array($c, ['last name', 'last_name', 'lname'])) $colMap['last_name'] = $idx;
            elseif (in_array($c, ['name', 'full name', 'fullname'])) $colMap['full_name'] = $idx;
            elseif (in_array($c, ['phone', 'phone number', 'mobile', 'telephone'])) $colMap['phone'] = $idx;
            elseif (in_array($c, ['company', 'organization', 'business'])) $colMap['company'] = $idx;
            elseif (in_array($c, ['title', 'job title', 'position', 'role'])) $colMap['title'] = $idx;
        }

        if (!isset($colMap['email'])) {
            fclose($handle);
            flash('error', 'Import CSV must contain an "email" column.');
            redirect('/leads');
            return;
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $email = isset($colMap['email']) && isset($row[$colMap['email']]) ? strtolower(trim($row[$colMap['email']])) : '';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            if (Lead::findByEmail($email, $user->id)) {
                $skipped++;
                continue;
            }

            $firstName = null;
            $lastName = null;
            if (isset($colMap['first_name']) && isset($row[$colMap['first_name']])) {
                $firstName = trim($row[$colMap['first_name']]);
            }
            if (isset($colMap['last_name']) && isset($row[$colMap['last_name']])) {
                $lastName = trim($row[$colMap['last_name']]);
            }
            if (!$firstName && isset($colMap['full_name']) && isset($row[$colMap['full_name']])) {
                $parts = explode(' ', trim($row[$colMap['full_name']]), 2);
                $firstName = $parts[0] ?? null;
                $lastName = $parts[1] ?? null;
            }

            Lead::create([
                'user_id' => $user->id,
                'email' => $email,
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'phone' => (isset($colMap['phone']) && isset($row[$colMap['phone']])) ? trim($row[$colMap['phone']]) : null,
                'company' => (isset($colMap['company']) && isset($row[$colMap['company']])) ? trim($row[$colMap['company']]) : null,
                'title' => (isset($colMap['title']) && isset($row[$colMap['title']])) ? trim($row[$colMap['title']]) : null,
                'source' => 'import',
                'status' => 'active',
                'file_ids' => [$leadFile->id],
            ]);

            $imported++;
        }
        fclose($handle);

        LeadAuditService::log(
            userId: $user->id,
            action: 'lead.import',
            entityType: 'lead_file',
            entityId: $leadFile->id,
            beforeState: null,
            afterState: ['file_name' => $leadFile->file_name, 'imported_count' => $imported, 'skipped_count' => $skipped],
            metadata: ['imported' => $imported, 'skipped' => $skipped],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Import complete: {$imported} new leads added successfully ({$skipped} duplicates or invalid rows skipped).");
        redirect('/leads');
    }

    public function operations(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission('leads.view')) {
            Response::json(['error' => 'Permission denied'], 403);
        }

        $ops = LeadDeletionOperation::getRecentForUser($user->id, 10);
        $result = array_map(function($op) {
            return [
                'id' => $op->id,
                'operation_type' => $op->operation_type,
                'total_leads' => $op->total_leads,
                'processed_leads' => $op->processed_leads,
                'deleted_leads' => $op->deleted_leads,
                'failed_leads' => $op->failed_leads,
                'progress_percentage' => $op->getProgressPercentage(),
                'status' => $op->status,
                'created_at' => $op->created_at,
            ];
        }, $ops);

        Response::json(['operations' => $result]);
    }
}
