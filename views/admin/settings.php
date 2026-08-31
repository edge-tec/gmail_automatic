<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Google OAuth & System Settings</h4>
        <p class="text-muted small mb-0">Configure your Google Cloud API credentials, redirect URIs, and push notification tokens.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <form action="<?= url('/admin/settings') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fa-brands fa-google me-2 text-danger"></i> Google Cloud OAuth 2.0 Credentials
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Google Client ID</label>
                        <input type="text" name="google_client_id" class="form-control font-monospace" value="<?= e($settings['google_client_id'] ?? '') ?>" placeholder="e.g. 1234567890-abcdefg.apps.googleusercontent.com" required>
                        <div class="form-text small">Obtained from Google Cloud Console &gt; APIs &amp; Services &gt; Credentials &gt; OAuth 2.0 Client IDs.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Google Client Secret</label>
                        <input type="password" name="google_client_secret" class="form-control font-monospace" value="<?= e($settings['google_client_secret'] ?? '') ?>" placeholder="GOCSPX-••••••••••••••••" required>
                        <div class="form-text small">Your secret key. Stored securely and used for token exchanges.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">OAuth 2.0 Authorized Redirect URI</label>
                        <input type="url" name="google_redirect_uri" class="form-control font-monospace" value="<?= e($settings['google_redirect_uri'] ?? url('/auth/google/callback')) ?>" required>
                        <div class="form-text small text-danger fw-semibold">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Exact match required! Add this URI to "Authorized redirect URIs" in your Google Cloud Console.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pub/Sub Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fa-solid fa-bell me-2 text-primary"></i> Google Cloud Pub/Sub Webhook (Optional Real-time Push)
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pub/Sub Topic Name</label>
                        <input type="text" name="google_pubsub_topic" class="form-control font-monospace" value="<?= e($settings['google_pubsub_topic'] ?? '') ?>" placeholder="projects/your-project-id/topics/gmail-notifications">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pub/Sub Verification Token</label>
                        <input type="text" name="google_pubsub_token" class="form-control font-monospace" value="<?= e($settings['google_pubsub_token'] ?? '') ?>" placeholder="secret_verification_token">
                    </div>

                    <div class="alert alert-light border small">
                        <strong>Webhook Endpoint URL:</strong><br>
                        <code><?= url('/webhook/gmail/pubsub') ?></code>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- Instructions Guide Card -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-book-open me-2 text-primary"></i> Google Setup Quick Guide
            </div>
            <div class="card-body small">
                <ol class="ps-3 mb-0 d-flex flex-column gap-2">
                    <li>Go to <a href="https://console.cloud.google.com" target="_blank" class="fw-semibold">Google Cloud Console</a> and create a new project.</li>
                    <li>Navigate to <strong>APIs &amp; Services &gt; Library</strong>, search for <strong>Gmail API</strong>, and click <strong>Enable</strong>.</li>
                    <li>Go to <strong>OAuth consent screen</strong>:
                        <ul>
                            <li>Select <strong>External</strong> User Type.</li>
                            <li>Add Scopes: <code>gmail.modify</code>, <code>gmail.send</code>, <code>userinfo.email</code>, <code>userinfo.profile</code>.</li>
                            <li>Publish App to Production or add your Gmail address as a Test User.</li>
                        </ul>
                    </li>
                    <li>Go to <strong>Credentials &gt; Create Credentials &gt; OAuth client ID</strong>:
                        <ul>
                            <li>Application type: <strong>Web application</strong>.</li>
                            <li>Add <strong>Authorized redirect URIs</strong> matching the URL in the form.</li>
                        </ul>
                    </li>
                    <li>Copy Client ID and Secret into the form on the left and click Save!</li>
                </ol>
            </div>
        </div>
    </div>
</div>
