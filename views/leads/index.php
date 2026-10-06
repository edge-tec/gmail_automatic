<?php
/**
 * Lead Management Dashboard & Index View
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-address-book text-success me-2"></i>Enterprise Lead Management</h4>
        <p class="text-muted small mb-0">Manage customer leads, import/export records, track file associations, and perform transaction-safe deletions.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if ($user->hasLeadPermission('leads.export')): ?>
            <a href="<?= url('/leads/export?' . http_build_query($filters)) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-export"></i>
                <span>Export CSV</span>
            </a>
        <?php endif; ?>

        <?php if ($user->hasLeadPermission('leads.import')): ?>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importLeadModal">
                <i class="fa-solid fa-file-import"></i>
                <span>Import Leads</span>
            </button>
        <?php endif; ?>

        <?php if ($user->hasLeadPermission('leads.create')): ?>
            <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createLeadModal">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add New Lead</span>
            </button>
        <?php endif; ?>

        <?php if ($user->hasLeadPermission('leads.clear')): ?>
            <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#clearAllLeadsModal">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Clear All Leads</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-primary-subtle text-primary rounded-3">
                    <i class="fa-solid fa-users fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Leads</div>
                    <div class="fs-4 fw-bold"><?= number_format($kpis['total_leads']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-success-subtle text-success rounded-3">
                    <i class="fa-solid fa-user-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Active Leads</div>
                    <div class="fs-4 fw-bold"><?= number_format($kpis['active_leads']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-warning-subtle text-warning rounded-3">
                    <i class="fa-solid fa-box-archive fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Archived Leads</div>
                    <div class="fs-4 fw-bold"><?= number_format($kpis['archived_leads']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-info-subtle text-info rounded-3">
                    <i class="fa-solid fa-paperclip fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Leads with Files</div>
                    <div class="fs-4 fw-bold"><?= number_format($kpis['leads_with_files']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form action="<?= url('/leads') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, company, phone..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sort_by" class="form-select">
                    <option value="created_at" <?= ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' ?>>Newest First</option>
                    <option value="email" <?= ($filters['sort_by'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="first_name" <?= ($filters['sort_by'] ?? '') === 'first_name' ? 'selected' : '' ?>>First Name</option>
                    <option value="company" <?= ($filters['sort_by'] ?? '') === 'company' ? 'selected' : '' ?>>Company</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sort_direction" class="form-select">
                    <option value="desc" <?= ($filters['sort_direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Descending</option>
                    <option value="asc" <?= ($filters['sort_direction'] ?? '') === 'asc' ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="<?= url('/leads') ?>" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Action Banner -->
<?php if ($user->hasLeadPermission('leads.bulk_delete')): ?>
<div id="bulkActionBar" class="card border-0 shadow-sm mb-3 bg-light d-none">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-semibold text-primary" id="selectedCountText">0 leads selected</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                <i class="fa-solid fa-trash-can"></i>
                <span>Delete Selected</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Leads Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 900px;">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                        </th>
                        <th style="width: 60px;">ID</th>
                        <th>Lead Info</th>
                        <th>Company &amp; Title</th>
                        <th>Phone</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Files</th>
                        <th>Created</th>
                        <th class="text-end" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leadData['items'])): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fs-1 mb-2 d-block text-secondary-emphasis"></i>
                                <span class="fw-semibold">No leads found matching your criteria.</span>
                                <div class="mt-2">
                                    <?php if ($user->hasLeadPermission('leads.create')): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createLeadModal">
                                            Create First Lead
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leadData['items'] as $lead): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input lead-select-checkbox" value="<?= $lead->id ?>">
                                </td>
                                <td class="font-monospace text-muted small">#<?= $lead->id ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle bg-success-subtle text-success fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; font-size: 12px;">
                                            <?= strtoupper(substr($lead->first_name ?: $lead->email, 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= e($lead->getFullName() ?: '—') ?></div>
                                            <div class="small text-muted font-monospace"><?= e($lead->email) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <div><?= e($lead->company ?: '—') ?></div>
                                    <div class="text-muted" style="font-size: 11px;"><?= e($lead->title ?: '') ?></div>
                                </td>
                                <td class="small"><?= e($lead->phone ?: '—') ?></td>
                                <td class="small">
                                    <span class="badge bg-light text-secondary border"><?= e($lead->source ?: 'manual') ?></span>
                                </td>
                                <td>
                                    <?php if ($lead->status === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php elseif ($lead->status === 'archived'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Archived</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= e(ucfirst($lead->status)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php $fCount = count($lead->file_ids ?? []); ?>
                                    <?php if ($fCount > 0): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle" title="<?= $fCount ?> attached file(s)">
                                            <i class="fa-solid fa-paperclip me-1"></i><?= $fCount ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" style="font-size: 11px;">
                                    <?= date('M d, Y', strtotime($lead->created_at)) ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <!-- View Details -->
                                        <button type="button" class="btn btn-sm btn-outline-info view-lead-btn" data-id="<?= $lead->id ?>" title="View Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        <?php if ($user->hasLeadPermission('leads.edit')): ?>
                                            <!-- Edit Lead -->
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-lead-btn" 
                                                data-id="<?= $lead->id ?>" 
                                                data-email="<?= e($lead->email) ?>"
                                                data-first-name="<?= e($lead->first_name ?? '') ?>"
                                                data-last-name="<?= e($lead->last_name ?? '') ?>"
                                                data-phone="<?= e($lead->phone ?? '') ?>"
                                                data-company="<?= e($lead->company ?? '') ?>"
                                                data-title="<?= e($lead->title ?? '') ?>"
                                                data-source="<?= e($lead->source ?? '') ?>"
                                                data-status="<?= e($lead->status ?? '') ?>"
                                                data-notes="<?= e($lead->notes ?? '') ?>"
                                                title="Edit Lead">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <!-- Archive / Restore -->
                                            <?php if ($lead->status === 'active'): ?>
                                                <form action="<?= url("/leads/{$lead->id}/archive") ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Archive Lead">
                                                        <i class="fa-solid fa-box-archive"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= url("/leads/{$lead->id}/restore") ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Restore Lead">
                                                        <i class="fa-solid fa-rotate-left"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($user->hasLeadPermission('leads.delete')): ?>
                                            <!-- Single Permanent Delete -->
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-lead-btn" 
                                                data-id="<?= $lead->id ?>" 
                                                data-email="<?= e($lead->email) ?>"
                                                title="Permanently Delete Lead">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($leadData['last_page'] > 1): ?>
            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">
                    Showing <?= count($leadData['items']) ?> of <?= number_format($leadData['total']) ?> leads
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $leadData['last_page']; $p++): ?>
                            <li class="page-item <?= $p === $leadData['current_page'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url('/leads?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>">
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

<!-- Recent Deletion Operations Card -->
<?php if (!empty($recentOperations)): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Batch Deletion Operations</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Operation ID</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Processed</th>
                        <th>Deleted</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOperations as $op): ?>
                    <tr>
                        <td class="font-monospace">#<?= $op->id ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e($op->operation_type) ?></span></td>
                        <td><?= number_format($op->total_leads) ?></td>
                        <td><?= number_format($op->processed_leads) ?></td>
                        <td class="text-success fw-semibold"><?= number_format($op->deleted_leads) ?></td>
                        <td style="min-width: 120px;">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $op->status === 'completed' ? 'bg-success' : ($op->status === 'failed' ? 'bg-danger' : 'bg-primary progress-bar-striped progress-bar-animated') ?>" style="width: <?= $op->getProgressPercentage() ?>%;"></div>
                            </div>
                            <span style="font-size: 10px;" class="text-muted"><?= $op->getProgressPercentage() ?>%</span>
                        </td>
                        <td>
                            <span class="badge <?= $op->status === 'completed' ? 'bg-success-subtle text-success' : ($op->status === 'failed' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') ?>">
                                <?= e(ucfirst($op->status)) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= date('M d, H:i', strtotime($op->created_at)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==================== MODALS ==================== -->

<!-- Create Lead Modal -->
<div class="modal fade" id="createLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="<?= url('/leads') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-success me-2"></i>Create New Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="contact@example.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" name="first_name" class="form-control" placeholder="John">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" name="last_name" class="form-control" placeholder="Doe">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Company</label>
                            <input type="text" name="company" class="form-control" placeholder="Acme Corp">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Title / Job Role</label>
                            <input type="text" name="title" class="form-control" placeholder="Marketing Director">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Source</label>
                            <select name="source" class="form-select">
                                <option value="manual">Manual Entry</option>
                                <option value="website">Website / Contact Form</option>
                                <option value="campaign">Email Campaign</option>
                                <option value="referral">Referral</option>
                                <option value="import">Import</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Attach Existing File</label>
                            <select name="file_id" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($files as $f): ?>
                                    <option value="<?= $f->id ?>"><?= e($f->file_name) ?> (<?= number_format($f->file_size / 1024, 1) ?> KB)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any initial notes about this lead..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="editLeadForm" action="" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="editEmail" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" id="editFirstName" name="first_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" id="editLastName" name="last_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" id="editPhone" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Company</label>
                            <input type="text" id="editCompany" name="company" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Title / Job Role</label>
                            <input type="text" id="editTitle" name="title" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Source</label>
                            <input type="text" id="editSource" name="source" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select id="editStatus" name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Internal Notes</label>
                            <textarea id="editNotes" name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Lead Modal -->
<div class="modal fade" id="viewLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-id-card text-info me-2"></i>Lead Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewLeadModalBody">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                    <div>Loading lead profile...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Delete Confirmation Modal -->
<div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="singleDeleteForm" action="" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can me-2"></i>Permanent Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to permanently delete lead <strong id="deleteLeadEmail"></strong>?</p>
                    <div class="alert alert-danger py-2 small mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        This operation is immediate and permanent. Database records will be deleted transactionally, and unshared physical storage files will be queued for disk cleanup.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/leads/bulk-delete') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="lead_ids" id="bulkDeleteLeadIds" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Bulk Delete Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">You are about to permanently delete <strong id="bulkDeleteCountText">0</strong> selected lead(s).</p>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        This action will permanently purge the selected leads across database tables. Any unshared files attached exclusively to these leads will be safely removed from disk.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Selected Leads</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear All Leads Modal (Requires DELETE ALL LEADS) -->
<div class="modal fade" id="clearAllLeadsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/leads/clear') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-skull-crossbones me-2"></i>Danger: Clear All Leads</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small">
                        <strong>CRITICAL WARNING:</strong> This action will permanently erase all leads in your account. Active automation jobs for these leads will be cancelled, and all unshared files will be removed.
                    </div>
                    <p class="small text-muted mb-3">To verify you wish to proceed with this irreversible destruction, type <code class="user-select-all fw-bold text-danger">DELETE ALL LEADS</code> below:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Confirmation Phrase <span class="text-danger">*</span></label>
                        <input type="text" name="confirmation_text" id="clearConfirmInput" class="form-control font-monospace" placeholder="DELETE ALL LEADS" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abort</button>
                    <button type="submit" id="clearConfirmBtn" class="btn btn-danger" disabled>Permanently Clear All Leads</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Lead Modal -->
<div class="modal fade" id="importLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/leads/import') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-import text-primary me-2"></i>Import Leads from File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Choose CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="lead_file" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text small">Accepted formats: .csv. File size limit: 50MB.</div>
                    </div>
                    <div class="card bg-light border-0 p-3 small">
                        <div class="fw-semibold mb-1 text-dark">Supported Column Headers:</div>
                        <ul class="mb-0 text-muted ps-3">
                            <li><code>email</code> (Required)</li>
                            <li><code>first_name</code>, <code>last_name</code>, or <code>full_name</code></li>
                            <li><code>phone</code></li>
                            <li><code>company</code></li>
                            <li><code>title</code></li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Checkbox selection management
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.lead-select-checkbox');
    const bulkBar = document.getElementById('bulkActionBar');
    const countText = document.getElementById('selectedCountText');
    const bulkLeadIds = document.getElementById('bulkDeleteLeadIds');
    const bulkDeleteCountText = document.getElementById('bulkDeleteCountText');

    function updateSelection() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (selected.length > 0) {
            bulkBar?.classList.remove('d-none');
            if (countText) countText.textContent = selected.length + ' lead(s) selected';
            if (bulkDeleteCountText) bulkDeleteCountText.textContent = selected.length;
            if (bulkLeadIds) bulkLeadIds.value = selected.join(',');
        } else {
            bulkBar?.classList.add('d-none');
            if (bulkLeadIds) bulkLeadIds.value = '';
        }
    }

    selectAll?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateSelection();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });

    // Clear confirmation phrase button state
    const clearInput = document.getElementById('clearConfirmInput');
    const clearBtn = document.getElementById('clearConfirmBtn');
    clearInput?.addEventListener('input', function() {
        if (clearInput.value.trim() === 'DELETE ALL LEADS') {
            clearBtn.removeAttribute('disabled');
        } else {
            clearBtn.setAttribute('disabled', 'true');
        }
    });

    // Edit modal populator
    document.querySelectorAll('.edit-lead-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const form = document.getElementById('editLeadForm');
            form.action = '<?= url("/leads") ?>/' + id + '/update';
            document.getElementById('editEmail').value = this.dataset.email || '';
            document.getElementById('editFirstName').value = this.dataset.firstName || '';
            document.getElementById('editLastName').value = this.dataset.lastName || '';
            document.getElementById('editPhone').value = this.dataset.phone || '';
            document.getElementById('editCompany').value = this.dataset.company || '';
            document.getElementById('editTitle').value = this.dataset.title || '';
            document.getElementById('editSource').value = this.dataset.source || '';
            document.getElementById('editStatus').value = this.dataset.status || 'active';
            document.getElementById('editNotes').value = this.dataset.notes || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editLeadModal'));
            modal.show();
        });
    });

    // Single delete modal populator
    document.querySelectorAll('.delete-lead-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const email = this.dataset.email;
            const form = document.getElementById('singleDeleteForm');
            form.action = '<?= url("/leads") ?>/' + id + '/delete';
            document.getElementById('deleteLeadEmail').textContent = email;
            const modal = new bootstrap.Modal(document.getElementById('singleDeleteModal'));
            modal.show();
        });
    });

    // View lead details modal
    document.querySelectorAll('.view-lead-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const modalBody = document.getElementById('viewLeadModalBody');
            modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>';
            const modal = new bootstrap.Modal(document.getElementById('viewLeadModal'));
            modal.show();

            fetch('<?= url("/leads") ?>/' + id, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    modalBody.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                    return;
                }
                const l = data.lead;
                let filesHtml = '<div class="text-muted small">No attached files</div>';
                if (data.files && data.files.length > 0) {
                    filesHtml = '<ul class="list-group list-group-flush small">';
                    data.files.forEach(f => {
                        filesHtml += '<li class="list-group-item d-flex justify-content-between align-items-center px-0">' +
                            '<span><i class="fa-solid fa-file me-2 text-primary"></i>' + f.file_name + '</span>' +
                            '<span class="badge bg-light text-dark">' + Math.round(f.file_size / 1024) + ' KB</span>' +
                        '</li>';
                    });
                    filesHtml += '</ul>';
                }

                modalBody.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Full Name</label>
                            <div class="fw-semibold fs-6">${l.first_name || ''} ${l.last_name || ''}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email Address</label>
                            <div class="fw-semibold fs-6 text-primary font-monospace">${l.email}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Company & Title</label>
                            <div>${l.company || '—'} ${l.title ? '(' + l.title + ')' : ''}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Phone</label>
                            <div>${l.phone || '—'}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Source</label>
                            <div><span class="badge bg-light text-dark border">${l.source || 'manual'}</span></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Status</label>
                            <div><span class="badge bg-success-subtle text-success">${l.status}</span></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Created Date</label>
                            <div class="small">${l.created_at}</div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Internal Notes</label>
                            <div class="p-2 bg-light rounded small">${l.notes || 'No notes recorded.'}</div>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Attached File References</label>
                            ${filesHtml}
                        </div>
                    </div>
                `;
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="alert alert-danger">Failed to load lead details.</div>';
            });
        });
    });
});
</script>
