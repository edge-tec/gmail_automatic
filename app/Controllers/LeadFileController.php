<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\LeadFile;
use App\Models\LeadStorageCleanup;
use App\Services\LeadStorageService;
use App\Services\LeadAuditService;
use Exception;

class LeadFileController {

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
            redirect('/lead-files');
            return false;
        }
        return true;
    }

    public function index(Request $request): string {
        $user = $this->getAuthenticatedUser();
        if (!$user->hasLeadPermission('lead_files.view')) {
            flash('error', 'You do not have permission to view lead files.');
            redirect('/dashboard');
            exit;
        }

        $files = LeadFile::forUser($user->id);
        $stats = LeadStorageService::getStorageStatistics($user->id);
        $pendingCleanups = LeadStorageCleanup::getPending(10);
        $recentCleanups = LeadStorageCleanup::getRecent(10);

        if ($request->input('format') === 'json' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            Response::json([
                'files' => array_map(fn($f) => $f->toArray(), $files),
                'stats' => $stats,
            ]);
        }

        return View::render('leads/files', [
            'user' => $user,
            'files' => $files,
            'stats' => $stats,
            'pendingCleanups' => $pendingCleanups,
            'recentCleanups' => $recentCleanups,
        ]);
    }

    public function upload(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('leads.create')) return;

        if (!isset($_FILES['lead_file']) || $_FILES['lead_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please provide a valid file to upload.');
            redirect('/lead-files');
            return;
        }

        $category = trim((string)$request->input('category', 'lead_import'));
        $result = LeadStorageService::storeUploadedLeadFile(
            $user->id,
            $_FILES['lead_file'],
            $category,
            null
        );

        if ($result['success']) {
            LeadAuditService::log(
                userId: $user->id,
                action: 'lead_file.upload',
                entityType: 'lead_file',
                entityId: $result['file']->id,
                beforeState: null,
                afterState: [
                    'file_name' => $result['file']->file_name,
                    'file_size' => $result['file']->file_size,
                    'file_hash' => $result['file']->file_hash,
                ],
                metadata: ['category' => $category],
                ipAddress: $request->server('REMOTE_ADDR'),
                userAgent: $request->server('HTTP_USER_AGENT')
            );
            flash('success', "File '{$result['file']->file_name}' uploaded successfully.");
        } else {
            flash('error', "Upload failed: " . $result['message']);
        }

        redirect('/lead-files');
    }

    public function deleteFile(Request $request, array $params = []): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('lead_files.delete')) return;

        $id = (int)($params['id'] ?? $request->input('id', 0));
        $file = LeadFile::findForUser($id, $user->id);

        if (!$file) {
            flash('error', 'Lead file record not found.');
            redirect('/lead-files');
            return;
        }

        $result = LeadStorageService::deleteFileRecordAndStorage($id, $user->id, [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => $request->server('HTTP_USER_AGENT'),
            'reason' => 'User deleted file record',
        ]);

        if ($result['success']) {
            if (!empty($result['shared'])) {
                flash('info', "File record removed. Physical file was safely retained on disk because it is shared by {$result['active_references']} other active records.");
            } else {
                flash('success', "File record deleted and physical file cleanup queued.");
            }
        } else {
            flash('error', "Failed to delete file: " . ($result['message'] ?? 'Unknown error'));
        }

        redirect('/lead-files');
    }

    public function scan(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('lead_storage.view')) return;

        $scan = LeadStorageService::scanStorageOrphans($user->id);

        if ($request->input('format') === 'json' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            Response::json($scan);
        }

        $diskOrphansCount = count($scan['disk_orphans']);
        $dbOrphansCount = count($scan['db_orphans']);
        $missingFilesCount = count($scan['missing_files']);

        flash('info', "Storage scan complete: {$diskOrphansCount} disk orphan(s) found, {$dbOrphansCount} DB orphan(s) with no leads, {$missingFilesCount} missing file(s).");
        redirect('/lead-files');
    }

    public function cleanup(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('lead_files.cleanup')) return;

        $processed = LeadStorageService::processPendingStorageCleanups(50);
        flash('success', "Storage cleanup processed: {$processed['success']} files removed from disk, {$processed['failed']} retry-queued.");
        redirect('/lead-files');
    }

    public function retryFailed(Request $request): void {
        $user = $this->getAuthenticatedUser();
        if (!$this->checkPermission('lead_files.cleanup')) return;

        // Reset failed tasks back to pending
        \App\Core\Database::execute("UPDATE lead_storage_cleanups SET status = 'pending', retry_count = 0, error_message = NULL WHERE status = 'failed'");
        $processed = LeadStorageService::processPendingStorageCleanups(50);
        flash('success', "Failed tasks reset and reprocessed: {$processed['success']} succeeded, {$processed['failed']} pending.");
        redirect('/lead-files');
    }
}
