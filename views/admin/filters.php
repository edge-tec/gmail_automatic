<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Blacklist &amp; Filter Rules</h4>
        <p class="text-muted small mb-0">Configure system-wide blacklist rules. If an email matches any blacklisted email, domain, or content, automated replies will be skipped immediately.</p>
    </div>
    <div>
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
            <i class="fa-solid fa-shield-halved me-1"></i> System-Wide Auto-Skip Active
        </span>
    </div>
</div>

<form action="<?= url('/admin/filters') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- 1. Blacklisted Email Addresses -->
        <div class="col-12 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-at text-danger fs-5"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Blacklisted Emails</h6>
                            <small class="text-muted">Exact email addresses</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2 text-muted small">
                        Incoming emails from these addresses will <strong>never</strong> receive auto-replies or follow-ups.
                    </div>
                    <textarea name="blacklist_emails" rows="12" class="form-control font-monospace" style="font-size: 0.88rem;" placeholder="spam@example.com, noreply@google.com, mailer-daemon@googlemail.com, blocked@domain.com"><?= e($blacklistEmails) ?></textarea>
                    <div class="form-text small mt-2">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Separate with comma (<code>,</code>) or new line.
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Blacklisted Domain Names -->
        <div class="col-12 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-globe text-primary fs-5"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Blacklisted Domains</h6>
                            <small class="text-muted">Domain &amp; subdomain filters</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2 text-muted small">
                        Any sender with matching domain (e.g. <code>@spamdomain.com</code>) will be skipped immediately.
                    </div>
                    <textarea name="blacklist_domains" rows="12" class="form-control font-monospace" style="font-size: 0.88rem;" placeholder="spamdomain.com, mailtrack.io, bounce.sender.org, junkmail.net"><?= e($blacklistDomains) ?></textarea>
                    <div class="form-text small mt-2">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Separate with comma (<code>,</code>) or new line (without '@').
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Blacklisted Content & Keywords -->
        <div class="col-12 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-file-lines text-warning fs-5"></i>
                        <div>
                            <h6 class="fw-bold mb-0">Blacklisted Content</h6>
                            <small class="text-muted">Subject &amp; body keywords</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2 text-muted small">
                        If subject or email body contains any of these words/phrases, auto-reply is skipped.
                    </div>
                    <textarea name="blacklist_keywords" rows="12" class="form-control font-monospace" style="font-size: 0.88rem;" placeholder="unsubscribe, out of office, automatic reply, delivery status notification, casino, bonus"><?= e($blacklistKeywords) ?></textarea>
                    <div class="form-text small mt-2">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> Separate with comma (<code>,</code>) or new line.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4 p-3 bg-light">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="small text-muted">
                <i class="fa-solid fa-circle-info text-primary me-1"></i>
                Skipped incoming emails will be logged in <strong>Activity Logs</strong> with the exact blacklist filter reason.
            </div>
            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold d-flex align-items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save All Blacklist Filters</span>
            </button>
        </div>
    </div>
</form>
