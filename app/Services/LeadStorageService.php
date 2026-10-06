<?php
namespace App\Services;

use App\Core\Database;
use App\Models\LeadFile;
use App\Models\LeadStorageCleanup;
use App\Models\Lead;

class LeadStorageService {

    public static function getStorageDir(?int $userId = null): string {
        $base = storage_path('leads');
        if ($userId) {
            $base .= '/' . $userId;
        }
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return $base;
    }

    /**
     * Store an uploaded file or temp file securely for a lead
     */
    public static function storeLeadFile(int $userId, ?int $leadId, string $sourcePath, string $originalName, ?string $mimeType = null): LeadFile {
        LeadFile::ensureSchema();
        $userDir = self::getStorageDir($userId);

        $hash = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);

        // Check if an identical file hash already exists actively for this user
        $existing = Database::first(
            "SELECT * FROM lead_files WHERE user_id = :uid AND file_hash = :h AND status = 'active' LIMIT 1",
            ['uid' => $userId, 'h' => $hash]
        );

        if ($existing && file_exists(storage_path($existing['file_path']))) {
            // Deduplicate: Reuse existing physical storage file!
            $relativeStoragePath = $existing['file_path'];
            $sanitizedName = $existing['file_name'];
            $targetPath = storage_path($relativeStoragePath);
        } else {
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $cleanExt = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
            $sanitizedName = uniqid('lf_') . '_' . time() . ($cleanExt ? '.' . $cleanExt : '');
            $targetPath = $userDir . '/' . $sanitizedName;
            $relativeStoragePath = 'leads/' . $userId . '/' . $sanitizedName;

            if (!copy($sourcePath, $targetPath)) {
                throw new \Exception("Failed to store file '{$originalName}' into permanent lead storage.");
            }
        }

