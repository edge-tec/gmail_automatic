<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Lead;
use App\Models\LeadFile;
use App\Models\LeadDeletionOperation;
use App\Models\LeadAuditLog;
use App\Models\User;
use App\Models\UserLeadPermission;
use App\Core\Request;

class LeadManagementService {

    /**
     * Compute all real KPI statistics for the Lead Dashboard
     */
    public static function getDashboardKPIs(int $userId, bool $isAdmin = false): array {
        Lead::ensureSchema();

        $params = [];
        $userWhere = "";
        if (!$isAdmin) {
            $userWhere = "WHERE user_id = :uid";
            $params['uid'] = $userId;
        }

        $driver = config('database.default', 'mysql');
        $todaySql = ($driver === 'mysql') ? "DATE(created_at) = CURDATE()" : "DATE(created_at) = DATE('now')";
        $weekSql = ($driver === 'mysql') ? "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : "created_at >= datetime('now', '-7 days')";
        $monthSql = ($driver === 'mysql') ? "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" : "created_at >= datetime('now', '-30 days')";

        $clause = $userWhere ? "{$userWhere} AND " : "WHERE ";

        // 1. Total Leads
        $rowTotal = Database::first("SELECT COUNT(*) as c FROM leads {$userWhere}", $params);
        $totalLeads = (int)($rowTotal['c'] ?? 0);

        // 2. Active Leads (not archived)
        $rowActive = Database::first("SELECT COUNT(*) as c FROM leads {$clause} is_archived = 0", $params);
        $activeLeads = (int)($rowActive['c'] ?? 0);

        // 3. Leads Created Today
        $rowToday = Database::first("SELECT COUNT(*) as c FROM leads {$clause} {$todaySql}", $params);
        $createdToday = (int)($rowToday['c'] ?? 0);

        // 4. Leads Created This Week (Last 7 Days)
        $rowWeek = Database::first("SELECT COUNT(*) as c FROM leads {$clause} {$weekSql}", $params);
        $createdWeek = (int)($rowWeek['c'] ?? 0);

        // 5. Leads Created This Month (Last 30 Days)
        $rowMonth = Database::first("SELECT COUNT(*) as c FROM leads {$clause} {$monthSql}", $params);
        $createdMonth = (int)($rowMonth['c'] ?? 0);

        // 6. Real Storage Metrics from LeadStorageService
        $storageStats = LeadStorageService::getStorageStatistics($isAdmin ? null : $userId);

        return [
            'total_leads' => $totalLeads,
            'active_leads' => $activeLeads,
            'archived_leads' => max(0, $totalLeads - $activeLeads),
            'leads_with_files' => $storageStats['total_files'],
            'created_today' => $createdToday,
            'created_this_week' => $createdWeek,
            'created_this_month' => $createdMonth,
            'total_storage_bytes' => $storageStats['total_size_bytes'],
            'total_storage_formatted' => $storageStats['total_size_formatted'],
            'total_files' => $storageStats['total_files'],
            'orphaned_files' => $storageStats['orphaned_files'],
            'pending_cleanups' => $storageStats['pending_cleanups'],
            'failed_cleanups' => $storageStats['failed_cleanups'],
        ];
    }

    /**
     * Query leads with server-side pagination, search, advanced filtering, and sorting
     */
    public static function queryLeads(
        int $userId,
        mixed $isAdminOrFilters = false,
        array $filters = [],
        int $limit = 25,
        int $offset = 0
    ): array {
        Lead::ensureSchema();

        $isAdmin = false;
        if (is_array($isAdminOrFilters)) {
            $filters = $isAdminOrFilters;
            $isAdmin = false;
        } elseif (is_bool($isAdminOrFilters)) {
            $isAdmin = $isAdminOrFilters;
        }

        if (isset($filters['page']) || isset($filters['per_page'])) {
            $page = max(1, (int)($filters['page'] ?? 1));
            $limit = max(1, (int)($filters['per_page'] ?? 25));
            $offset = ($page - 1) * $limit;
        }

        $where = [];
        $params = [];

        if (!$isAdmin) {
            $where[] = "l.user_id = :uid";
            $params['uid'] = $userId;
        } elseif (!empty($filters['user_id'])) {
            $where[] = "l.user_id = :uid";
            $params['uid'] = (int)$filters['user_id'];
        }

        // Active vs Archived filter
        if (isset($filters['archived']) && $filters['archived'] === '1') {
            $where[] = "l.is_archived = 1";
        } elseif (isset($filters['status']) && $filters['status'] === 'archived') {
            $where[] = "l.is_archived = 1";
        } elseif (!isset($filters['show_all']) || $filters['show_all'] !== '1') {
            if (!isset($filters['status']) || $filters['status'] !== 'all') {
                $where[] = "l.is_archived = 0";
            }
        }

        // Search text
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $where[] = "(l.first_name LIKE :s OR l.last_name LIKE :s OR l.email LIKE :s OR l.phone LIKE :s OR l.company LIKE :s OR l.tags LIKE :s)";
            $params['s'] = $search;
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all' && $filters['status'] !== 'archived') {
            $where[] = "l.status = :stat";
            $params['stat'] = $filters['status'];
        }

