-- Migration: Create Email Open Tracking and Events Tables
-- Safe for MySQL 8+, MariaDB 10.4+, and SQLite 3

CREATE TABLE IF NOT EXISTS email_open_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_token VARCHAR(128) NOT NULL,
    message_id VARCHAR(191) NULL,
    gmail_account_id INT NOT NULL,
    user_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    thread_id INT NULL,
    campaign_id INT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'auto_reply',
    scheduled_job_id INT NULL,
    subject VARCHAR(500) NULL,
    open_count INT NOT NULL DEFAULT 0,
    first_opened_at DATETIME NULL,
    last_opened_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tracking_token (tracking_token),
    INDEX idx_track_token (tracking_token),
    INDEX idx_track_msg (message_id),
    INDEX idx_track_recipient (recipient_email),
    INDEX idx_track_acc (gmail_account_id),
    INDEX idx_track_user (user_id),
    INDEX idx_track_thread (thread_id),
    INDEX idx_track_campaign (campaign_id),
    INDEX idx_track_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_open_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id INT NOT NULL,
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(100) NULL,
    user_agent TEXT NULL,
    device_type VARCHAR(50) NULL,
    operating_system VARCHAR(100) NULL,
    browser VARCHAR(100) NULL,
    mail_client VARCHAR(100) NULL,
    is_bot_or_prefetch TINYINT(1) NOT NULL DEFAULT 0,
    metadata TEXT NULL,
    INDEX idx_ev_tracking (tracking_id),
    INDEX idx_ev_opened_at (opened_at),
    INDEX idx_ev_device (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
