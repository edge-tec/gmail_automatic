-- Gmail Auto Reply & Follow-up Automation Database Schema
-- Compatible with MySQL 8+, MariaDB 10.4+, and SQLite 3

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    plan_id INT NULL,
    plan_type VARCHAR(50) NOT NULL DEFAULT 'free',
    subscription_status VARCHAR(50) NOT NULL DEFAULT 'inactive',
    gmail_limit INT NOT NULL DEFAULT 1,
    trial_status VARCHAR(50) NOT NULL DEFAULT 'not_started',
    trial_started_at DATETIME NULL,
    trial_ends_at DATETIME NULL,
    trial_days INT NOT NULL DEFAULT 0,
    trial_used TINYINT(1) NOT NULL DEFAULT 0,
    subscription_started_at DATETIME NULL,
    subscription_expires_at DATETIME NULL,
    stripe_customer_id VARCHAR(191) NULL,
    stripe_subscription_id VARCHAR(191) NULL,
    email_verified_at DATETIME NULL,
    verification_token VARCHAR(191) NULL,
    verification_token_expires_at DATETIME NULL,
    remember_token VARCHAR(191) NULL,
    remember_token_expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(191) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gmail_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_email VARCHAR(255) NOT NULL,
    google_user_id VARCHAR(191) NULL,
    access_token LONGTEXT NULL,
    refresh_token LONGTEXT NULL,
    token_expires_at DATETIME NULL,
    history_id VARCHAR(100) NULL,
    connected_at DATETIME NULL,
    initial_sync_completed TINYINT(1) NOT NULL DEFAULT 0,
    initial_history_id VARCHAR(191) NULL,
    initial_sync_at DATETIME NULL,
    baseline_message_date DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'connected',
    last_sync_at DATETIME NULL,
    last_error LONGTEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_gmail_email (gmail_email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gmail_account_id INT NOT NULL UNIQUE,
    auto_reply_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reply_message LONGTEXT NULL,
    max_reply_per_thread INT NOT NULL DEFAULT 3,
    daily_reply_limit INT NOT NULL DEFAULT 100,
    reply_delay INT NOT NULL DEFAULT 0, -- delay in seconds
    followup_enabled TINYINT(1) NOT NULL DEFAULT 0,
    daily_followup_limit INT NOT NULL DEFAULT 100,
    require_recipient_reply_before_next_reply TINYINT(1) NOT NULL DEFAULT 0,
    timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Dhaka',
    working_days VARCHAR(255) NOT NULL DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
    working_start VARCHAR(20) NOT NULL DEFAULT '00:00',
    working_end VARCHAR(20) NOT NULL DEFAULT '23:59',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_acc_id (gmail_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gmail_account_id INT NOT NULL,
    gmail_thread_id VARCHAR(191) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255) NULL,
    subject VARCHAR(500) NULL,
    reply_count INT NOT NULL DEFAULT 0,
    followup_count INT NOT NULL DEFAULT 0,
    automation_status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, replied, stopped, completed
    last_incoming_at DATETIME NULL,
    last_outgoing_at DATETIME NULL,
    next_followup_at DATETIME NULL,
    last_processed_message_id VARCHAR(191) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_thread (gmail_account_id, gmail_thread_id),
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_acc_thread (gmail_account_id, gmail_thread_id),
    INDEX idx_auto_status (automation_status),
    INDEX idx_next_followup (next_followup_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    gmail_message_id VARCHAR(191) NOT NULL,
    direction VARCHAR(20) NOT NULL DEFAULT 'incoming', -- incoming, outgoing
    sender VARCHAR(255) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NULL,
    snippet TEXT NULL,
    message_body LONGTEXT NULL,
    received_at DATETIME NULL,
    sent_at DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'processed',
    is_historical TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_msg (gmail_account_id, gmail_message_id),
    FOREIGN KEY (thread_id) REFERENCES email_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_thread_id (thread_id),
    INDEX idx_gmail_msg (gmail_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reply_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tpl_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    step_number INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    delay_value INT NOT NULL DEFAULT 2,
    delay_unit VARCHAR(20) NOT NULL DEFAULT 'days', -- minutes, hours, days
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_fu_account (gmail_account_id),
    INDEX idx_fu_step (gmail_account_id, step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gmail_account_id INT NOT NULL,
    thread_id INT NOT NULL,
    job_type VARCHAR(50) NOT NULL, -- auto_reply, follow_up, sync_account
    payload JSON NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, completed, failed, cancelled
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES email_threads(id) ON DELETE CASCADE,
    INDEX idx_job_status_sched (status, scheduled_at),
    INDEX idx_job_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    rule_type VARCHAR(50) NOT NULL, -- sender_contains, subject_contains, body_contains
    rule_value VARCHAR(255) NOT NULL,
    template_id INT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'reply', -- reply, skip, custom_reply
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_rule_acc (gmail_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gmail_account_id INT NOT NULL,
    usage_date DATE NOT NULL,
    reply_count INT NOT NULL DEFAULT 0, -- Unique traffic sequences counted towards daily limit (1 per lead sequence)
    reply_messages_count INT NOT NULL DEFAULT 0, -- Actual auto-reply messages sent
    followup_count INT NOT NULL DEFAULT 0, -- Unique follow-up campaigns counted towards daily limit
    followup_messages_count INT NOT NULL DEFAULT 0, -- Actual follow-up messages sent
    total_sent INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_date (gmail_account_id, usage_date),
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    thread_id INT NOT NULL,
    gmail_thread_id VARCHAR(191) NOT NULL,
    message_id VARCHAR(191) NULL,
    sender_email VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    normalized_subject VARCHAR(500) NULL,
    campaign_status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, completed, cancelled, replied, stopped
    daily_follow_counted TINYINT(1) NOT NULL DEFAULT 0,
    counted_date DATE NULL,
    total_steps INT NOT NULL DEFAULT 0,
    current_step INT NOT NULL DEFAULT 0,
    last_sent_at DATETIME NULL,
    next_step_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_acc_thread_camp (gmail_account_id, gmail_thread_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES email_threads(id) ON DELETE CASCADE,
    INDEX idx_fc_user (user_id),
    INDEX idx_fc_acc (gmail_account_id),
    INDEX idx_fc_thread (thread_id),
    INDEX idx_fc_status (campaign_status),
    INDEX idx_fc_date (counted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followup_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    thread_id INT NOT NULL,
    followup_step INT NOT NULL DEFAULT 1,
    template_id INT NULL,
    message LONGTEXT NULL,
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, sent, failed, cancelled
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES followup_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES email_threads(id) ON DELETE CASCADE,
    INDEX idx_fj_camp (campaign_id),
    INDEX idx_fj_status (status, scheduled_at),
    INDEX idx_fj_acc (gmail_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auto_reply_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    normalized_sender_email VARCHAR(255) NOT NULL,
    first_message_id VARCHAR(191) NULL,
    first_thread_id VARCHAR(191) NULL,
    reply_sequence_step INT NOT NULL DEFAULT 0,
    reply_sequence_total INT NOT NULL DEFAULT 1,
    reply_sequence_status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, completed
    reply_sequence_completed_at DATETIME NULL,
    reply_sent_at DATETIME NULL,
    daily_counted TINYINT(1) NOT NULL DEFAULT 0,
    counted_date DATE NULL,
    recipient_replied_for_step INT NOT NULL DEFAULT 0,
    last_recipient_reply_at DATETIME NULL,
    reply_status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, active, completed, replied, cancelled, failed
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_sender_reply (user_id, normalized_sender_email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_arr_user (user_id),
    INDEX idx_arr_acc (gmail_account_id),
    INDEX idx_arr_status (reply_status),
    INDEX idx_arr_seq_status (reply_sequence_status),
    INDEX idx_arr_counted (daily_counted, counted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    gmail_account_id INT NULL,
    log_type VARCHAR(50) NOT NULL DEFAULT 'info', -- info, success, warning, error
    message TEXT NOT NULL,
    context_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_type (log_type),
    INDEX idx_log_created (created_at),
    INDEX idx_log_acc (gmail_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
    gmail_limit INT NOT NULL DEFAULT 100,
    features TEXT NULL,
    stripe_price_id VARCHAR(191) NULL,
    is_popular TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_slug (slug),
    INDEX idx_plan_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NULL,
    gateway VARCHAR(50) NOT NULL DEFAULT 'stripe', -- stripe, bkash, nagad
    payment_method_type VARCHAR(50) NOT NULL DEFAULT 'api', -- api, manual_number
    sender_number VARCHAR(100) NULL,
    transaction_id VARCHAR(191) NULL,
    stripe_session_id VARCHAR(191) NULL UNIQUE,
    stripe_payment_intent_id VARCHAR(191) NULL,
    stripe_invoice_id VARCHAR(191) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_bdt DECIMAL(10,2) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'usd',
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, paid, failed, rejected, cancelled, refunded
    admin_notes TEXT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payment_user (user_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_gateway (gateway),
    INDEX idx_payment_trx (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body LONGTEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_tpl_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NULL,
    template_slug VARCHAR(100) NULL,
    event_key VARCHAR(191) NULL UNIQUE,
    subject VARCHAR(500) NOT NULL,
    body LONGTEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, sent, failed, cancelled
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_job_status (status, scheduled_at),
    INDEX idx_email_job_event (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(191) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_path VARCHAR(191) NOT NULL UNIQUE,
    page_name VARCHAR(255) NOT NULL,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    focus_keyword VARCHAR(255) NULL,
    secondary_keywords VARCHAR(500) NULL,
    canonical_url VARCHAR(500) NULL,
    is_indexable TINYINT(1) NOT NULL DEFAULT 1,
    is_followable TINYINT(1) NOT NULL DEFAULT 1,
    og_title VARCHAR(255) NULL,
    og_description VARCHAR(500) NULL,
    og_image VARCHAR(500) NULL,
    twitter_card VARCHAR(50) NOT NULL DEFAULT 'summary_large_image',
    schema_type VARCHAR(50) NOT NULL DEFAULT 'WebPage',
    custom_schema_json LONGTEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seo_page_route (route_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    old_url VARCHAR(500) NOT NULL UNIQUE,
    new_url VARCHAR(500) NOT NULL,
    status_code INT NOT NULL DEFAULT 301,
    hits INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_redirect_old (old_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) NOT NULL,
    answer LONGTEXT NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faq_sort (sort_order, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(500) NULL,
    author_name VARCHAR(255) NOT NULL DEFAULT 'Team',
    category VARCHAR(100) NOT NULL DEFAULT 'Guides',
    tags VARCHAR(500) NULL,
    seo_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    focus_keyword VARCHAR(255) NULL,
    canonical_url VARCHAR(500) NULL,
    og_image VARCHAR(500) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'published', -- published, draft
    views INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_blog_slug (slug),
    INDEX idx_blog_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skipped_email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_account_id INT NOT NULL,
    thread_id INT NULL,
    gmail_thread_id VARCHAR(191) NULL,
    gmail_message_id VARCHAR(191) NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255) NULL,
    recipient_email VARCHAR(255) NULL,
    subject VARCHAR(500) NULL,
    snippet TEXT NULL,
    skip_reason VARCHAR(255) NOT NULL,
    skip_type VARCHAR(50) NOT NULL DEFAULT 'duplicate_traffic', -- duplicate_traffic, blacklist, spam_filter, limit_reached, rule_skip, disabled
    first_reply_sent_at DATETIME NULL,
    received_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_sel_user (user_id),
    INDEX idx_sel_acc (gmail_account_id),
    INDEX idx_sel_sender (sender_email),
    INDEX idx_sel_type (skip_type),
    INDEX idx_sel_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



