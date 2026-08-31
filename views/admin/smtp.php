<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-server text-primary me-2"></i> SMTP Server Settings</h4>
        <p class="text-muted small mb-0">Configure SMTP credentials for sending automated registration, verification, trial, and purchase notification emails.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<div class="row g-4">
    <!-- Form 1: SMTP Config -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i> SMTP Configuration</h6>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/smtp') ?>" method="POST" id="smtpSettingsForm">
                    <?= csrf_field() ?>

                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" name="smtp_enabled" id="smtp_enabled" value="1" <?= $config['enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark" for="smtp_enabled">
                            Enable SMTP Email Notifications System
                        </label>
                        <div class="text-muted small ms-4">When enabled, welcome, verification, trial, and invoice emails will be dispatched via SMTP.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold small">SMTP Host</label>
                            <input type="text" name="smtp_host" id="cfg_host" class="form-control font-monospace" placeholder="e.g. mail.antiprofiles.com or smtp.gmail.com" value="<?= e($config['host']) ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small">Port</label>
                            <input type="number" name="smtp_port" id="cfg_port" class="form-control font-monospace" placeholder="587 / 465 / 25" value="<?= e($config['port']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">SMTP Username</label>
                            <input type="text" name="smtp_username" id="cfg_username" class="form-control font-monospace" placeholder="username / email" value="<?= e($config['username']) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">SMTP Password</label>
                            <input type="password" name="smtp_password" id="cfg_password" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep current)">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small">Encryption</label>
                            <select name="smtp_encryption" id="cfg_encryption" class="form-select">
                                <option value="tls" <?= ($config['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Port 587)</option>
                                <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                                <option value="none" <?= ($config['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (Port 25)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small">From Name</label>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= e($config['from_name']) ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold small">From Email</label>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= e($config['from_email']) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save SMTP Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form 2: Test SMTP Connection & Email -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-network-wired text-success me-2"></i> Test SMTP Connection &amp; Delivery</h6>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <!-- Action 1: Test Connection Only Button -->
                    <div class="bg-light p-3 rounded-3 border mb-4">
                        <h6 class="fw-bold text-dark small mb-1"><i class="fa-solid fa-bolt text-warning me-1"></i> Step 1: Test Handshake &amp; Auth</h6>
                        <p class="text-muted small mb-3">
                            Verifies socket connection, TLS/SSL handshake, EHLO response, and authentication credentials without sending an email.
                        </p>
                        <form action="<?= url('/admin/smtp/test-connection') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary fw-bold w-100 py-2">
                                <i class="fa-solid fa-plug-circle-check me-1"></i> Test Connection Only
                            </button>
                        </form>
                    </div>

                    <!-- Action 2: Test Connection & Send Email -->
                    <div class="border-top pt-3">
                        <h6 class="fw-bold text-dark small mb-1"><i class="fa-solid fa-paper-plane text-success me-1"></i> Step 2: Send Real Test Email</h6>
                        <p class="text-muted small mb-3">
                            Dispatches an actual test email to the recipient address to confirm end-to-end inbox delivery.
                        </p>

                        <form action="<?= url('/admin/smtp/test') ?>" method="POST">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Recipient Email</label>
                                <input type="email" name="test_email" class="form-control" placeholder="your-email@example.com" value="<?= e(auth_user()->email) ?>" required>
                            </div>

                            <button type="submit" class="btn btn-success fw-bold w-100 py-2">
                                <i class="fa-solid fa-envelope-circle-check me-1"></i> Test Connection &amp; Send Email
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 border mt-4">
                    <h6 class="fw-bold small text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-1"></i> Port &amp; Encryption Guide</h6>
                    <ul class="text-muted small mb-0 ps-3">
                        <li><strong>Port 587:</strong> Select <code>TLS / STARTTLS</code> (Standard for cPanel/aaPanel/Gmail).</li>
                        <li><strong>Port 465:</strong> Select <code>SSL</code> (Direct SSL connection).</li>
                        <li><strong>Self-Signed / Custom Certs:</strong> Supported automatically with stream context.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
