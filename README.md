# Gmail Auto Reply & Follow-up Automation System (PHP 8.2+ & MySQL)

A high-performance, enterprise-ready **Gmail Auto Reply & Follow-up Automation Web Application** built with modern **PHP 8.2+**, **MySQL 8+ / MariaDB**, **Official Google Gmail API**, **Google OAuth 2.0**, and tailored for easy deployment on **aaPanel** and Linux servers.

---

## 🌟 Key Features

1. **Official Google OAuth 2.0 Integration**:
   - Secure one-click Google authorization with offline refresh tokens.
   - **Zero Password Storage**: Passwords are never collected or stored.
   - **AES-256-GCM Token Encryption**: OAuth Access and Refresh Tokens are securely encrypted at rest in the MySQL database.
   - Automatic token refresh lifecycle management.

2. **Smart Auto Reply Engine**:
   - In-thread RFC 2822 compliant replies preserving Gmail conversation headers (`In-Reply-To`, `References`, `Thread-ID`, `Re: ...`).
   - Dynamic template variables: `{{first_name}}`, `{{last_name}}`, `{{sender_email}}`, `{{subject}}`, `{{date}}`.
   - Per-thread reply limits (e.g. max 3 replies per conversation).
   - Daily reply quotas (e.g. max 100 replies/day).
   - Configurable reply delay (instant or delayed by $N$ seconds/minutes).
   - Working hours and active working days scheduling in custom timezones (Default: `Asia/Dhaka`).

3. **Multi-Step Follow-up Automation**:
   - Visual sequence builder for multiple follow-up steps (Step 1: after 2 days, Step 2: after 4 days, Step 3: after 7 days).
   - Custom delay units: **Minutes**, **Hours**, or **Days**.
   - **Smart Stop (Recipient Reply Detection)**: If the contact replies at any time, the conversation status is set to `replied` and all scheduled follow-ups are cancelled immediately!
   - Manual "Stop Automation" / "Resume Automation" per conversation thread.

4. **24/7 Background Architecture (Zero Browser Dependency)**:
   - Automated MySQL-based job queue with row locking to eliminate race conditions.
   - CLI Queue Worker (`php worker.php`) with exponential backoff on transient errors.
   - Cron Scheduler (`php cron.php`) for automatic email synchronization and job dispatching.
   - Webhook endpoint (`/webhook/gmail/pubsub`) for optional Google Cloud Pub/Sub real-time push notifications.

5. **Multi-User & Admin Suite**:
   - User registration, login, session security, and complete data isolation.
   - Admin Panel with system overview, user management (suspend/activate), system-wide automation pause/resume, audit logs, and Google Cloud credentials configuration.

---

## 🏗️ System Architecture

```
                               ┌────────────────────────────────┐
                               │   Google Cloud Console OAuth   │
                               └───────────────┬────────────────┘
                                               │ (OAuth 2.0 Auth Code)
                                               ▼
┌─────────────────────────┐    ┌────────────────────────────────┐    ┌───────────────────────────┐
│     User / Admin UI     │ ──►│   PHP Web Application (MVC)    │ ──►│      MySQL Database       │
│  (Bootstrap 5.3 + CSS)  │ ◄──│   (Auth, CSRF, Token Enc)      │ ◄──│   (AES-256 Tokens, Jobs)  │
└─────────────────────────┘    └───────────────┬────────────────┘    └─────────────┬─────────────┘
                                               │                                   │
                                               ▼                                   │
                               ┌────────────────────────────────┐                  │
                               │     Official Gmail API V1      │                  │
                               │   (MIME Parsing, RFC Reply)    │                  │
                               └───────────────▲────────────────┘                  │
                                               │                                   │
                                               │ (Send / Fetch)                    │
                                               │                                   │
┌─────────────────────────┐    ┌───────────────┴────────────────┐                  │
│    aaPanel Cron Job     │ ──►│      CLI Worker & Poller       │ ◄────────────────┘
│  & Process Supervisor   │    │    (cron.php / worker.php)     │ (Locking, Queue Engine)
└─────────────────────────┘    └────────────────────────────────┘
```

---

## 📋 Server Requirements

* **Server OS**: Ubuntu 20.04/22.04/24.04, Debian 11/12, or AlmaLinux/CentOS (Supported by aaPanel)
* **Web Server**: Nginx or Apache
* **PHP**: **PHP 8.2+** (PHP 8.2, 8.3, or 8.4)
  * Extensions: `curl`, `openssl`, `pdo_mysql`, `mbstring`, `xml`, `zip`, `bcmath`, `json`, `fileinfo`
* **Database**: MySQL 8.0+ or MariaDB 10.4+ (or SQLite 3 for testing)
* **Composer**: Composer 2.x
* **SSL Certificate**: Let's Encrypt / HTTPS (Required by Google OAuth 2.0)

---

## 🚀 aaPanel Step-by-Step Installation Guide

### Step 1: Add Website in aaPanel
1. Log into your **aaPanel** control panel.
2. Go to **Website** > **Add site**.
3. Enter your domain name (e.g. `mailautomation.example.com`).
4. Select **PHP version**: `PHP-82` (or PHP 8.3).
5. Set **Database**: Create MySQL database (note down Database Name, Username, and Password).
6. Click **Submit**.

### Step 2: Upload Files & Set Document Root
1. Open aaPanel **File Manager** and navigate to your website directory: `/www/wwwroot/mailautomation.example.com`.
2. Upload this project repository into the folder.
3. Open aaPanel > **Website** > Click your domain name to open site settings.
4. Go to **Site directory**:
   * Set **Running directory (Document Root)** to: `/public`
   * Click **Save**.

