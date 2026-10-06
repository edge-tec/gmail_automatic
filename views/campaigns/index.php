<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Bulk Email Campaigns</h4>
        <p class="text-muted small mb-0">Multi-Gmail round-robin campaign engine with automated recipient import and personalization.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearAllCampaignsLeadsModal">
            <i class="fa-solid fa-broom me-1"></i> <span class="d-none d-sm-inline">Clear Campaign Leads</span><span class="d-sm-none">Clear Leads</span>
        </button>
        <a href="<?= url('/campaigns/accounts') ?>" class="btn btn-outline-secondary">
            <i class="fa-brands fa-google me-1"></i> <span class="d-none d-sm-inline">Per-Gmail Sending Limits</span><span class="d-sm-none">Gmail Limits</span>
        </a>
        <a href="<?= url('/campaigns/create') ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> <span class="d-none d-sm-inline">Create New Campaign</span><span class="d-sm-none">New Campaign</span>
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
                        <div class="d-inline-flex gap-1 align-items-center">
                            <a href="<?= url('/campaigns/' . $c->id) ?>" class="btn btn-sm btn-outline-primary" title="View Dashboard">
                                <i class="fa-solid fa-chart-pie"></i>
                            </a>
                            <a href="<?= url('/campaigns/' . $c->id . '/edit') ?>" class="btn btn-sm btn-outline-secondary" title="Edit Campaign">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <a href="<?= url('/campaigns/' . $c->id . '/export-recipients') ?>" class="btn btn-sm btn-outline-success" title="Download Leads (CSV)">
                                <i class="fa-solid fa-file-csv"></i>
                            </a>

                            <?php if (in_array($c->status, ['active', 'completed'])): ?>
                                <form action="<?= url('/campaigns/' . $c->id . '/pause') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="/campaigns">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Pause Campaign">
                                        <i class="fa-solid fa-pause"></i>
                                    </button>
                                </form>
                            <?php elseif (in_array($c->status, ['paused', 'draft', 'cancelled'])): ?>
                                <form action="<?= url('/campaigns/' . $c->id . '/resume') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="/campaigns">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Resume Campaign">
                                        <i class="fa-solid fa-play"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#clearLeadsModal<?= $c->id ?>" title="Clear Imported Leads">
                                <i class="fa-solid fa-broom"></i>
                            </button>

                            <form action="<?= url('/campaigns/' . $c->id . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete campaign \'<?= htmlspecialchars(addslashes($c->name), ENT_QUOTES) ?>\' and all its recipients?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="redirect_to" value="/campaigns">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Campaign">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Modal for Clearing Leads of Campaign #<?= $c->id ?> -->
                        <div class="modal fade" id="clearLeadsModal<?= $c->id ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form action="<?= url('/campaigns/' . $c->id . '/clear-recipients') ?>" method="POST" class="modal-content text-start">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="/campaigns">
                                    <div class="modal-header bg-warning-subtle text-dark">
                                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-broom text-warning me-2"></i>Clear Imported Leads</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2">You are about to clear imported recipient leads for campaign <strong><?= e($c->name) ?></strong>.</p>
                                        <div class="p-3 bg-light rounded mb-3 small">
                                            <div>Total Leads: <strong><?= number_format($c->total_recipients) ?></strong></div>
                                            <div>Sent: <strong><?= number_format($c->sent_count) ?></strong> | Failed: <strong><?= number_format($c->failed_count) ?></strong> | Pending: <strong><?= number_format($c->getRemainingCount()) ?></strong></div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold small">Choose What to Clear:</label>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="scope" id="scope_all_<?= $c->id ?>" value="all" checked>
                                                <label class="form-check-label" for="scope_all_<?= $c->id ?>">
                                                    <strong>Clear All Leads (<?= number_format($c->total_recipients) ?>)</strong>
                                                    <div class="text-muted small">Wipes all imported leads and resets campaign status to draft.</div>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="scope" id="scope_completed_<?= $c->id ?>" value="completed">
                                                <label class="form-check-label" for="scope_completed_<?= $c->id ?>">
                                                    <strong>Clear Completed &amp; Failed Leads Only (<?= number_format($c->sent_count + $c->failed_count + $c->skipped_count) ?>)</strong>
                                                    <div class="text-muted small">Retains pending queue leads and removes already sent or failed leads.</div>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="scope" id="scope_failed_<?= $c->id ?>" value="failed">
                                                <label class="form-check-label" for="scope_failed_<?= $c->id ?>">
                                                    <strong>Clear Failed Leads Only (<?= number_format($c->failed_count) ?>)</strong>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="scope" id="scope_pending_<?= $c->id ?>" value="pending">
                                                <label class="form-check-label" for="scope_pending_<?= $c->id ?>">
                                                    <strong>Clear Pending Queue Only (<?= number_format($c->getRemainingCount()) ?>)</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="confirm_check_<?= $c->id ?>" required>
                                            <label class="form-check-label small text-danger fw-semibold" for="confirm_check_<?= $c->id ?>">
                                                I confirm that I want to permanently delete these imported campaign leads.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-broom me-1"></i> Clear Leads Now</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Top Modal: Clear All Campaign Leads -->
<div class="modal fade" id="clearAllCampaignsLeadsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= url('/campaigns/clear-all-recipients') ?>" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-danger-subtle text-danger">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-broom me-2"></i>Clear Bulk Campaign Leads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <p class="mb-3 text-muted small">Select which campaign you want to clear imported leads from, or clear leads across all campaigns at once.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Select Target Campaign:</label>
                    <select name="campaign_id" class="form-select">
                        <option value="0">All Campaigns (<?= number_format($stats['total_recipients'] ?? 0) ?> total leads)</option>
                        <?php if (!empty($campaigns)): ?>
                            <?php foreach ($campaigns as $camp): ?>
                                <option value="<?= $camp->id ?>"><?= e($camp->name) ?> (<?= number_format($camp->total_recipients) ?> leads)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Choose What to Clear:</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="scope" id="scope_all_global" value="all" checked>
                        <label class="form-check-label" for="scope_all_global">
                            <strong>Clear All Imported Leads</strong>
                            <div class="text-muted small">Wipes all imported recipient records and resets campaigns to draft.</div>
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="scope" id="scope_completed_global" value="completed">
                        <label class="form-check-label" for="scope_completed_global">
                            <strong>Clear Completed &amp; Failed Leads Only</strong>
                            <div class="text-muted small">Deletes already sent or failed records to free database space.</div>
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="scope" id="scope_failed_global" value="failed">
                        <label class="form-check-label" for="scope_failed_global">
                            <strong>Clear Failed Leads Only</strong>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" id="scope_pending_global" value="pending">
                        <label class="form-check-label" for="scope_pending_global">
                            <strong>Clear Pending Queue Only</strong>
                        </label>
                    </div>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="confirm_check_global" required>
                    <label class="form-check-label small text-danger fw-semibold" for="confirm_check_global">
                        I confirm that I want to permanently delete these imported campaign leads.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-broom me-1"></i> Clear Campaign Leads</button>
            </div>
        </form>
    </div>
</div>
