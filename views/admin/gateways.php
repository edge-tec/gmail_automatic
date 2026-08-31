<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-credit-card text-primary me-2"></i> Payment Gateways &amp; Methods</h4>
        <p class="text-muted small mb-0">Configure Stripe Card Gateway, bKash (Personal Number / API), and Nagad (Personal Number / API) payment systems.</p>
    </div>
    <a href="<?= url('/admin') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Admin
    </a>
</div>

<form action="<?= url('/admin/gateways') ?>" method="POST">
    <?= csrf_field() ?>

    <!-- 1. Stripe Gateway Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-brands fa-stripe text-primary fs-4 me-2"></i> Stripe Payment Gateway
            </h5>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="stripe_enabled" id="stripe_enabled" value="1" <?= $stripe['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold small" for="stripe_enabled">Enable Stripe</label>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">Stripe Publishable Key</label>
                    <input type="text" name="stripe_publishable_key" class="form-control font-monospace" placeholder="pk_test_..." value="<?= e($stripe['publishable_key']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold small">Stripe Secret Key</label>
                    <input type="password" name="stripe_secret_key" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep existing)">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label fw-semibold small">Stripe Webhook Signing Secret</label>
                <input type="password" name="stripe_webhook_secret" class="form-control font-monospace" placeholder="whsec_... (Leave blank to keep existing)">
                <div class="form-text">Webhook Endpoint: <code>https://2xbets.net/webhook/stripe</code></div>
            </div>
        </div>
    </div>

    <!-- 2. bKash Gateway Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-danger">
                <i class="fa-solid fa-mobile-screen-button fs-4 me-2"></i> bKash Payment System (বিকাশ)
            </h5>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="bkash_enabled" id="bkash_enabled" value="1" <?= $bkash['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold small" for="bkash_enabled">Enable bKash</label>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Integration Mode</label>
                    <select name="bkash_type" class="form-select">
                        <option value="manual_number" <?= $bkash['type'] === 'manual_number' ? 'selected' : '' ?>>Personal / Manual Number (TrxID Verify)</option>
                        <option value="merchant_api" <?= $bkash['type'] === 'merchant_api' ? 'selected' : '' ?>>bKash Merchant API (Auto Checkout)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">bKash Account Number</label>
                    <input type="text" name="bkash_number" class="form-control font-monospace fw-bold" placeholder="01611195794" value="<?= e($bkash['number']) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Account Type</label>
                    <select name="bkash_account_type" class="form-select">
                        <option value="Personal" <?= $bkash['account_type'] === 'Personal' ? 'selected' : '' ?>>Personal (Send Money)</option>
                        <option value="Agent" <?= $bkash['account_type'] === 'Agent' ? 'selected' : '' ?>>Agent (Cash Out)</option>
                        <option value="Merchant" <?= $bkash['account_type'] === 'Merchant' ? 'selected' : '' ?>>Merchant (Make Payment)</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Exchange Rate (1 USD = ? BDT)</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input type="number" step="0.5" name="bkash_exchange_rate" class="form-control" value="<?= e($bkash['rate']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold small">User Payment Instructions</label>
                    <input type="text" name="bkash_instructions" class="form-control" value="<?= e($bkash['instructions']) ?>">
                </div>
            </div>

            <!-- bKash API Fields (Optional) -->
            <div class="border-top pt-3 mt-3">
                <h6 class="fw-bold small text-secondary mb-2"><i class="fa-solid fa-key me-1"></i> bKash Merchant API Credentials (Optional)</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted">bKash App Key</label>
                        <input type="text" name="bkash_app_key" class="form-control font-monospace" value="<?= e($bkash['app_key']) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted">bKash App Secret</label>
                        <input type="password" name="bkash_app_secret" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep existing)">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted">bKash API Username</label>
                        <input type="text" name="bkash_username" class="form-control font-monospace" value="<?= e($bkash['username']) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted">bKash API Password</label>
                        <input type="password" name="bkash_password" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep existing)">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Nagad Gateway Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-warning">
                <i class="fa-solid fa-wallet fs-4 me-2"></i> Nagad Payment System (নগদ)
            </h5>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="nagad_enabled" id="nagad_enabled" value="1" <?= $nagad['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold small" for="nagad_enabled">Enable Nagad</label>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Integration Mode</label>
                    <select name="nagad_type" class="form-select">
                        <option value="manual_number" <?= $nagad['type'] === 'manual_number' ? 'selected' : '' ?>>Personal / Manual Number (TrxID Verify)</option>
                        <option value="merchant_api" <?= $nagad['type'] === 'merchant_api' ? 'selected' : '' ?>>Nagad Merchant API (Auto Checkout)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Nagad Account Number</label>
                    <input type="text" name="nagad_number" class="form-control font-monospace fw-bold" placeholder="01611195794" value="<?= e($nagad['number']) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Account Type</label>
                    <select name="nagad_account_type" class="form-select">
                        <option value="Personal" <?= $nagad['account_type'] === 'Personal' ? 'selected' : '' ?>>Personal (Send Money)</option>
                        <option value="Agent" <?= $nagad['account_type'] === 'Agent' ? 'selected' : '' ?>>Agent (Cash Out)</option>
                        <option value="Merchant" <?= $nagad['account_type'] === 'Merchant' ? 'selected' : '' ?>>Merchant (Make Payment)</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold small">Exchange Rate (1 USD = ? BDT)</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input type="number" step="0.5" name="nagad_exchange_rate" class="form-control" value="<?= e($nagad['rate']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold small">User Payment Instructions</label>
                    <input type="text" name="nagad_instructions" class="form-control" value="<?= e($nagad['instructions']) ?>">
                </div>
            </div>

            <!-- Nagad API Fields (Optional) -->
            <div class="border-top pt-3 mt-3">
                <h6 class="fw-bold small text-secondary mb-2"><i class="fa-solid fa-key me-1"></i> Nagad Merchant API Credentials (Optional)</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-muted">Nagad Merchant ID</label>
                        <input type="text" name="nagad_merchant_id" class="form-control font-monospace" value="<?= e($nagad['merchant_id']) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-muted">Nagad Public Key</label>
                        <input type="password" name="nagad_public_key" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep existing)">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold small text-muted">Nagad Private Key</label>
                        <input type="password" name="nagad_private_key" class="form-control font-monospace" placeholder="•••••••• (Leave blank to keep existing)">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 py-3 shadow">
        <i class="fa-solid fa-floppy-disk me-2"></i> Save All Gateway Settings
    </button>
</form>
