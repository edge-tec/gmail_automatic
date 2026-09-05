<?php
namespace App\Core;

class DatabaseSanitizer {
    private static bool $hasRun = false;

    public static function reset(): void {
        self::$hasRun = false;
    }

    public static function run(): void {
        self::$hasRun = false;
        self::runOnce();
    }

    public static function runOnce(): void {
        if (self::$hasRun) {
            return;
        }
        self::$hasRun = true;

        try {
            // 0. Ensure schema migrations and seeds are up to date
            try {
                \Database\MigrationRunner::run();
            } catch (\Throwable $t) {
                error_log("Notice: MigrationRunner non-critical error: " . $t->getMessage());
            }

            // Safe ALTER for existing users table on MySQL
            $driver = config('database.default', 'mysql');
            if ($driver === 'mysql') {
                $userCols = [
                    'plan_id' => 'INT NULL',
                    'plan_type' => "VARCHAR(50) NOT NULL DEFAULT 'free'",
                    'subscription_status' => "VARCHAR(50) NOT NULL DEFAULT 'inactive'",
                    'gmail_limit' => 'INT NOT NULL DEFAULT 1',
                    'can_bulk_send' => 'TINYINT(1) NOT NULL DEFAULT 0',
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

                self::ensureTableColumns('users', $userCols);

                $paymentCols = [
                    'gateway' => "VARCHAR(50) NOT NULL DEFAULT 'stripe'",
                    'payment_method_type' => "VARCHAR(50) NOT NULL DEFAULT 'api'",
                    'sender_number' => "VARCHAR(100) NULL",
                    'transaction_id' => "VARCHAR(191) NULL",
                    'amount_bdt' => "DECIMAL(10,2) NULL",
                    'admin_notes' => "TEXT NULL",
                ];

                self::ensureTableColumns('payments', $paymentCols);

                $dailyCols = [
                    'reply_messages_count' => 'INT NOT NULL DEFAULT 0',
                    'followup_messages_count' => 'INT NOT NULL DEFAULT 0',
                ];

                self::ensureTableColumns('daily_usage', $dailyCols);

                $settingCols = [
                    'require_recipient_reply_before_next_reply' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'use_account_override' => 'TINYINT(1) NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('automation_settings', $settingCols);

                $recipientCols = [
                    'reply_sequence_step' => 'INT NOT NULL DEFAULT 0',
                    'reply_sequence_total' => 'INT NOT NULL DEFAULT 1',
                    'reply_sequence_status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
                    'reply_sequence_completed_at' => 'DATETIME NULL',
                    'daily_counted' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'counted_date' => 'DATE NULL',
                    'recipient_replied_for_step' => 'INT NOT NULL DEFAULT 0',
                    'last_recipient_reply_at' => 'DATETIME NULL',
                ];

                self::ensureTableColumns('auto_reply_recipients', $recipientCols);

                $accountCols = [
                    'connected_at' => 'DATETIME NULL',
                    'initial_sync_completed' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'initial_history_id' => 'VARCHAR(191) NULL',
                    'initial_sync_at' => 'DATETIME NULL',
                    'baseline_message_date' => 'DATETIME NULL',
                    'bulk_daily_limit' => 'INT NOT NULL DEFAULT 50',
                    'campaign_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
                    'temp_unavailable_until' => 'DATETIME NULL',
                    'temp_failure_count' => 'INT NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('gmail_accounts', $accountCols);

                $userCols = [
                    'can_bulk_send' => 'TINYINT(1) NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('users', $userCols);

                $messageCols = [
                    'is_historical' => 'TINYINT(1) NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('email_messages', $messageCols);

                // Ensure columns with large text / rich content / JSON / base64 images use LONGTEXT to prevent 1406 truncation errors
                $longtextCols = [
                    'automation_settings' => ['reply_message' => 'LONGTEXT NULL'],
                    'activity_logs' => ['message' => 'LONGTEXT NOT NULL', 'context_json' => 'LONGTEXT NULL'],
                    'scheduled_jobs' => ['payload' => 'LONGTEXT NULL', 'last_error' => 'LONGTEXT NULL'],
                    'followup_jobs' => ['message' => 'LONGTEXT NULL', 'last_error' => 'LONGTEXT NULL'],
                    'email_messages' => ['snippet' => 'LONGTEXT NULL', 'message_body' => 'LONGTEXT NULL'],
                    'skipped_email_logs' => ['snippet' => 'LONGTEXT NULL'],
                    'gmail_accounts' => ['access_token' => 'LONGTEXT NULL', 'refresh_token' => 'LONGTEXT NULL', 'last_error' => 'LONGTEXT NULL'],
                    'system_settings' => ['setting_value' => 'LONGTEXT NULL'],
                    'payments' => ['admin_notes' => 'LONGTEXT NULL'],
                    'email_jobs' => ['last_error' => 'LONGTEXT NULL'],
                ];
                foreach ($longtextCols as $table => $cols) {
                    self::ensureTableColumnTypes($table, $cols);
                }
            } else {
                $settingColsSqlite = [
                    'require_recipient_reply_before_next_reply' => 'INTEGER NOT NULL DEFAULT 0',
                    'use_account_override' => 'INTEGER NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('automation_settings', $settingColsSqlite);

                $dailyColsSqlite = [
                    'reply_messages_count' => 'INTEGER NOT NULL DEFAULT 0',
                    'followup_messages_count' => 'INTEGER NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('daily_usage', $dailyColsSqlite);

                $recipientColsSqlite = [
                    'reply_sequence_step' => 'INTEGER NOT NULL DEFAULT 0',
                    'reply_sequence_total' => 'INTEGER NOT NULL DEFAULT 1',
                    'reply_sequence_status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
                    'reply_sequence_completed_at' => 'DATETIME NULL',
                    'daily_counted' => 'INTEGER NOT NULL DEFAULT 0',
                    'counted_date' => 'TEXT NULL',
                    'recipient_replied_for_step' => 'INTEGER NOT NULL DEFAULT 0',
                    'last_recipient_reply_at' => 'TEXT NULL',
                ];
                self::ensureTableColumns('auto_reply_recipients', $recipientColsSqlite);

                $accountColsSqlite = [
                    'connected_at' => 'TEXT NULL',
                    'initial_sync_completed' => 'INTEGER NOT NULL DEFAULT 0',
                    'initial_history_id' => 'TEXT NULL',
                    'initial_sync_at' => 'TEXT NULL',
                    'baseline_message_date' => 'TEXT NULL',
                    'bulk_daily_limit' => 'INTEGER NOT NULL DEFAULT 50',
                    'campaign_enabled' => 'INTEGER NOT NULL DEFAULT 1',
                    'temp_unavailable_until' => 'TEXT NULL',
                    'temp_failure_count' => 'INTEGER NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('gmail_accounts', $accountColsSqlite);

                $messageColsSqlite = [
                    'is_historical' => 'INTEGER NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('email_messages', $messageColsSqlite);

                $userColsSqlite = [
                    'can_bulk_send' => 'INTEGER NOT NULL DEFAULT 0',
                ];
                self::ensureTableColumns('users', $userColsSqlite);
            }

            // Ensure professional plan features include bulk campaigns and sync active subscribers
            try {
                $profPlan = Database::first("SELECT id, features FROM plans WHERE slug = 'professional' LIMIT 1");
                if ($profPlan) {
                    $feats = json_decode($profPlan['features'] ?? '[]', true) ?: [];
                    $checkTitle = 'High-Volume Bulk Email Campaigns (Multi-Account Sender)';
                    if (!in_array($checkTitle, $feats)) {
                        array_splice($feats, 1, 0, [
                            'High-Volume Bulk Email Campaigns (Multi-Account Sender)',
                            'Bulk Sender Access Permission Included',
                            'CSV, TXT & Excel (XLSX) Recipient List Importer',
                            'Multi-Variation Message Spin (A/B Deliverability Engine)',
                        ]);
                        Database::execute("UPDATE plans SET features = :feat WHERE id = :id", [
                            'feat' => json_encode($feats),
                            'id' => $profPlan['id'],
                        ]);
                    }
                }
                // Auto-grant bulk send to existing active professional subscribers
                Database::execute("UPDATE users SET can_bulk_send = 1 WHERE (plan_type = 'professional' OR plan_id IN (SELECT id FROM plans WHERE slug = 'professional')) AND subscription_status = 'active'");
            } catch (\Throwable $t) {
                // Safe fallback if tables not yet ready
            }

            // Ensure skipped_email_logs table exists
            Database::execute("CREATE TABLE IF NOT EXISTS skipped_email_logs (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL') . ",
                gmail_account_id " . ($driver === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL') . ",
                thread_id " . ($driver === 'mysql' ? 'INT NULL' : 'INTEGER NULL') . ",
                gmail_thread_id VARCHAR(191) NULL,
                gmail_message_id VARCHAR(191) NULL,
                sender_email VARCHAR(255) NOT NULL,
                sender_name VARCHAR(255) NULL,
                recipient_email VARCHAR(255) NULL,
                subject VARCHAR(500) NULL,
                snippet TEXT NULL,
                skip_reason VARCHAR(255) NOT NULL,
                skip_type VARCHAR(50) NOT NULL DEFAULT 'duplicate_traffic',
                first_reply_sent_at " . ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL') . ",
                received_at " . ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL') . ",
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            // Ensure email_templates and email_jobs tables exist
            Database::execute("CREATE TABLE IF NOT EXISTS email_templates (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                slug VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body LONGTEXT NOT NULL,
                available_vars TEXT NULL,
                is_enabled " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            Database::execute("CREATE TABLE IF NOT EXISTS email_jobs (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NULL' : 'INTEGER NULL') . ",
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255) NULL,
                template_slug VARCHAR(100) NULL,
                event_key VARCHAR(191) NULL UNIQUE,
                subject VARCHAR(500) NOT NULL,
                body LONGTEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                attempts " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0') . ",
                max_attempts " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 3' : 'INTEGER NOT NULL DEFAULT 3') . ",
                scheduled_at " . ($driver === 'mysql' ? 'DATETIME NOT NULL' : 'TEXT NOT NULL') . ",
                sent_at " . ($driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL') . ",
                last_error LONGTEXT NULL,
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            // Ensure Global Automation tables exist
            Database::execute("CREATE TABLE IF NOT EXISTS global_automation_settings (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NOT NULL UNIQUE' : 'INTEGER NOT NULL UNIQUE') . ",
                auto_reply_enabled " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                followup_enabled " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                require_recipient_reply_before_next_reply " . ($driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0') . ",
                max_reply_per_thread " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 3' : 'INTEGER NOT NULL DEFAULT 3') . ",
                daily_reply_limit " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 100' : 'INTEGER NOT NULL DEFAULT 100') . ",
                daily_followup_limit " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 100' : 'INTEGER NOT NULL DEFAULT 100') . ",
                reply_delay " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0') . ",
                timezone VARCHAR(100) NOT NULL DEFAULT 'Asia/Dhaka',
                working_days VARCHAR(255) NOT NULL DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                working_start VARCHAR(20) NOT NULL DEFAULT '00:00',
                working_end VARCHAR(20) NOT NULL DEFAULT '23:59',
                version " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            Database::execute("CREATE TABLE IF NOT EXISTS global_auto_reply_messages (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL') . ",
                step_number " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                variation_name VARCHAR(100) NOT NULL DEFAULT 'Variation 1',
                message LONGTEXT NOT NULL,
                delay_value " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0') . ",
                delay_unit VARCHAR(50) NOT NULL DEFAULT 'seconds',
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            Database::execute("CREATE TABLE IF NOT EXISTS global_followup_sequences (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL') . ",
                step_number " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                name VARCHAR(255) NOT NULL DEFAULT 'Follow-up #1',
                delay_value " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                delay_unit VARCHAR(50) NOT NULL DEFAULT 'days',
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");

            Database::execute("CREATE TABLE IF NOT EXISTS global_followup_messages (
                id " . ($driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
                user_id " . ($driver === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL') . ",
                step_number " . ($driver === 'mysql' ? 'INT NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1') . ",
                variation_name VARCHAR(100) NOT NULL DEFAULT 'Variation 1',
                message LONGTEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . ",
                updated_at " . ($driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP') . "
            )");


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

            // 3. Ensure active subscribed users have valid 1-month expiry dates and converted trial status
            $oneMonthAhead = date('Y-m-d H:i:s', strtotime('+1 month'));
            Database::execute(
                "UPDATE users 
                 SET subscription_expires_at = :exp, trial_status = 'converted'
                 WHERE subscription_status = 'active' AND (subscription_expires_at IS NULL OR subscription_expires_at = '')",
                ['exp' => $oneMonthAhead]
            );

            // 4. Create SEO & Blog tables if not exist
            $isMysql = config('database.default', 'mysql') === 'mysql';
            $autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $nowFunc = $isMysql ? 'CURRENT_TIMESTAMP' : "datetime('now')";

            Database::execute("
                CREATE TABLE IF NOT EXISTS seo_settings (
                    id {$autoInc},
                    setting_key VARCHAR(191) NOT NULL UNIQUE,
                    setting_value LONGTEXT NULL,
                    created_at DATETIME DEFAULT {$nowFunc},
                    updated_at DATETIME DEFAULT {$nowFunc}
                )
            ");

            Database::execute("
                CREATE TABLE IF NOT EXISTS seo_pages (
                    id {$autoInc},
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
                    created_at DATETIME DEFAULT {$nowFunc},
                    updated_at DATETIME DEFAULT {$nowFunc}
                )
            ");

            Database::execute("
                CREATE TABLE IF NOT EXISTS seo_redirects (
                    id {$autoInc},
                    old_url VARCHAR(500) NOT NULL UNIQUE,
                    new_url VARCHAR(500) NOT NULL,
                    status_code INT NOT NULL DEFAULT 301,
                    hits INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT {$nowFunc},
                    updated_at DATETIME DEFAULT {$nowFunc}
                )
            ");

            Database::execute("
                CREATE TABLE IF NOT EXISTS seo_faqs (
                    id {$autoInc},
                    question VARCHAR(500) NOT NULL,
                    answer LONGTEXT NOT NULL,
                    category VARCHAR(100) NOT NULL DEFAULT 'General',
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT {$nowFunc},
                    updated_at DATETIME DEFAULT {$nowFunc}
                )
            ");

            Database::execute("
                CREATE TABLE IF NOT EXISTS blog_posts (
                    id {$autoInc},
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
                    status VARCHAR(50) NOT NULL DEFAULT 'published',
                    views INT NOT NULL DEFAULT 0,
                    published_at DATETIME NULL,
                    created_at DATETIME DEFAULT {$nowFunc},
                    updated_at DATETIME DEFAULT {$nowFunc}
                )
            ");

            // Seed default SEO pages
            $defaultPages = [
                [
                    'route_path' => '/',
                    'page_name' => 'Homepage',
                    'seo_title' => 'Gmail Auto Reply & Follow-up Automation Software',
                    'meta_description' => 'Scale your outreach and customer response time with official Gmail API auto reply and intelligent multi-step follow-up automation.',
                    'focus_keyword' => 'Gmail auto reply',
                    'secondary_keywords' => 'Gmail automation, email follow up software, automated email replies',
                    'schema_type' => 'SoftwareApplication',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/features',
                    'page_name' => 'Features',
                    'seo_title' => 'Features — Gmail Auto Reply, Follow-ups & Automation Rules',
                    'meta_description' => 'Explore all features: multi-step replies, sequential follow-ups, blacklist filtering, working hour scheduling, and duplicate traffic protection.',
                    'focus_keyword' => 'Gmail automation features',
                    'secondary_keywords' => 'email filters, sequential followups, Gmail API tool',
                    'schema_type' => 'WebPage',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/pricing',
                    'page_name' => 'Pricing & Plans',
                    'seo_title' => 'Pricing Plans — Transparent Gmail Automation Software',
                    'meta_description' => 'Choose the ideal plan for your business. Starter at $50/mo (100 Gmail accounts) or Professional at $100/mo (250 accounts). Free 7-day trial available.',
                    'focus_keyword' => 'Gmail automation pricing',
                    'secondary_keywords' => 'email marketing plans, multi-account Gmail software',
                    'schema_type' => 'Product',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/how-it-works',
                    'page_name' => 'How It Works',
                    'seo_title' => 'How It Works — Effortless Gmail API Automation Setup',
                    'meta_description' => 'Learn how simple it is to connect your Gmail accounts via official Google OAuth, configure custom replies, and let our 24/7 worker handle the rest.',
                    'focus_keyword' => 'how Gmail automation works',
                    'secondary_keywords' => 'Gmail OAuth integration, auto reply setup',
                    'schema_type' => 'HowTo',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/faq',
                    'page_name' => 'Frequently Asked Questions',
                    'seo_title' => 'Frequently Asked Questions (FAQ) — Gmail Automation',
                    'meta_description' => 'Find answers to common questions regarding account safety, Gmail limits, OAuth permissions, pricing, billing, and follow-up campaign rules.',
                    'focus_keyword' => 'Gmail automation FAQ',
                    'secondary_keywords' => 'Gmail API questions, auto reply help',
                    'schema_type' => 'FAQPage',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/contact',
                    'page_name' => 'Contact Us',
                    'seo_title' => 'Contact Support — Gmail Automation Team',
                    'meta_description' => 'Get in touch with our technical support team for agency limits, custom enterprise integrations, or setup assistance.',
                    'focus_keyword' => 'contact Gmail automation',
                    'secondary_keywords' => 'customer support, enterprise email automation',
                    'schema_type' => 'ContactPage',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/blog',
                    'page_name' => 'Blog & Guides',
                    'seo_title' => 'Blog & Automation Guides — Gmail Auto Reply Insights',
                    'meta_description' => 'Read our latest expert articles, deliverability best practices, email follow-up templates, and productivity guides.',
                    'focus_keyword' => 'Gmail productivity blog',
                    'secondary_keywords' => 'email follow up guides, automation best practices',
                    'schema_type' => 'CollectionPage',
                    'is_indexable' => 1,
                ],
                [
                    'route_path' => '/login',
                    'page_name' => 'Sign In',
                    'seo_title' => 'Sign In — Gmail Automation Portal',
                    'meta_description' => 'Access your Gmail automation dashboard.',
                    'focus_keyword' => 'login',
                    'secondary_keywords' => '',
                    'schema_type' => 'WebPage',
                    'is_indexable' => 0,
                ],
                [
                    'route_path' => '/register',
                    'page_name' => 'Create Account',
                    'seo_title' => 'Create Account — 7-Day Free Trial',
                    'meta_description' => 'Start your 7-day free trial today.',
                    'focus_keyword' => 'register',
                    'secondary_keywords' => '',
                    'schema_type' => 'WebPage',
                    'is_indexable' => 0,
                ],
            ];

            foreach ($defaultPages as $dp) {
                try {
                    $exists = Database::first("SELECT id FROM seo_pages WHERE route_path = :rp LIMIT 1", ['rp' => $dp['route_path']]);
                    if (!$exists) {
                        Database::execute("
                            INSERT INTO seo_pages (route_path, page_name, seo_title, meta_description, focus_keyword, secondary_keywords, is_indexable, schema_type, created_at)
                            VALUES (:rp, :pn, :st, :md, :fk, :sk, :idx, :stype, {$nowFunc})
                        ", [
                            'rp' => $dp['route_path'],
                            'pn' => $dp['page_name'],
                            'st' => $dp['seo_title'],
                            'md' => $dp['meta_description'],
                            'fk' => $dp['focus_keyword'],
                            'sk' => $dp['secondary_keywords'],
                            'idx' => $dp['is_indexable'],
                            'stype' => $dp['schema_type'],
                        ]);
                    }
                } catch (\Throwable $t) {}
            }

            // Seed default FAQs
            $defaultFaqs = [
                [
                    'q' => 'What is Gmail Auto Reply & Follow-up Automation?',
                    'a' => 'It is a cloud-based SaaS platform connecting with your Gmail accounts via official Google OAuth APIs to send automated sequential replies and smart follow-up campaigns without requiring your computer to stay on.',
                    'cat' => 'Product Overview',
                    'order' => 1
                ],
                [
                    'q' => 'How does the Duplicate Traffic Protection work?',
                    'a' => 'Our engine ensures that multiple incoming emails from the same sender/lead trigger only one initial Auto Reply per connected account. Additional incoming emails are recognized as duplicate traffic and skipped.',
                    'cat' => 'Features',
                    'order' => 2
                ],
                [
                    'q' => 'How does the 1-Per-Conversation Follow-up Quota work?',
                    'a' => 'Sending multiple sequential follow-ups (e.g. Step 1, Step 2, Step 3) to a single conversation counts as only 1 Daily Follow toward your plan quota, maximizing your outreach capacity.',
                    'cat' => 'Features',
                    'order' => 3
                ],
                [
                    'q' => 'What happens when a recipient replies to a follow-up email?',
                    'a' => 'Our 24/7 background worker automatically detects the incoming reply in the Gmail thread, marks the campaign as Replied, and immediately stops any remaining scheduled follow-up steps.',
                    'cat' => 'Features',
                    'order' => 4
                ],
                [
                    'q' => 'What pricing plans are available?',
                    'a' => 'We offer the Starter Plan at $50/month (supporting up to 100 connected Gmail accounts) and the Professional Plan at $100/month (supporting up to 250 connected Gmail accounts). Both include full 24/7 queue processing.',
                    'cat' => 'Billing',
                    'order' => 5
                ],
                [
                    'q' => 'Is there a free trial available?',
                    'a' => 'Yes! Every new user can activate a 7-Day Free Trial with zero credit card required to connect Gmail accounts and experience real automation before subscribing.',
                    'cat' => 'Billing',
                    'order' => 6
                ],
            ];

            foreach ($defaultFaqs as $df) {
                try {
                    $fExists = Database::first("SELECT id FROM seo_faqs WHERE question = :q LIMIT 1", ['q' => $df['q']]);
                    if (!$fExists) {
                        Database::execute("
                            INSERT INTO seo_faqs (question, answer, category, sort_order, is_active, created_at)
                            VALUES (:q, :a, :c, :ord, 1, {$nowFunc})
                        ", [
                            'q' => $df['q'],
                            'a' => $df['a'],
                            'c' => $df['cat'],
                            'ord' => $df['order']
                        ]);
                    }
                } catch (\Throwable $t) {}
            }

            // Seed default Blog Posts
            $defaultBlogs = [
                [
                    'title' => 'How to Automate Gmail Replies and Follow-ups Effortlessly in 2026',
                    'slug' => 'how-to-automate-gmail-replies-and-followups',
                    'excerpt' => 'Discover how modern businesses use official Gmail API automation to reduce lead response time to seconds and automate 5-step follow-up campaigns.',
                    'content' => '<p>In modern sales and customer support, response speed is the single most critical factor determining lead conversion rates. Studies consistently show that responding within 5 minutes increases lead qualification by up to 21x.</p><h2>The Challenge with Traditional Email Tools</h2><p>Desktop extensions and browser plug-ins require your computer to remain awake, consume significant local RAM, and frequently break when browser updates occur. Cloud-native Gmail automation solves this by executing 24/7 background queue workers directly communicating with Google Cloud APIs.</p><h2>Setting Up Smart Multi-Step Sequences</h2><p>With sequential follow-up automation, you can configure personalized message steps spaced by custom delays (hours or days). Crucially, the moment a prospect replies, our system halts subsequent follow-ups automatically.</p>',
                    'author' => 'Automation Team',
                    'cat' => 'Guides',
                    'seo_title' => 'How to Automate Gmail Replies & Follow-ups in 2026',
                    'meta_desc' => 'Complete guide on scaling your outreach with cloud-native Gmail API auto replies and intelligent follow-up campaign workflows.',
                    'focus_kw' => 'automate Gmail replies'
                ],
                [
                    'title' => '5 Best Practices for Managing Multi-Account Gmail Automation',
                    'slug' => 'best-practices-multi-account-gmail-automation',
                    'excerpt' => 'Learn how to safely scale email outreach across 100+ Gmail accounts while maintaining pristine sender reputation and duplicate protection.',
                    'content' => '<p>Managing multiple Gmail accounts across different sales reps, brands, or service divisions requires robust centralized controls.</p><h2>1. Enforce Traffic Deduplication</h2><p>Ensure that an incoming lead sending emails to multiple accounts only receives a single auto-reply per account to avoid customer annoyance.</p><h2>2. Respect Natural Working Hours</h2><p>Align auto-reply and follow-up dispatch times with regional working hours and business days for maximum credibility and deliverability.</p><h2>3. Monitor Live Deliverability Metrics</h2><p>Track reply counts, queue status, and daily limits in real time from a centralized dashboard.</p>',
                    'author' => 'Deliverability Expert',
                    'cat' => 'Best Practices',
                    'seo_title' => '5 Best Practices for Multi-Account Gmail Automation',
                    'meta_desc' => 'Essential strategies for managing and scaling email automation across dozens of connected Gmail inboxes safely.',
                    'focus_kw' => 'multi-account Gmail automation'
                ]
            ];

            foreach ($defaultBlogs as $db) {
                try {
                    $bExists = Database::first("SELECT id FROM blog_posts WHERE slug = :s LIMIT 1", ['s' => $db['slug']]);
                    if (!$bExists) {
                        Database::execute("
                            INSERT INTO blog_posts (title, slug, excerpt, content, author_name, category, seo_title, meta_description, focus_keyword, status, published_at, created_at)
                            VALUES (:t, :s, :ex, :cnt, :auth, :cat, :st, :md, :fk, 'published', {$nowFunc}, {$nowFunc})
                        ", [
                            't' => $db['title'],
                            's' => $db['slug'],
                            'ex' => $db['excerpt'],
                            'cnt' => $db['content'],
                            'auth' => $db['author'],
                            'cat' => $db['cat'],
                            'st' => $db['seo_title'],
                            'md' => $db['meta_desc'],
                            'fk' => $db['focus_kw'],
                        ]);
                    }
                } catch (\Throwable $t) {}
            }

        } catch (\Throwable $e) {
            // Silently ignore if database error
        }
    }

    /**
     * Inspect table columns and add any missing columns safely across MySQL 5.7+, MariaDB, and SQLite
     */
    public static function ensureTableColumns(string $table, array $columns): void {
        $driver = config('database.default', 'mysql');
        try {
            if ($driver === 'mysql') {
                $rows = Database::query("SHOW COLUMNS FROM `{$table}`");
                $existingCols = array_map(fn($r) => strtolower($r['Field'] ?? $r['field'] ?? ''), $rows);
                foreach ($columns as $col => $type) {
                    if (!in_array(strtolower($col), $existingCols)) {
                        try {
                            Database::execute("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type}");
                        } catch (\Throwable $t) {}
                    }
                }
            } else {
                $rows = Database::query("PRAGMA table_info(`{$table}`)");
                $existingCols = array_map(fn($r) => strtolower($r['name'] ?? ''), $rows);
                foreach ($columns as $col => $type) {
                    if (!in_array(strtolower($col), $existingCols)) {
                        try {
                            Database::execute("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type}");
                        } catch (\Throwable $t) {}
                    }
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Safely upgrade existing column data types on MySQL/MariaDB only if they differ from the desired definition
     */
    public static function ensureTableColumnTypes(string $table, array $columnTypes): void {
        $driver = config('database.default', 'mysql');
        if ($driver !== 'mysql') {
            return;
        }

        try {
            $rows = Database::query("SHOW COLUMNS FROM `{$table}`");
            $existingCols = [];
            foreach ($rows as $r) {
                $colName = strtolower($r['Field'] ?? $r['field'] ?? '');
                $colType = strtolower($r['Type'] ?? $r['type'] ?? '');
                $existingCols[$colName] = $colType;
            }

            foreach ($columnTypes as $col => $desiredDefinition) {
                $colLower = strtolower($col);
                if (isset($existingCols[$colLower])) {
                    $currentType = $existingCols[$colLower];
                    // Skip ALTER TABLE if the column is already longtext
                    if (str_starts_with(strtolower($desiredDefinition), 'longtext') && $currentType === 'longtext') {
                        continue;
                    }
                    try {
                        Database::execute("ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` {$desiredDefinition}");
                    } catch (\Throwable $t) {}
                }
            }
        } catch (\Throwable $e) {}
    }
}

