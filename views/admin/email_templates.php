<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i> System Email Templates</h4>
        <p class="text-muted small mb-0">Customize automated notification emails (Welcome, Verification, Trial, Purchase, Renewal) with dynamic template variables.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<div class="row g-4">
    <!-- Templates List Accordion -->
    <div class="col-12 col-lg-8">
        <div class="accordion" id="templatesAccordion">
            <?php foreach ($templates as $idx => $tpl): ?>
            <div class="card shadow-sm border-0 mb-3 overflow-hidden">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" id="heading_<?= $tpl->id ?>">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark"><?= e($tpl->name) ?></h6>
                        <span class="badge bg-light text-dark border">Slug: <code><?= e($tpl->slug) ?></code></span>
                        <span class="badge <?= $tpl->is_enabled ? 'bg-success' : 'bg-secondary' ?> ms-1"><?= $tpl->is_enabled ? 'Enabled' : 'Disabled' ?></span>
                    </div>
                    <button class="btn btn-sm btn-outline-primary fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $tpl->id ?>">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Template
                    </button>
                </div>

                <div id="collapse_<?= $tpl->id ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#templatesAccordion">
                    <div class="card-body p-4 border-top">
                        <form action="<?= url('/admin/email-templates/' . $tpl->id . '/update') ?>" method="POST">
                            <?= csrf_field() ?>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="en_<?= $tpl->id ?>" value="1" <?= $tpl->is_enabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold small" for="en_<?= $tpl->id ?>">Enable this email template</label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Email Subject</label>
                                <input type="text" name="subject" class="form-control" value="<?= e($tpl->subject) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">HTML Email Body</label>
                                <textarea name="body" class="form-control font-monospace" rows="6" required><?= e($tpl->body) ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Variables Help Box -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm border-0 sticky-top" style="top: 1rem;">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-code text-primary me-2"></i> Supported Variables</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">You can insert any of these tags into your email subjects and HTML bodies:</p>
                <div class="d-flex flex-column gap-2 small">
                    <div><code>{{name}}</code> &mdash; Recipient's full name</div>
                    <div><code>{{email}}</code> &mdash; Recipient's email address</div>
                    <div><code>{{plan_name}}</code> &mdash; Plan name (e.g. Starter)</div>
                    <div><code>{{plan_price}}</code> &mdash; Price amount (e.g. 50.00)</div>
                    <div><code>{{gmail_limit}}</code> &mdash; Max Gmail accounts</div>
                    <div><code>{{trial_days}}</code> &mdash; Free trial days duration</div>
                    <div><code>{{start_date}}</code> &mdash; Start/Activation date</div>
                    <div><code>{{expiry_date}}</code> &mdash; Expiry date</div>
                    <div><code>{{renewal_date}}</code> &mdash; Next renewal date</div>
                    <div><code>{{transaction_id}}</code> &mdash; Stripe transaction ref</div>
                    <div><code>{{verification_url}}</code> &mdash; Email verify link</div>
                    <div><code>{{dashboard_url}}</code> &mdash; User dashboard URL</div>
                    <div><code>{{login_url}}</code> &mdash; Sign in page URL</div>
                    <div><code>{{support_email}}</code> &mdash; Support contact email</div>
                </div>
            </div>
        </div>
    </div>
</div>
