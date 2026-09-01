<div class="row g-3 mb-4">
    <!-- Stat 1: Total Accounts -->
    <div class="col-12 col-sm-6 col-xl-auto flex-fill">
        <div class="stat-card d-flex align-items-center justify-content-between p-3">
            <div>
                <div class="text-muted small fw-semibold">Connected Accounts</div>
                <div class="fs-4 fw-bold mt-1"><?= count($accounts) ?></div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fa-brands fa-google"></i>
            </div>
        </div>
    </div>
    <!-- Stat 2: Today's Replies -->
    <div class="col-12 col-sm-6 col-xl-auto flex-fill">
        <div class="stat-card d-flex align-items-center justify-content-between p-3">
            <div>
                <div class="text-muted small fw-semibold">Today's Leads Replied</div>
                <div class="fs-4 fw-bold mt-1 text-success"><?= $todayUsage['total_replies'] ?></div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
        </div>
    </div>
    <!-- Stat 3: Daily Follow Campaigns (Unique Conversations) -->
    <div class="col-12 col-sm-6 col-xl-auto flex-fill">
        <div class="stat-card d-flex align-items-center justify-content-between p-3">
            <div>
                <div class="text-muted small fw-semibold">Daily Follow Campaigns</div>
                <div class="fs-4 fw-bold mt-1 text-info"><?= $todayUsage['total_followups'] ?> <span class="fs-6 fw-normal text-muted">/ conv</span></div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="fa-solid fa-arrows-split-up-and-left"></i>
            </div>
        </div>
    </div>
    <!-- Stat 4: Follow-up Messages Sent -->
    <div class="col-12 col-sm-6 col-xl-auto flex-fill">
        <div class="stat-card d-flex align-items-center justify-content-between p-3">
            <div>
                <div class="text-muted small fw-semibold">Follow-up Messages Sent</div>
                <div class="fs-4 fw-bold mt-1 text-primary"><?= $todayUsage['total_followup_messages'] ?? $todayUsage['total_followups'] ?> <span class="fs-6 fw-normal text-muted">emails</span></div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
        </div>
    </div>
    <!-- Stat 5: Pending Queue -->
    <div class="col-12 col-sm-6 col-xl-auto flex-fill">
        <div class="stat-card d-flex align-items-center justify-content-between p-3">
            <div>
                <div class="text-muted small fw-semibold">Scheduled / Pending</div>
                <div class="fs-4 fw-bold mt-1 text-warning"><?= $pendingJobsCount ?></div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Connected Accounts Status -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-brands fa-google me-2 text-primary"></i> Gmail Accounts</span>
                <a href="<?= url('/accounts') ?>" class="btn btn-sm btn-outline-primary">Manage</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($accounts)): ?>
                <div class="text-center p-4">
                    <i class="fa-solid fa-envelope-circle-check fs-1 text-muted opacity-50 mb-2"></i>
                    <p class="text-muted mb-3">No Gmail accounts connected yet.</p>
                    <a href="<?= url('/accounts/connect') ?>" class="btn btn-sm btn-primary">
                        <i class="fa-brands fa-google me-1"></i> Connect Now
                    </a>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($accounts as $acc): 
                        $sett = $acc->getSettings();
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                        <div>
                            <div class="fw-semibold text-truncate" style="max-width: 220px;">
                                <?= e($acc->gmail_email) ?>
                            </div>
                            <div class="small text-muted">
                                Sync: <?= $acc->last_sync_at ? date('M d, H:i', strtotime($acc->last_sync_at)) : 'Never' ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?php if ($sett && $sett->auto_reply_enabled): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Reply ON</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Reply OFF</span>
                            <?php endif; ?>

                            <?php if ($sett && $sett->followup_enabled): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">Follow-up ON</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Follow-up OFF</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity Logs -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-list-check me-2 text-primary"></i> Real-time Automation Activity</span>
                <span class="badge bg-light text-muted border">Live Logs</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentLogs)): ?>
                <div class="text-center p-4 text-muted">
                    No recent activity recorded yet.
                </div>
                <?php else: ?>
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
                                    <div class="text-truncate" style="max-width: 420px;"><?= e($log['message']) ?></div>
                                </td>
                                <td class="text-end text-muted font-monospace" style="font-size: 0.75rem;">
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Conversation Threads -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-comments me-2 text-primary"></i> Recent Conversation Threads</span>
        <a href="<?= url('/threads') ?>" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentThreads)): ?>
        <div class="text-center p-4 text-muted">
            No incoming conversations detected yet. The background worker or cron will detect emails automatically.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th>Sender</th>
                        <th>Subject</th>
                        <th>Account</th>
                        <th>Replies</th>
                        <th>Follow-ups</th>
                        <th>Next Follow-up</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentThreads as $thread): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-truncate" style="max-width: 180px;"><?= e($thread['sender_name'] ?: $thread['sender_email']) ?></div>
                            <div class="text-muted small text-truncate" style="max-width: 180px;"><?= e($thread['sender_email']) ?></div>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;"><?= e($thread['subject']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= e($thread['gmail_email']) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $thread['reply_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $thread['followup_count'] ?></span>
                        </td>
                        <td>
                            <?php if ($thread['next_followup_at']): ?>
                                <span class="small text-muted font-monospace"><i class="fa-regular fa-clock me-1"></i><?= date('M d, H:i', strtotime($thread['next_followup_at'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($thread['automation_status'] === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php elseif ($thread['automation_status'] === 'replied'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-reply me-1"></i>Replied</span>
                            <?php elseif ($thread['automation_status'] === 'completed'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Stopped</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url("/threads/{$thread['id']}") ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
