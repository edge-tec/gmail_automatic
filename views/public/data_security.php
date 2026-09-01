<?php
$pageTitle = 'Data Security & Encryption Policy';
$title = 'Data Security & Encryption Policy';
?>
<div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
    <div class="mb-4 pb-3 border-bottom">
        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 mb-2 fw-semibold">
            <i class="fa-solid fa-lock me-1"></i> Infrastructure Security
        </span>
        <h1 class="fw-extrabold display-6 text-dark mb-2">Data Security &amp; Encryption Policy</h1>
        <p class="text-muted small mb-0">Last updated: <?= date('F d, Y') ?></p>
    </div>
    <div class="legal-content">
        <p class="lead text-dark">
            Security and privacy are the core pillars of the Gmail Automation Engine. We implement industry-leading technical and operational safeguards to protect your communications.
        </p>

        <h2>1. Tokenized Google OAuth 2.0 Security</h2>
        <p>
            We never see, ask for, or store your Gmail password. Authentication is performed directly via Google's secure OAuth 2.0 dialog.
        </p>
        <ul>
            <li>Tokens are encrypted and stored in private databases behind firewalls.</li>
            <li>Tokens can be revoked with a single click at any time.</li>
        </ul>

        <h2>2. Data in Transit and at Rest</h2>
        <ul>
            <li><strong>In Transit:</strong> All HTTP communications are encrypted using high-grade TLS/HTTPS (256-bit SSL).</li>
            <li><strong>At Rest:</strong> Sensitive access credentials and webhook secrets are stored with cryptographic hashing and encryption.</li>
        </ul>

        <h2>3. Server-Side Protection</h2>
        <ul>
            <li><strong>CSRF Protection:</strong> Every state-changing request is guarded by cryptographic Anti-CSRF verification tokens.</li>
            <li><strong>XSS &amp; Injection Defense:</strong> All user inputs are sanitized and rendered using secure HTML entity escaping and parameterized SQL statements.</li>
            <li><strong>Isolated Queue Workers:</strong> Background dispatch jobs run in segregated server processes with strict memory and execution limits.</li>
        </ul>

        <h2>4. Contact Us</h2>
        <div class="p-3 bg-light rounded border">
            <strong>Support Email:</strong> <a href="mailto:support@2xbets.net">support@2xbets.net</a><br>
            <strong>Website:</strong> <a href="https://2xbets.net">https://2xbets.net</a>
        </div>
    </div>
</div>
