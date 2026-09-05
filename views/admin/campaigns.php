<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Admin Campaign Oversight</h4>
        <p class="text-muted small mb-0">System-wide monitoring of all bulk email campaigns, sending limits, quotas, and suppression records.</p>
    </div>
</div>

<!-- All System Campaigns -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-layer-group text-primary me-2"></i>All User Campaigns (<?= count($campaigns) ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>ID</th>
                    <th>Campaign</th>
                    <th>User ID</th>
                    <th>Status</th>
                    <th>Sent / Total</th>
                    <th>Daily Limit</th>
                    <th>Schedule</th>
                    <th>Created</th>
                    <th class="text-end">Admin Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted small">No campaigns created yet across the platform.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td class="text-muted">#<?= $c->id ?></td>
                        <td>
                            <strong class="text-dark"><?= e($c->name) ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">User #<?= $c->user_id ?></span>
                        </td>
                        <td>
                            <?php if ($c->status === 'active'): ?>
                                <span class="badge bg-success-subtle text-success">ACTIVE</span>
                            <?php elseif ($c->status === 'paused'): ?>
                                <span class="badge bg-warning-subtle text-warning">PAUSED</span>
                            <?php elseif ($c->status === 'completed'): ?>
                                <span class="badge bg-primary-subtle text-primary">COMPLETED</span>
                            <?php elseif ($c->status === 'cancelled'): ?>
                                <span class="badge bg-danger-subtle text-danger">CANCELLED</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary"><?= strtoupper(e($c->status)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <strong><?= number_format($c->sent_count) ?></strong> / <?= number_format($c->total_recipients) ?>
                            <span class="text-muted">(<?= $c->getProgressPercentage() ?>%)</span>
                        </td>
                        <td class="small"><?= $c->daily_campaign_limit ?>/day</td>
                        <td class="small text-muted"><?= e($c->start_time) ?> - <?= e($c->end_time) ?></td>
                        <td class="small text-muted"><?= $c->created_at ? date('M d, Y', strtotime($c->created_at)) : '-' ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($c->status === 'active'): ?>
                                    <form action="<?= url('/admin/campaigns/' . $c->id . '/pause') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-warning" title="Pause Campaign">Pause</button>
                                    </form>
                                <?php elseif ($c->status === 'paused'): ?>
                                    <form action="<?= url('/admin/campaigns/' . $c->id . '/pause') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-success" title="Resume Campaign">Resume</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($c->status, ['active', 'paused'])): ?>
                                    <form action="<?= url('/admin/campaigns/' . $c->id . '/stop') ?>" method="POST" class="d-inline" onsubmit="return confirm('Force stop and cancel this campaign?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Force Stop">Force Stop</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Gmail Daily Usage -->
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-chart-column text-success me-2"></i>Account Daily Quotas &amp; Telemetry</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Gmail Account</th>
                            <th>User</th>
                            <th>Sent Today</th>
                            <th>Daily Limit</th>
                            <th>Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyUsage)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted small">No daily usage recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dailyUsage as $u): ?>
                            <tr>
                                <td class="small text-muted"><?= e($u['usage_date']) ?></td>
                                <td class="small fw-semibold text-dark"><i class="fa-brands fa-google text-danger me-1"></i><?= e($u['gmail_email']) ?></td>
                                <td class="small text-muted"><?= e($u['user_email']) ?></td>
                                <td class="small fw-bold text-success"><?= $u['emails_sent'] ?></td>
                                <td class="small text-muted"><?= $u['daily_limit'] ?></td>
                                <td class="small text-danger"><?= $u['emails_failed'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Suppressed Recipients -->
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-ban text-danger me-2"></i>Suppression List (<?= count($suppressions) ?>)</h6>
            </div>
            <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th>Suppressed Email</th>
                            <th>Reason</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppressions)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted small">No suppressed recipients recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($suppressions as $s): ?>
                            <tr>
                                <td class="small fw-semibold text-dark"><?= e($s->email) ?></td>
                                <td>
                                    <?php if ($s->reason === 'unsubscribed'): ?>
                                        <span class="badge bg-warning-subtle text-warning">Unsubscribed</span>
                                    <?php elseif ($s->reason === 'hard_bounce'): ?>
                                        <span class="badge bg-danger-subtle text-danger">Hard Bounce</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary"><?= e($s->reason) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= $s->created_at ? date('M d, H:i', strtotime($s->created_at)) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- System Recent Sends Audit Trail -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-dark me-2"></i>System-wide Campaign Sends Audit Trail</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Timestamp</th>
                    <th>Campaign</th>
                    <th>Recipient</th>
                    <th>Gmail Dispatched Via</th>
                    <th>Status</th>
                    <th>Message ID</th>
                    <th>Error Code</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentSends)): ?>
                    <tr><td colspan="7" class="text-center py-3 text-muted small">No send logs recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentSends as $log): ?>
                    <tr>
                        <td class="text-muted"><?= e($log['created_at']) ?></td>
                        <td><strong class="text-dark"><?= e($log['campaign_name']) ?></strong></td>
                        <td class="fw-semibold text-dark"><?= e($log['recipient_email']) ?></td>
                        <td><i class="fa-brands fa-google text-danger me-1"></i><?= e($log['gmail_email']) ?></td>
                        <td>
                            <?php if ($log['status'] === 'sent'): ?>
                                <span class="badge bg-success-subtle text-success">SENT</span>
                            <?php elseif ($log['status'] === 'failed'): ?>
                                <span class="badge bg-danger-subtle text-danger">FAILED</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary"><?= strtoupper(e($log['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted font-monospace small"><?= e($log['gmail_message_id'] ?: '-') ?></td>
                        <td class="text-danger small"><?= e($log['error_code'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
