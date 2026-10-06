<?php
/**
 * Lead Files & Storage Manager View
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-folder-tree text-info me-2"></i>Lead Files &amp; Storage Manager</h4>
        <p class="text-muted small mb-0">Monitor physical disk usage, inspect deduplicated file hashes, scan orphan storage files, and manage outbox cleanups.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if ($user->hasLeadPermission('lead_storage.view')): ?>
            <form action="<?= url('/lead-files/scan') ?>" method="POST" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <span>Scan Storage Health</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($user->hasLeadPermission('lead_files.cleanup')): ?>
            <form action="<?= url('/lead-files/cleanup') ?>" method="POST" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning d-flex align-items-center gap-2 text-dark">
                    <i class="fa-solid fa-broom"></i>
                    <span>Process Cleanup Outbox</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($user->hasLeadPermission('leads.create')): ?>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                <i class="fa-solid fa-upload"></i>
                <span>Upload Lead File</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Storage KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-primary-subtle text-primary rounded-3">
                    <i class="fa-solid fa-file-lines fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Files Tracked</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['total_files']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-info-subtle text-info rounded-3">
                    <i class="fa-solid fa-hard-drive fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Storage Utilized</div>
                    <div class="fs-4 fw-bold"><?= $stats['total_size_formatted'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-warning-subtle text-warning rounded-3">
                    <i class="fa-solid fa-clock fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending Cleanups</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['pending_cleanups']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="p-3 bg-danger-subtle text-danger rounded-3">
                    <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small">Failed Cleanups</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['failed_cleanups']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tracked Files Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-list me-2 text-primary"></i>Tracked Lead Storage Files</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 800px;">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>File Name</th>
                        <th>Type / Category</th>
                        <th>Size</th>
                        <th>SHA-256 Hash</th>
                        <th>Shared Protection</th>
                        <th>Uploaded At</th>
                        <th class="text-end" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-1 mb-2 d-block text-secondary-emphasis"></i>
                                No lead files uploaded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <?php 
                                $refCount = !empty($file->file_hash) ? \App\Models\LeadFile::countActiveReferencesByHash($file->file_hash) : 1; 
                            ?>
                            <tr>
                                <td class="font-monospace text-muted small">#<?= $file->id ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-file-csv fs-5 text-success"></i>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= e($file->file_name) ?></div>
                                            <div class="text-muted font-monospace" style="font-size: 10px;"><?= e($file->storage_path) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <span class="badge bg-light text-dark border"><?= e($file->category ?: 'lead_import') ?></span>
                                </td>
                                <td class="small fw-semibold"><?= $file->getFormattedSize() ?></td>
                                <td class="small font-monospace text-muted" style="font-size: 11px;">
                                    <?= substr($file->file_hash, 0, 12) ?>...
                                </td>
                                <td>
                                    <?php if ($refCount > 1): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Shared by <?= $refCount ?> records - protected from premature unlinking">
                                            <i class="fa-solid fa-shield-halved me-1"></i>Shared (<?= $refCount ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Sole Record</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('M d, Y H:i', strtotime($file->created_at)) ?></td>
                                <td class="text-end">
                                    <?php if ($user->hasLeadPermission('lead_files.delete')): ?>
                                        <form action="<?= url("/lead-files/{$file->id}/delete") ?>" method="POST" class="d-inline" onsubmit="return confirm('Permanently remove this file record? Unshared disk files will be safely cleaned.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete File Record">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Storage Outbox Cleanups Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-recycle me-2 text-warning"></i>Durable Storage Cleanup Outbox Tasks</h6>
        <?php if ($stats['failed_cleanups'] > 0 && $user->hasLeadPermission('lead_files.cleanup')): ?>
            <form action="<?= url('/lead-files/retry') ?>" method="POST" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fa-solid fa-rotate-right me-1"></i>Retry <?= $stats['failed_cleanups'] ?> Failed
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Task ID</th>
                        <th>Relative Path</th>
                        <th>Triggered By</th>
                        <th>Retries</th>
                        <th>Next Attempt</th>
                        <th>Status</th>
                        <th>Error Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentCleanups)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Cleanup outbox is currently empty. All physical file removals are up-to-date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentCleanups as $c): ?>
                            <tr>
                                <td class="font-monospace">#<?= $c->id ?></td>
                                <td class="font-monospace"><?= e($c->file_path) ?></td>
                                <td><?= e($c->trigger_type) ?></td>
                                <td><?= $c->retry_count ?></td>
                                <td class="text-muted"><?= $c->next_retry_at ? date('H:i:s', strtotime($c->next_retry_at)) : 'Immediate' ?></td>
                                <td>
                                    <?php if ($c->status === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success">Completed</span>
                                    <?php elseif ($c->status === 'failed'): ?>
                                        <span class="badge bg-danger-subtle text-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-danger small"><?= e($c->error_message ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/lead-files/upload') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-upload text-primary me-2"></i>Upload File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select File <span class="text-danger">*</span></label>
                        <input type="file" name="lead_file" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select">
                            <option value="lead_import">Lead Import Dataset</option>
                            <option value="lead_attachment">Lead Attachment</option>
                            <option value="export_archive">Export Archive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
