<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-receipt text-primary me-2"></i> Stripe Payments &amp; Subscriptions</h4>
        <p class="text-muted small mb-0">Review all Stripe checkout transactions, verified payments, currencies, and customer package activations.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="py-3">ID</th>
                    <th class="py-3">User</th>
                    <th class="py-3">Plan</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Stripe Session / Payment Intent</th>
                    <th class="py-3">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-credit-card fs-3 d-block mb-2 text-secondary"></i>
                        No payment records recorded yet.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payments as $pay): ?>
                <tr>
                    <td class="fw-bold">#<?= $pay->id ?></td>
                    <td>
                        <?php if ($pay->getUser()): ?>
                        <div class="fw-semibold text-dark"><?= e($pay->getUser()->name) ?></div>
                        <div class="text-muted small"><?= e($pay->getUser()->email) ?></div>
                        <?php else: ?>
                        <span class="text-muted">User #<?= $pay->user_id ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border fw-bold">
                            <?= $pay->getPlan() ? e($pay->getPlan()->name) : 'Subscription' ?>
                        </span>
                    </td>
                    <td class="fw-bold text-dark">$<?= number_format($pay->amount, 2) ?> <?= strtoupper($pay->currency) ?></td>
                    <td>
                        <span class="badge <?= $pay->status === 'paid' ? 'bg-success' : ($pay->status === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                            <?= ucfirst($pay->status) ?>
                        </span>
                    </td>
                    <td class="small text-muted font-monospace"><?= e($pay->stripe_session_id ?? $pay->stripe_payment_intent_id ?? 'N/A') ?></td>
                    <td class="small text-muted"><?= date('d M Y, H:i', strtotime($pay->created_at)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
