<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="fa-solid fa-chart-line text-success"></i>
            <span>Replies &amp; Follow-ups Report</span>
            <span class="badge bg-success bg-opacity-10 text-success fs-6 fw-normal px-2.5 py-1 border border-success border-opacity-20 rounded-pill">
                <?= e($filters['range_label'] ?? 'Last 7 Days') ?>
            </span>
        </h4>
        <p class="text-muted small mb-0">
            সকল রিপ্লে ও ফলোআপের বিস্তারিত রিপোর্ট ও ট্র্যাকিং — ট্র্যাক করুন কোন অ্যাকাউন্টে কখন কোন রিপ্লে বা ফলোআপ পাঠানো হয়েছে।
        </p>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= url('/reports/replies/export' . '?' . http_build_query($filters)) ?>" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1.5 shadow-sm px-3 py-1.5">
            <i class="fa-solid fa-file-csv fs-6"></i>
            <span class="fw-semibold">Export CSV</span>
        </a>

        <a href="<?= url('/reports/replies?date_range=7days') ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1.5 shadow-sm px-3 py-1.5">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Reset 7 Days</span>
        </a>
    </div>
</div>

<!-- Quick Metric Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Stat 1: Auto-Replies in Range -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Auto-Replies (<?= e($filters['date_range'] === '7days' ? 'Last 7 Days' : 'Period') ?>)</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($stats['range_replies'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        All time: <span class="fw-semibold text-dark"><?= number_format($stats['all_replies'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-reply-all text-success fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Follow-up Messages in Range -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #e0e7ff 0%, #eef2ff 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Follow-ups (<?= e($filters['date_range'] === '7days' ? 'Last 7 Days' : 'Period') ?>)</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($stats['range_followups'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        All time: <span class="fw-semibold text-dark"><?= number_format($stats['all_followups'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-arrows-split-up-and-left text-primary fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Total Outgoing Sent -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Total Messages Sent</div>
                    <div class="fs-4 fw-bold text-warning mt-1" style="color:#d97706 !important;"><?= number_format($stats['range_total'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        All time: <span class="fw-semibold text-dark"><?= number_format($stats['all_total'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-paper-plane text-warning fs-5" style="color:#d97706 !important;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: Unique Leads Contacted -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #fae8ff 0%, #faf5ff 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Unique Leads Engaged</div>
                    <div class="fs-4 fw-bold mt-1" style="color: #9333ea;"><?= number_format($stats['unique_leads'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Total distinct recipients</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-users text-purple fs-5" style="color: #9333ea;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 5: Pending In Queue -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card shadow-sm border-0 h-100 rounded-3" style="background: linear-gradient(135deg, #f1f5f9 0%, #f8fafc 100%);">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">Scheduled / In Queue</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($stats['pending_count'] ?? 0) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Waiting for delivery time</div>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white shadow-sm" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-hourglass-half text-secondary fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 7-Day Day-by-Day Activity Summary Section -->
<?php if (!empty($dailyBreakdown)): ?>
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="fa-solid fa-calendar-week fs-6"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-dark">Day-by-Day Activity Summary (প্রতিদিনের বিস্তারিত রিপোর্ট)</h6>
                <div class="text-muted" style="font-size: 0.78rem;">Detailed reply and follow-up breakdown for <?= e($filters['range_label'] ?? 'Last 7 Days') ?></div>
            </div>
        </div>
        <span class="badge bg-light text-secondary border px-2.5 py-1">
            <?= count($dailyBreakdown) ?> Days Shown
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 650px;">
                <thead class="table-light small text-muted text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-4 py-2.5">Date &amp; Day</th>
                        <th class="py-2.5 text-center">Auto-Replies</th>
                        <th class="py-2.5 text-center">Follow-ups</th>
                        <th class="py-2.5 text-center">Total Sent</th>
                        <th class="pe-4 py-2.5 text-end" style="min-width: 160px;">Volume Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxTotal = 1;
                    foreach ($dailyBreakdown as $bRow) {
                        if ($bRow['total_sent'] > $maxTotal) {
                            $maxTotal = $bRow['total_sent'];
                        }
                    }
                    foreach ($dailyBreakdown as $day): 
                        $pct = round(($day['total_sent'] / $maxTotal) * 100);
                        $isToday = str_contains($day['day_label'], 'Today');
                        $isYesterday = str_contains($day['day_label'], 'Yesterday');
                    ?>
                    <tr class="<?= $isToday ? 'bg-success bg-opacity-10' : '' ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-regular fa-calendar <?= $isToday ? 'text-success fw-bold' : 'text-muted' ?>"></i>
                                <div>
                                    <div class="fw-semibold <?= $isToday ? 'text-success' : 'text-dark' ?>">
                                        <?= e($day['day_label']) ?>
                                    </div>
                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                        <?= e($day['date']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2.5 py-1 rounded-pill">
                                <?= number_format($day['auto_replies']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2.5 py-1 rounded-pill">
                                <?= number_format($day['followups']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold fs-6 text-dark">
                                <?= number_format($day['total_sent']) ?>
                            </span>
                        </td>
                        <td class="pe-4 text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div class="progress flex-grow-1" style="height: 6px; max-width: 120px; background-color: #e2e8f0;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="text-muted small font-monospace" style="font-size: 0.75rem; min-width: 32px;">
                                    <?= $pct ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters & Search Toolbar -->
<div class="card shadow-sm border-0 mb-4 rounded-3">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('/reports/replies') ?>" id="reportFilterForm" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search email, name, subject..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="col-6 col-md-2">
                <select name="date_range" id="dateRangeSelect" class="form-select form-select-sm" onchange="handleDateRangeChange(this.value)">
                    <option value="7days" <?= ($filters['date_range'] ?? '7days') === '7days' ? 'selected' : '' ?>>🗓️ Last 7 Days (Default)</option>
                    <option value="today" <?= ($filters['date_range'] ?? '') === 'today' ? 'selected' : '' ?>>⚡ Today</option>
                    <option value="yesterday" <?= ($filters['date_range'] ?? '') === 'yesterday' ? 'selected' : '' ?>>⏮️ Yesterday</option>
                    <option value="14days" <?= ($filters['date_range'] ?? '') === '14days' ? 'selected' : '' ?>>📅 Last 14 Days</option>
                    <option value="30days" <?= ($filters['date_range'] ?? '') === '30days' ? 'selected' : '' ?>>📊 Last 30 Days</option>
                    <option value="all" <?= ($filters['date_range'] ?? '') === 'all' ? 'selected' : '' ?>>🌐 All Time</option>
                    <option value="custom" <?= ($filters['date_range'] ?? '') === 'custom' ? 'selected' : '' ?>>⚙️ Custom Range</option>
                </select>
            </div>

            <!-- Custom Date Range Inputs (Hidden unless custom is chosen) -->
            <div class="col-12 col-md-3 <?= ($filters['date_range'] ?? '') === 'custom' ? '' : 'd-none' ?>" id="customDateFields">
                <div class="d-flex align-items-center gap-1">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($filters['start_date'] ?? '') ?>" title="Start Date">
                    <span class="text-muted small">to</span>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($filters['end_date'] ?? '') ?>" title="End Date">
                </div>
            </div>

            <!-- Type Filter -->
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">All Types</option>
                    <option value="auto_reply" <?= ($filters['type'] ?? '') === 'auto_reply' ? 'selected' : '' ?>>📩 Auto-Replies Only</option>
                    <option value="follow_up" <?= ($filters['type'] ?? '') === 'follow_up' ? 'selected' : '' ?>>🔄 Follow-ups Only</option>
                </select>
            </div>

            <!-- Account Filter -->
            <div class="col-6 col-md-2">
                <select name="account_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Accounts</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc->id ?>" <?= ($filters['account_id'] ?? null) == $acc->id ? 'selected' : '' ?>>
                            <?= e($acc->gmail_email) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">All Statuses</option>
                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>✓ Sent / Delivered</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>⏳ Pending / Scheduled</option>
                    <option value="processing" <?= ($filters['status'] ?? '') === 'processing' ? 'selected' : '' ?>>⚙️ Processing</option>
                    <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>✗ Failed</option>
                    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>🚫 Cancelled</option>
                </select>
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filter">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Detailed Activities Table Card -->
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-list-ul text-primary me-2"></i>Detailed Activity Logs
            </h6>
            <span class="badge bg-light text-muted border px-2 py-0.5 rounded-pill">
                <?= number_format($totalItems) ?> records
            </span>
        </div>

        <?php if (!empty($filters['search']) || !empty($filters['account_id']) || ($filters['type'] ?? 'all') !== 'all' || ($filters['status'] ?? 'all') !== 'all'): ?>
        <div>
            <a href="<?= url('/reports/replies?date_range=' . urlencode($filters['date_range'] ?? '7days')) ?>" class="btn btn-sm btn-link text-decoration-none p-0 text-muted" style="font-size: 0.8rem;">
                <i class="fa-solid fa-xmark me-1"></i> Clear active filters
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center p-5">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted mb-3" style="width: 64px; height: 64px;">
                <i class="fa-solid fa-inbox fs-3"></i>
            </div>
            <h5 class="fw-bold mb-1">No Replies or Follow-ups Found</h5>
            <p class="text-muted small mb-3">No automation records match the selected date range or filter criteria.</p>
            <a href="<?= url('/reports/replies?date_range=all') ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="fa-solid fa-globe me-1"></i> View All Time
            </a>
            <a href="<?= url('/settings/automation') ?>" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-sliders me-1"></i> Review Automation Settings
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 960px;">
                <thead class="table-light small text-muted text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-4 py-3">Type &amp; Step</th>
                        <th class="py-3">Recipient Lead</th>
                        <th class="py-3">Subject &amp; Snippet</th>
                        <th class="py-3">Sending Account</th>
                        <th class="py-3">Date &amp; Time</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="pe-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $isReply = ($log['job_type'] === 'auto_reply');
                        $status = $log['status'];
                    ?>
                    <tr>
                        <!-- Type & Step -->
                        <td class="ps-4">
                            <?php if ($isReply): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-reply-all" style="font-size: 0.75rem;"></i>
                                    <span class="fw-semibold"><?= e($log['step_label']) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-arrows-split-up-and-left" style="font-size: 0.75rem;"></i>
                                    <span class="fw-semibold"><?= e($log['step_label']) ?></span>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Recipient -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light text-secondary fw-bold flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.82rem;">
                                    <?= strtoupper(substr($log['recipient_name'] ?: $log['recipient_email'], 0, 1)) ?>
                                </div>
                                <div class="text-truncate" style="max-width: 220px;">
                                    <?php if (!empty($log['recipient_name'])): ?>
                                        <div class="fw-semibold text-dark text-truncate" title="<?= e($log['recipient_name']) ?>">
                                            <?= e($log['recipient_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted small text-truncate font-monospace" style="font-size: 0.78rem;" title="<?= e($log['recipient_email']) ?>">
                                        <?= e($log['recipient_email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Subject & Snippet -->
                        <td>
                            <div class="text-truncate" style="max-width: 280px;">
                                <div class="fw-semibold text-dark text-truncate" title="<?= e($log['subject']) ?>">
                                    <?= e($log['subject']) ?>
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
                            <div class="d-flex align-items-center gap-1.5 text-muted small">
                                <i class="fa-brands fa-google text-danger" style="font-size: 0.82rem;"></i>
                                <span class="text-truncate" style="max-width: 170px;" title="<?= e($log['gmail_email']) ?>">
                                    <?= e($log['gmail_email']) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Date & Time -->
                        <td>
                            <div>
                                <div class="fw-semibold text-dark small">
                                    <?= date('M j, Y', strtotime($log['display_date'])) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <?= date('h:i A', strtotime($log['display_date'])) ?>
                                </div>
                            </div>
                        </td>

                        <!-- Status Badge -->
                        <td class="text-center">
                            <?php if ($status === 'completed'): ?>
                                <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                    <i class="fa-solid fa-check me-1"></i> Sent
                                </span>
                            <?php elseif ($status === 'pending'): ?>
                                <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 px-2 py-1 rounded-pill">
                                    <i class="fa-regular fa-clock me-1"></i> Scheduled
                                </span>
                            <?php elseif ($status === 'processing'): ?>
                                <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 px-2 py-1 rounded-pill">
                                    <i class="fa-solid fa-spinner fa-spin me-1"></i> In Flight
                                </span>
                            <?php elseif ($status === 'failed'): ?>
                                <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill" title="<?= e($log['last_error'] ?? 'Error occurred') ?>">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Failed
                                </span>
                            <?php elseif ($status === 'cancelled'): ?>
                                <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill" title="<?= e($log['last_error'] ?? 'Cancelled') ?>">
                                    <i class="fa-solid fa-ban me-1"></i> Cancelled
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Action: View Modal -->
                        <td class="pe-4 text-end">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-secondary px-2.5 py-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewMessageModal"
                                    data-id="<?= $log['id'] ?>"
                                    data-type="<?= e($log['job_type'] === 'auto_reply' ? 'Auto-Reply' : 'Follow-up') ?>"
                                    data-step="<?= e($log['step_label']) ?>"
                                    data-recipient="<?= e($log['recipient_name'] ? ($log['recipient_name'] . ' <' . $log['recipient_email'] . '>') : $log['recipient_email']) ?>"
                                    data-sender="<?= e($log['gmail_email']) ?>"
                                    data-subject="<?= e($log['subject']) ?>"
                                    data-date="<?= e(date('M j, Y h:i A', strtotime($log['display_date']))) ?>"
                                    data-status="<?= e(ucfirst($status)) ?>"
                                    data-error="<?= e($log['last_error'] ?? '') ?>"
                                    data-body="<?= e($log['message_body']) ?>"
                                    onclick="populateMessageModal(this)">
                                <i class="fa-regular fa-eye me-1"></i> Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Showing page <span class="fw-semibold text-dark"><?= $currentPage ?></span> of <span class="fw-semibold text-dark"><?= $totalPages ?></span> (<?= number_format($totalItems) ?> total entries)
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/reports/replies' . '?' . http_build_query(array_merge($filters, ['page' => $currentPage - 1]))) ?>">
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $startP = max(1, $currentPage - 2);
                    $endP = min($totalPages, $currentPage + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                    ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('/reports/replies' . '?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= url('/reports/replies' . '?' . http_build_query(array_merge($filters, ['page' => $currentPage + 1]))) ?>">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Inspect Email Message Details -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2.5 py-1 rounded-pill" id="modalTypeBadge">Auto-Reply</span>
                    <h5 class="modal-title fw-bold fs-6 mb-0" id="viewMessageModalLabel">Automation Message Details</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Metadata table -->
                <div class="bg-light p-3 rounded-3 mb-3 border">
                    <div class="row g-2 small">
                        <div class="col-sm-3 text-muted">To (Recipient):</div>
                        <div class="col-sm-9 fw-semibold text-dark font-monospace" id="modalRecipient"></div>

                        <div class="col-sm-3 text-muted">From (Account):</div>
                        <div class="col-sm-9 text-dark font-monospace" id="modalSender"></div>

                        <div class="col-sm-3 text-muted">Subject:</div>
                        <div class="col-sm-9 fw-bold text-dark" id="modalSubject"></div>

                        <div class="col-sm-3 text-muted">Date &amp; Time:</div>
                        <div class="col-sm-9 text-dark" id="modalDate"></div>

                        <div class="col-sm-3 text-muted">Delivery Status:</div>
                        <div class="col-sm-9" id="modalStatusContainer">
                            <span class="badge bg-success" id="modalStatusBadge">Sent</span>
                        </div>

                        <div class="col-12 d-none" id="modalErrorContainer">
                            <div class="alert alert-danger mb-0 py-2 small" id="modalErrorText"></div>
                        </div>
                    </div>
                </div>

                <!-- Message Body Preview -->
                <div class="mb-2 fw-semibold text-dark small">
                    <i class="fa-regular fa-envelope-open me-1 text-primary"></i> Sent Email Content (মেসেজ বডি):
                </div>
                <div class="border rounded-3 p-3 bg-white" style="min-height: 180px; max-height: 400px; overflow-y: auto; line-height: 1.6; font-size: 0.92rem;" id="modalBodyContainer">
                </div>
            </div>
            <div class="modal-footer border-top py-2.5 px-4 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function handleDateRangeChange(val) {
    var customDiv = document.getElementById('customDateFields');
    if (val === 'custom') {
        if (customDiv) customDiv.classList.remove('d-none');
    } else {
        if (customDiv) customDiv.classList.add('d-none');
        document.getElementById('reportFilterForm').submit();
    }
}

function populateMessageModal(btn) {
    var type = btn.getAttribute('data-type') || 'Message';
    var step = btn.getAttribute('data-step') || '';
    var recipient = btn.getAttribute('data-recipient') || '';
    var sender = btn.getAttribute('data-sender') || '';
    var subject = btn.getAttribute('data-subject') || '';
    var date = btn.getAttribute('data-date') || '';
    var status = btn.getAttribute('data-status') || '';
    var error = btn.getAttribute('data-error') || '';
    var body = btn.getAttribute('data-body') || '';

    document.getElementById('modalTypeBadge').textContent = type + ' ' + step;
    document.getElementById('modalRecipient').textContent = recipient;
    document.getElementById('modalSender').textContent = sender;
    document.getElementById('modalSubject').textContent = subject;
    document.getElementById('modalDate').textContent = date;
    
    var statusBadge = document.getElementById('modalStatusBadge');
    statusBadge.textContent = status;
    if (status.toLowerCase() === 'sent' || status.toLowerCase() === 'completed') {
        statusBadge.className = 'badge bg-success';
    } else if (status.toLowerCase() === 'pending') {
        statusBadge.className = 'badge bg-info';
    } else if (status.toLowerCase() === 'failed') {
        statusBadge.className = 'badge bg-danger';
    } else {
        statusBadge.className = 'badge bg-secondary';
    }

    var errContainer = document.getElementById('modalErrorContainer');
    var errText = document.getElementById('modalErrorText');
    if (error && error.trim().length > 0) {
        errText.textContent = 'Error: ' + error;
        errContainer.classList.remove('d-none');
    } else {
        errContainer.classList.add('d-none');
    }

    var bodyContainer = document.getElementById('modalBodyContainer');
    // If message body contains HTML tags, render it safely or display with preserve formatting
    if (body.indexOf('<') !== -1 && body.indexOf('>') !== -1) {
        bodyContainer.innerHTML = body;
    } else {
        bodyContainer.innerText = body;
    }
}
</script>
