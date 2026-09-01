<?php
$pageTitle = 'Google API Services User Data Disclosure';
$title = 'Google API Services User Data Disclosure';
?>
<div class="card border-0 shadow-sm rounded-4 p-4 p-lg-5">
    <div class="mb-4 pb-3 border-bottom">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 mb-2 fw-semibold">
            <i class="fa-brands fa-google me-1"></i> Google API Compliance
        </span>
        <h1 class="fw-extrabold display-6 text-dark mb-2">Google API Services User Data Disclosure</h1>
        <p class="text-muted small mb-0">Last updated: <?= date('F d, Y') ?></p>
    </div>
    <div class="legal-content">
        <p class="lead text-dark">
            This Disclosure outlines how the Gmail Automation Platform accesses, uses, stores, and protects Google User Data via the official Google Gmail API.
        </p>

        <div class="alert alert-primary border-0 rounded-3 p-3 mb-4">
            <strong><i class="fa-solid fa-shield-halved me-2"></i> Limited Use Policy Compliance:</strong><br>
            Our application's use and transfer to any other app of information received from Google APIs adheres to the 
            <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer" class="fw-bold">Google API Services User Data Policy</a>, including the Limited Use requirements.
        </div>

        <h2>1. Scopes Requested &amp; Why They Are Needed</h2>
        <p>When connecting your Gmail account via Google OAuth 2.0, we request the following restricted scope:</p>
        <ul>
            <li><code>https://www.googleapis.com/auth/gmail.modify</code>: Allows the application to read inbound email message headers, evaluate automated reply triggers, send automated replies/follow-ups that you explicitly configured, and mark messages processed.</li>
        </ul>

        <h2>2. How We Access &amp; Use Your Gmail Data</h2>
        <p>We access and use Google user data strictly to perform email automation actions on your behalf:</p>
        <ul>
            <li><strong>Detecting Inbound Emails:</strong> Scanning incoming messages to check if an email matches your auto-reply rules and sequence steps.</li>
            <li><strong>Dispatching Configured Replies:</strong> Sending automated replies and scheduled follow-ups created by you in your dashboard.</li>
            <li><strong>Recipient Reply Halting:</strong> Detecting when a recipient has responded to an email to immediately halt any pending follow-up messages in that thread.</li>
        </ul>

        <h2>3. Strict Protections &amp; Prohibitions</h2>
        <ul>
            <li><strong>No Human Reading:</strong> No humans read your emails unless you provide explicit consent for technical troubleshooting or if required by law.</li>
            <li><strong>No Selling of Data:</strong> We NEVER sell, rent, or monetize your Google user data or contact lists.</li>
            <li><strong>No AI Model Training:</strong> Your Google user data is NEVER used to train generalized artificial intelligence or machine learning models.</li>
            <li><strong>No Advertising:</strong> Your Google user data is NEVER used for serving advertisements or behavioral profiling.</li>
        </ul>

        <h2>4. Data Retention and Revocation</h2>
        <p>
            You can revoke our application's access to your Google Account at any time directly through the 
            <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer">Google Account Security Dashboard</a> 
            or by disconnecting your account from our dashboard. Upon disconnection, stored OAuth access tokens and refresh tokens are immediately deleted from our active database.
        </p>

        <h2>5. Contact Us</h2>
        <div class="p-3 bg-light rounded border">
            <strong>Support Email:</strong> <a href="mailto:support@2xbets.net">support@2xbets.net</a><br>
            <strong>Website:</strong> <a href="https://2xbets.net">https://2xbets.net</a>
        </div>
    </div>
</div>
