<section class="py-5">
    <div class="text-center max-w-700 mx-auto mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold text-uppercase tracking-wider mb-2">Transparent Pricing</span>
        <h1 class="fw-extrabold display-5 text-dark">Simple, Predictable Plans for High-Volume Outreach</h1>
        <p class="text-muted fs-5">Scale your connected Gmail accounts with zero hidden fees. Every plan includes full 24/7 cloud queue execution, smart follow-ups, and duplicate protection.</p>
    </div>

    <!-- Pricing Cards Grid -->
    <div class="row g-4 justify-content-center mb-5">
        <?php foreach ($plans as $plan): ?>
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 p-lg-5 d-flex flex-column justify-content-between <?= $plan->is_popular ? 'border border-primary border-2 shadow-lg position-relative' : '' ?>">
                <?php if ($plan->is_popular): ?>
                <div class="position-absolute top-0 end-0 translate-middle-y me-4">
                    <span class="badge bg-primary px-3 py-2 text-uppercase fw-bold shadow-sm">Most Popular</span>
                </div>
                <?php endif; ?>

                <div>
                    <h2 class="h4 fw-bold text-dark mb-1"><?= e($plan->name) ?> Plan</h2>
                    <p class="text-muted small mb-3">Designed for growing teams and agencies.</p>

                    <div class="d-flex align-items-baseline gap-1 mb-4 pb-3 border-bottom">
                        <span class="display-4 fw-extrabold text-dark">$<?= number_format($plan->price, 0) ?></span>
                        <span class="text-secondary fw-semibold">/ <?= e($plan->billing_period) ?></span>
                    </div>

                    <div class="badge bg-primary bg-opacity-10 text-primary fw-bold mb-4 p-2 px-3 fs-6 w-100 text-start">
                        <i class="fa-brands fa-google me-2"></i> Connect up to <strong><?= $plan->gmail_limit ?> Gmail Accounts</strong>
                    </div>

                    <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-3">Included Features:</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 mb-4 text-secondary small">
                        <?php foreach ($plan->getFeaturesList() as $feat): ?>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span><?= e($feat) ?></span>
                        </li>
                        <?php endforeach; ?>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span>Duplicate Traffic Protection (1 Reply / Lead)</span>
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span>1-Per-Conversation Follow-up Quota</span>
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span>24/7 Cloud Background Queue Workers</span>
                        </li>
                    </ul>
                </div>

                <div class="pt-3">
                    <a href="<?= url('/register') ?>" class="btn <?= $plan->is_popular ? 'btn-primary' : 'btn-outline-dark' ?> w-100 py-3 fw-bold rounded-3 shadow-sm">
                        Start 7-Day Free Trial
                    </a>
                    <div class="text-center text-muted small mt-2">Zero risk &bull; No credit card required</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Free Trial Banner -->
    <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-4 text-center my-4">
        <div class="row align-items-center">
            <div class="col-12 col-md-8 text-md-start mb-3 mb-md-0">
                <h3 class="h5 fw-bold text-primary mb-1"><i class="fa-solid fa-gift me-2"></i> Try Free for 7 Days Before You Buy</h3>
                <p class="text-dark small mb-0">Experience full Gmail automation with real accounts. Zero commitment, cancel anytime.</p>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <a href="<?= url('/register') ?>" class="btn btn-primary fw-bold px-4 py-2">
                    Activate Free Trial
                </a>
            </div>
        </div>
    </div>
</section>
