<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-gift text-primary me-2"></i> Free Trial Configuration</h4>
        <p class="text-muted small mb-0">Manage SaaS free trial duration, connected Gmail account limits, one-trial-per-user enforcement, and trial expiry rules.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i> Trial Parameters</h6>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/trial') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" name="trial_enabled" id="trial_enabled" value="1" <?= $trialEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark" for="trial_enabled">
                            Enable Free Trial System for New Users
                        </label>
                        <div class="text-muted small ms-4">Allows newly registered users to experience the full Gmail automation engine for a limited time.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Trial Duration (Days)</label>
                            <div class="input-group">
                                <input type="number" name="trial_duration_days" class="form-control" min="1" max="90" value="<?= $trialDuration ?>" required>
                                <span class="input-group-text">Days</span>
                            </div>
                            <div class="form-text">e.g. 7, 14, or 30 days. Landing page will automatically reflect this duration.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Gmail Account Limit During Trial</label>
                            <div class="input-group">
                                <input type="number" name="trial_gmail_limit" class="form-control" min="1" max="100" value="<?= $trialLimit ?>" required>
                                <span class="input-group-text">Accounts</span>
                            </div>
                            <div class="form-text">Maximum number of Gmail accounts trial users can connect.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" name="trial_one_per_user" id="trial_one_per_user" value="1" <?= $trialOnePerUser ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark" for="trial_one_per_user">
                            Enforce One Free Trial Per User Account
                        </label>
                        <div class="text-muted small ms-4">Prevents users from repeatedly starting new trials once expired without admin manual permission.</div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Trial Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 bg-light h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-bell text-warning me-2"></i> Automated Reminders</h6>
                <p class="text-muted small">
                    The background worker automatically checks trials daily and sends automated email notifications:
                </p>
                <ul class="list-unstyled small text-secondary d-flex flex-column gap-2 mb-0">
                    <li><i class="fa-solid fa-check text-success me-2"></i> <strong>Trial Started:</strong> Sent immediately upon trial activation.</li>
                    <li><i class="fa-solid fa-check text-success me-2"></i> <strong>Trial Expiring:</strong> Sent 3 days and 1 day before expiration.</li>
                    <li><i class="fa-solid fa-check text-success me-2"></i> <strong>Trial Expired:</strong> Sent on the day trial expires with upgrade CTA.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
