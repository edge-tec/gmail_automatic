<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Bulk Email Campaigns</h4>
        <p class="text-muted small mb-0">Multi-Gmail round-robin campaign engine with automated recipient import and personalization.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/campaigns/accounts') ?>" class="btn btn-outline-secondary">
            <i class="fa-brands fa-google me-1"></i> Per-Gmail Sending Limits
        </a>
        <a href="<?= url('/campaigns/create') ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Create New Campaign
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card p-3 h-100 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-layer-group text-primary me-1"></i> Total Campaigns</div>
            <h3 class="fw-bold mb-0"><?= number_format($stats['total_campaigns']) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card p-3 h-100 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-circle-play text-success me-1"></i> Active Campaigns</div>
            <h3 class="fw-bold text-success mb-0"><?= number_format($stats['active_campaigns']) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card p-3 h-100 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-users text-info me-1"></i> Total Recipients</div>
            <h3 class="fw-bold text-info mb-0"><?= number_format($stats['total_recipients']) ?></h3>
        </div>
    </div>
    <div class="col-6 col-md-6 col-xl">
        <div class="card p-3 h-100 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-check-double text-success me-1"></i> Emails Sent</div>
            <h3 class="fw-bold text-success mb-0"><?= number_format($stats['total_sent']) ?></h3>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="card p-3 h-100 border-0 shadow-sm">
            <div class="text-muted small mb-1"><i class="fa-solid fa-hourglass-half text-warning me-1"></i> Pending Queue</div>
            <h3 class="fw-bold text-warning mb-0"><?= number_format($stats['total_pending']) ?></h3>
        </div>
    </div>
</div>

<?php if (empty($campaigns)): ?>
<div class="card p-5 text-center border-0 shadow-sm">
    <div class="mb-3">
        <div class="d-inline-flex p-4 rounded-circle bg-warning bg-opacity-10 text-warning fs-1">
            <i class="fa-solid fa-paper-plane"></i>
        </div>
    </div>
    <h5 class="fw-bold">No Bulk Email Campaigns Created Yet</h5>
    <p class="text-muted small mx-auto" style="max-width: 480px;">
        Reach out to your leads and clients using true round-robin Gmail distribution. Upload CSV, TXT, or Excel lists, define multiple dynamic message variations, and protect your accounts with per-Gmail sending limits.
    </p>
    <div class="mt-2">
        <a href="<?= url('/campaigns/create') ?>" class="btn btn-primary px-4 py-2">
            <i class="fa-solid fa-plus me-1"></i> Create Your First Campaign
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase small text-muted">
                <tr>
                    <th style="min-width: 220px;">Campaign Name</th>
                    <th>Status</th>
                    <th style="min-width: 200px;">Progress</th>
                    <th>Limits &amp; Pace</th>
                    <th>Schedule</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): 
                    $percent = $c->getProgressPercentage();
                    $rem = $c->getRemainingCount();
                ?>
                <tr>
                    <td>
                        <a href="<?= url('/campaigns/' . $c->id) ?>" class="fw-bold text-decoration-none text-dark d-block">
                            <?= e($c->name) ?>
                        </a>
                        <span class="small text-muted">
                            <i class="fa-solid fa-envelope me-1"></i> <?= number_format($c->total_recipients) ?> recipients
                        </span>
                    </td>
                    <td>
                        <?php if ($c->status === 'active'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="fa-solid fa-play me-1"></i> Active
                            </span>
                        <?php elseif ($c->status === 'paused'): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                <i class="fa-solid fa-pause me-1"></i> Paused
                            </span>
                        <?php elseif ($c->status === 'completed'): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                <i class="fa-solid fa-check-circle me-1"></i> Completed
                            </span>
                        <?php elseif ($c->status === 'cancelled'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                <i class="fa-solid fa-ban me-1"></i> Cancelled
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                <i class="fa-solid fa-pen me-1"></i> Draft
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Sent: <strong class="text-dark"><?= number_format($c->sent_count) ?></strong> / <?= number_format($c->total_recipients) ?></span>
                            <span class="fw-semibold"><?= $percent ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <?php if ($c->failed_count > 0 || $c->skipped_count > 0): ?>
                            <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                <?php if ($c->failed_count > 0): ?>
                                    <span class="text-danger me-2"><i class="fa-solid fa-circle-exclamation me-1"></i><?= $c->failed_count ?> failed</span>
                                <?php endif; ?>
                                <?php if ($c->skipped_count > 0): ?>
                                    <span class="text-muted"><i class="fa-solid fa-forward me-1"></i><?= $c->skipped_count ?> skipped</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small">
                            <span class="text-muted">Daily Limit:</span> <strong><?= number_format($c->daily_campaign_limit) ?></strong><br>
                            <span class="text-muted">Interval:</span> <?= $c->sending_interval ?>s
                        </div>
                    </td>
                    <td>
                        <div class="small">
                            <i class="fa-regular fa-clock me-1 text-muted"></i><?= e($c->start_time) ?> - <?= e($c->end_time) ?><br>
                            <span class="text-muted" style="font-size: 0.75rem;"><?= e($c->timezone) ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="small text-muted"><?= $c->created_at ? date('M d, Y', strtotime($c->created_at)) : '-' ?></span>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="<?= url('/campaigns/' . $c->id) ?>" class="btn btn-sm btn-outline-primary" title="View Dashboard">
                                <i class="fa-solid fa-chart-pie"></i>
                            </a>
                            <a href="<?= url('/campaigns/' . $c->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary" title="Edit Campaign">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>

                            <?php if ($c->status === 'active'): ?>
                                <form action="<?= url('/campaigns/' . $c->id . '/pause') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Pause Campaign">
                                        <i class="fa-solid fa-pause"></i>
                                    </button>
                                </form>
                            <?php elseif (in_array($c->status, ['paused', 'draft'])): ?>
                                <form action="<?= url('/campaigns/' . $c->id . '/resume') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Resume Campaign">
                                        <i class="fa-solid fa-play"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($c->status, ['active', 'paused', 'draft'])): ?>
                                <form action="<?= url('/campaigns/' . $c->id . '/cancel') ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently cancel this campaign?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Campaign">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
