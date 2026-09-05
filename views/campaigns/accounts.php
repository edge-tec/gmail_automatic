<div class="mb-4">
    <a href="<?= url('/campaigns') ?>" class="text-decoration-none text-muted small">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Campaigns
    </a>
    <h4 class="fw-bold mt-2 mb-1"><i class="fa-brands fa-google text-danger me-2"></i>Per-Gmail Sending Limits &amp; Quotas</h4>
    <p class="text-muted small mb-0">Configure account-level daily bulk sending limits and enable/disable specific accounts from participating in round-robin campaigns.</p>
</div>

<?php if (empty($accounts)): ?>
<div class="card p-5 text-center border-0 shadow-sm">
    <div class="mb-3">
        <div class="d-inline-flex p-4 rounded-circle bg-danger bg-opacity-10 text-danger fs-1">
            <i class="fa-brands fa-google"></i>
        </div>
    </div>
    <h5 class="fw-bold">No Gmail Accounts Connected</h5>
    <p class="text-muted small mx-auto" style="max-width: 440px;">
        Connect your Gmail accounts first to configure bulk sending limits.
    </p>
    <div class="mt-2">
        <a href="<?= url('/accounts/connect') ?>" class="btn btn-primary">
            <i class="fa-brands fa-google me-1"></i> Connect Gmail Account
        </a>
    </div>
</div>
<?php else: ?>

<form action="<?= url('/campaigns/accounts') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0">Connected Accounts Sending Quotas</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted text-uppercase">
                    <tr>
                        <th>Gmail Account</th>
                        <th style="width: 160px;">Bulk Daily Limit</th>
                        <th>Sent Today</th>
                        <th>Remaining</th>
                        <th>Round-Robin Pool</th>
                        <th>Account Health</th>
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
                            <div class="d-flex align-items-center gap-2">
                                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-circle">
                                    <i class="fa-brands fa-google"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= e($acc->gmail_email) ?></div>
                                    <span class="small text-muted">ID: #<?= $acc->id ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" name="limits[<?= $acc->id ?>]" class="form-control" value="<?= $acc->bulk_daily_limit ?>" min="1" max="2000" required>
                                <span class="input-group-text text-muted" style="font-size: 0.75rem;">/day</span>
                            </div>
                        </td>
                        <td>
                            <span class="fw-bold text-dark"><?= $sent ?></span> <span class="text-muted small">/ <?= $limit ?></span>
                            <div class="progress mt-1" style="height: 5px; max-width: 140px;">
                                <div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $pct ?>%;"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-6 fw-bold">
                                <?= $rem ?>
                            </span>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enabled[<?= $acc->id ?>]" value="1" id="sw_<?= $acc->id ?>" <?= $acc->campaign_enabled ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="sw_<?= $acc->id ?>">
                                    <?= $acc->campaign_enabled ? 'Enabled' : 'Excluded' ?>
                                </label>
                            </div>
                        </td>
                        <td>
                            <?php if ($acc->status !== 'connected'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Disconnected</span>
                            <?php elseif ($acc->temp_unavailable_until !== null && strtotime($acc->temp_unavailable_until) > time()): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Cooling Down</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>Healthy</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white p-3 border-0 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                <i class="fa-solid fa-circle-info text-primary me-1"></i> Daily quotas reset at 00:00:00 system time.
            </div>
            <button type="submit" class="btn btn-primary px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save Limits &amp; Settings
            </button>
        </div>
    </div>
</form>

<?php endif; ?>
