<?php
$pageTitle = 'Privacy Policy';
$title = 'Privacy Policy';
?>
<div class="legal-content">
    <p class="lead text-dark">
        Your privacy is important to us. This Privacy Policy describes how the Gmail Automation Application (referred to as "we", "our", or "the Platform") collects, uses, and safeguards information when you connect and use our email auto-response and sequential follow-up services.
    </p>

    <h2>1. Information We Collect</h2>
    <p>When you use our application, we may collect the following information:</p>
    <ul>
        <li><strong>Account Information:</strong> Name, email address, and authentication credentials necessary to create and secure your user account.</li>
        <li><strong>Google User Data:</strong> When you connect your Gmail account via Google OAuth 2.0, we receive authorization tokens that allow the application to read incoming email message headers and send automated replies and follow-ups on your behalf.</li>
        <li><strong>Automation Configuration:</strong> Custom reply templates, sequence steps, schedule settings, and delivery delay preferences configured by you in the dashboard.</li>
        <li><strong>Usage &amp; Audit Logs:</strong> Timestamps of incoming messages processed, replies sent, follow-ups scheduled, and error diagnostics.</li>
    </ul>

    <h2>2. How We Use Google User Data</h2>
    <p>We access and use your Gmail data strictly for the purpose of providing email automation services as explicitly configured by you:</p>
    <ul>
        <li><strong>Processing Incoming Emails:</strong> Reading sender information, subject lines, and message timestamps to trigger your configured auto-reply sequences.</li>
        <li><strong>Sending Automated Replies &amp; Follow-ups:</strong> Delivering your custom reply and follow-up templates through the official Gmail API (<code>https://www.googleapis.com/auth/gmail.modify</code>).</li>
        <li><strong>Cancelling Pending Follow-ups:</strong> Detecting when a recipient has responded to an email to immediately halt any pending follow-up messages.</li>
    </ul>

    <h2>3. Google API Services User Data Policy Compliance</h2>
    <p>
        The application's use and transfer to any other app of information received from Google APIs will adhere to the 
        <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>, including the Limited Use requirements.
    </p>
    <ul>
        <li><strong>No Human Access to Emails:</strong> No human employees or contractors read your private emails unless required for security investigations or authorized by you.</li>
        <li><strong>No Selling of Data:</strong> We do NOT sell, rent, or trade your personal data or Google user data to any third parties or data brokers.</li>
        <li><strong>No Advertising:</strong> Your email data is NEVER used for serving advertisements or training generalized AI models.</li>
    </ul>

    <h2>4. Data Storage &amp; Security</h2>
    <p>
        We take industry-standard technical and organizational security measures to protect your OAuth tokens and configuration data:
    </p>
    <ul>
        <li>OAuth tokens are securely stored in private database environments with encrypted connection channels (HTTPS/TLS).</li>
        <li>Access tokens and refresh tokens are strictly protected against unauthorized access.</li>
        <li>You can revoke access to your Gmail account at any time from within the application or via your <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer">Google Security Settings</a>.</li>
    </ul>

    <h2>5. Data Retention &amp; Deletion</h2>
    <p>
        We retain your automation configuration and logs only for as long as your account remains active. You may delete individual connected accounts, clear conversation logs, or request complete account deletion at any time from your settings panel.
    </p>

    <h2>6. Contact Us</h2>
    <p>
        If you have any questions or concerns regarding this Privacy Policy or our data practices, please contact us at:
    </p>
    <div class="p-3 bg-light rounded border">
        <strong>Support Email:</strong> <a href="mailto:forhadaistudio007@gmail.com">forhadaistudio007@gmail.com</a><br>
        <strong>Domain:</strong> <a href="https://2xbets.net">https://2xbets.net</a>
    </div>
</div>
