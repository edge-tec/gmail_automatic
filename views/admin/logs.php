<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">System &amp; Audit Logs</h4>
        <p class="text-muted small mb-0">Complete audit trail of Gmail API syncs, auto-replies, follow-ups, and worker executions.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center p-5 text-muted">
            <i class="fa-solid fa-inbox fs-2 d-block mb-2 text-secondary"></i>
            No logs recorded yet.
        </div>
        <?php else: ?>
        <!-- Mobile Card List View (< 768px) -->
        <div class="d-md-none divide-y">
            <?php foreach ($logs as $log): ?>
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <?php if ($log['log_type'] === 'success'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Success</span>
                        <?php elseif ($log['log_type'] === 'error'): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Error</span>
                        <?php elseif ($log['log_type'] === 'warning'): ?>
                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1">Warning</span>
                        <?php else: ?>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">Info</span>
                        <?php endif; ?>
                    </div>
                    <span class="font-monospace text-muted small" style="font-size: 0.75rem;">
                        <i class="fa-regular fa-clock me-1"></i><?= date('M d, H:i:s', strtotime($log['created_at'])) ?>
                    </span>
                </div>
                <div class="fw-semibold text-dark mb-2" style="font-size: 0.88rem; line-height: 1.5;">
                    <?= e($log['message']) ?>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                    <span><i class="fa-solid fa-user me-1 text-secondary"></i><?= e($log['user_name'] ?? 'System') ?></span>
                    <?php if (!empty($log['gmail_email'])): ?>
                        <span>&bull;</span>
                        <span class="badge bg-light text-dark border font-monospace"><?= e($log['gmail_email']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($log['ip_address'])): ?>
                        <span>&bull;</span>
                        <span class="font-monospace text-secondary" style="font-size: 0.72rem;"><?= e($log['ip_address']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($log['context_json'])): ?>
                    <div class="text-muted font-monospace small mt-2 p-2 bg-light rounded" style="font-size: 0.75rem; word-break: break-all;">
                        <?= e($log['context_json']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Desktop Table View (>= 768px) -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem; min-width: 650px;">
                <thead class="table-light text-muted">
                    <tr>
                        <th style="width: 90px;">Type</th>
                        <th style="min-width: 250px;">Event Message</th>
                        <th style="width: 120px;">User</th>
                        <th style="width: 160px;">Account</th>
                        <th style="width: 110px;">IP Address</th>
                        <th class="text-end" style="width: 150px;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php if ($log['log_type'] === 'success'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Success</span>
                            <?php elseif ($log['log_type'] === 'error'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Error</span>
                            <?php elseif ($log['log_type'] === 'warning'): ?>
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1">Warning</span>
                            <?php else: ?>
                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">Info</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-medium text-break" style="line-height: 1.5;"><?= e($log['message']) ?></div>
                            <?php if ($log['context_json']): ?>
                                <div class="text-muted font-monospace small mt-1" style="word-break: break-all;"><?= e($log['context_json']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($log['user_name'] ?? 'System') ?></td>
                        <td>
                            <?= $log['gmail_email'] ? '<span class="badge bg-light text-dark border font-monospace">' . e($log['gmail_email']) . '</span>' : '—' ?>
                        </td>
                        <td class="font-monospace text-muted" style="font-size: 0.75rem;"><?= e($log['ip_address']) ?></td>
                        <td class="text-end font-monospace text-muted" style="font-size: 0.75rem;">
                            <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
