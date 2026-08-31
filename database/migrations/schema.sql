-- Gmail Auto Reply & Follow-up Automation Database Schema
-- Compatible with MySQL 8+, MariaDB 10.4+, and SQLite 3

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(191) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gmail_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gmail_email VARCHAR(255) NOT NULL,
    google_user_id VARCHAR(191) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    history_id VARCHAR(100) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'connected',
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
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
    reply_message TEXT NULL,
    max_reply_per_thread INT NOT NULL DEFAULT 3,
    daily_reply_limit INT NOT NULL DEFAULT 100,
    reply_delay INT NOT NULL DEFAULT 0, -- delay in seconds
    followup_enabled TINYINT(1) NOT NULL DEFAULT 0,
    daily_followup_limit INT NOT NULL DEFAULT 100,
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
    reply_count INT NOT NULL DEFAULT 0,
    followup_count INT NOT NULL DEFAULT 0,
    total_sent INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_date (gmail_account_id, usage_date),
    FOREIGN KEY (gmail_account_id) REFERENCES gmail_accounts(id) ON DELETE CASCADE,
    INDEX idx_usage_date (usage_date)
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
