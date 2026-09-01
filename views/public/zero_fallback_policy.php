<?php
$pageTitle = 'Zero-Fallback Security Policy';
$title = 'Zero-Fallback Security Policy';
?>
<div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
    <div class="mb-4 pb-3 border-bottom">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-2 fw-semibold">
            <i class="fa-solid fa-shield-check me-1"></i> Data Integrity Guarantee
        </span>
        <h1 class="fw-extrabold display-6 text-dark mb-2">Zero-Fallback Security Policy</h1>
        <p class="text-muted small mb-0">Last updated: <?= date('F d, Y') ?></p>
    </div>
    <div class="legal-content">
        <p class="lead text-dark">
            Our platform guarantees an absolute <strong>Zero-Fallback Policy</strong> across all automated email processing, queuing, and sending pipelines.
        </p>

        <h2>1. What is the Zero-Fallback Policy?</h2>
        <p>
            Unlike generic automation tools that send hardcoded boilerplate text (e.g. <em>"Thank you for contacting us, we will reply soon"</em>) when a message template is missing, our system strictly enforces:
        </p>
        <div class="p-3 bg-light rounded border mb-3">
            <h5 class="fw-bold text-danger mb-2"><i class="fa-solid fa-ban me-1"></i> No Silent or Default Fallback Emails</h5>
            <p class="mb-0 small text-muted">
                If an auto-reply or follow-up step has empty content, or if a user deletes a message template, the system will <strong>never</strong> invent, substitute, or deliver fallback placeholder content. The email is simply skipped, and a clear audit log entry is recorded in your dashboard.
            </p>
        </div>

        <h2>2. Why This Matters for You</h2>
        <ul>
            <li><strong>Brand Protection:</strong> You maintain 100% control over every word sent from your connected Gmail address.</li>
            <li><strong>No Accidental Replies:</strong> Disabling an automation rule or deleting a message guarantees that zero outbound emails will leave your mailbox.</li>
            <li><strong>Immediate Queue Cleanup:</strong> When you modify or delete templates, all corresponding pending jobs in the queue worker are instantly sanitized or cancelled.</li>
        </ul>

        <h2>3. Verification &amp; Audit Logs</h2>
        <p>
            Every skipped incoming message or cancelled queue job is documented with precise timestamps and explanations in your <strong>Duplicate &amp; Skipped Mails</strong> report and <strong>Activity Logs</strong>.
        </p>

        <h2>4. Contact Us</h2>
        <div class="p-3 bg-light rounded border">
            <strong>Support Email:</strong> <a href="mailto:support@2xbets.net">support@2xbets.net</a><br>
            <strong>Website:</strong> <a href="https://2xbets.net">https://2xbets.net</a>
        </div>
    </div>
</div>
