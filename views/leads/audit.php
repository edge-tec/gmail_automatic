<?php
/**
 * Lead Audit Logs Trail View
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-list text-primary me-2"></i>Lead Audit Trail</h4>
        <p class="text-muted small mb-0">Immutable, enterprise compliance log recording every lead creation, modification, deletion, and permission change.</p>
    </div>
    <div>
        <a href="<?= url('/leads') ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Leads</span>
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form action="<?= url('/lead-audit') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <option value="lead.create" <?= ($filters['action'] ?? '') === 'lead.create' ? 'selected' : '' ?>>lead.create (Single Creation)</option>
                    <option value="lead.update" <?= ($filters['action'] ?? '') === 'lead.update' ? 'selected' : '' ?>>lead.update (Profile Update)</option>
                    <option value="lead.archive" <?= ($filters['action'] ?? '') === 'lead.archive' ? 'selected' : '' ?>>lead.archive (Soft Delete)</option>
                    <option value="lead.restore" <?= ($filters['action'] ?? '') === 'lead.restore' ? 'selected' : '' ?>>lead.restore (Restoration)</option>
                    <option value="lead.delete" <?= ($filters['action'] ?? '') === 'lead.delete' ? 'selected' : '' ?>>lead.delete (Single Permanent)</option>
                    <option value="lead.bulk_delete" <?= ($filters['action'] ?? '') === 'lead.bulk_delete' ? 'selected' : '' ?>>lead.bulk_delete (Batch Delete)</option>
                    <option value="lead.clear" <?= ($filters['action'] ?? '') === 'lead.clear' ? 'selected' : '' ?>>lead.clear (Total Wipeout)</option>
                    <option value="lead.import" <?= ($filters['action'] ?? '') === 'lead.import' ? 'selected' : '' ?>>lead.import (CSV Import)</option>
                    <option value="lead_file.upload" <?= ($filters['action'] ?? '') === 'lead_file.upload' ? 'selected' : '' ?>>lead_file.upload (Storage File)</option>
                    <option value="lead_file.delete" <?= ($filters['action'] ?? '') === 'lead_file.delete' ? 'selected' : '' ?>>lead_file.delete (Storage File)</option>
                    <option value="lead_permission.update" <?= ($filters['action'] ?? '') === 'lead_permission.update' ? 'selected' : '' ?>>lead_permission.update (RBAC)</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="entity_type" class="form-select">
                    <option value="">All Entity Types</option>
                    <option value="lead" <?= ($filters['entity_type'] ?? '') === 'lead' ? 'selected' : '' ?>>lead</option>
                    <option value="lead_file" <?= ($filters['entity_type'] ?? '') === 'lead_file' ? 'selected' : '' ?>>lead_file</option>
                    <option value="user" <?= ($filters['entity_type'] ?? '') === 'user' ? 'selected' : '' ?>>user</option>
                </select>
            </div>
            <?php if ($user->role === 'admin'): ?>
            <div class="col-md-3">
                <div class="form-check pt-2">
                    <input class="form-check-input" type="checkbox" name="all_users" value="1" id="allUsersCheck" <?= empty($scopeUserId) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="allUsersCheck">
                        Show logs across all users (Admin)
                    </label>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="<?= url('/lead-audit') ?>" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Audit Trail Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 900px;">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 60px;">Log ID</th>
                        <th>Actor</th>
                        <th>Action Performed</th>
                        <th>Target Entity</th>
                        <th>Diff / Changes</th>
                        <th>Client Info</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logData['items'])): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-clipboard-check fs-1 mb-2 d-block text-secondary-emphasis"></i>
                                No audit log records found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logData['items'] as $log): ?>
                            <tr>
                                <td class="font-monospace text-muted small">#<?= $log->id ?></td>
                                <td class="small">
                                    <div class="fw-semibold text-dark">User #<?= $log->user_id ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $badgeColor = match(true) {
                                            str_contains($log->action, 'delete') || str_contains($log->action, 'clear') => 'bg-danger-subtle text-danger border-danger-subtle',
                                            str_contains($log->action, 'create') || str_contains($log->action, 'import') => 'bg-success-subtle text-success border-success-subtle',
                                            str_contains($log->action, 'archive') => 'bg-warning-subtle text-warning border-warning-subtle',
                                            default => 'bg-primary-subtle text-primary border-primary-subtle',
                                        };
                                    ?>
                                    <span class="badge <?= $badgeColor ?> border font-monospace">
                                        <?= e($log->action) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border me-1"><?= e($log->entity_type) ?></span>
                                    <span class="font-monospace text-muted">#<?= $log->entity_id ?? 'N/A' ?></span>
                                </td>
                                <td class="small" style="max-width: 320px;">
                                    <?php if (!empty($log->before_state) || !empty($log->after_state)): ?>
                                        <details class="cursor-pointer">
                                            <summary class="text-primary small user-select-none">View State Details</summary>
                                            <div class="p-2 bg-light rounded mt-1 font-monospace" style="font-size: 10px; max-height: 150px; overflow-y: auto;">
                                                <?php if (!empty($log->before_state)): ?>
                                                    <div class="text-danger fw-bold">Before:</div>
                                                    <pre class="mb-1 text-wrap"><?= e(json_encode($log->before_state, JSON_PRETTY_PRINT)) ?></pre>
                                                <?php endif; ?>
                                                <?php if (!empty($log->after_state)): ?>
                                                    <div class="text-success fw-bold">After:</div>
                                                    <pre class="mb-0 text-wrap"><?= e(json_encode($log->after_state, JSON_PRETTY_PRINT)) ?></pre>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" style="font-size: 11px;">
                                    <div class="font-monospace"><?= e($log->ip_address ?: 'Unknown IP') ?></div>
                                    <div class="text-truncate" style="max-width: 140px;" title="<?= e($log->user_agent ?? '') ?>">
                                        <?= e($log->user_agent ? substr($log->user_agent, 0, 30) . '...' : '—') ?>
                                    </div>
                                </td>
                                <td class="small text-muted" style="font-size: 11px;">
                                    <?= date('M d, Y H:i:s', strtotime($log->created_at)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($logData['last_page'] > 1): ?>
            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">
                    Showing <?= count($logData['items']) ?> of <?= number_format($logData['total']) ?> entries
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $logData['last_page']; $p++): ?>
                            <li class="page-item <?= $p === $logData['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url('/lead-audit?' . http_build_query(array_merge($filters, ['page' => $p, 'all_users' => empty($scopeUserId) ? 1 : 0]))) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