        // Source filter
        if (!empty($filters['source']) && $filters['source'] !== 'all') {
            $where[] = "l.source = :src";
            $params['src'] = $filters['source'];
        }

        // Date range
        if (!empty($filters['start_date'])) {
            $where[] = "l.created_at >= :start_date";
            $params['start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where[] = "l.created_at <= :end_date";
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $whereSql = !empty($where) ? implode(' AND ', $where) : "1=1";

        // Sort configuration
        $allowedSortCols = ['id', 'email', 'first_name', 'company', 'status', 'source', 'created_at', 'updated_at'];
        $sortCol = in_array($filters['sort_by'] ?? '', $allowedSortCols) ? $filters['sort_by'] : 'id';
        $sortDir = strtolower($filters['sort_direction'] ?? $filters['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Total count
        $countRow = Database::first("SELECT COUNT(*) as c FROM leads l WHERE {$whereSql}", $params);
        $total = (int)($countRow['c'] ?? 0);

        // Query records
        $sql = "SELECT l.*, u.name as owner_name, u.email as owner_email,
                (SELECT COUNT(*) FROM lead_files lf WHERE lf.lead_id = l.id AND lf.status = 'active') as files_count
                FROM leads l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE {$whereSql}
                ORDER BY l.{$sortCol} {$sortDir}
                LIMIT {$limit} OFFSET {$offset}";

        $rows = Database::query($sql, $params);
        $leads = array_map(function($r) {
            $lead = Lead::fromRow($r);
            $lead->owner_name = $r['owner_name'] ?? 'Unknown';
            $lead->owner_email = $r['owner_email'] ?? '';
            $lead->files_count = (int)($r['files_count'] ?? 0);
            return $lead;
        }, $rows);

        $page = ($limit > 0) ? (int)floor($offset / $limit) + 1 : 1;
        $lastPage = ($limit > 0) ? (int)ceil($total / $limit) : 1;

        return [
            0 => $leads,
            1 => $total,
            'items' => $leads,
            'total' => $total,
            'current_page' => $page,
            'last_page' => max(1, $lastPage),
            'per_page' => $limit,
        ];
    }

    /**
     * Delete an individual Lead permanently with transaction safety and storage outbox
     */
    public static function deleteLeadPermanently(int $param1, int $param2, mixed $optionsOrRequest = null): array {
        Lead::ensureSchema();
        LeadFile::ensureSchema();

        $leadId = $param1;
        $actorId = $param2;
        $request = ($optionsOrRequest instanceof Request) ? $optionsOrRequest : null;

        // Auto-detect if parameters were passed as ($actorId, $leadId)
        $lead = Lead::find($leadId);
        if (!$lead) {
            $candidateLead = Lead::find($actorId);
            if ($candidateLead) {
                $leadId = $param2;
                $actorId = $param1;
                $lead = $candidateLead;
            }
        }

        $actor = User::find($actorId);
        if (!$actor || (!$actor->hasPermission('leads.delete') && $actor->role !== 'admin')) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_category' => 'AUTHORIZATION_FAILED',
                'error' => 'You do not have permission to delete leads.',
                'message' => 'You do not have permission to delete leads.',
                'deleted' => 0,
            ];
        }

        if (!$lead) {
            return [
                'success' => false,
                'status' => 'already_deleted',
                'error_category' => 'ALREADY_DELETED',
                'message' => 'Lead already deleted.',
                'deleted' => 0,
                'files_deleted' => 0,
                'files_pending' => 0,
            ];
        }

        // Enforce tenant boundary
        if ($actor->role !== 'admin' && $lead->user_id !== $actor->id) {
            return [
                'success' => false,
                'status' => 'failed',
                'error_category' => 'AUTHORIZATION_FAILED',
                'error' => 'Unauthorized: Cannot delete a lead belonging to another tenant.',
                'message' => 'Unauthorized: Cannot delete a lead belonging to another tenant.',
                'deleted' => 0,
            ];
        }

        $targetUserId = $lead->user_id;
        $leadEmail = $lead->email;
        $operationUuid = bin2hex(random_bytes(16));

        // 1. Collect associated files before DB deletion
        $files = $lead->getFiles();
        $totalFiles = count($files);
        $totalStorageBytes = array_sum(array_map(fn($f) => $f->file_size, $files));

        // 2. Start ACID Database Transaction
        Database::beginTransaction();
        try {
            // A. Cancel any pending scheduled automation jobs referencing this lead's email
            try {
                Database::execute(
                    "UPDATE scheduled_jobs SET status = 'cancelled', last_error = 'Lead permanently deleted from system' 
                     WHERE status = 'pending' AND thread_id IN (SELECT id FROM email_threads WHERE sender_email = :e)",
                    ['e' => $leadEmail]
                );
            } catch (\Throwable $t) {
                // Ignore if tables not present
            }

            // B. Clean associated auto-reply recipient sequence record so queue worker never resurrects it
            try {
                Database::execute(
                    "DELETE FROM auto_reply_recipients WHERE user_id = :uid AND normalized_sender_email = :e",
                    ['uid' => $targetUserId, 'e' => $leadEmail]
                );
            } catch (\Throwable $t) {
                // Ignore
            }

            // C. Remove from bulk campaign recipient queues
            try {
                Database::execute(
                    "DELETE FROM email_campaign_recipients WHERE user_id = :uid AND email = :e",
                    ['uid' => $targetUserId, 'e' => $leadEmail]
                );
            } catch (\Throwable $t) {
                // Ignore
            }

            // D. Queue durable file cleanup tasks in lead_storage_cleanups
            foreach ($files as $file) {
                LeadStorageService::queueCleanupTask(
                    $targetUserId,
                    $file->file_path,
                    $file->file_hash,
                    $file->file_size,
                    $file->id
                );
                // Mark DB record status as marked_for_deletion
                $file->update(['status' => 'marked_for_deletion', 'lead_id' => null]);
            }

            // E. Delete the Lead database record
            $deleted = Database::execute("DELETE FROM leads WHERE id = :id", ['id' => $leadId]);
            if (!$deleted) {
                throw new \Exception("Database failed to delete lead record #{$leadId}");
            }

            // Commit DB transaction
            Database::commit();

        } catch (\Throwable $e) {
            Database::rollBack();
            LeadAuditService::logDestructiveAction(
                'delete_lead',
                $targetUserId,
                1,
                0,
                1,
                0,
                0,
                0,
                'FAILED',
                'FAILED',
                ['lead_id' => $leadId, 'error' => $e->getMessage()],
                $operationUuid,
                $request
            );

            return [
                'success' => false,
                'status' => 'failed',
                'error_category' => 'FAILED',
                'error' => 'Database transaction failed: ' . $e->getMessage(),
                'message' => 'Database transaction failed: ' . $e->getMessage(),
                'deleted' => 0,
            ];
        }

        // 3. Process Physical File Cleanup Worker
        $cleanupResult = LeadStorageService::processPendingCleanups();
        $filesDeleted = $cleanupResult['deleted'];
        $filesFailed = $cleanupResult['failed'];
        $filesPending = max(0, $totalFiles - $filesDeleted);

        $finalStatus = ($filesFailed > 0 || $filesPending > 0) ? 'PARTIAL' : 'SUCCESS';
        $errorCategory = ($finalStatus === 'PARTIAL') ? 'PARTIAL' : 'SUCCESS';

        // 4. Record Immutable Audit Log
        LeadAuditService::logDestructiveAction(
            'delete_lead',
            $targetUserId,
            1,
            1,
            0,
            $filesDeleted,
            $filesPending,
            $totalStorageBytes,
            $finalStatus,
            $errorCategory,
            ['lead_id' => $leadId, 'email_sha256' => hash('sha256', $leadEmail)],
            $operationUuid,
            $request
        );

        return [
            'success' => true,
            'status' => strtolower($finalStatus),
            'deleted' => 1,
            'files_deleted' => $filesDeleted,
            'files_pending' => $filesPending,
            'error_category' => $errorCategory,
            'message' => ($finalStatus === 'SUCCESS') 
                ? 'Lead and associated files were permanently deleted.' 
                : "Lead record was permanently deleted, but {$filesPending} file(s) are pending retry.",
        ];
    }

    /**
     * Bulk delete multiple Leads with controlled batch processing and crash recovery
     */
    public static function bulkDeleteLeads(
        array $leadIds,
        int $actorId,
        ?Request $request = null,
        bool $requirePreExport = false
    ): array {
        Lead::ensureSchema();

        $actor = User::find($actorId);
        if (!$actor || (!$actor->hasPermission('leads.bulk_delete') && $actor->role !== 'admin')) {
            return [
                'status' => 'failed',
                'error_category' => 'AUTHORIZATION_FAILED',
                'error' => 'You do not have permission to bulk delete leads.',
                'deleted' => 0,
            ];
        }

        $leadIds = array_values(array_filter(array_map('intval', $leadIds)));
        if (empty($leadIds)) {
            return [
                'status' => 'failed',
                'error_category' => 'FAILED',
                'error' => 'No valid lead IDs provided for deletion.',
                'deleted' => 0,
            ];
        }

        $targetUserId = ($actor->role === 'admin') ? $actor->id : $actor->id;
        $totalRequested = count($leadIds);
        $operationUuid = bin2hex(random_bytes(16));

        // Create durable operation tracker
        $operation = LeadDeletionOperation::create([
            'operation_uuid' => $operationUuid,
            'user_id' => $targetUserId,
            'actor_id' => $actor->id,
            'action_type' => 'bulk_delete',
            'scope' => 'selected',
            'requested_count' => $totalRequested,
            'status' => 'processing',
            'total_batches' => (int)ceil($totalRequested / 500),
        ]);

        $completedCount = 0;
        $failedCount = 0;
        $totalFilesDeleted = 0;
        $totalFilesPending = 0;
        $totalStorageFreed = 0;

        // Process in controlled batches of 500
        $chunks = array_chunk($leadIds, 500);
        $batchIndex = 0;

        foreach ($chunks as $chunk) {
            $batchIndex++;
            $operation->update(['current_batch' => $batchIndex]);

            foreach ($chunk as $id) {
                $res = self::deleteLeadPermanently($id, $actorId, $request);
                if ($res['deleted'] > 0) {
                    $completedCount++;
                    $totalFilesDeleted += ($res['files_deleted'] ?? 0);
                    $totalFilesPending += ($res['files_pending'] ?? 0);
                } else {
                    $failedCount++;
                }
            }

            $operation->update([
                'completed_count' => $completedCount,
                'failed_count' => $failedCount,
                'files_deleted' => $totalFilesDeleted,
                'files_pending' => $totalFilesPending,
            ]);
        }

        $finalStatus = ($failedCount === 0 && $totalFilesPending === 0) ? 'completed' : 'partial';
        $errorCategory = ($finalStatus === 'completed') ? 'SUCCESS' : 'PARTIAL';

        $operation->update([
            'status' => $finalStatus,
            'error_category' => $errorCategory,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        LeadAuditService::logDestructiveAction(
            'bulk_delete_leads',
            $targetUserId,
            $totalRequested,
            $completedCount,
            $failedCount,
            $totalFilesDeleted,
            $totalFilesPending,
            $totalStorageFreed,
            strtoupper($finalStatus),
            $errorCategory,
            ['operation_uuid' => $operationUuid],
            $operationUuid,
            $request
        );

        return [
            'status' => $finalStatus,
            'operation_uuid' => $operationUuid,
            'requested' => $totalRequested,
            'deleted' => $completedCount,
            'failed' => $failedCount,
            'files_deleted' => $totalFilesDeleted,
            'files_pending' => $totalFilesPending,
            'error_category' => $errorCategory,
        ];
    }

    /**
     * Clear Lead Data by filter scope with mandatory safety confirmation text
     */
    public static function clearLeadData(
        int $actorId,
        string $scope = 'all',
        array $filters = [],
        string $confirmationText = '',
        ?Request $request = null
    ): array {
        Lead::ensureSchema();

        $actor = User::find($actorId);
        if (!$actor || (!$actor->hasPermission('leads.clear') && $actor->role !== 'admin')) {
            return [
                'status' => 'failed',
                'error_category' => 'AUTHORIZATION_FAILED',
                'error' => 'You do not have permission to execute Clear Lead Data operations.',
                'deleted' => 0,
            ];
        }

        // Section 11 Safety: Clearing all leads strictly requires typing "DELETE ALL LEADS"
        if ($scope === 'all') {
            if (trim($confirmationText) !== 'DELETE ALL LEADS') {
                return [
                    'status' => 'failed',
                    'error_category' => 'AUTHORIZATION_FAILED',
                    'error' => 'Safety validation failed: You must type exactly "DELETE ALL LEADS" to confirm complete data deletion.',
                    'deleted' => 0,
                ];
            }
        }

        $where = [];
        $params = [];

        if ($actor->role !== 'admin') {
            $where[] = "user_id = :uid";
            $params['uid'] = $actor->id;
        } elseif (!empty($filters['user_id'])) {
            $where[] = "user_id = :uid";
            $params['uid'] = (int)$filters['user_id'];
        }

        if ($scope === 'status' && !empty($filters['status'])) {
            $where[] = "status = :stat";
            $params['stat'] = $filters['status'];
        }

        if ($scope === 'source' && !empty($filters['source'])) {
            $where[] = "source = :src";
            $params['src'] = $filters['source'];
        }

        if ($scope === 'date_range') {
            if (!empty($filters['start_date'])) {
                $where[] = "created_at >= :start";
                $params['start'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $where[] = "created_at <= :end";
                $params['end'] = $filters['end_date'] . ' 23:59:59';
            }
        }

        $whereSql = !empty($where) ? implode(' AND ', $where) : "1=1";
        $ids = Database::query("SELECT id FROM leads WHERE {$whereSql}", $params);
        $leadIds = array_column($ids, 'id');

        if (empty($leadIds)) {
            return [
                'status' => 'completed',
                'deleted' => 0,
                'files_deleted' => 0,
                'files_pending' => 0,
                'message' => 'No leads matched the specified criteria to clear.',
            ];
        }

        $res = self::bulkDeleteLeads($leadIds, $actorId, $request);

        LeadAuditService::log(
            userId: $actorId,
            action: 'lead.clear',
            entityType: 'lead',
            entityId: null,
            beforeState: ['count' => count($leadIds)],
            afterState: ['count' => 0],
            metadata: ['scope' => $scope, 'deleted_count' => $res['deleted'] ?? 0]
        );

        return $res;
    }

    /**
     * Export leads matching filter criteria into a CSV stream
     */
    public static function exportLeadsToCsv(int $userId, bool $isAdmin = false, array $filters = []): void {
        [$leads] = self::queryLeads($userId, $isAdmin, $filters, 50000, 0);

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d_His') . '.csv');
        }

        $out = fopen('php://output', 'w');
        fputs($out, "ID,First Name,Last Name,Email,Phone,Company,Status,Source,Tags,Created At\n");

        foreach ($leads as $l) {
            fputcsv($out, [
                $l->id,
                $l->first_name ?: '',
                $l->last_name ?: '',
                $l->email,
                $l->phone ?: '',
                $l->company ?: '',
                $l->status,
                $l->source,
                $l->tags ?: '',
                $l->created_at,
            ]);
        }

        fclose($out);
    }

    public static function bulkDeletePermanently(int $actorId, array $leadIds, array $options = []): array {
        $res = self::bulkDeleteLeads($leadIds, $actorId);
        $res['success'] = in_array($res['status'] ?? '', ['completed', 'success', 'partial']);
        $res['deleted_count'] = $res['deleted'] ?? 0;
        $res['queued_cleanups'] = $res['files_pending'] ?? 0;
        return $res;
    }

    public static function clearAllLeads(int $userId, array $options = []): array {
        $confirmationText = trim((string)($options['confirmation_text'] ?? ''));
        if ($confirmationText !== 'DELETE ALL LEADS') {
            throw new \Exception('Clear leads operation requires explicit confirmation phrase');
        }

        $res = self::clearLeadData($userId, 'all', $options['scope'] ?? [], $confirmationText);
        $res['success'] = ($res['status'] ?? '') === 'completed';
        $res['deleted_count'] = $res['deleted'] ?? 0;
        return $res;
    }

    public static function exportCsv(int $userId, array $filters = []): void {
        self::exportLeadsToCsv($userId, false, $filters);
    }
}