        return LeadFile::create([
            'user_id' => $userId,
            'lead_id' => $leadId,
            'file_name' => $sanitizedName,
            'original_name' => $originalName,
            'file_path' => $relativeStoragePath,
            'file_size' => $size,
            'mime_type' => $mimeType ?: mime_content_type($targetPath) ?: 'application/octet-stream',
            'file_hash' => $hash,
            'is_orphaned' => empty($leadId) ? 1 : 0,
            'status' => 'active',
        ]);
    }

    public static function storeUploadedLeadFile(int $userId, array $fileData, string $category = 'lead_import', ?int $leadId = null): array {
        try {
            if (empty($fileData['tmp_name']) || !file_exists($fileData['tmp_name'])) {
                return ['success' => false, 'message' => 'Upload file not found or invalid.'];
            }
            $file = self::storeLeadFile(
                $userId,
                $leadId,
                $fileData['tmp_name'],
                $fileData['name'] ?? basename($fileData['tmp_name']),
                $fileData['type'] ?? null
            );
            return ['success' => true, 'file' => $file];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function scanStorageOrphans(?int $userId = null): array {
        $res = self::scanStorage($userId);
        $diskOrphans = [];
        foreach ($res['untracked_on_disk'] ?? [] as $item) {
            $diskOrphans[] = $item['relative_path'] ?? $item['disk_path'];
        }
        return [
            'disk_orphans' => $diskOrphans,
            'db_orphans' => $res['orphaned_db_files'] ?? [],
            'missing_files' => $res['missing_on_disk'] ?? [],
            'total_discrepancies' => $res['total_discrepancies'] ?? 0,
        ];
    }

    public static function deleteFileRecordAndStorage(int $fileId, int $userId, array $options = []): array {
        LeadFile::ensureSchema();
        LeadStorageCleanup::ensureSchema();

        $file = LeadFile::findForUser($fileId, $userId);
        if (!$file) {
            return ['success' => false, 'message' => 'Lead file not found.'];
        }

        // Shared File Protection: check active references
        $refCount = LeadFile::countActiveReferencesByHash($file->file_hash, $file->id);

        if ($refCount > 0) {
            // Other active records reference this file hash! Retain physical file on disk.
            $file->update(['status' => 'deleted']);
            return [
                'success' => true,
                'shared' => true,
                'active_references' => $refCount,
                'message' => "File record deleted. Physical storage retained as {$refCount} other record(s) share this file.",
            ];
        }

        // Sole reference: mark record deleted and dispatch durable cleanup outbox
        $file->update(['status' => 'deleted']);
        self::queueCleanupTask(
            $userId,
            $file->file_path,
            $file->file_hash,
            $file->file_size,
            $file->id
        );

        return [
            'success' => true,
            'shared' => false,
            'active_references' => 0,
            'message' => 'File record deleted and physical cleanup queued in outbox.',
        ];
    }

    public static function processPendingStorageCleanups(int $limit = 50): array {
        $res = self::processPendingCleanups($limit);
        return [
            'success' => ($res['deleted'] ?? 0) + ($res['retained_shared'] ?? 0),
            'failed' => $res['failed'] ?? 0,
        ];
    }

    /**
     * Get real lead storage statistics
     */
    public static function getStorageStatistics(?int $userId = null): array {
        LeadFile::ensureSchema();
        LeadStorageCleanup::ensureSchema();

        $params = [];
        $userWhere = "";
        if ($userId) {
            $userWhere = "WHERE user_id = :uid";
            $params['uid'] = $userId;
        }

        // 1. Total Files & Size from Database
        $totalRow = Database::first(
            "SELECT COUNT(*) as total_files, COALESCE(SUM(file_size), 0) as total_size 
             FROM lead_files {$userWhere} AND status = 'active'",
            $params
        );
        $totalFiles = (int)($totalRow['total_files'] ?? 0);
        $totalSize = (int)($totalRow['total_size'] ?? 0);

        // 2. Orphaned files count in DB
        $orphanRow = Database::first(
            "SELECT COUNT(*) as orphans, COALESCE(SUM(file_size), 0) as orphan_size 
             FROM lead_files 
             WHERE status = 'active' AND (is_orphaned = 1 OR lead_id IS NULL OR lead_id NOT IN (SELECT id FROM leads))" . 
            ($userId ? " AND user_id = :uid" : ""),
            $params
        );
        $orphanedFilesCount = (int)($orphanRow['orphans'] ?? 0);
        $orphanedSize = (int)($orphanRow['orphan_size'] ?? 0);

        // 3. Pending Cleanups
        $cleanupsPendingRow = Database::first(
            "SELECT COUNT(*) as pending FROM lead_storage_cleanups WHERE status = 'pending'" . 
            ($userId ? " AND user_id = :uid" : ""),
            $params
        );
        $pendingCleanups = (int)($cleanupsPendingRow['pending'] ?? 0);

        // 4. Failed Cleanups
        $cleanupsFailedRow = Database::first(
            "SELECT COUNT(*) as failed FROM lead_storage_cleanups WHERE status = 'failed'" . 
            ($userId ? " AND user_id = :uid" : ""),
            $params
        );
        $failedCleanups = (int)($cleanupsFailedRow['failed'] ?? 0);

        // 5. Valid files
        $validFiles = max(0, $totalFiles - $orphanedFilesCount);
        $validSize = max(0, $totalSize - $orphanedSize);

        return [
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => self::formatBytes($totalSize),
            'valid_files' => $validFiles,
            'valid_size_bytes' => $validSize,
            'valid_size_formatted' => self::formatBytes($validSize),
            'orphaned_files' => $orphanedFilesCount,
            'orphaned_size_bytes' => $orphanedSize,
            'orphaned_size_formatted' => self::formatBytes($orphanedSize),
            'pending_cleanups' => $pendingCleanups,
            'failed_cleanups' => $failedCleanups,
        ];
    }

    /**
     * Scan storage and database to discover orphaned files and storage discrepancies
     */
    public static function scanStorage(?int $userId = null): array {
        LeadFile::ensureSchema();

        $discoveredOrphans = [];
        $missingOnDisk = [];
        $untrackedOnDisk = [];

        // Step A: Check DB records whose parent lead no longer exists
        $userFilter = $userId ? " AND lf.user_id = :uid" : "";
        $params = $userId ? ['uid' => $userId] : [];

        $orphanedDbRows = Database::query(
            "SELECT lf.* FROM lead_files lf 
             LEFT JOIN leads l ON l.id = lf.lead_id 
             WHERE lf.status = 'active' AND (lf.lead_id IS NULL OR l.id IS NULL){$userFilter}",
            $params
        );

        foreach ($orphanedDbRows as $row) {
            $file = LeadFile::fromRow($row);
            $file->update(['is_orphaned' => 1]);
            $discoveredOrphans[] = [
                'type' => 'db_record_without_lead',
                'file_id' => $file->id,
                'file_name' => $file->file_name,
                'original_name' => $file->original_name,
                'file_path' => $file->file_path,
                'file_size' => $file->file_size,
                'exists_on_disk' => $file->existsOnDisk(),
            ];
        }

        // Step B: Check DB records missing from disk
        $activeFiles = Database::query(
            "SELECT lf.* FROM lead_files lf WHERE lf.status = 'active'{$userFilter}",
            $params
        );
        foreach ($activeFiles as $row) {
            $file = LeadFile::fromRow($row);
            if (!$file->existsOnDisk()) {
                $missingOnDisk[] = [
                    'file_id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_path' => $file->file_path,
                ];
            }
        }

        // Step C: Scan physical disk directory for files with no DB record
        $baseDir = storage_path('leads');
        if (is_dir($baseDir)) {
            $targetDirs = $userId ? [$baseDir . '/' . $userId] : glob($baseDir . '/*', GLOB_ONLYDIR);
            if (!empty($targetDirs)) {
                foreach ($targetDirs as $uDir) {
                    if (!is_dir($uDir)) continue;
                    $files = scandir($uDir);
                    foreach ($files as $f) {
                        if ($f === '.' || $f === '..' || $f === '.gitignore') continue;
                        $diskFullPath = $uDir . '/' . $f;
                        if (!is_file($diskFullPath)) continue;

                        $rel = 'leads/' . basename($uDir) . '/' . $f;
                        $existsInDb = Database::first(
                            "SELECT id FROM lead_files WHERE file_path = :fp AND status = 'active' LIMIT 1",
                            ['fp' => $rel]
                        );

                        if (!$existsInDb) {
                            $untrackedOnDisk[] = [
                                'type' => 'disk_file_without_db_record',
                                'disk_path' => $diskFullPath,
                                'relative_path' => $rel,
                                'file_size' => filesize($diskFullPath),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'orphaned_db_files' => $discoveredOrphans,
            'missing_on_disk' => $missingOnDisk,
            'untracked_on_disk' => $untrackedOnDisk,
            'total_discrepancies' => count($discoveredOrphans) + count($missingOnDisk) + count($untrackedOnDisk),
        ];
    }

    /**
     * Queue a durable cleanup task in lead_storage_cleanups (Outbox Pattern)
     */
    public static function queueCleanupTask(int $userId, string $filePath, ?string $fileHash = null, int $fileSize = 0, ?int $leadFileId = null, ?int $operationId = null): LeadStorageCleanup {
        LeadStorageCleanup::ensureSchema();

        return LeadStorageCleanup::create([
            'operation_id' => $operationId,
            'lead_file_id' => $leadFileId,
            'user_id' => $userId,
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'file_size' => $fileSize,
            'status' => 'pending',
            'max_retries' => 5,
        ]);
    }

    /**
     * Process pending cleanup outbox tasks with retry backoff and shared file protection
     */
    public static function processPendingCleanups(int $limit = 50): array {
        LeadStorageCleanup::ensureSchema();
        LeadFile::ensureSchema();

        $tasks = LeadStorageCleanup::getPendingCleanups($limit);
        $deletedCount = 0;
        $failedCount = 0;
        $retainedShared = 0;

        foreach ($tasks as $task) {
            $fullPath = $task->getAbsolutePath();

            // 1. Shared File Protection: check if another active record shares this file hash
            if (!empty($task->file_hash)) {
                $activeRefs = LeadFile::countActiveReferencesByHash($task->file_hash, $task->lead_file_id);
                if ($activeRefs > 0) {
                    // Shared file is still in use! Do NOT delete physical file.
                    $task->update([
                        'status' => 'completed',
                        'last_error' => 'Preserved physical file because it is still referenced by ' . $activeRefs . ' other active lead record(s).',
                    ]);
                    if ($task->lead_file_id) {
                        $lf = LeadFile::find($task->lead_file_id);
                        if ($lf) $lf->update(['status' => 'deleted']);
                    }
                    $retainedShared++;
                    continue;
                }
            }

            // 2. Perform physical file deletion
            if (file_exists($fullPath)) {
                $unlinked = @unlink($fullPath);
                if ($unlinked) {
                    $task->update([
                        'status' => 'completed',
                        'last_error' => null,
                    ]);
                    if ($task->lead_file_id) {
                        $lf = LeadFile::find($task->lead_file_id);
                        if ($lf) $lf->update(['status' => 'deleted']);
                    }
                    $deletedCount++;
                } else {
                    // Temporary or permission failure: schedule retry with exponential backoff
                    $newRetry = $task->retry_count + 1;
                    $delayMinutes = pow(2, $newRetry); // 2, 4, 8, 16, 32 mins
                    $driver = config('database.default', 'mysql');
                    $nextRetry = ($driver === 'mysql') 
                        ? date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"))
                        : date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));

                    $isPermanent = ($newRetry >= $task->max_retries);
                    $task->update([
                        'status' => $isPermanent ? 'failed' : 'pending',
                        'retry_count' => $newRetry,
                        'next_retry_at' => $nextRetry,
                        'error_category' => $isPermanent ? 'PERMANENT_FAILURE' : 'TEMPORARY_FAILURE',
                        'last_error' => 'Failed to unlink physical file: ' . (error_get_last()['message'] ?? 'Permission denied or file locked'),
                    ]);
                    $failedCount++;
                }
            } else {
                // File was already absent from disk: desired final state is satisfied!
                $task->update([
                    'status' => 'completed',
                    'last_error' => 'File was already absent on disk.',
                ]);
                if ($task->lead_file_id) {
                    $lf = LeadFile::find($task->lead_file_id);
                    if ($lf) $lf->update(['status' => 'deleted']);
                }
                $deletedCount++;
            }
        }

        return [
            'processed' => count($tasks),
            'deleted' => $deletedCount,
            'failed' => $failedCount,
            'retained_shared' => $retainedShared,
        ];
    }

    /**
     * Clean up all discovered orphaned files (both DB orphaned records and untracked files on disk)
     */
    public static function cleanupOrphanedFiles(?int $userId = null): array {
        $scan = self::scanStorage($userId);
        $deleted = 0;
        $failed = 0;

        // Clean DB orphaned records
        foreach ($scan['orphaned_db_files'] as $item) {
            $file = LeadFile::find($item['file_id']);
            if (!$file) continue;

            // Check shared file protection
            if (!empty($file->file_hash) && LeadFile::countActiveReferencesByHash($file->file_hash, $file->id) > 0) {
                $file->update(['status' => 'deleted']);
                continue;
            }

            $diskPath = $file->getAbsolutePath();
            if (file_exists($diskPath)) {
                if (@unlink($diskPath)) {
                    $deleted++;
                } else {
                    $failed++;
                    self::queueCleanupTask($file->user_id, $file->file_path, $file->file_hash, $file->file_size, $file->id);
                }
            } else {
                $deleted++;
            }
            $file->update(['status' => 'deleted']);
        }

        // Clean untracked files on disk
        foreach ($scan['untracked_on_disk'] as $item) {
            $diskPath = $item['disk_path'];
            if (file_exists($diskPath)) {
                if (@unlink($diskPath)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
            'remaining_pending' => self::getStorageStatistics($userId)['pending_cleanups'],
        ];
    }

    /**
     * Retry all previously failed cleanups
     */
    public static function retryFailedCleanups(?int $userId = null): int {
        LeadStorageCleanup::ensureSchema();
        $params = [];
        $sql = "UPDATE lead_storage_cleanups SET status = 'pending', retry_count = 0, next_retry_at = NULL WHERE status = 'failed'";
        if ($userId) {
            $sql .= " AND user_id = :uid";
            $params['uid'] = $userId;
        }
        Database::execute($sql, $params);
        $result = self::processPendingCleanups(100);
        return $result['deleted'];
    }

    public static function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
