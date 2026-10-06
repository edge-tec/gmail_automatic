-- Migration: 2026_10_06_create_lead_management_system.sql
-- Enterprise Lead Management, Lead Deletion, Lead Files, Storage Cleanup, Permissions & Audit Logging

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    company VARCHAR(200) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new', -- new, contacted, qualified, replied, unresponsive, archived
    source VARCHAR(100) NOT NULL DEFAULT 'manual', -- manual, import, campaign, auto_reply, api
    tags VARCHAR(500) NULL,
    notes TEXT NULL,
    custom_fields JSON NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_leads_user (user_id),
    INDEX idx_leads_email (email),
    INDEX idx_leads_user_email (user_id, email),
    INDEX idx_leads_status (status),
    INDEX idx_leads_source (source),
    INDEX idx_leads_archived (is_archived),
    INDEX idx_leads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lead_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) NULL,
    file_hash VARCHAR(64) NULL, -- SHA-256 for deduplication and shared protection
    is_orphaned TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- active, marked_for_deletion, deleted, cleanup_pending, cleanup_failed
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lf_user (user_id),
    INDEX idx_lf_lead (lead_id),
    INDEX idx_lf_hash (file_hash),
    INDEX idx_lf_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_lead_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    granted_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_perm (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ulp_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_deletion_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_uuid VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    actor_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- single_delete, bulk_delete, clear_data, clear_all
    scope VARCHAR(100) NULL,
    scope_filter JSON NULL,
    requested_count INT NOT NULL DEFAULT 0,
    completed_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    files_deleted INT NOT NULL DEFAULT 0,
    files_pending INT NOT NULL DEFAULT 0,
    storage_freed BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, completed, partial, failed, cancelled
    error_category VARCHAR(50) NULL, -- SUCCESS, PARTIAL, FAILED, ALREADY_DELETED, AUTHORIZATION_FAILED, TEMPORARY_FAILURE, PERMANENT_FAILURE
    last_error TEXT NULL,
    current_batch INT NOT NULL DEFAULT 0,
    total_batches INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ldo_uuid (operation_uuid),
    INDEX idx_ldo_user (user_id),
    INDEX idx_ldo_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_storage_cleanups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_id INT NULL,
    lead_file_id INT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(64) NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, completed, failed, abandoned
    retry_count INT NOT NULL DEFAULT 0,
    max_retries INT NOT NULL DEFAULT 5,
    next_retry_at DATETIME NULL,
    last_error TEXT NULL,
    error_category VARCHAR(50) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lsc_status (status),
    INDEX idx_lsc_next_retry (next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_uuid VARCHAR(64) NULL,
    actor_id INT NOT NULL,
    actor_email VARCHAR(255) NOT NULL,
    target_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    requested_count INT NOT NULL DEFAULT 0,
    deleted_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    files_deleted INT NOT NULL DEFAULT 0,
    files_pending INT NOT NULL DEFAULT 0,
    storage_deleted BIGINT NOT NULL DEFAULT 0,
    result VARCHAR(50) NOT NULL, -- SUCCESS, PARTIAL, FAILED, ALREADY_DELETED, AUTHORIZATION_FAILED
    error_category VARCHAR(50) NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lal_actor (actor_id),
    INDEX idx_lal_target (target_user_id),
    INDEX idx_lal_action (action),
    INDEX idx_lal_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
