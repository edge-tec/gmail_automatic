<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <a href="<?= url('/billing') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Billing
                </a>
                <h4 class="fw-bold mb-1">Select Payment Gateway</h4>
                <p class="text-muted small mb-0">Choose your preferred payment method to activate the <strong><?= e($plan->name) ?> Plan</strong>.</p>
            </div>
            <div class="text-end">
                <span class="badge bg-primary fs-6 px-3 py-2">
                    $<?= number_format($plan->price, 0) ?> USD / <?= e($plan->billing_period) ?>
                </span>
            </div>
        </div>

        <!-- Plan Order Summary Card -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-box-open text-primary me-2"></i> <?= e($plan->name) ?> Plan Subscription</h5>
                        <div class="text-muted small">
                            <i class="fa-brands fa-google me-1 text-danger"></i> Connect up to <strong><?= $plan->gmail_limit ?> Gmail accounts</strong> with 24/7 background queue automation.
                        </div>
                    </div>
                    <div class="text-md-end">
                        <div class="fs-4 fw-extrabold text-dark">$<?= number_format($plan->price, 2) ?> USD</div>
                        <div class="text-muted small fw-semibold">
                            ≈ ৳ <?= number_format($plan->price * 120, 2) ?> BDT (Rate: 1 USD = 120 BDT)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Method Tabs -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-0 border-bottom">
                <ul class="nav nav-tabs nav-fill card-header-tabs m-0 border-0" id="paymentTabs" role="tablist">
                    <?php if ($stripeEnabled): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3 fw-bold d-flex align-items-center justify-content-center gap-2" id="stripe-tab" data-bs-toggle="tab" data-bs-target="#stripe-content" type="button" role="tab">
                            <i class="fa-brands fa-stripe text-primary fs-5"></i>
                            <span>Stripe (Card / Apple Pay)</span>
                        </button>
                    </li>
                    <?php endif; ?>

                    <?php if ($bkash['enabled']): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= !$stripeEnabled ? 'active' : '' ?> py-3 fw-bold d-flex align-items-center justify-content-center gap-2 text-danger" id="bkash-tab" data-bs-toggle="tab" data-bs-target="#bkash-content" type="button" role="tab">
                            <i class="fa-solid fa-mobile-screen-button fs-5"></i>
                            <span>bKash (বিকাশ)</span>
                        </button>
                    </li>
                    <?php endif; ?>

                    <?php if ($nagad['enabled']): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= (!$stripeEnabled && !$bkash['enabled']) ? 'active' : '' ?> py-3 fw-bold d-flex align-items-center justify-content-center gap-2 text-warning" id="nagad-tab" data-bs-toggle="tab" data-bs-target="#nagad-content" type="button" role="tab">
                            <i class="fa-solid fa-wallet fs-5"></i>
                            <span>Nagad (নগদ)</span>
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card-body p-4 p-md-5">
                <div class="tab-content" id="paymentTabsContent">
                    
                    <!-- 1. Stripe Tab -->
                    <?php if ($stripeEnabled): ?>
                    <div class="tab-pane fade show active" id="stripe-content" role="tabpanel">
                        <div class="text-center max-w-600 mx-auto py-3">
                            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-10 text-primary fs-1 mb-3">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Instant Checkout with Stripe</h5>
                            <p class="text-muted small mb-4">
                                Pay securely using Visa, MasterCard, American Express, Apple Pay, or Google Pay. Your package will be activated automatically immediately after payment.
                            </p>

                            <div class="bg-light p-3 rounded-3 border mb-4 text-start">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="text-muted">Subscription:</span>
                                    <span class="fw-bold text-dark"><?= e($plan->name) ?> Plan</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="text-muted">Total Billed:</span>
                                    <span class="fw-bold text-primary">$<?= number_format($plan->price, 2) ?> USD</span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Security:</span>
                                    <span class="text-success fw-semibold"><i class="fa-solid fa-shield-check"></i> 256-Bit Encrypted</span>
                                </div>
                            </div>

                            <form action="<?= url('/billing/checkout/' . $plan->id . '/stripe') ?>" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                                    <i class="fa-solid fa-lock me-2"></i> Pay $<?= number_format($plan->price, 2) ?> with Stripe
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 2. bKash Tab -->
                    <?php if ($bkash['enabled']): ?>
                    <div class="tab-pane fade <?= !$stripeEnabled ? 'show active' : '' ?>" id="bkash-content" role="tabpanel">
                        <div class="row g-4 align-items-center">
                            <div class="col-12 col-md-6">
                                <div class="p-4 rounded-4" style="background: #e2136e; color: #ffffff;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-mobile-screen-button me-2"></i> bKash Payment</h5>
                                        <span class="badge bg-white text-dark fw-bold"><?= e($bkash['account_type']) ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-white-50 small">Send Money / Payment Number:</div>
                                        <div class="fs-4 fw-extrabold text-white font-monospace mt-1 d-flex align-items-center gap-2">
                                            <span><?= e($bkash['number']) ?></span>
                                            <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="navigator.clipboard.writeText('<?= e($bkash['number']) ?>'); alert('bKash Number Copied!');" title="Copy Number">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-white-50 small">Total Payable Amount:</div>
                                        <div class="fs-3 fw-extrabold text-white">৳ <?= number_format($bkash['amount_bdt'], 2) ?> BDT</div>
                                        <div class="text-white-50 small">(Rate: 1 USD = <?= $bkash['rate'] ?> BDT)</div>
                                    </div>
                                    <div class="small text-white-50 border-top pt-2" style="border-color: rgba(255,255,255,0.2) !important;">
                                        <?= nl2br(e($bkash['instructions'])) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clipboard-check text-success me-2"></i> Submit Payment Verification</h6>
                                
                                <form action="<?= url('/billing/checkout/' . $plan->id . '/bkash') ?>" method="POST">
                                    <?= csrf_field() ?>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Sender bKash Mobile Number <span class="text-danger">*</span></label>
                                        <input type="text" name="sender_number" class="form-control" placeholder="e.g. 017XXXXXXXX" required>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold small">bKash Transaction ID (TrxID) <span class="text-danger">*</span></label>
                                        <input type="text" name="transaction_id" class="form-control font-monospace" placeholder="e.g. 9J82KLS91" required>
                                        <div class="form-text">Enter the 8-10 character TrxID from your bKash SMS.</div>
                                    </div>

                                    <button type="submit" class="btn btn-danger btn-lg w-100 py-3 fw-bold shadow-sm" style="background: #e2136e; border: none;">
                                        <i class="fa-solid fa-check-circle me-2"></i> Verify &amp; Activate Subscription
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 3. Nagad Tab -->
                    <?php if ($nagad['enabled']): ?>
                    <div class="tab-pane fade <?= (!$stripeEnabled && !$bkash['enabled']) ? 'show active' : '' ?>" id="nagad-content" role="tabpanel">
                        <div class="row g-4 align-items-center">
                            <div class="col-12 col-md-6">
                                <div class="p-4 rounded-4" style="background: linear-gradient(135deg, #f7941d 0%, #ed1c24 100%); color: #ffffff;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="fw-bold mb-0 text-white"><i class="fa-solid fa-wallet me-2"></i> Nagad Payment</h5>
                                        <span class="badge bg-white text-dark fw-bold"><?= e($nagad['account_type']) ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-white-50 small">Send Money / Payment Number:</div>
                                        <div class="fs-4 fw-extrabold text-white font-monospace mt-1 d-flex align-items-center gap-2">
                                            <span><?= e($nagad['number']) ?></span>
                                            <button type="button" class="btn btn-sm btn-light py-0 px-2" onclick="navigator.clipboard.writeText('<?= e($nagad['number']) ?>'); alert('Nagad Number Copied!');" title="Copy Number">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-white-50 small">Total Payable Amount:</div>
                                        <div class="fs-3 fw-extrabold text-white">৳ <?= number_format($nagad['amount_bdt'], 2) ?> BDT</div>
                                        <div class="text-white-50 small">(Rate: 1 USD = <?= $nagad['rate'] ?> BDT)</div>
                                    </div>
                                    <div class="small text-white-50 border-top pt-2" style="border-color: rgba(255,255,255,0.2) !important;">
                                        <?= nl2br(e($nagad['instructions'])) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-clipboard-check text-success me-2"></i> Submit Payment Verification</h6>
                                
                                <form action="<?= url('/billing/checkout/' . $plan->id . '/nagad') ?>" method="POST">
                                    <?= csrf_field() ?>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Sender Nagad Mobile Number <span class="text-danger">*</span></label>
                                        <input type="text" name="sender_number" class="form-control" placeholder="e.g. 018XXXXXXXX" required>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold small">Nagad Transaction ID (TrxID) <span class="text-danger">*</span></label>
                                        <input type="text" name="transaction_id" class="form-control font-monospace" placeholder="e.g. 7X92MNL21" required>
                                        <div class="form-text">Enter the 8-10 character TrxID from your Nagad SMS.</div>
                                    </div>

                                    <button type="submit" class="btn btn-warning btn-lg w-100 py-3 fw-bold text-white shadow-sm" style="background: #f7941d; border: none;">
                                        <i class="fa-solid fa-check-circle me-2"></i> Verify &amp; Activate Subscription
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
