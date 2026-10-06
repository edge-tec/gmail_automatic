<?php
/**
 * Admin Lead Permissions & RBAC View for User
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-user-shield text-info me-2"></i>Lead Access Control (RBAC)</h4>
        <p class="text-muted small mb-0">Configure granular lead permissions or apply role presets for <strong><?= e($targetUser->name) ?></strong> (<code><?= e($targetUser->email) ?></code>).</p>
    </div>
    <div>
        <a href="<?= url('/admin/users') ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Users</span>
        </a>
    </div>
</div>

<!-- Role Presets Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i>Quick Role Presets</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">Apply a standardized set of permissions with a single click:</p>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between bg-light">
                    <div>
                        <div class="fw-bold text-dark mb-1">Lead Viewer</div>
                        <div class="small text-muted mb-3">Read-only access to browse leads and view lead files.</div>
                    </div>
                    <form action="<?= url("/admin/users/{$targetUser->id}/permissions/preset") ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="preset" value="lead_viewer">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Apply Viewer</button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between bg-light">
                    <div>
                        <div class="fw-bold text-dark mb-1">Lead Editor</div>
                        <div class="small text-muted mb-3">Can view, create, edit, and import leads and view storage.</div>
                    </div>
                    <form action="<?= url("/admin/users/{$targetUser->id}/permissions/preset") ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="preset" value="lead_editor">
                        <button type="submit" class="btn btn-sm btn-outline-success w-100">Apply Editor</button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between bg-light">
                    <div>
                        <div class="fw-bold text-dark mb-1">Lead Manager</div>
                        <div class="small text-muted mb-3">Full lead lifecycle including single deletion, import, and export.</div>
                    </div>
                    <form action="<?= url("/admin/users/{$targetUser->id}/permissions/preset") ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="preset" value="lead_manager">
                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark w-100">Apply Manager</button>
                    </form>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between bg-light">
                    <div>
                        <div class="fw-bold text-dark mb-1">Lead Admin</div>
                        <div class="small text-muted mb-3">Unrestricted access: bulk deletion, clear-all, and disk cleanups.</div>
                    </div>
                    <form action="<?= url("/admin/users/{$targetUser->id}/permissions/preset") ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="preset" value="lead_admin">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Apply Admin</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Granular Permissions Form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-sliders text-success me-2"></i>Granular Permission Matrix</h6>
    </div>
    <div class="card-body">
        <form action="<?= url("/admin/users/{$targetUser->id}/permissions") ?>" method="POST">
            <?= csrf_field() ?>

            <!-- Lead Lifecycle Group -->
            <h6 class="fw-bold text-secondary text-uppercase small mb-3 border-bottom pb-2">
                <i class="fa-solid fa-users me-1"></i> Lead Lifecycle &amp; Deletion
            </h6>
            <div class="row g-3 mb-4">
                <?php 
                    $leadOps = [
                        'leads.view' => 'View and search lead records',
                        'leads.create' => 'Create new leads manually',
                        'leads.edit' => 'Edit lead profiles, archive, and restore',
                        'leads.delete' => 'Permanently delete individual leads',
                        'leads.bulk_delete' => 'Execute batch / multi-lead deletions',
                        'leads.clear' => 'Execute total account wipe (Requires DELETE ALL LEADS confirmation)',
                        'leads.import' => 'Import CSV lead files into database',
                        'leads.export' => 'Export leads to CSV format',
                        'leads.manage' => 'Full lead management administrative authority',
                    ];
                ?>
                <?php foreach ($leadOps as $permKey => $desc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check form-switch p-3 border rounded h-100 <?= !empty($userPerms[$permKey]) ? 'bg-light border-success-subtle' : '' ?>">
                            <input class="form-check-input ms-0 me-3" type="checkbox" name="permissions[<?= $permKey ?>]" value="1" id="perm_<?= str_replace('.', '_', $permKey) ?>" <?= !empty($userPerms[$permKey]) ? 'checked' : '' ?>>
                            <label class="form-check-label d-block cursor-pointer" for="perm_<?= str_replace('.', '_', $permKey) ?>">
                                <div class="fw-semibold text-dark font-monospace small"><?= $permKey ?></div>
                                <div class="text-muted" style="font-size: 11px;"><?= $desc ?></div>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Storage & File Group -->
            <h6 class="fw-bold text-secondary text-uppercase small mb-3 border-bottom pb-2">
                <i class="fa-solid fa-hard-drive me-1"></i> File &amp; Storage Maintenance
            </h6>
            <div class="row g-3 mb-4">
                <?php 
                    $storageOps = [
                        'lead_files.view' => 'View uploaded lead files and storage usage statistics',
                        'lead_files.delete' => 'Delete lead file metadata and storage references',
                        'lead_files.cleanup' => 'Trigger physical disk cleanup outbox processor and retry tasks',
                        'lead_storage.view' => 'Scan physical storage for orphaned files and broken references',
                    ];
                ?>
                <?php foreach ($storageOps as $permKey => $desc): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check form-switch p-3 border rounded h-100 <?= !empty($userPerms[$permKey]) ? 'bg-light border-success-subtle' : '' ?>">
                            <input class="form-check-input ms-0 me-3" type="checkbox" name="permissions[<?= $permKey ?>]" value="1" id="perm_<?= str_replace('.', '_', $permKey) ?>" <?= !empty($userPerms[$permKey]) ? 'checked' : '' ?>>
                            <label class="form-check-label d-block cursor-pointer" for="perm_<?= str_replace('.', '_', $permKey) ?>">
                                <div class="fw-semibold text-dark font-monospace small"><?= $permKey ?></div>
                                <div class="text-muted" style="font-size: 11px;"><?= $desc ?></div>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Audit Trail Group -->
            <h6 class="fw-bold text-secondary text-uppercase small mb-3 border-bottom pb-2">
                <i class="fa-solid fa-clipboard-check me-1"></i> Compliance &amp; Audit Logs
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="form-check form-switch p-3 border rounded h-100 <?= !empty($userPerms['lead_audit.view']) ? 'bg-light border-success-subtle' : '' ?>">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="permissions[lead_audit.view]" value="1" id="perm_lead_audit_view" <?= !empty($userPerms['lead_audit.view']) ? 'checked' : '' ?>>
                        <label class="form-check-label d-block cursor-pointer" for="perm_lead_audit_view">
                            <div class="fw-semibold text-dark font-monospace small">lead_audit.view</div>
                            <div class="text-muted" style="font-size: 11px;">View immutable audit trail and historical deletion diffs</div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('/admin/users') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save User Permissions
                </button>
            </div>
        </form>
    </div>
</div>