### Step 3: Configure URL Rewrite (Nginx)
In your website settings in aaPanel:
1. Click **URL rewrite**.
2. Paste the following rewrite rule:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
3. Click **Save**.

### Step 4: Install Dependencies & Run Database Migration
Open aaPanel **Terminal** or SSH into your server:

```bash
cd /www/wwwroot/mailautomation.example.com

# 1. Install Composer dependencies
composer install --no-dev --optimize-autoloader

# 2. Run automated installer
php install.php
```

Or visit `https://mailautomation.example.com/install.php` in your web browser to use the graphical web setup wizard!

#### Default Admin Credentials:
* **Email**: `admin@example.com`
* **Password**: `Admin@123456`
*(Change password immediately in profile/admin settings)*

### Step 5: Enable SSL (HTTPS)
1. In aaPanel > **Website** > Click your domain > **SSL**.
2. Select **Let's Encrypt** > Check your domain > Click **Apply**.
3. Turn on **Force HTTPS**.

### Step 6: Setup Background Cron Job in aaPanel
The automation engine runs 24/7 in the background without needing a browser open.

1. Go to aaPanel > **Cron** menu.
2. Add Cron Task:
   * **Type of Task**: `Shell Script`
   * **Name of Task**: `Gmail Automation Poller`
   * **Period**: `Every Minute` (Expression: `* * * * *`)
   * **Script Content**:
   ```bash
   cd /www/wwwroot/mailautomation.example.com && php cron.php >> storage/logs/cron.log 2>&1
   ```
3. Click **Add Task**.

### Step 7: (Optional & Recommended) Setup Process Supervisor for Worker
1. Go to aaPanel > **App Store** > Search for `Process Supervisor` > Click **Install**.
2. Open **Process Supervisor** > Click **Add Program**:
   * **Name**: `gmail-queue-worker`
   * **Run User**: `www`
   * **Run Dir**: `/www/wwwroot/mailautomation.example.com`
   * **Command**: `php worker.php`
   * **Number of processes**: `2`
3. Click **Save**.

---

## 🔑 Google Cloud Console OAuth 2.0 Setup Guide

To connect Gmail accounts, obtain OAuth 2.0 credentials from Google Cloud:

1. Visit [Google Cloud Console](https://console.cloud.google.com).
2. Create a new Project (e.g. `Gmail Email Automation`).
3. Navigate to **APIs & Services** > **Library**:
   * Search for **Gmail API** and click **Enable**.
4. Navigate to **APIs & Services** > **OAuth consent screen**:
   * Select User Type: **External** > Click **Create**.
   * Fill in App name, User support email, Developer contact email.
   * On **Scopes** page, click **Add or Remove Scopes** and add:
     * `https://www.googleapis.com/auth/gmail.modify`
     * `https://www.googleapis.com/auth/gmail.send`
     * `https://www.googleapis.com/auth/userinfo.email`
     * `https://www.googleapis.com/auth/userinfo.profile`
   * Save and continue. If in Testing mode, add your Gmail account to **Test Users**.
5. Navigate to **APIs & Services** > **Credentials**:
   * Click **Create Credentials** > **OAuth client ID**.
   * Application type: **Web application**.
   * Name: `Gmail Auto Reply App`.
   * **Authorized redirect URIs**:
     ```
     https://mailautomation.example.com/auth/google/callback
     ```
   * Click **Create**.
6. Copy the generated **Client ID** and **Client Secret**.
7. In your Gmail Automation website, log in as Admin > go to **API Settings** > Paste the **Google Client ID** and **Google Client Secret** > Click **Save Configuration**.

---

## 🛠️ Usage Instructions

### 1. Connecting a Gmail Account
1. Go to **Gmail Accounts** from the sidebar.
2. Click **Connect Gmail Account**.
3. Select your Google account and grant the requested permissions.
4. Your account will appear as **Connected** with Auto Reply and Follow-up toggles.

### 2. Customizing Auto Reply
1. Go to **Auto Reply** from the sidebar.
2. Customize your reply message. Available variables:
   * `{{first_name}}` - Sender's first name
   * `{{last_name}}` - Sender's last name
   * `{{sender_email}}` - Sender's email address
   * `{{subject}}` - Original email subject
   * `{{date}}` - Date received
3. Set your **Timezone** (e.g. `Asia/Dhaka`), **Working Days**, and **Working Hours**.
4. Set **Per-Thread Limit** (e.g. 3) and **Daily Reply Limit** (e.g. 100).
5. Click **Save Settings**.

### 3. Multi-Step Follow-ups
1. Go to **Follow-up Sequence**.
2. Add sequence steps:
   * **Step 1**: Delay `2 Days` -> Message template.
   * **Step 2**: Delay `4 Days` -> Message template.
   * **Step 3**: Delay `7 Days` -> Message template.
3. If the recipient replies to any automated email, the system automatically marks the thread as `replied` and cancels all pending follow-up steps.

---

## 🔒 Security Best Practices Implemented

* **Encrypted Tokens**: OAuth tokens encrypted with AES-256-GCM.
* **CSRF Protection**: Synchronizer token pattern verified on all state-changing POST requests.
* **SQL Injection Prevention**: All queries use PDO prepared statements with strict parameter binding.
* **XSS Prevention**: Output context escaping via `htmlspecialchars` across all views.
* **Strict User Isolation**: Foreign key constraints ensure users only access their own accounts and threads.
* **Official Google API Compliance**: Sends RFC 2822 compliant MIME messages via official Gmail API endpoints; strictly adheres to Google rate limits.

---

## 🧪 Automated Testing

Run the automated test suite locally:

```bash
./vendor/bin/phpunit
```

---

## 📄 License
This project is open-sourced under the MIT License.
