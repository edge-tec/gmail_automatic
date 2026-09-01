<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">System Administration &amp; Notifications</h4>
        <p class="text-muted small mb-0">Monitor new user registrations, verify incoming payments, track Gmail automations, and manage queue health.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('/admin/gateways') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-credit-card me-1"></i> Gateways
        </a>
        <!-- Global Automation Toggle -->
        <form action="<?= url('/admin/toggle-global') ?>" method="POST">
            <?= csrf_field() ?>
            <?php if ($globalAutomation === '1'): ?>
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Disable automation for ALL accounts system-wide?')">
                    <i class="fa-solid fa-power-off me-1"></i> Disable Global
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fa-solid fa-play me-1"></i> Enable Global
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Real-Time Notification Banners -->
<?php if ($pendingPaymentsCount > 0): ?>
<div class="alert alert-warning border-warning d-flex justify-content-between align-items-center shadow-sm p-3 mb-4 rounded-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="p-2 rounded-circle bg-warning text-dark fs-4">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div>
            <h6 class="fw-bold mb-0 text-dark">
                <?= $pendingPaymentsCount ?> Pending Payment Submission(s) Waiting for Verification!
            </h6>
            <div class="small text-muted">Users have submitted bKash/Nagad payments. Please verify TrxIDs and approve subscriptions.</div>
        </div>
    </div>
    <a href="<?= url('/admin/payments') ?>" class="btn btn-warning fw-bold text-dark px-3 py-2">
        <i class="fa-solid fa-list-check me-1"></i> Review &amp; Approve (<?= $pendingPaymentsCount ?>)
    </a>
</div>
<?php endif; ?>

<!-- Main Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card p-3 bg-white rounded-3 border shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Total Users</span>
                <span class="badge bg-primary bg-opacity-10 text-primary">+<?= $newUsersTodayCount ?> Today</span>
            </div>
            <div class="fs-3 fw-bold mt-1 text-primary"><?= $totalUsers ?></div>
            <div class="small text-muted"><?= $activeSubscriptions ?> Active Subscribers</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card p-3 bg-white rounded-3 border shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Pending Payments</span>
                <?php if ($pendingPaymentsCount > 0): ?>
                <span class="badge bg-danger">Action Required</span>
                <?php else: ?>
                <span class="badge bg-success bg-opacity-10 text-success">Up to date</span>
                <?php endif; ?>
            </div>
            <div class="fs-3 fw-bold mt-1 text-warning"><?= $pendingPaymentsCount ?></div>
            <div class="small text-muted"><a href="<?= url('/admin/payments') ?>" class="text-decoration-none">View All Payments &rarr;</a></div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card p-3 bg-white rounded-3 border shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Active Free Trials</span>
                <i class="fa-solid fa-gift text-info"></i>
            </div>
            <div class="fs-3 fw-bold mt-1 text-info"><?= $activeTrials ?></div>
            <div class="small text-muted"><?= $activeAccounts ?> Active Gmails Connected</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card p-3 bg-white rounded-3 border shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Total Paid Revenue</span>
                <i class="fa-solid fa-dollar-sign text-success"></i>
            </div>
            <div class="fs-3 fw-bold mt-1 text-success">$<?= number_format($totalRevenue, 2) ?></div>
            <div class="small text-muted"><?= $totalReplies + ($totalFollowupMessages ?: $totalFollowups) ?> Total Sent Emails</div>
        </div>
    </div>
</div>

<!-- Auto-Reply Duplicate Traffic Protection Stats -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-shield-halved text-success me-2"></i> Auto-Reply Duplicate Traffic Protection Analytics
        </h6>
        <span class="badge bg-success-subtle text-success border border-success-subtle">1 Reply Per Unique Traffic</span>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Unique Traffic Today</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= $uniqueTrafficToday ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Distinct senders</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Auto Replies Today</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= $autoRepliesToday ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Replies sent</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Duplicate Traffic Blocked</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= $duplicateEmailsPrevented ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Redundant emails skipped</div>
                </div>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Pending Replies</div>
                    <div class="fs-4 fw-bold text-info mt-1"><?= $pendingAutoReplies ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">In queue/processing</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Failed Auto Replies</div>
                    <div class="fs-4 fw-bold text-danger mt-1"><?= $failedAutoReplies ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Delivery failures</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Follow-up System & Duplicate Protection Stats -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-arrows-split-up-and-left text-info me-2"></i> Follow-up Campaign &amp; Duplicate Protection Analytics
        </h6>
        <span class="badge bg-info-subtle text-info border border-info-subtle">Per-Conversation Tracking</span>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Campaigns Today</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= $todayCampaignsCount ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Unique conversations</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Messages Sent Today</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= $todayFollowupMessages ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Actual step messages</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Duplicates Prevented</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= $duplicateEmailsPrevented ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Duplicate emails blocked</div>
                </div>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Active Campaigns</div>
                    <div class="fs-4 fw-bold text-info mt-1"><?= $activeFollowupCampaigns ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Ongoing sequences</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="p-2 border rounded-3 bg-light">
                    <div class="text-muted small fw-semibold">Cancelled / Replied</div>
                    <div class="fs-4 fw-bold text-secondary mt-1"><?= $cancelledFollowupCampaigns ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Replied or stopped</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Payment Action Table (If any) -->
