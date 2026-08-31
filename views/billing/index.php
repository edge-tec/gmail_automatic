<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Billing &amp; Subscription Management</h4>
        <p class="text-muted small mb-0">Manage your active subscription, upgrade Gmail account limits, view invoices, and track free trial status.</p>
    </div>
    <div>
        <span class="badge <?= $user->hasActiveSubscription() ? 'bg-success' : ($user->isTrialActive() ? 'bg-info' : 'bg-warning text-dark') ?> fs-6 px-3 py-2">
            <i class="fa-solid fa-circle me-1 small"></i> <?= e($user->getActivePlanName()) ?>
        </span>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Card 1: Subscription Status -->
    <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-credit-card text-primary me-2"></i> Current Plan Details</h6>
                <span class="badge bg-light text-dark border"><?= ucfirst($user->subscription_status) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Plan Type</div>
                        <div class="fs-5 fw-bold text-dark mt-1"><?= ucfirst($user->plan_type) ?> Plan</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Gmail Account Limit</div>
                        <div class="fs-5 fw-bold text-primary mt-1"><?= $user->getMaxGmailAccounts() ?> Accounts</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Billing Cycle</div>
                        <div class="fs-5 fw-bold text-dark mt-1">Monthly</div>
                    </div>
                </div>

                <!-- Free Trial Status Bar if Active -->
                <?php if ($user->isTrialActive()): ?>
                <?php 
                    $totalTrialSeconds = $user->trial_days * 86400;
                    $remainingSeconds = max(0, strtotime($user->trial_ends_at) - time());
                    $remainingDays = ceil($remainingSeconds / 86400);
                    $percentLeft = $totalTrialSeconds > 0 ? max(5, min(100, round(($remainingSeconds / $totalTrialSeconds) * 100))) : 0;
                ?>
                <div class="bg-primary bg-opacity-10 p-3 rounded-3 border border-primary border-opacity-25 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-primary small"><i class="fa-solid fa-gift me-1"></i> Free Trial Active</span>
                        <span class="fw-bold text-dark small"><?= $remainingDays ?> Days Remaining (Expires: <?= date('d M Y', strtotime($user->trial_ends_at)) ?>)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $percentLeft ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user->canStartTrial()): ?>
                <div class="bg-light p-3 rounded-3 border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold text-dark"><i class="fa-solid fa-wand-magic-sparkles text-warning me-1"></i> Start Your <?= $trialDays ?>-Day Free Trial</div>
                        <div class="text-muted small">Connect up to <?= $trialLimit ?> Gmail accounts for <?= $trialDays ?> days with zero commitment.</div>
                    </div>
                    <form action="<?= url('/billing/start-trial') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 py-2">
                            Activate Free Trial
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Account Usage Meter -->
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold">Connected Gmail Accounts Usage</span>
                        <span class="fw-bold text-dark small"><?= $connectedAccountsCount ?> / <?= $user->getMaxGmailAccounts() ?> Accounts</span>
                    </div>
                    <?php $usagePercent = min(100, round(($connectedAccountsCount / max(1, $user->getMaxGmailAccounts())) * 100)); ?>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar <?= $usagePercent >= 90 ? 'bg-danger' : ($usagePercent >= 70 ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= max(5, $usagePercent) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Security & Payment Gateways -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div>
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-shield-check text-success me-2"></i> Safe &amp; Encrypted Billing</h6>
                    <p class="text-muted small">
                        All payments are processed securely via Stripe. We do not store any credit card numbers on our server. Subscriptions can be managed or cancelled anytime.
                    </p>
                    <ul class="list-unstyled small text-secondary d-flex flex-column gap-2 mb-0">
                        <li><i class="fa-solid fa-check text-success me-2"></i> Official Stripe 256-bit encryption</li>
                        <li><i class="fa-solid fa-check text-success me-2"></i> Automatic package activation upon checkout</li>
                        <li><i class="fa-solid fa-check text-success me-2"></i> Instant invoice delivery via email</li>
                    </ul>
                </div>
                <div class="pt-3 border-top mt-3 text-center text-muted small">
                    Need custom agency limits or enterprise plans? <a href="mailto:support@2xbets.net" class="text-primary fw-semibold">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Available Plans Row -->
<div class="mb-5">
    <h5 class="fw-bold mb-3"><i class="fa-solid fa-tags text-primary me-2"></i> Available Subscription Plans</h5>
    <div class="row g-4">
        <?php foreach ($plans as $plan): ?>
        <?php $isCurrent = ($user->hasActiveSubscription() && $user->plan_id === $plan->id); ?>
        <div class="col-12 col-md-6 col-lg-6">
            <div class="card h-100 shadow-sm border-0 <?= $plan->is_popular ? 'border-primary border-2' : '' ?>">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0"><?= e($plan->name) ?> Plan</h5>
                            <?php if ($isCurrent): ?>
                            <span class="badge bg-success">Current Plan</span>
                            <?php elseif ($plan->is_popular): ?>
                            <span class="badge bg-primary">Most Popular</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-baseline gap-1 mb-3">
                            <span class="fs-2 fw-extrabold text-dark">$<?= number_format($plan->price, 0) ?></span>
                            <span class="text-muted fw-semibold">/ <?= e($plan->billing_period) ?></span>
                        </div>
                        <div class="badge bg-primary bg-opacity-10 text-primary fw-bold mb-3 px-3 py-2">
                            <i class="fa-brands fa-google me-1"></i> Up to <?= $plan->gmail_limit ?> Gmail Accounts
                        </div>
                        <ul class="list-unstyled small text-secondary d-flex flex-column gap-2 mb-4">
                            <?php foreach ($plan->getFeaturesList() as $feat): ?>
                            <li class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check text-success"></i>
                                <span><?= e($feat) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div>
                        <?php if ($isCurrent): ?>
                        <button class="btn btn-outline-success w-100 py-2 fw-bold" disabled>
                            <i class="fa-solid fa-circle-check me-1"></i> Active Plan
                        </button>
                        <?php else: ?>
                        <a href="<?= url('/billing/checkout/' . $plan->id) ?>" class="btn <?= $plan->is_popular ? 'btn-primary' : 'btn-outline-dark' ?> w-100 py-2 fw-bold">
                            <i class="fa-solid fa-credit-card me-1"></i> <?= $user->hasActiveSubscription() ? "Switch to {$plan->name}" : "Subscribe with Stripe" ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Payment History Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-receipt text-primary me-2"></i> Payment History &amp; Invoices</h6>
        <span class="badge bg-light text-dark border"><?= count($payments) ?> Transaction(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="py-3">Date</th>
                    <th class="py-3">Plan</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Transaction Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="fa-solid fa-receipt fs-3 d-block mb-2 text-secondary"></i>
                        No payment records found yet.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td class="small fw-semibold"><?= date('d M Y, H:i', strtotime($pay->created_at)) ?></td>
                    <td class="small fw-bold"><?= $pay->getPlan() ? e($pay->getPlan()->name) : 'Subscription' ?></td>
                    <td class="small fw-bold text-dark">$<?= number_format($pay->amount, 2) ?> <?= strtoupper($pay->currency) ?></td>
                    <td>
                        <span class="badge <?= $pay->status === 'paid' ? 'bg-success' : ($pay->status === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                            <?= ucfirst($pay->status) ?>
                        </span>
                    </td>
                    <td class="small text-muted font-monospace"><?= e($pay->stripe_session_id ?? $pay->stripe_payment_intent_id ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
