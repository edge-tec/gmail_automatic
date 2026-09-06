# 🚀 Full aaPanel Installation & Deployment Guide

A step-by-step production deployment guide for running **Gmail Automation & Bulk Campaign Sender** on **aaPanel** (Linux VPS).

---

## 📋 System Requirements

* **Web Server**: Nginx 1.22+ (or Apache)
* **PHP**: PHP 8.2 or 8.3
* **Required PHP Extensions**: `curl`, `openssl`, `pdo_mysql`, `mbstring`, `xml`, `zip`, `bcmath`, `fileinfo`
* **Database**: MySQL 5.7/8.0+ or MariaDB 10.4+
* **Process Manager**: aaPanel Process Supervisor
* **SSL**: Let's Encrypt / HTTPS (Required for Google OAuth 2.0)

---

## 🛠️ Step-by-Step Installation

### Step 1: Pre-requisites in aaPanel App Store
1. Open your **aaPanel Control Panel**.
2. Go to **App Store** and install:
   - **Nginx**
   - **MySQL**
   - **PHP-8.2** (or PHP-8.3)
   - **Process Supervisor**
3. Configure PHP:
   - Go to **App Store** > **Installed** > **PHP-8.2** > **Settings**.
   - Under **Install extensions**, ensure `fileinfo`, `curl`, `pdo_mysql`, `mbstring`, `xml`, `zip`, `bcmath` are installed.
   - Under **Disabled functions**, remove `putenv`, `exec`, and `proc_open` from the list.

---

### Step 2: Add Website & Database in aaPanel
1. In aaPanel, navigate to **Website** > **Add site**.
2. Fill in:
   - **Domain**: `your-domain.com` (e.g. `2xbets.net`)
   - **Database**: MySQL (Copy down the database name, username, and password)
   - **PHP Version**: `PHP-82` (or PHP-83)
3. Click **Submit**.

---

### Step 3: Deploy Project Files
Open aaPanel **Terminal** or connect via SSH, then execute:

```bash
cd /www/wwwroot/your-domain.com

# Clone repository:
git clone https://github.com/edge-tec/gmail_automatic.git .
# Or if already present:
git pull origin main

# Install composer dependencies:
composer install --no-dev --optimize-autoloader

# Set file permissions:
chown -R www:www /www/wwwroot/your-domain.com
chmod -R 775 /www/wwwroot/your-domain.com/storage
```

---

### Step 4: Set Running Directory (`/public`) ⚠️ [MANDATORY]
1. Go to **Website** > Click your domain name to open site settings.
2. Go to the **Site directory** tab:
   - Set **Running directory** to: `/public`
   - Click **Save**.

---

### Step 5: Configure URL Rewrite (Nginx)
In the same settings window:
1. Go to the **URL rewrite** tab.
2. Paste the following rule:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
3. Click **Save**.

---

### Step 6: Enable SSL (HTTPS) 🔒
1. In the same settings window, go to the **SSL** tab.
2. Select **Let's Encrypt**, check your domain, and click **Apply**.
3. Once active, toggle **Force HTTPS** ON.

---

### Step 7: Configure Environment & Run Installer
1. In terminal, copy and edit `.env`:
```bash
cp .env.example .env
nano .env
```
2. Set your database credentials:
```env
APP_NAME="Gmail Automation"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```
3. Run the installer to generate database schema and default admin:
```bash
php install.php
```

* **Default Admin Login**:
  - **Email**: `admin@example.com`
  - **Password**: `Admin@123456`
  *(Change password immediately in profile settings)*

---

### Step 8: Google Cloud Console OAuth Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/) and enable **Gmail API**.
2. Configure **OAuth Consent Screen** (User Type: External).
3. Create **Credentials** > **OAuth Client ID** (Web application).
4. Set **Authorized redirect URIs**:
   ```
   https://your-domain.com/auth/google/callback
   ```
5. Save your **Client ID** and **Client Secret** into your app:
   - Log in to your app as admin > Go to **Admin** > **Settings** / **Google API Credentials**.

---

### Step 9: Configure aaPanel Cron Job
1. In aaPanel, go to **Cron** in the left menu.
2. Click **Add Cron Task**:
   - **Type of Task**: `Shell Script`
   - **Name**: `Gmail Automation Poller`
   - **Period**: `Every Minute` (`* * * * *`)
   - **Script Content**:
     ```bash
     cd /www/wwwroot/your-domain.com && php cron.php >> storage/logs/cron.log 2>&1
     ```
3. Click **Add Task**.

---

### Step 10: Configure aaPanel Process Supervisor (Workers)
1. Go to **App Store** > **Process Supervisor** > **Setting**.
2. **Add Process 1 (Real-time Email Queue)**:
   - **Name**: `gmail-queue-worker`
   - **Run User**: `www`
   - **Run Dir**: `/www/wwwroot/your-domain.com`
   - **Start Command**: `php /www/wwwroot/your-domain.com/worker.php --queue-only`
   - **Processes**: `2`
   - Click **Confirm / Save**.
3. **Add Process 2 (Bulk Campaign Dispatcher)**:
   - **Name**: `gmail-campaign-worker`
   - **Run User**: `www`
   - **Run Dir**: `/www/wwwroot/your-domain.com`
   - **Start Command**: `php /www/wwwroot/your-domain.com/worker.php --campaign-only`
   - **Processes**: `1`
   - Click **Confirm / Save**.

---

## 🔍 Log Monitoring Commands

```bash
# Monitor Cron Poller:
tail -f /www/wwwroot/your-domain.com/storage/logs/cron.log

# Monitor Queue Worker:
tail -f /www/wwwroot/your-domain.com/storage/logs/worker-queue.log

# Monitor Bulk Campaign Worker:
tail -f /www/wwwroot/your-domain.com/storage/logs/worker-campaign.log
```
