<?php
namespace App\Core;

class DatabaseSanitizer {
    private static bool $hasRun = false;

    public static function runOnce(): void {
        if (self::$hasRun) {
            return;
        }
        self::$hasRun = true;

        try {
            // 0. Ensure schema migrations and seeds are up to date
            \Database\MigrationRunner::run();

            // Safe ALTER for existing users table on MySQL
            $driver = config('database.default', 'mysql');
            if ($driver === 'mysql') {
                $userCols = [
                    'plan_id' => 'INT NULL',
                    'plan_type' => "VARCHAR(50) NOT NULL DEFAULT 'free'",
                    'subscription_status' => "VARCHAR(50) NOT NULL DEFAULT 'inactive'",
                    'gmail_limit' => 'INT NOT NULL DEFAULT 1',
                    'trial_status' => "VARCHAR(50) NOT NULL DEFAULT 'not_started'",
                    'trial_started_at' => 'DATETIME NULL',
                    'trial_ends_at' => 'DATETIME NULL',
                    'trial_days' => 'INT NOT NULL DEFAULT 0',
                    'trial_used' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'subscription_started_at' => 'DATETIME NULL',
                    'subscription_expires_at' => 'DATETIME NULL',
                    'stripe_customer_id' => 'VARCHAR(191) NULL',
                    'stripe_subscription_id' => 'VARCHAR(191) NULL',
                    'email_verified_at' => 'DATETIME NULL',
                    'verification_token' => 'VARCHAR(191) NULL',
                    'verification_token_expires_at' => 'DATETIME NULL',
                    'remember_token' => 'VARCHAR(191) NULL',
                    'remember_token_expires_at' => 'DATETIME NULL',
                ];

                foreach ($userCols as $col => $type) {
                    try {
                        Database::execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS {$col} {$type}");
                    } catch (\Throwable $t) {
                        // Ignore if column already exists
                    }
                }

                $paymentCols = [
                    'gateway' => "VARCHAR(50) NOT NULL DEFAULT 'stripe'",
                    'payment_method_type' => "VARCHAR(50) NOT NULL DEFAULT 'api'",
                    'sender_number' => "VARCHAR(100) NULL",
                    'transaction_id' => "VARCHAR(191) NULL",
                    'amount_bdt' => "DECIMAL(10,2) NULL",
                    'admin_notes' => "TEXT NULL",
                ];

                foreach ($paymentCols as $col => $type) {
                    try {
                        Database::execute("ALTER TABLE payments ADD COLUMN IF NOT EXISTS {$col} {$type}");
                    } catch (\Throwable $t) {
                        // Ignore if column already exists
                    }
                }

                $dailyCols = [
                    'followup_messages_count' => 'INT NOT NULL DEFAULT 0',
                ];

                foreach ($dailyCols as $col => $type) {
                    try {
                        Database::execute("ALTER TABLE daily_usage ADD COLUMN IF NOT EXISTS {$col} {$type}");
                    } catch (\Throwable $t) {
                        // Ignore if column already exists
                    }
                }
            } else {
                try {
                    Database::execute("ALTER TABLE users ADD COLUMN remember_token VARCHAR(191) NULL");
                } catch (\Throwable $t) {}
                try {
                    Database::execute("ALTER TABLE users ADD COLUMN remember_token_expires_at DATETIME NULL");
                } catch (\Throwable $t) {}
                try {
                    Database::execute("ALTER TABLE daily_usage ADD COLUMN followup_messages_count INTEGER NOT NULL DEFAULT 0");
                } catch (\Throwable $t) {
                    // Ignore if column already exists
                }
            }

            // 1. Purge any legacy default boilerplate from automation_settings table
            Database::execute(
                "UPDATE automation_settings 
                 SET reply_message = NULL 
                 WHERE reply_message LIKE '%Automated Support%' 
                    OR reply_message LIKE '%Thank you for reaching out%'
                    OR reply_message LIKE '%received your message%'
                    OR reply_message = 'Where are you located?'"
            );

            // 2. Cancel any pending queue jobs containing old boilerplate text
            Database::execute(
                "UPDATE scheduled_jobs 
                 SET status = 'cancelled', last_error = 'Purged legacy default boilerplate' 
                 WHERE status = 'pending' 
                   AND (payload LIKE '%Automated Support%' 
                     OR payload LIKE '%Thank you for reaching out%'
                     OR payload LIKE '%received your message%')"
            );
        } catch (\Throwable $e) {
            // Silently ignore if database error
        }
    }
}
