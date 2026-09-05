<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('/campaigns') ?>" class="text-decoration-none text-muted small">
                <i class="fa-solid fa-arrow-left me-1"></i> Campaigns
            </a>
            <span class="text-muted small">/</span>
            <span class="text-muted small">Campaign #<?= $campaign->id ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <h4 class="fw-bold mb-0"><?= e($campaign->name) ?></h4>
            <?php if ($campaign->status === 'active'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="fa-solid fa-play me-1"></i> Active
                </span>
            <?php elseif ($campaign->status === 'paused'): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                    <i class="fa-solid fa-pause me-1"></i> Paused
                </span>
            <?php elseif ($campaign->status === 'completed'): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="fa-solid fa-check-circle me-1"></i> Completed
                </span>
            <?php elseif ($campaign->status === 'cancelled'): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                    <i class="fa-solid fa-ban me-1"></i> Cancelled
                </span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                    <i class="fa-solid fa-pen me-1"></i> Draft
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('/campaigns/' . $campaign->id . '/edit') ?>" class="btn btn-outline-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Campaign
        </a>

        <?php if ($campaign->status === 'active' && $campaign->pending_recipients > 0): ?>
            <form action="<?= url('/campaigns/' . $campaign->id . '/send-batch-now') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-paper-plane me-1"></i> Send Next Batch Now
                </button>
            </form>
        <?php endif; ?>

        <?php if ($campaign->status === 'active'): ?>
            <form action="<?= url('/campaigns/' . $campaign->id . '/pause') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-warning">
                    <i class="fa-solid fa-pause me-1"></i> Pause Campaign
                </button>
            </form>
        <?php elseif (in_array($campaign->status, ['paused', 'draft'])): ?>
            <form action="<?= url('/campaigns/' . $campaign->id . '/resume') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-success">
                    <i class="fa-solid fa-play me-1"></i> Resume Campaign
                </button>
            </form>
        <?php endif; ?>

        <?php if (in_array($campaign->status, ['active', 'paused', 'draft'])): ?>
            <form action="<?= url('/campaigns/' . $campaign->id . '/cancel') ?>" method="POST" onsubmit="return confirm('Are you sure you want to permanently cancel this campaign?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fa-solid fa-ban me-1"></i> Cancel
                </button>
            </form>
        <?php endif; ?>

        <form action="<?= url('/campaigns/' . $campaign->id . '/delete') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign and all its recipients?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary" title="Delete Campaign">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </form>
    </div>
</div>

<!-- KPI Stat Cards -->
<?php $progress = $campaign->getProgressPercentage(); ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-users text-primary me-1"></i> Total Recipients</div>
            <h3 class="fw-bold mb-0"><?= number_format($campaign->total_recipients) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-check-double text-success me-1"></i> Sent</div>
            <h3 class="fw-bold text-success mb-0"><?= number_format($campaign->sent_count) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-hourglass-start text-warning me-1"></i> Pending</div>
            <h3 class="fw-bold text-warning mb-0"><?= number_format($campaign->getRemainingCount()) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Failed</div>
            <h3 class="fw-bold text-danger mb-0"><?= number_format($campaign->failed_count) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-forward text-muted me-1"></i> Skipped</div>
            <h3 class="fw-bold text-muted mb-0"><?= number_format($campaign->skipped_count) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card p-3 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-chart-line text-info me-1"></i> Completion</div>
            <h3 class="fw-bold text-info mb-0"><?= $progress ?>%</h3>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small fw-semibold text-muted">Overall Campaign Progress</span>
        <span class="small fw-bold text-dark"><?= number_format($campaign->sent_count) ?> / <?= number_format($campaign->total_recipients) ?> sent (<?= $progress ?>%)</span>
    </div>
    <div class="progress" style="height: 10px;">
        <div class="progress-bar bg-success" style="width: <?= $progress ?>%;"></div>
    </div>
    <div class="d-flex justify-content-between small text-muted mt-2">
        <span><i class="fa-solid fa-gauge me-1"></i> Pace: 1 email / <?= $campaign->sending_interval ?>s</span>
        <span><i class="fa-regular fa-clock me-1"></i> Active Hours: <?= e($campaign->start_time) ?> - <?= e($campaign->end_time) ?> (<?= e($campaign->timezone) ?>)</span>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Round-Robin Gmail Distribution Dashboard -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-brands fa-google text-danger me-2"></i>Gmail Round-Robin Distribution</h6>
                <a href="<?= url('/campaigns/accounts') ?>" class="small text-decoration-none">Manage Limits <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            </div>
            <div class="card-body pt-0">
                <p class="text-muted small mb-3">Sending alternates across connected accounts in true round-robin turns. Accounts reaching limits are automatically skipped until the quota window resets.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Gmail Account</th>
                                <th>Daily Limit</th>
                                <th>Sent Today</th>
                                <th>Remaining</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $item): 
                                $acc = $item['account'];
                                $limit = $item['limit'];
                                $sent = $item['sent'];
                                $rem = $item['remaining'];
                                $pct = $limit > 0 ? min(100, round(($sent / $limit) * 100)) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-truncate small" style="max-width: 170px;">
                                        <?= e($acc->gmail_email) ?>
                                        <?php if ($campaign->last_used_gmail_account_id === $acc->id): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0 px-1" style="font-size: 0.65rem;">Last Used</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress mt-1" style="height: 4px; max-width: 150px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </td>
                                <td class="small"><?= $limit ?></td>
                                <td class="small fw-semibold text-dark"><?= $sent ?></td>
                                <td class="small text-success"><?= $rem ?></td>
                                <td>
                                    <?php if ($item['eligible']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">Eligible</span>
                                    <?php elseif ($rem <= 0): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.7rem;">Limit Reached</span>
                                    <?php elseif ($acc->temp_unavailable_until !== null): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">Cooldown</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.7rem;">Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Variations Statistics -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-shuffle text-warning me-2"></i>Message Variations &amp; Telemetry</h6>
            </div>
            <div class="card-body pt-0">
                <p class="text-muted small mb-3">System randomly selects from these active variations for each recipient prior to sending.</p>
                <?php if (empty($messages)): ?>
                    <div class="alert alert-warning small mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> No message variations configured! Sending is halted under Zero Fallback Policy.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($messages as $idx => $msg): ?>
                            <div class="list-group-item px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small text-dark"><i class="fa-solid fa-envelope me-1 text-muted"></i> Variation #<?= $idx + 1 ?></span>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-paper-plane me-1 text-primary"></i> <?= $msg->usage_count ?> sends</span>
                                </div>
                                <div class="small fw-semibold text-secondary mb-1">Subject: <?= e($msg->subject) ?></div>
                                <div class="small text-muted text-truncate" style="max-height: 45px; font-size: 0.8rem;">
                                    <?= nl2br(e(substr($msg->body, 0, 180))) ?>...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recipients Table Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-address-book text-primary me-2"></i>Recipients (<?= number_format($pagination['total_count']) ?>)</h6>
            <!-- Search & Filter Controls -->
            <form action="<?= url('/campaigns/' . $campaign->id) ?>" method="GET" class="d-flex gap-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $pagination['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="sending" <?= $pagination['status'] === 'sending' ? 'selected' : '' ?>>Sending</option>
                    <option value="sent" <?= $pagination['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= $pagination['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="skipped" <?= $pagination['status'] === 'skipped' ? 'selected' : '' ?>>Skipped</option>
                    <option value="cancelled" <?= $pagination['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <div class="input-group input-group-sm" style="width: 220px;">
                    <input type="text" name="q" class="form-control" placeholder="Search email/name..." value="<?= e($pagination['q'] ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Email Address</th>
                    <th>Personalization Data</th>
                    <th>Status</th>
                    <th>Sent Timestamp</th>
                    <th>Dispatched Via</th>
                    <th>Error / Skip Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recipients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted small">No recipients match the current filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recipients as $r): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold text-dark small"><?= e($r->email) ?></span>
                        </td>
                        <td>
                            <div class="small">
                                <?php if ($r->first_name || $r->last_name): ?>
                                    <span class="text-dark"><?= e(trim("{$r->first_name} {$r->last_name}")) ?></span>
                                <?php endif; ?>
                                <?php if ($r->company): ?>
                                    <span class="text-muted">(<?= e($r->company) ?>)</span>
                                <?php endif; ?>
                                <?php if (!$r->first_name && !$r->company): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($r->status === 'sent'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Sent</span>
                            <?php elseif ($r->status === 'pending'): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Pending</span>
                            <?php elseif ($r->status === 'sending'): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Sending</span>
                            <?php elseif ($r->status === 'failed'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            <?php elseif ($r->status === 'skipped'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Skipped</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><?= e($r->status) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $r->sent_at ? date('M d, H:i:s', strtotime($r->sent_at)) : '-' ?>
                        </td>
                        <td class="small text-muted">
                            <?php if ($r->sent_gmail_account_id): ?>
                                <?php 
                                    $accMatch = null;
                                    foreach ($accounts as $aItem) {
                                        if ($aItem['account']->id === $r->sent_gmail_account_id) {
                                            $accMatch = $aItem['account']->gmail_email;
                                            break;
                                        }
                                    }
                                ?>
                                <i class="fa-brands fa-google text-danger me-1"></i><?= e($accMatch ?: 'Account #' . $r->sent_gmail_account_id) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="small text-danger text-truncate" style="max-width: 250px;" title="<?= e($r->last_error ?: $r->skip_reason ?: '') ?>">
                            <?= e($r->last_error ?: $r->skip_reason ?: '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <span class="small text-muted">Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?></span>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= min(10, $pagination['total_pages']); $p++): ?>
                    <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('/campaigns/' . $campaign->id . '?page=' . $p . '&status=' . urlencode($pagination['status'] ?? '') . '&q=' . urlencode($pagination['q'] ?? '')) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Send Audit Logs -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-dark me-2"></i>Audit Trail (Latest 25 Sends)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
            <thead class="table-light text-muted small">
                <tr>
                    <th>Timestamp</th>
                    <th>Recipient</th>
                    <th>Gmail Account</th>
                    <th>Status</th>
                    <th>Message ID</th>
                    <th>Error Code</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-3 text-muted small">No audit logs recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td class="text-muted"><?= e($log['created_at']) ?></td>
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
