<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Models\UserLeadPermission;
use App\Services\LeadAuditService;
use Exception;

class LeadPermissionController {

    private function requireAdmin(): \App\Models\User {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            flash('error', 'Administrator privileges are required.');
            redirect('/dashboard');
            exit;
        }
        return $user;
    }

    public function show(Request $request, array $params = []): string {
        $admin = $this->requireAdmin();

        $userId = (int)($params['id'] ?? $request->input('id', 0));
        $targetUser = User::find($userId);

        if (!$targetUser) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            exit;
        }

        $allKeys = UserLeadPermission::ALL_PERMISSIONS;
        $userPerms = $targetUser->getLeadPermissions();
        $presets = UserLeadPermission::ROLE_PRESETS;

        return View::render('admin/users/permissions', [
            'admin' => $admin,
            'targetUser' => $targetUser,
            'allKeys' => $allKeys,
            'userPerms' => $userPerms,
            'presets' => $presets,
        ]);
    }

    public function update(Request $request, array $params = []): void {
        $admin = $this->requireAdmin();

        $userId = (int)($params['id'] ?? $request->input('id', 0));
        $targetUser = User::find($userId);

        if (!$targetUser) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            return;
        }

        $submitted = (array)$request->input('permissions', []);
        $beforePerms = $targetUser->getLeadPermissions();

        // Build boolean map for all known keys
        $newPerms = [];
        foreach (UserLeadPermission::ALL_PERMISSIONS as $key) {
            $newPerms[$key] = !empty($submitted[$key]);
        }

        UserLeadPermission::setUserPermissions($targetUser->id, $newPerms, $admin->id);

        LeadAuditService::log(
            userId: $admin->id,
            action: 'lead_permission.update',
            entityType: 'user',
            entityId: $targetUser->id,
            beforeState: $beforePerms,
            afterState: $newPerms,
            metadata: ['target_user_email' => $targetUser->email],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Lead permissions updated for {$targetUser->name} ({$targetUser->email}).");
        redirect("/admin/users/{$targetUser->id}/permissions");
    }

    public function applyPreset(Request $request, array $params = []): void {
        $admin = $this->requireAdmin();

        $userId = (int)($params['id'] ?? $request->input('id', 0));
        $targetUser = User::find($userId);

        if (!$targetUser) {
            flash('error', 'User not found.');
            redirect('/admin/users');
            return;
        }

        $preset = trim((string)$request->input('preset', ''));
        if (!isset(UserLeadPermission::ROLE_PRESETS[$preset])) {
            flash('error', "Invalid permission preset '{$preset}'.");
            redirect("/admin/users/{$targetUser->id}/permissions");
            return;
        }

        $beforePerms = $targetUser->getLeadPermissions();
        $targetUser->applyLeadRolePreset($preset, $admin->id);
        $afterPerms = $targetUser->getLeadPermissions();

        LeadAuditService::log(
            userId: $admin->id,
            action: 'lead_permission.apply_preset',
            entityType: 'user',
            entityId: $targetUser->id,
            beforeState: $beforePerms,
            afterState: $afterPerms,
            metadata: ['preset' => $preset, 'target_user_email' => $targetUser->email],
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT')
        );

        flash('success', "Applied '{$preset}' preset to {$targetUser->name}.");
        redirect("/admin/users/{$targetUser->id}/permissions");
    }
}
