<section class="py-5">
    <div class="text-center max-w-700 mx-auto mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold text-uppercase tracking-wider mb-2">Step-by-Step Guide</span>
        <h1 class="fw-extrabold display-5 text-dark">How Gmail Auto Reply &amp; Follow-up Works</h1>
        <p class="text-muted fs-5">Connect in seconds with official Google OAuth. No coding, complex webhooks, or local software installation required.</p>
    </div>

    <!-- Step Progression -->
    <div class="row g-4 mb-5">
        <!-- Step 1 -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fw-bold fs-3 mb-3 mx-auto shadow" style="width: 60px; height: 60px;">
                    1
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Connect Gmail Inboxes</h3>
                <p class="text-secondary small leading-relaxed mb-0">
                    Authenticate your Gmail or Google Workspace accounts with one click via Google official OAuth. Your access tokens are securely encrypted with AES-256.
                </p>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fw-bold fs-3 mb-3 mx-auto shadow" style="width: 60px; height: 60px;">
                    2
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Configure Rules &amp; Messages</h3>
                <p class="text-secondary small leading-relaxed mb-0">
                    Craft your initial Auto-Reply and sequential Follow-up steps. Use dynamic variables like <code>{{first_name}}</code>, set custom delay intervals, and define working hours.
                </p>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center fw-bold fs-3 mb-3 mx-auto shadow" style="width: 60px; height: 60px;">
                    3
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">24/7 Background Automation</h3>
                <p class="text-secondary small leading-relaxed mb-0">
                    Our cloud engine monitors incoming emails, prevents duplicate traffic, schedules replies, and halts follow-ups immediately when a recipient replies.
                </p>
            </div>
        </div>
    </div>

    <!-- Technical Architecture Breakdown -->
    <div class="card border-0 bg-white shadow-sm rounded-4 p-4 p-lg-5 mb-5">
        <h2 class="h4 fw-bold text-dark mb-4"><i class="fa-solid fa-microchip text-primary me-2"></i> Technical Architecture &amp; Deliverability Highlights</h2>
        <div class="row g-4">
            <div class="col-12 col-md-6">
                <h4 class="h6 fw-bold text-dark"><i class="fa-solid fa-lock text-success me-2"></i> Official Google API Compliance</h4>
                <p class="text-secondary small mb-3">All email operations are conducted via official Google Cloud REST API endpoints using approved OAuth scopes. No insecure IMAP/SMTP password storage.</p>
            </div>
            <div class="col-12 col-md-6">
                <h4 class="h6 fw-bold text-dark"><i class="fa-solid fa-envelope-circle-check text-info me-2"></i> Native Thread Header Preservation</h4>
                <p class="text-secondary small mb-3">Replies strictly include <code>In-Reply-To</code> and <code>References</code> RFC email headers, guaranteeing that messages appear seamlessly inside the client's original conversation thread.</p>
            </div>
            <div class="col-12 col-md-6">
                <h4 class="h6 fw-bold text-dark"><i class="fa-solid fa-calendar-check text-warning me-2"></i> Timezone &amp; Working Day Engine</h4>
                <p class="text-secondary small mb-3">Messages scheduled outside your designated working window are postponed safely to the beginning of the next business day.</p>
            </div>
            <div class="col-12 col-md-6">
                <h4 class="h6 fw-bold text-dark"><i class="fa-solid fa-users-gear text-primary me-2"></i> Atomic Concurrency Locking</h4>
                <p class="text-secondary small mb-3">Database-level unique keys and status transitions prevent race conditions even when high volumes of simultaneous emails arrive.</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="text-center">
        <a href="<?= url('/register') ?>" class="btn btn-primary btn-lg fw-bold px-4 py-3 shadow">
            <i class="fa-solid fa-rocket me-2"></i> Get Started Free Today
        </a>
    </div>
</section>
