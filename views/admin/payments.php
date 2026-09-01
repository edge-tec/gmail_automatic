<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-receipt text-primary me-2"></i> Payments &amp; Subscriptions</h4>
        <p class="text-muted small mb-0">Review all Stripe, bKash, and Nagad transactions, approve manual submissions, and track customer subscriptions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/admin/gateways') ?>" class="btn btn-outline-primary btn-sm fw-semibold">
            <i class="fa-solid fa-sliders me-1"></i> Gateway Settings
        </a>
        <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="min-width: 820px;">
            <thead class="table-light">
                <tr>
                    <th class="py-3">ID</th>
                    <th class="py-3">User</th>
                    <th class="py-3">Gateway / Method</th>
                    <th class="py-3">Sender / TrxID</th>
                    <th class="py-3">Plan</th>
                    <th class="py-3">Amount</th>
                    <th class="py-3">Status</th>
                    <th class="py-3">Date</th>
                    <th class="text-end py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
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
                        <?php if ($pay->gateway === 'bkash'): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                <i class="fa-solid fa-mobile-screen-button me-1"></i> bKash (<?= ucfirst($pay->payment_method_type) ?>)
                            </span>
                        <?php elseif ($pay->gateway === 'nagad'): ?>
                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1">
                                <i class="fa-solid fa-wallet me-1"></i> Nagad (<?= ucfirst($pay->payment_method_type) ?>)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                <i class="fa-brands fa-stripe me-1"></i> Stripe
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pay->transaction_id): ?>
                            <div class="fw-bold font-monospace text-dark"><?= e($pay->transaction_id) ?></div>
                            <div class="text-muted small">Sender: <?= e($pay->sender_number ?? 'N/A') ?></div>
                        <?php elseif ($pay->stripe_session_id): ?>
                            <div class="text-muted small font-monospace text-truncate" style="max-width: 140px;"><?= e($pay->stripe_session_id) ?></div>
                        <?php else: ?>
                            <span class="text-muted small">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border fw-bold">
                            <?= $pay->getPlan() ? e($pay->getPlan()->name) : 'Subscription' ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark">$<?= number_format($pay->amount, 2) ?> USD</div>
                        <?php if ($pay->amount_bdt): ?>
                        <div class="text-muted small">৳ <?= number_format($pay->amount_bdt, 2) ?> BDT</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $pay->status === 'paid' ? 'bg-success' : ($pay->status === 'pending' ? 'bg-warning text-dark' : ($pay->status === 'rejected' ? 'bg-danger' : 'bg-secondary')) ?>">
                            <?= ucfirst($pay->status) ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= date('d M Y, H:i', strtotime($pay->created_at)) ?></td>
                    <td class="text-end">
                        <?php if ($pay->status === 'pending'): ?>
                            <div class="d-inline-flex gap-1">
                                <form action="<?= url('/admin/payments/' . $pay->id . '/approve') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Approve payment #<?= $pay->id ?> and activate subscription plan for this user?');" title="Approve & Activate">
                                        <i class="fa-solid fa-check me-1"></i> Approve
                                    </button>
                                </form>
                                <form action="<?= url('/admin/payments/' . $pay->id . '/reject') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject payment verification #<?= $pay->id ?>?');" title="Reject">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small"><i class="fa-solid fa-circle-check text-success"></i> <?= ucfirst($pay->status) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
