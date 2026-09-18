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

<!-- ========================================================================= -->
<!-- Real Email Open-Rate Tracking & Recipient Analytics Section -->
<!-- ========================================================================= -->
<div class="card shadow-sm border-0 mb-4 rounded-3 bg-white" id="openRateAnalyticsSection">
    <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fa-solid fa-envelope-open-text fs-5 text-primary"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <span>Email Open Rate &amp; Recipient Engagement</span>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 fw-normal px-2.5 py-0.5 rounded-pill" id="livePollingBadge">
                        <i class="fa-solid fa-circle-dot text-success me-1"></i> Live Real-Time
                    </span>
                </h5>
                <div class="text-muted" style="font-size: 0.8rem;">
                    বাস্তব ইমেইল ওপেন রেট ট্র্যাকিং — ট্র্যাক করুন কোন প্রাপক কতবার কখন ইমেইল খুলেছেন।
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1.5 shadow-sm px-3 py-1.5" id="btnRefreshOpenStats" onclick="refreshOpenRateData()">
                <i class="fa-solid fa-arrows-rotate" id="refreshOpenIcon"></i>
                <span class="fw-semibold">Refresh Live Stats</span>
            </button>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Open Rate KPI Metric Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Metric 1: Overall Open Rate % -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-none h-100 rounded-3 p-3 text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-semibold text-white text-opacity-75">Open Rate</div>
                            <div class="display-6 fw-bold mt-1" id="kpiOpenRate"><?= number_format($openStats['open_rate'] ?? 0, 1) ?>%</div>
                            <div class="small text-white text-opacity-75 mt-1" style="font-size: 0.75rem;">
                                Unique Opens ÷ Delivered Recipients
                            </div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-percent text-white fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metric 2: Unique Opened Recipients -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-none h-100 rounded-3 p-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-semibold text-primary">Unique Opened Recipients</div>
                            <div class="display-6 fw-bold text-primary mt-1" id="kpiUniqueOpened"><?= number_format($openStats['unique_opened'] ?? 0) ?></div>
                            <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                of <span class="fw-semibold text-dark" id="kpiTotalRecipients"><?= number_format($openStats['total_recipients'] ?? 0) ?></span> delivered recipients
                            </div>
                        </div>
                        <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-user-check text-primary fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metric 3: Total Open Events -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-none h-100 rounded-3 p-3" style="background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-semibold" style="color: #be185d;">Total Open Events</div>
                            <div class="display-6 fw-bold mt-1" style="color: #be185d;" id="kpiTotalOpens"><?= number_format($openStats['total_opens'] ?? 0) ?></div>
                            <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                Multi-reads recorded
                            </div>
                        </div>
                        <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fa-solid fa-fire" style="color: #be185d; font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metric 4: Unopened / Pending -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-none h-100 rounded-3 p-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-semibold text-secondary">Unopened / Not Opened</div>
                            <div class="display-6 fw-bold text-dark mt-1" id="kpiNotOpened"><?= number_format($openStats['not_opened'] ?? 0) ?></div>
                            <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                Across <span class="fw-semibold text-dark" id="kpiTotalTracked"><?= number_format($openStats['total_tracked'] ?? 0) ?></span> sent emails
                            </div>
                        </div>
                        <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="fa-regular fa-envelope text-secondary fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipient-Level Engagement Table -->
        <div class="border rounded-3 overflow-hidden">
            <div class="bg-light py-2.5 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold text-dark small">
                    <i class="fa-solid fa-users text-primary me-1"></i> Recipient-Level Open Information (প্রাপকভিত্তিক ওপেন রিপোর্ট)
                </span>
                <span class="badge bg-white text-muted border px-2 py-0.5 rounded-pill" id="openRecipientsCountBadge">
                    <?= count($openRecipients ?? []) ?> Recipients
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="recipientOpenTable" style="min-width: 760px;">
                    <thead class="table-light small text-muted text-uppercase" style="font-size: 0.74rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-3 py-2.5">Recipient</th>
                            <th class="py-2.5 text-center">Open Status</th>
                            <th class="py-2.5 text-center">Total Opens</th>
                            <th class="py-2.5">First Opened</th>
                            <th class="py-2.5">Last Opened</th>
                            <th class="pe-3 py-2.5 text-end">Telemetry</th>
                        </tr>
                    </thead>
                    <tbody id="recipientOpenTableBody">
                        <?php if (empty($openRecipients)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4 small">
                                No tracked recipient emails found for this period. As outgoing emails are opened, recipient-level telemetry will appear here in real time.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($openRecipients as $rRow): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark font-monospace text-truncate" style="max-width: 240px;">
                                        <?= e($rRow['recipient_email']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        Via <?= e($rRow['sending_account']) ?> &bull; <?= e($rRow['last_subject']) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($rRow['status'] === 'Opened'): ?>
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill fw-semibold">
                                            <i class="fa-solid fa-check me-1"></i> Opened
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 px-2.5 py-1 rounded-pill">
                                            <i class="fa-regular fa-envelope me-1"></i> Not Opened
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rRow['total_opens'] > 0): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 rounded-pill fw-bold fs-6">
                                            <?= (int)$rRow['total_opens'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted font-monospace small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($rRow['first_opened']): ?>
                                        <div class="fw-semibold text-dark"><?= date('M j, Y', strtotime($rRow['first_opened'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($rRow['first_opened'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($rRow['last_opened']): ?>
                                        <div class="fw-semibold text-dark"><?= date('M j, Y', strtotime($rRow['last_opened'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($rRow['last_opened'])) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-end">
                                    <?php if (!empty($rRow['latest_tracking_id'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0.5" style="font-size: 0.78rem;" onclick="loadOpenEvents(<?= (int)$rRow['latest_tracking_id'] ?>, '<?= e(addslashes($rRow['recipient_email'])) ?>')">
                                            <i class="fa-solid fa-timeline me-1"></i> Events
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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

                            <?php if (!empty($log['open_count']) && $log['open_count'] > 0): ?>
                                <div>
                                    <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-25 px-2 py-0.5 rounded-pill mt-1 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;" title="Opened <?= (int)$log['open_count'] ?> times">
                                        <i class="fa-solid fa-envelope-open text-primary"></i> Opened (<?= (int)$log['open_count'] ?>)
                                    </span>
                                </div>
                            <?php elseif ($status === 'completed'): ?>
                                <div>
                                    <span class="badge bg-light text-muted border px-2 py-0.5 rounded-pill mt-1 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                        <i class="fa-regular fa-envelope"></i> Unopened
                                    </span>
                                </div>
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
                                    data-tracking-id="<?= (int)($log['tracking_id'] ?? 0) ?>"
                                    data-open-status="<?= e($log['open_status'] ?? 'Not Opened') ?>"
                                    data-open-count="<?= (int)($log['open_count'] ?? 0) ?>"
                                    data-first-opened="<?= e(!empty($log['first_opened_at']) ? date('M j, Y h:i A', strtotime($log['first_opened_at'])) : '—') ?>"
                                    data-last-opened="<?= e(!empty($log['last_opened_at']) ? date('M j, Y h:i A', strtotime($log['last_opened_at'])) : '—') ?>"
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

                <!-- Real Email Open-Tracking Card inside Message Details -->
                <div class="card border rounded-3 mb-3" style="background: #fdfdfd;">
                    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-dark">
                            <i class="fa-solid fa-envelope-open-text text-primary me-1"></i> Open-Rate Tracking Status
                        </span>
                        <span class="badge bg-secondary rounded-pill" id="modalOpenStatusBadge">Not Opened</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 small text-muted">
                            <div class="col-6 col-sm-3">
                                <div>Total Opens:</div>
                                <div class="fw-bold fs-6 text-dark" id="modalTotalOpens">0</div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div>Unique Open:</div>
                                <div class="fw-semibold text-dark" id="modalUniqueOpen">—</div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div>First Opened:</div>
                                <div class="fw-semibold text-dark" id="modalFirstOpened">—</div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div>Last Opened:</div>
                                <div class="fw-semibold text-dark" id="modalLastOpened">—</div>
                            </div>
                        </div>

                        <!-- Open Events Timeline Inside Modal -->
                        <div class="mt-3 pt-2 border-top d-none" id="modalOpenEventsSection">
                            <div class="small fw-bold text-dark mb-2">
                                <i class="fa-solid fa-timeline text-primary me-1"></i> Open Event History:
                            </div>
                            <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.78rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Device &amp; OS</th>
                                            <th>Client / Proxy</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalEventsTableBody">
                                    </tbody>
                                </table>
                            </div>
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

<!-- Modal: Recipient Open Events History -->
<div class="modal fade" id="viewOpenEventsModal" tabindex="-1" aria-labelledby="viewOpenEventsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-timeline"></i>
                    </div>
                    <h5 class="modal-title fw-bold fs-6 mb-0" id="viewOpenEventsModalLabel">Recipient Open Events History</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 mb-3 border">
                    <div class="row g-2 small">
                        <div class="col-sm-3 text-muted">Recipient:</div>
                        <div class="col-sm-9 fw-bold text-dark font-monospace" id="eventModalRecipient"></div>

                        <div class="col-sm-3 text-muted">Subject:</div>
                        <div class="col-sm-9 text-dark" id="eventModalSubject"></div>

                        <div class="col-sm-3 text-muted">Status:</div>
                        <div class="col-sm-9">
                            <span class="badge bg-success rounded-pill" id="eventModalStatusBadge">Opened</span>
                            <span class="text-muted ms-2 small" id="eventModalTotalOpensText"></span>
                        </div>
                    </div>
                </div>

                <div class="small fw-bold text-dark mb-2">
                    <i class="fa-solid fa-list-check text-primary me-1"></i> Recorded Open Events (বাস্তব ট্র্যাকিং ইভেন্টসমূহ):
                </div>
                <div class="table-responsive border rounded-3" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-2">Time</th>
                                <th class="py-2">Client / App</th>
                                <th class="py-2">Device &amp; OS</th>
                                <th class="py-2">Type</th>
                                <th class="pe-3 py-2 text-end">IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="openEventsFullTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Loading open telemetry...</td>
                            </tr>
                        </tbody>
                    </table>
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

    var trackingId = parseInt(btn.getAttribute('data-tracking-id') || '0', 10);
    var openStatus = btn.getAttribute('data-open-status') || 'Not Opened';
    var openCount = parseInt(btn.getAttribute('data-open-count') || '0', 10);
    var firstOpened = btn.getAttribute('data-first-opened') || '—';
    var lastOpened = btn.getAttribute('data-last-opened') || '—';

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

    // Open tracking information in modal
    var openStatusBadge = document.getElementById('modalOpenStatusBadge');
    if (openCount > 0) {
        openStatusBadge.textContent = 'Opened (' + openCount + ')';
        openStatusBadge.className = 'badge bg-success rounded-pill';
        document.getElementById('modalUniqueOpen').textContent = 'Yes (1)';
    } else {
        openStatusBadge.textContent = 'Not Opened';
        openStatusBadge.className = 'badge bg-secondary rounded-pill';
        document.getElementById('modalUniqueOpen').textContent = 'No (0)';
    }

    document.getElementById('modalTotalOpens').textContent = openCount;
    document.getElementById('modalFirstOpened').textContent = firstOpened || '—';
    document.getElementById('modalLastOpened').textContent = lastOpened || '—';

    var eventsSection = document.getElementById('modalOpenEventsSection');
    var eventsTbody = document.getElementById('modalEventsTableBody');
    eventsTbody.innerHTML = '';

    if (trackingId > 0 && openCount > 0) {
        eventsSection.classList.remove('d-none');
        fetch('<?= url('/reports/open-rate/events') ?>/' + trackingId)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.events && data.events.length > 0) {
                    var html = '';
                    data.events.forEach(function(ev) {
                        html += '<tr>' +
                            '<td>' + ev.opened_at_formatted + '</td>' +
                            '<td>' + (ev.device_type || 'Unknown') + ' / ' + (ev.operating_system || 'Unknown') + '</td>' +
                            '<td>' + (ev.mail_client || ev.browser || 'Unknown') + '</td>' +
                            '<td class="font-monospace">' + (ev.ip_address || '—') + '</td>' +
                            '</tr>';
                    });
                    eventsTbody.innerHTML = html;
                } else {
                    eventsTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No individual event records.</td></tr>';
                }
            })
            .catch(function() {
                eventsTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Could not load event timeline.</td></tr>';
            });
    } else {
        eventsSection.classList.add('d-none');
    }

    var bodyContainer = document.getElementById('modalBodyContainer');
    if (body.indexOf('<') !== -1 && body.indexOf('>') !== -1) {
        bodyContainer.innerHTML = body;
    } else {
        bodyContainer.innerText = body;
    }
}

// Load full open events for recipient modal
function loadOpenEvents(trackingId, recipientEmail) {
    var modalEl = document.getElementById('viewOpenEventsModal');
    var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    document.getElementById('eventModalRecipient').textContent = recipientEmail;
    document.getElementById('eventModalSubject').textContent = 'Loading...';
    var tbody = document.getElementById('openEventsFullTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading telemetry...</td></tr>';
    
    bsModal.show();

    fetch('<?= url('/reports/open-rate/events') ?>/' + trackingId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Could not load tracking record.</td></tr>';
                return;
            }
            document.getElementById('eventModalSubject').textContent = data.tracking.subject || '(No Subject)';
            var statusBadge = document.getElementById('eventModalStatusBadge');
            if (data.tracking.open_count > 0) {
                statusBadge.textContent = 'Opened (' + data.tracking.open_count + ' total opens)';
                statusBadge.className = 'badge bg-success rounded-pill';
            } else {
                statusBadge.textContent = 'Not Opened';
                statusBadge.className = 'badge bg-secondary rounded-pill';
            }

            if (data.events && data.events.length > 0) {
                var html = '';
                data.events.forEach(function(ev) {
                    var botBadge = ev.is_bot_or_prefetch ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Proxy/Prefetch</span>' : '<span class="badge bg-info text-white ms-1" style="font-size:0.65rem;">Direct</span>';
                    html += '<tr>' +
                        '<td class="ps-3 font-monospace">' + ev.opened_at_formatted + '</td>' +
                        '<td><span class="fw-semibold">' + (ev.mail_client || 'Standard Client') + '</span></td>' +
                        '<td>' + (ev.device_type || 'Unknown') + ' &bull; ' + (ev.operating_system || 'Unknown') + '</td>' +
                        '<td>' + botBadge + '</td>' +
                        '<td class="pe-3 text-end font-monospace text-muted">' + (ev.ip_address || '—') + '</td>' +
                        '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No open events recorded yet.</td></tr>';
            }
        })
        .catch(function(err) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Failed to load open events.</td></tr>';
        });
}

// Live polling & stats refresh
function refreshOpenRateData() {
    var icon = document.getElementById('refreshOpenIcon');
    if (icon) icon.classList.add('fa-spin');

    var currentParams = new URLSearchParams(window.location.search);
    var queryUrl = '<?= url('/reports/open-rate/stats') ?>?' + currentParams.toString();

    fetch(queryUrl)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (icon) icon.classList.remove('fa-spin');
            if (!data.success) return;

            var s = data.stats;
            document.getElementById('kpiOpenRate').textContent = parseFloat(s.open_rate).toFixed(1) + '%';
            document.getElementById('kpiUniqueOpened').textContent = s.unique_opened.toLocaleString();
            document.getElementById('kpiTotalOpens').textContent = s.total_opens.toLocaleString();
            document.getElementById('kpiNotOpened').textContent = s.not_opened.toLocaleString();
            document.getElementById('kpiTotalTracked').textContent = s.total_tracked.toLocaleString();
            document.getElementById('kpiTotalRecipients').textContent = s.total_recipients.toLocaleString();

            // Update recipient table
            var tbody = document.getElementById('recipientOpenTableBody');
            if (tbody && data.recipients) {
                document.getElementById('openRecipientsCountBadge').textContent = data.recipients.length + ' Recipients';
                if (data.recipients.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4 small">No tracked recipient emails found for this period. As outgoing emails are opened, recipient-level telemetry will appear here in real time.</td></tr>';
                } else {
                    var html = '';
                    data.recipients.forEach(function(r) {
                        var statusBadge = (r.status === 'Opened')
                            ? '<span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill fw-semibold"><i class="fa-solid fa-check me-1"></i> Opened</span>'
                            : '<span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 px-2.5 py-1 rounded-pill"><i class="fa-regular fa-envelope me-1"></i> Not Opened</span>';
                        
                        var totalOpensBadge = (r.total_opens > 0)
                            ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1 rounded-pill fw-bold fs-6">' + r.total_opens + '</span>'
                            : '<span class="text-muted font-monospace small">0</span>';

                        var firstOp = r.first_opened ? '<div class="fw-semibold text-dark">' + r.first_opened.substring(0, 10) + '</div><div class="text-muted" style="font-size:0.75rem;">' + r.first_opened.substring(11) + '</div>' : '&mdash;';
                        var lastOp = r.last_opened ? '<div class="fw-semibold text-dark">' + r.last_opened.substring(0, 10) + '</div><div class="text-muted" style="font-size:0.75rem;">' + r.last_opened.substring(11) + '</div>' : '&mdash;';

                        var actionBtn = r.latest_tracking_id
                            ? '<button type="button" class="btn btn-sm btn-outline-primary px-2 py-0.5" style="font-size: 0.78rem;" onclick="loadOpenEvents(' + r.latest_tracking_id + ', \'' + (r.recipient_email || '').replace(/'/g, "\\'") + '\')"><i class="fa-solid fa-timeline me-1"></i> Events</button>'
                            : '&mdash;';

                        html += '<tr>' +
                            '<td class="ps-3"><div class="fw-semibold text-dark font-monospace text-truncate" style="max-width: 240px;">' + r.recipient_email + '</div><div class="text-muted" style="font-size:0.75rem;">Via ' + (r.sending_account || '') + ' &bull; ' + (r.last_subject || '') + '</div></td>' +
                            '<td class="text-center">' + statusBadge + '</td>' +
                            '<td class="text-center">' + totalOpensBadge + '</td>' +
                            '<td class="small">' + firstOp + '</td>' +
                            '<td class="small">' + lastOp + '</td>' +
                            '<td class="pe-3 text-end">' + actionBtn + '</td>' +
                            '</tr>';
                    });
                    tbody.innerHTML = html;
                }
            }
        })
        .catch(function(err) {
            if (icon) icon.classList.remove('fa-spin');
        });
}

// Automatic background polling every 20 seconds
setInterval(function() {
    refreshOpenRateData();
}, 20000);
</script>
