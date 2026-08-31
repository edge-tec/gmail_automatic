<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Gmail Accounts</h4>
        <p class="text-muted small mb-0">Connect and manage your authorized Gmail accounts for automated replies and follow-ups.</p>
    </div>
    <a href="<?= url('/accounts/connect') ?>" class="btn btn-primary">
        <i class="fa-brands fa-google me-1"></i> Connect Gmail Account
    </a>
</div>

<?php if (empty($accountsData)): ?>
<div class="card p-5 text-center">
    <div class="mb-3">
        <div class="d-inline-flex p-4 rounded-circle bg-primary bg-opacity-10 text-primary fs-1">
            <i class="fa-brands fa-google"></i>
        </div>
    </div>
    <h5 class="fw-bold">No Gmail Accounts Connected</h5>
    <p class="text-muted small mx-auto" style="max-width: 460px;">
        Connect your Gmail account securely using Google OAuth 2.0. We never store your password and access is granted via official Google tokens.
    </p>
    <div class="mt-2">
        <a href="<?= url('/accounts/connect') ?>" class="btn btn-primary px-4 py-2">
            <i class="fa-brands fa-google me-1"></i> Connect Your First Account
        </a>
    </div>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($accountsData as $item): 
        $acc = $item['account'];
        $sett = $item['settings'];
        $usage = $item['usage'];
    ?>
    <div class="col-12 col-xl-6">
        <div class="card card-hover h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                            <i class="fa-brands fa-google"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-truncate" style="max-width: 280px;"><?= e($acc->gmail_email) ?></h5>
                            <div>
                                <?php if ($acc->status === 'connected'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-circle-check me-1"></i> Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Disconnected
                                    </span>
                                <?php endif; ?>
                                <span class="small text-muted ms-2">
                                    <i class="fa-solid fa-rotate me-1"></i> Sync: <?= $acc->last_sync_at ? date('M d, H:i', strtotime($acc->last_sync_at)) : 'Pending' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Sync Button -->
                    <form action="<?= url("/accounts/{$acc->id}/sync") ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sync Inbox Now">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </form>
                </div>

                <?php if ($acc->last_error): ?>
                <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div class="text-truncate"><?= e($acc->last_error) ?></div>
                </div>
                <?php endif; ?>

                <div class="row g-2 p-3 bg-light rounded-3 mb-3 text-center">
                    <div class="col-6 border-end">
                        <div class="small text-muted">Today's Replies</div>
                        <div class="fs-5 fw-bold text-success mt-1">
                            <?= $usage['reply_count'] ?> <span class="text-muted fs-6 fw-normal">/ <?= $sett->daily_reply_limit ?? 100 ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Today's Follow-ups</div>
                        <div class="fs-5 fw-bold text-info mt-1">
                            <?= $usage['followup_count'] ?> <span class="text-muted fs-6 fw-normal">/ <?= $sett->daily_followup_limit ?? 100 ?></span>
                        </div>
                    </div>
                </div>

                <!-- Feature Toggles -->
                <div class="d-flex flex-column gap-2 mb-4">
                    <div class="d-flex justify-content-between align-items-center p-2 border rounded-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-robot text-primary"></i>
                            <span class="small fw-semibold">Auto Reply Engine</span>
                        </div>
                        <form action="<?= url("/accounts/{$acc->id}/toggle-reply") ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" onchange="this.form.submit()" <?= ($sett && $sett->auto_reply_enabled) ? 'checked' : '' ?>>
                            </div>
                        </form>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-2 border rounded-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-arrows-split-up-and-left text-info"></i>
                            <span class="small fw-semibold">Follow-up Automation</span>
                        </div>
                        <form action="<?= url("/accounts/{$acc->id}/toggle-followup") ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" onchange="this.form.submit()" <?= ($sett && $sett->followup_enabled) ? 'checked' : '' ?>>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <div class="d-flex gap-2">
                        <a href="<?= url("/settings/automation/{$acc->id}") ?>" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-sliders me-1"></i> Auto Reply
                        </a>
                        <a href="<?= url("/settings/followups/{$acc->id}") ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-list-ol me-1"></i> Follow-up Steps
                        </a>
                    </div>

                    <!-- Disconnect Button -->
                    <form action="<?= url("/accounts/{$acc->id}/disconnect") ?>" method="POST" onsubmit="return confirm('Are you sure you want to disconnect this Gmail account? All scheduled jobs for this account will be removed.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fa-solid fa-link-slash me-1"></i> Disconnect
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
