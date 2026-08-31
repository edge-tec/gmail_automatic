<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-tags text-primary me-2"></i> Subscription Plans Management</h4>
        <p class="text-muted small mb-0">Manage SaaS plan pricing, Gmail account limits, features list, and Stripe Price IDs dynamically without editing code.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<div class="row g-4">
    <?php foreach ($plans as $plan): ?>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><?= e($plan->name) ?> Plan (<code><?= e($plan->slug) ?></code>)</h5>
                <span class="badge <?= $plan->is_active ? 'bg-success' : 'bg-secondary' ?>"><?= $plan->is_active ? 'Active' : 'Disabled' ?></span>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/plans/' . $plan->id . '/update') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Plan Name</label>
                            <input type="text" name="name" class="form-control" value="<?= e($plan->name) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Price (USD)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="price" class="form-control" value="<?= number_format($plan->price, 2, '.', '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Gmail Account Limit</label>
                            <input type="number" name="gmail_limit" class="form-control" value="<?= $plan->gmail_limit ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Billing Period</label>
                            <select name="billing_period" class="form-select">
                                <option value="monthly" <?= $plan->billing_period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="yearly" <?= $plan->billing_period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Stripe Price ID (Optional for recurring)</label>
                        <input type="text" name="stripe_price_id" class="form-control" placeholder="price_1NXXXXXXXXXXXX" value="<?= e($plan->stripe_price_id ?? '') ?>">
                        <div class="form-text">If left empty, standard Stripe one-time payment mode is used.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Features List (One per line)</label>
                        <textarea name="features" class="form-control" rows="5"><?= e(implode("\n", $plan->getFeaturesList())) ?></textarea>
                    </div>

                    <div class="d-flex gap-4 mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_popular" id="pop_<?= $plan->id ?>" value="1" <?= $plan->is_popular ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold small" for="pop_<?= $plan->id ?>">Highlight as 'Most Popular'</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="act_<?= $plan->id ?>" value="1" <?= $plan->is_active ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold small" for="act_<?= $plan->id ?>">Active on Landing Page</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update <?= e($plan->name) ?> Plan
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