<?php if (!empty($pendingPayments)): ?>
<div class="card shadow-sm border-0 mb-4 border-start border-warning border-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-circle-exclamation text-warning me-2"></i> Pending Payments Awaiting Approval
        </h6>
        <a href="<?= url('/admin/payments') ?>" class="btn btn-sm btn-outline-primary">Manage All Payments</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>User</th>
                    <th>Gateway</th>
                    <th>Sender Number</th>
                    <th>Transaction ID (TrxID)</th>
                    <th>Plan &amp; Amount</th>
                    <th>Submitted At</th>
                    <th class="text-end">Quick Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingPayments as $pPay): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-dark"><?= e($pPay->getUser()->name ?? 'User #' . $pPay->user_id) ?></div>
                        <div class="small text-muted"><?= e($pPay->getUser()->email ?? '') ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $pPay->gateway === 'bkash' ? 'bg-danger' : ($pPay->gateway === 'nagad' ? 'bg-warning text-dark' : 'bg-primary') ?>">
                            <?= strtoupper($pPay->gateway) ?>
                        </span>
                    </td>
                    <td class="font-monospace fw-semibold"><?= e($pPay->sender_number ?? 'N/A') ?></td>
                    <td class="font-monospace fw-bold text-primary"><?= e($pPay->transaction_id ?? 'N/A') ?></td>
                    <td>
                        <div class="fw-bold"><?= $pPay->getPlan() ? e($pPay->getPlan()->name) : 'Subscription' ?></div>
                        <div class="small text-muted">$<?= number_format($pPay->amount, 2) ?> USD <?php if ($pPay->amount_bdt): ?>(৳ <?= number_format($pPay->amount_bdt, 2) ?>)<?php endif; ?></div>
                    </td>
                    <td class="small text-muted"><?= date('d M Y, H:i', strtotime($pPay->created_at)) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <form action="<?= url('/admin/payments/' . $pPay->id . '/approve') ?>" method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Approve TrxID <?= e($pPay->transaction_id) ?> and activate subscription?');">
                                    <i class="fa-solid fa-check me-1"></i> Approve
                                </button>
                            </form>
                            <form action="<?= url('/admin/payments/' . $pPay->id . '/reject') ?>" method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject TrxID <?= e($pPay->transaction_id) ?>?');">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Recent User Registrations -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-user-plus text-primary me-2"></i> Recent Registrations</h6>
                <a href="<?= url('/admin/users') ?>" class="btn btn-sm btn-outline-primary">View All Users</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <tbody>
                            <?php foreach ($recentRegistrations as $rUser): ?>
                            <tr>
                                <td style="width: 40px;">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?= strtoupper(substr($rUser['name'], 0, 1)) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($rUser['name']) ?></div>
                                    <div class="small text-muted"><?= e($rUser['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $rUser['subscription_status'] === 'active' ? 'bg-success' : ($rUser['trial_status'] === 'active' ? 'bg-info' : 'bg-light text-dark border') ?>">
                                        <?= ucfirst($rUser['plan_type']) ?> (<?= ucfirst($rUser['subscription_status']) ?>)
                                    </span>
                                </td>
                                <td class="text-end small text-muted font-monospace">
                                    <?= date('d M, H:i', strtotime($rUser['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Audit Logs -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i> Live Activity &amp; Notifications</h6>
                <a href="<?= url('/admin/logs') ?>" class="btn btn-sm btn-outline-primary">View Full Logs</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td style="width: 30px;">
                                    <?php if ($log['log_type'] === 'success'): ?>
                                        <i class="fa-solid fa-circle-check text-success"></i>
                                    <?php elseif ($log['log_type'] === 'error'): ?>
                                        <i class="fa-solid fa-circle-xmark text-danger"></i>
                                    <?php elseif ($log['log_type'] === 'warning'): ?>
                                        <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-info text-primary"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;"><?= e($log['message']) ?></div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        <?= e($log['user_name'] ?? 'System') ?>
                                    </div>
                                </td>
                                <td class="text-end text-muted font-monospace" style="font-size: 0.75rem;">
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
