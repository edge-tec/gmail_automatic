<?php
namespace App\Services;

use App\Models\LeadAuditLog;
use App\Models\User;
use App\Core\Auth;
use App\Core\Request;

class LeadAuditService {

    /**
     * Record a destructive operation in the immutable lead audit log
     */
    public static function logDestructiveAction(
        string $action,
        int $targetUserId,
        int $requestedCount,
        int $deletedCount,
        int $failedCount,
        int $filesDeleted = 0,
        int $filesPending = 0,
        int $storageDeleted = 0,
        string $result = 'SUCCESS',
        ?string $errorCategory = null,
        ?array $metadata = null,
        ?string $operationUuid = null,
        ?Request $request = null
    ): LeadAuditLog {
        LeadAuditLog::ensureSchema();

        $actor = Auth::user();
        $actorId = $actor ? $actor->id : 0;
        $actorEmail = $actor ? $actor->email : 'system@platform.local';

        $ip = null;
        $ua = null;
        if ($request) {
            $ip = $request->ip();
            $ua = $request->server('HTTP_USER_AGENT') ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        return LeadAuditLog::record([
            'operation_uuid' => $operationUuid,
            'actor_id' => $actorId,
            'actor_email' => $actorEmail,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'requested_count' => $requestedCount,
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'files_deleted' => $filesDeleted,
            'files_pending' => $filesPending,
            'storage_deleted' => $storageDeleted,
            'result' => $result,
            'error_category' => $errorCategory,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }

    /**
     * Log permission change by an administrator
     */
    public static function logPermissionChange(int $adminId, string $adminEmail, int $targetUserId, array $changes, ?Request $request = null): LeadAuditLog {
        $ip = $request ? $request->ip() : ($_SERVER['REMOTE_ADDR'] ?? null);
        $ua = $request ? $request->server('HTTP_USER_AGENT') : ($_SERVER['HTTP_USER_AGENT'] ?? null);

        return LeadAuditLog::record([
            'actor_id' => $adminId,
            'actor_email' => $adminEmail,
            'target_user_id' => $targetUserId,
            'action' => 'permission_change',
            'requested_count' => count($changes),
            'deleted_count' => 0,
            'failed_count' => 0,
            'result' => 'SUCCESS',
            'metadata' => ['changes' => $changes],
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }

    public static function log(
        int $userId,
        string $action,
        string $entityType = 'lead',
        ?int $entityId = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): LeadAuditLog {
        LeadAuditLog::ensureSchema();

        $user = User::find($userId);
        $userEmail = $user ? $user->email : 'system@platform.local';

        $metadataPayload = array_merge($metadata, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $beforeState,
            'after' => $afterState,
        ]);

        return LeadAuditLog::record([
            'actor_id' => $userId,
            'actor_email' => $userEmail,
            'target_user_id' => $userId,
            'action' => $action,
            'requested_count' => 1,
            'deleted_count' => (str_contains($action, 'delete') || str_contains($action, 'clear')) ? 1 : 0,
            'failed_count' => 0,
            'result' => 'SUCCESS',
            'metadata' => $metadataPayload,
            'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? null),
            'user_agent' => $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? null),
        ]);
    }

    public static function getLogs(?int $userId = null, int $page = 1, int $perPage = 25, array $filters = []): array {
        LeadAuditLog::ensureSchema();
        $offset = max(0, ($page - 1) * $perPage);
        $filterParams = array_merge($filters, $userId ? ['user_id' => $userId] : []);

        [$items, $total] = LeadAuditLog::queryLogs($filterParams, $perPage, $offset);

        foreach ($items as $item) {
            $item->before_state = $item->metadata['before'] ?? null;
            $item->after_state = $item->metadata['after'] ?? null;
            $item->entity_type = $item->metadata['entity_type'] ?? 'lead';
            $item->entity_id = $item->metadata['entity_id'] ?? null;
            $item->user_id = $item->target_user_id;
        }

        return [
            'items' => $items,
            'total' => $total,
            'current_page' => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'per_page' => $perPage,
        ];
    }
}
