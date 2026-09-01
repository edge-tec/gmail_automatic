<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fa-solid fa-shield-halved text-warning me-2"></i>Duplicate &amp; Skipped Emails Report
        </h4>
        <p class="text-muted small mb-0">Review incoming emails that were recognized as duplicate traffic or skipped by smart anti-spam and rule filters.</p>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= url('/skipped-emails/export' . '?' . http_build_query($filters)) ?>" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1 shadow-sm">
            <i class="fa-solid fa-file-csv"></i>
            <span>Export CSV</span>
        </a>

        <?php if (!empty($logs)): ?>
        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#clearSkippedModal">
            <i class="fa-solid fa-trash-can"></i>
            <span>Clear Report</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Total Skipped Mails</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($stats['total_skipped'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">All filter &amp; duplicate events</div>
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
                    <div class="text-success" style="font-size: 0.75rem;">1-Reply rule protected</div>
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
                    <div class="text-muted small fw-semibold">Unique Senders Replied</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($stats['unique_senders_protected'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Eligible traffic sources</div>
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
                    <div class="text-muted" style="font-size: 0.75rem;">Auto-reply quota saved</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-bolt text-purple fs-5" style="color: #9333ea;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search Toolbar -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('/skipped-emails') ?>" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search email, name, subject..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <!-- Skip Reason / Type Filter -->
            <div class="col-6 col-md-3">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Skip Reasons</option>
                    <option value="duplicate_traffic" <?= ($filters['skip_type'] ?? '') === 'duplicate_traffic' ? 'selected' : '' ?>>🛡️ Duplicate Traffic (1 Reply / Lead)</option>
                    <option value="blacklist" <?= ($filters['skip_type'] ?? '') === 'blacklist' ? 'selected' : '' ?>>🚫 Blacklisted Email / Domain / Keyword</option>
                    <option value="spam_filter" <?= ($filters['skip_type'] ?? '') === 'spam_filter' ? 'selected' : '' ?>>⚠️ Spam / Multiple Recipients</option>
                    <option value="limit_reached" <?= ($filters['skip_type'] ?? '') === 'limit_reached' ? 'selected' : '' ?>>📊 Per-Thread Limit Reached</option>
                    <option value="rule_skip" <?= ($filters['skip_type'] ?? '') === 'rule_skip' ? 'selected' : '' ?>>⚙️ Custom Filter Rule Skip</option>
                </select>
            </div>

            <!-- Account Filter -->
            <div class="col-6 col-md-3">
                <select name="account_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Connected Accounts</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc->id ?>" <?= ($filters['account_id'] ?? null) == $acc->id ? 'selected' : '' ?>>
                            <?= e($acc->gmail_email) ?>
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
        <div class="text-center p-5">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted mb-3" style="width: 64px; height: 64px;">
                <i class="fa-solid fa-shield-halved fs-3"></i>
            </div>
            <h5 class="fw-bold mb-1">No Skipped or Duplicate Emails Recorded</h5>
            <p class="text-muted small mb-3">When duplicate incoming emails or filtered messages are received, they are automatically cataloged here for complete visibility.</p>
            <a href="<?= url('/settings/automation') ?>" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-sliders me-1"></i> Review Automation Settings
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 920px;">
                <thead class="table-light small text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-3 py-3">Sender / Traffic Source</th>
                        <th class="py-3">Subject &amp; Snippet</th>
                        <th class="py-3">Connected Account</th>
                        <th class="py-3">Protection Reason</th>
                        <th class="py-3">1st Reply Sent</th>
                        <th class="py-3">Skipped At</th>
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
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-secondary fw-bold flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.82rem;">
                                    <?= strtoupper(substr($log['sender_name'] ?: $log['sender_email'], 0, 1)) ?>
                                </div>
                                <div class="text-truncate" style="max-width: 220px;">
                                    <div class="fw-semibold text-dark text-truncate" title="<?= e($log['sender_name'] ?: $log['sender_email']) ?>">
                                        <?= e($log['sender_name'] ?: $log['sender_email']) ?>
                                    </div>
                                    <div class="text-muted small text-truncate font-monospace" style="font-size: 0.78rem;" title="<?= e($log['sender_email']) ?>">
                                        <?= e($log['sender_email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Subject & Snippet -->
                        <td>
                            <div class="text-truncate" style="max-width: 260px;">
                                <div class="fw-semibold text-dark text-truncate" title="<?= e($log['subject'] ?: '(No Subject)') ?>">
                                    <?= e($log['subject'] ?: '(No Subject)') ?>
                                </div>
                                <?php if (!empty($log['snippet'])): ?>
                                <div class="text-muted small text-truncate" style="font-size: 0.78rem;" title="<?= e($log['snippet']) ?>">
                                    <?= e($log['snippet']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Connected Gmail Account -->
                        <td>
                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.78rem;">
                                <i class="fa-brands fa-google text-danger me-1"></i><?= e($log['gmail_email'] ?? '') ?>
                            </span>
                        </td>

                        <!-- Reason Badge -->
                        <td>
                            <?php if ($stype === 'duplicate_traffic'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-shield-halved"></i> Duplicate Traffic (1 Reply / Lead)
                                </span>
                            <?php elseif ($stype === 'blacklist'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-ban"></i> Blacklist Rule
                                </span>
                            <?php elseif ($stype === 'spam_filter'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Spam / Multi-Recipient
                                </span>
                            <?php elseif ($stype === 'limit_reached'): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-chart-simple"></i> Per-Thread Limit Reached
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-filter"></i> Rule Filter Skip
                                </span>
                            <?php endif; ?>

                            <div class="text-muted small mt-1 text-truncate" style="max-width: 220px; font-size: 0.75rem;" title="<?= e($log['skip_reason']) ?>">
                                <?= e($log['skip_reason']) ?>
                            </div>
                        </td>

                        <!-- 1st Reply Sent Timestamp -->
                        <td>
                            <?php if (!empty($log['first_reply_sent_at'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace" style="font-size: 0.76rem;">
                                    <i class="fa-solid fa-check me-1"></i><?= date('M d, H:i', strtotime($log['first_reply_sent_at'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Skipped At Timestamp -->
                        <td>
                            <span class="text-muted small font-monospace" style="font-size: 0.78rem;">
                                <i class="fa-regular fa-clock me-1 text-muted"></i><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
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
                Showing <?= number_format(min($totalItems, ($currentPage - 1) * 25 + 1)) ?> to <?= number_format(min($totalItems, $currentPage * 25)) ?> of <?= number_format($totalItems) ?> records
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $currentPage - 1])) ?>">Previous</a>
                    </li>
                    <?php endif; ?>

                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/skipped-emails') ?>?<?= http_build_query(array_merge($filters, ['page' => $currentPage + 1])) ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Clear Skipped Modal -->
<div class="modal fade" id="clearSkippedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= url('/skipped-emails/clear') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger">
                        <i class="fa-solid fa-trash-can me-2"></i>Clear Skipped Emails Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-0">Are you sure you want to clear your skipped and duplicate email history? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirm &amp; Clear</button>
                </div>
            </form>
        </div>
    </div>
</div>
