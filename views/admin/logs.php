<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">System &amp; Audit Logs</h4>
        <p class="text-muted small mb-0">Complete audit trail of Gmail API syncs, auto-replies, follow-ups, and worker executions.</p>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center p-5 text-muted">No logs recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-muted">
                    <tr>
                        <th style="width: 80px;">Type</th>
                        <th>Event Message</th>
                        <th>User</th>
                        <th>Account</th>
                        <th>IP Address</th>
                        <th class="text-end">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php if ($log['log_type'] === 'success'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                            <?php elseif ($log['log_type'] === 'error'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Error</span>
                            <?php elseif ($log['log_type'] === 'warning'): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Warning</span>
                            <?php else: ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Info</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-medium text-break"><?= e($log['message']) ?></div>
                            <?php if ($log['context_json']): ?>
                                <div class="text-muted font-monospace small mt-1"><?= e($log['context_json']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($log['user_name'] ?? 'System') ?></td>
                        <td>
                            <?= $log['gmail_email'] ? '<span class="badge bg-light text-dark border">' . e($log['gmail_email']) . '</span>' : '—' ?>
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
