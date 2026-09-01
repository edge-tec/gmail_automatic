<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fa-solid fa-shield-halved text-warning me-2"></i>Global Skipped &amp; Duplicate Emails
        </h4>
        <p class="text-muted small mb-0">System-wide log of all duplicate traffic detections, blacklist skips, spam prevention, and filter rule executions.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#adminClearDuplicateModal">
            <i class="fa-solid fa-rotate-left"></i>
            <span>Reset Duplicate History &amp; Clear Logs</span>
        </button>
    </div>
</div>

<!-- Quick Stats -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Total Skipped Mails</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($stats['total_skipped'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Global system total</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-ban text-warning fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Duplicate Traffic Blocked</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($stats['duplicate_traffic_skipped'] ?? 0) ?></div>
                    <div class="text-success" style="font-size: 0.75rem;">1-Reply per lead enforced</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-shield-check text-success fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #e0e7ff 0%, #eef2ff 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Unique Leads Claimed</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($stats['unique_senders_protected'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Protected traffic sources</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-users text-primary fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #fae8ff 0%, #faf5ff 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Today's Skipped</div>
                    <div class="fs-4 fw-bold text-purple mt-1" style="color: #9333ea;"><?= number_format($stats['today_skipped'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Saved quota today</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-bolt text-purple fs-5" style="color: #9333ea;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('/admin/skipped-emails') ?>" class="row g-2 align-items-center">
            <!-- Search -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search email, name, subject, user..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <!-- Skip Reason / Type Filter -->
            <div class="col-6 col-md-3">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Skip Reasons</option>
                    <option value="duplicate_traffic" <?= ($filters['skip_type'] ?? '') === 'duplicate_traffic' ? 'selected' : '' ?>>🛡️ Duplicate Traffic</option>
                    <option value="blacklist" <?= ($filters['skip_type'] ?? '') === 'blacklist' ? 'selected' : '' ?>>🚫 Blacklist Filter</option>
                    <option value="spam_filter" <?= ($filters['skip_type'] ?? '') === 'spam_filter' ? 'selected' : '' ?>>⚠️ Spam / Multi-Recipient</option>
                    <option value="limit_reached" <?= ($filters['skip_type'] ?? '') === 'limit_reached' ? 'selected' : '' ?>>📊 Per-Thread Limit</option>
                    <option value="rule_skip" <?= ($filters['skip_type'] ?? '') === 'rule_skip' ? 'selected' : '' ?>>⚙️ Rule Skip</option>
                </select>
            </div>

            <!-- User Filter -->
            <div class="col-6 col-md-3">
                <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u->id ?>" <?= ($filters['user_id'] ?? null) == $u->id ? 'selected' : '' ?>>
                            <?= e($u->name ?: $u->email) ?> (<?= e($u->email) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range Filter -->
            <div class="col-6 col-md-2">
                <select name="date_range" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($filters['date_range'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= ($filters['date_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="7days" <?= ($filters['date_range'] ?? '') === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30days" <?= ($filters['date_range'] ?? '') === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center p-5 text-muted">
            <i class="fa-solid fa-shield-halved fs-1 opacity-50 mb-3"></i>
            <h5 class="fw-bold">No Skipped or Duplicate Emails</h5>
            <p class="small text-muted mb-0">Skipped and duplicate emails recorded across all user accounts will be listed here.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 960px;">
                <thead class="table-light small text-muted text-uppercase" style="font-size: 0.75rem;">
                    <tr>
                        <th class="ps-3 py-3">Sender / Lead</th>
                        <th class="py-3">Subject &amp; Snippet</th>
                        <th class="py-3">Owner &amp; Gmail Account</th>
                        <th class="py-3">Skip Reason</th>
                        <th class="py-3">1st Reply Sent</th>
                        <th class="py-3">Logged At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $stype = $log['skip_type'] ?? 'duplicate_traffic';
                    ?>
                    <tr>
                        <!-- Sender -->
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-secondary fw-bold flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                    <?= strtoupper(substr($log['sender_name'] ?: $log['sender_email'], 0, 1)) ?>
                                </div>
                                <div class="text-truncate" style="max-width: 200px;">
                                    <div class="fw-semibold text-dark text-truncate"><?= e($log['sender_name'] ?: $log['sender_email']) ?></div>
                                    <div class="text-muted small text-truncate font-monospace" style="font-size: 0.76rem;"><?= e($log['sender_email']) ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Subject -->
                        <td>
                            <div class="text-truncate" style="max-width: 240px;">
                                <div class="fw-semibold text-dark text-truncate"><?= e($log['subject'] ?: '(No Subject)') ?></div>
                                <?php if (!empty($log['snippet'])): ?>
                                <div class="text-muted small text-truncate" style="font-size: 0.76rem;"><?= e($log['snippet']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Owner User & Gmail Account -->
                        <td>
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 0.74rem;">
                                    <i class="fa-solid fa-user me-1"></i><?= e($log['user_account_email'] ?? '') ?>
                                </span>
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.74rem;">
                                    <i class="fa-brands fa-google text-danger me-1"></i><?= e($log['gmail_email'] ?? '') ?>
                                </span>
                            </div>
                        </td>

                        <!-- Skip Reason -->
                        <td>
                            <?php if ($stype === 'duplicate_traffic'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="fa-solid fa-shield-halved me-1"></i> Duplicate Traffic
                                </span>
                            <?php elseif ($stype === 'blacklist'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="fa-solid fa-ban me-1"></i> Blacklist
                                </span>
                            <?php elseif ($stype === 'spam_filter'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Spam Filter
                                </span>
                            <?php elseif ($stype === 'limit_reached'): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <i class="fa-solid fa-chart-simple me-1"></i> Limit Reached
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">
                                    <i class="fa-solid fa-filter me-1"></i> Filter Rule
                                </span>
                            <?php endif; ?>
                            <div class="text-muted small mt-1 text-truncate" style="max-width: 220px; font-size: 0.74rem;" title="<?= e($log['skip_reason']) ?>">
                                <?= e($log['skip_reason']) ?>
                            </div>
                        </td>

                        <!-- 1st Reply Sent -->
                        <td>
                            <?php if (!empty($log['first_reply_sent_at'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace" style="font-size: 0.74rem;">
                                    <i class="fa-solid fa-check me-1"></i><?= date('M d, H:i', strtotime($log['first_reply_sent_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Logged At -->
                        <td>
                            <span class="text-muted small font-monospace" style="font-size: 0.76rem;">
                                <?= date('M d, H:i:s', strtotime($log['created_at'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted">
                Showing <?= number_format(min($totalItems, ($currentPage - 1) * 30 + 1)) ?> to <?= number_format(min($totalItems, $currentPage * 30)) ?> of <?= number_format($totalItems) ?> records
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/admin/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $currentPage - 1])) ?>">Previous</a>
                    </li>
                    <?php endif; ?>

                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('/admin/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/admin/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $currentPage + 1])) ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Admin Reset Duplicate Traffic Modal -->
<div class="modal fade" id="adminClearDuplicateModal" tabindex="-1" aria-labelledby="adminClearDuplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="adminClearDuplicateModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Reset Duplicate Traffic History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('/admin/clear-duplicate-traffic') ?>">
                <?= csrf_field() ?>
                <div class="modal-body pt-2">
                    <p class="text-secondary small mb-3">
                        This action will <strong>permanently purge all duplicate traffic and skipped logs</strong>, and reset completed sender duplicate history across the database.
                    </p>
                    <div class="alert alert-warning border-0 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>What happens next?</strong> All previously completed duplicate senders will immediately be recognized as <strong>brand new traffic</strong> and start from <strong>Reply #1</strong> upon their next email.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Scope of Reset:</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">System-Wide (All Users &amp; Connected Accounts)</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u->id ?>"><?= e($u->name) ?> (<?= e($u->email) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> Confirm &amp; Reset Duplicate State
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
