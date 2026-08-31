<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">System Administration</h4>
        <p class="text-muted small mb-0">Monitor system-wide Gmail automations, total accounts, queue health, and activity logs.</p>
    </div>
    <!-- Global Automation Toggle -->
    <form action="<?= url('/admin/toggle-global') ?>" method="POST">
        <?= csrf_field() ?>
        <?php if ($globalAutomation === '1'): ?>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Disable automation for ALL accounts system-wide?')">
                <i class="fa-solid fa-power-off me-1"></i> Disable Global Automation
            </button>
        <?php else: ?>
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-play me-1"></i> Enable Global Automation
            </button>
        <?php endif; ?>
    </form>
</div>

<!-- Admin Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small fw-semibold">Total Registered Users</div>
            <div class="fs-3 fw-bold mt-1 text-primary"><?= $totalUsers ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small fw-semibold">Connected Gmail Accounts</div>
            <div class="fs-3 fw-bold mt-1 text-success"><?= $activeAccounts ?> <span class="text-muted fs-6 fw-normal">/ <?= $totalAccounts ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small fw-semibold">Total Replies Sent</div>
            <div class="fs-3 fw-bold mt-1 text-info"><?= $totalReplies ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small fw-semibold">Total Follow-ups Sent</div>
            <div class="fs-3 fw-bold mt-1 text-dark"><?= $totalFollowups ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Queue & Cron Health -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="fa-solid fa-server me-2 text-primary"></i> System Health & Background Status
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Global Automation Status</span>
                        <?php if ($globalAutomation === '1'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">ONLINE (Active)</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">PAUSED (Global Off)</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Cron Poller Last Executed</span>
                        <span class="font-monospace small fw-semibold text-muted"><?= e($cronLastRun) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Pending Queue Jobs</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= $pendingJobs ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Failed Jobs</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= $failedJobs ?></span>
                    </li>
                </ul>

                <div class="alert alert-info py-2 px-3 small">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Cron should run every minute via aaPanel cron: <code>* * * * * php /www/wwwroot/your-domain/cron.php</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent System Logs -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-list-check me-2 text-primary"></i> Recent Audit Logs</span>
                <a href="<?= url('/admin/logs') ?>" class="btn btn-sm btn-outline-primary">View All Logs</a>
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
                                    <div class="text-truncate" style="max-width: 380px;"><?= e($log['message']) ?></div>
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                                        <?= e($log['user_name'] ?? 'System') ?> <?= $log['gmail_email'] ? '(' . e($log['gmail_email']) . ')' : '' ?>
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
