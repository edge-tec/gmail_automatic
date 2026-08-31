<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> System Email Notification Logs</h4>
        <p class="text-muted small mb-0">Monitor all system-generated email notification jobs dispatched via your configured SMTP server.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/admin/smtp') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-server me-1"></i> SMTP Settings
        </a>
        <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('/admin/email-logs') ?>" class="btn btn-sm <?= empty($currentStatus) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    All Logs
                </a>
                <a href="<?= url('/admin/email-logs?status=pending') ?>" class="btn btn-sm <?= $currentStatus === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    Pending
                </a>
                <a href="<?= url('/admin/email-logs?status=sent') ?>" class="btn btn-sm <?= $currentStatus === 'sent' ? 'btn-success' : 'btn-outline-success' ?>">
                    Sent
                </a>
                <a href="<?= url('/admin/email-logs?status=failed') ?>" class="btn btn-sm <?= $currentStatus === 'failed' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    Failed
                </a>
                <a href="<?= url('/admin/email-logs?status=cancelled') ?>" class="btn btn-sm <?= $currentStatus === 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                    Cancelled
                </a>
            </div>
            <div class="text-muted small">
                Showing latest <?= count($logs) ?> email notifications
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="py-3">ID</th>
                    <th class="py-3">Recipient</th>
                    <th class="py-3">Template / Type</th>
                    <th class="py-3">Subject</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Attempts</th>
                    <th class="py-3">Scheduled / Sent</th>
                    <th class="text-end py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-inbox fs-3 d-block mb-2 text-secondary"></i>
                        No email notification logs found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $job): ?>
                <tr>
                    <td class="fw-bold">#<?= $job->id ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= e($job->recipient_name ?? 'User') ?></div>
                        <div class="text-muted small"><?= e($job->recipient_email) ?></div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace">
                            <?= e($job->template_slug ?? 'custom') ?>
                        </span>
                    </td>
                    <td>
                        <div class="text-truncate fw-semibold text-dark" style="max-width: 250px;" title="<?= e($job->subject) ?>">
                            <?= e($job->subject) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($job->status === 'sent'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                <i class="fa-solid fa-check me-1"></i> Sent
                            </span>
                        <?php elseif ($job->status === 'pending'): ?>
                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1">
                                <i class="fa-solid fa-clock me-1"></i> Pending
                            </span>
                        <?php elseif ($job->status === 'processing'): ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                <i class="fa-solid fa-spinner fa-spin me-1"></i> Processing
                            </span>
                        <?php elseif ($job->status === 'failed'): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1" title="<?= e($job->last_error ?? '') ?>">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Failed
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">
                                <?= ucfirst($job->status) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <?= $job->attempts ?> / <?= $job->max_attempts ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($job->sent_at): ?>
                            <div class="small fw-semibold text-dark"><?= date('d M Y, H:i', strtotime($job->sent_at)) ?></div>
                        <?php else: ?>
                            <div class="small text-muted"><?= date('d M Y, H:i', strtotime($job->scheduled_at)) ?></div>
                        <?php endif; ?>
                        <?php if ($job->last_error): ?>
                            <div class="text-danger small text-truncate" style="max-width: 180px;" title="<?= e($job->last_error) ?>">
                                <?= e($job->last_error) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal_<?= $job->id ?>" title="View Email Preview">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php if ($job->status === 'failed' || $job->status === 'cancelled' || $job->status === 'pending'): ?>
                            <form action="<?= url('/admin/email-logs/' . $job->id . '/resend') ?>" method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Resend / Retry Delivery">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- Email Detail Modal -->
                        <div class="modal fade text-start" id="modal_<?= $job->id ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title fw-bold">
                                            <i class="fa-solid fa-envelope text-primary me-2"></i> Email Notification #<?= $job->id ?>
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="bg-light p-3 rounded-3 border mb-3 small">
                                            <div class="row g-2">
                                                <div class="col-6"><strong>To:</strong> <?= e($job->recipient_name ?? '') ?> &lt;<?= e($job->recipient_email) ?>&gt;</div>
                                                <div class="col-6"><strong>Template:</strong> <code><?= e($job->template_slug ?? 'custom') ?></code></div>
                                                <div class="col-12"><strong>Subject:</strong> <?= e($job->subject) ?></div>
                                                <div class="col-6"><strong>Status:</strong> <?= ucfirst($job->status) ?> (Attempts: <?= $job->attempts ?>/<?= $job->max_attempts ?>)</div>
                                                <div class="col-6"><strong>Sent At:</strong> <?= $job->sent_at ? date('d M Y, H:i:s', strtotime($job->sent_at)) : 'Not sent yet' ?></div>
                                                <?php if ($job->last_error): ?>
                                                <div class="col-12 text-danger"><strong>Error:</strong> <?= e($job->last_error) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <h6 class="fw-bold small text-muted mb-2">Rendered HTML Body:</h6>
                                        <div class="p-3 bg-white border rounded-3" style="max-height: 400px; overflow-y: auto;">
                                            <?= $job->body ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
