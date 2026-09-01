<?php
namespace Database;

use App\Core\Database;
use App\Models\User;
use App\Models\SystemSetting;

class SeedData {
    public static function run(): void {
        // Create default Admin User if not exists
        $admin = User::findByEmail('admin@example.com');
        if (!$admin) {
            User::create([
                'name' => 'System Admin',
                'email' => 'admin@example.com',
                'password' => password_hash('Admin@123456', PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
            ]);
        }

        // Initialize default system settings
        $defaults = [
            'app_name' => 'Gmail Auto Reply & Follow-up Automation',
            'google_client_id' => '',
            'google_client_secret' => '',
            'google_redirect_uri' => 'http://localhost:8000/auth/google/callback',
            'google_pubsub_topic' => '',
            'google_pubsub_token' => '',
            'global_automation_enabled' => '1',
            'cron_last_run' => '',
            // Trial settings
            'trial_enabled' => '1',
            'trial_duration_days' => '14',
            'trial_gmail_limit' => '5',
            'trial_one_per_user' => '1',
            // SMTP settings
            'smtp_enabled' => '0',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_name' => 'Gmail Automation',
            'smtp_from_email' => 'support@2xbets.net',
            // Stripe Gateway
            'stripe_enabled' => '1',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            // bKash Gateway (Personal Number & API)
            'bkash_enabled' => '1',
            'bkash_type' => 'manual_number', // manual_number or merchant_api
            'bkash_number' => '01611195794',
            'bkash_account_type' => 'Personal', // Personal, Agent, Merchant
            'bkash_exchange_rate' => '120', // 1 USD = 120 BDT
            'bkash_instructions' => 'Send Money to bKash Personal Number: 01611195794. Enter your Sender Phone Number & Transaction ID (TrxID) below to submit verification.',
            'bkash_app_key' => '',
            'bkash_app_secret' => '',
            'bkash_username' => '',
            'bkash_password' => '',
            // Nagad Gateway (Personal Number & API)
            'nagad_enabled' => '1',
            'nagad_type' => 'manual_number', // manual_number or merchant_api
            'nagad_number' => '01611195794',
            'nagad_account_type' => 'Personal', // Personal, Agent, Merchant
            'nagad_exchange_rate' => '120', // 1 USD = 120 BDT
            'nagad_instructions' => 'Send Money to Nagad Personal Number: 01611195794. Enter your Sender Phone Number & Transaction ID (TrxID) below to submit verification.',
            'nagad_merchant_id' => '',
            'nagad_public_key' => '',
            'nagad_private_key' => '',
        ];

        foreach ($defaults as $key => $val) {
            $existing = SystemSetting::get($key);
            if ($existing === null) {
                SystemSetting::set($key, $val);
            }
        }

        // Seed Plans
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 50.00,
                'billing_period' => 'monthly',
                'gmail_limit' => 100,
                'features' => json_encode([
                    'Up to 100 Gmail accounts',
                    'Automatic replies & sequential steps',
                    'Custom reply & follow-up messages',
                    'Smart scheduling & working hours',
                    'Daily & per-conversation limits',
                    'Reusable templates with variables',
                    'Automation & condition rules',
                    'Gmail thread preservation',
                    'Real-time analytics dashboard',
                    '24/7 server-side queue processing',
                    'Email notifications',
                ]),
                'is_popular' => 0,
                'is_active' => 1,
                'display_order' => 1,
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'price' => 100.00,
                'billing_period' => 'monthly',
                'gmail_limit' => 250,
                'features' => json_encode([
                    'Up to 250 Gmail accounts',
                    'Automatic replies & sequential steps',
                    'Custom reply & follow-up messages',
                    'Smart scheduling & working hours',
                    'Daily & per-conversation limits',
                    'Reusable templates with variables',
                    'Automation & condition rules',
                    'Gmail thread preservation',
                    'Real-time analytics dashboard',
                    '24/7 server-side queue processing',
                    'Email notifications',
                    'Priority background queue & support',
                ]),
                'is_popular' => 1,
                'is_active' => 1,
                'display_order' => 2,
            ],
        ];

        foreach ($plans as $p) {
            $exists = Database::first("SELECT id FROM plans WHERE slug = :slug", ['slug' => $p['slug']]);
            if (!$exists) {
                $driver = config('database.default', 'mysql');
                $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
                Database::execute(
                    "INSERT INTO plans (slug, name, price, billing_period, gmail_limit, features, is_popular, is_active, display_order, created_at) 
                     VALUES (:slug, :name, :price, :period, :limit, :feat, :pop, :act, :ord, {$now})",
                    [
                        'slug' => $p['slug'],
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'period' => $p['billing_period'],
                        'limit' => $p['gmail_limit'],
                        'feat' => $p['features'],
                        'pop' => $p['is_popular'],
                        'act' => $p['is_active'],
                        'ord' => $p['display_order'],
                    ]
                );
            }
        }

        // Seed Email Templates
        $emailTemplates = [
            [
                'slug' => 'welcome',
                'name' => 'Welcome / Account Created',
                'subject' => 'Welcome to {{site_name}}, {{name}}!',
                'body' => '<h2>Welcome to {{site_name}}!</h2><p>Hi {{name}},</p><p>Thank you for creating an account with our Gmail Automation Platform. Your account has been registered with <strong>{{email}}</strong>.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Go to Dashboard</a></p><p>If you need any assistance, contact our support team at {{support_email}}.</p>',
            ],
            [
                'slug' => 'email_verification',
                'name' => 'Email Verification',
                'subject' => 'Verify Your Email Address - {{site_name}}',
                'body' => '<h2>Verify Your Email</h2><p>Hi {{name}},</p><p>Please verify your email address to activate all features of your {{site_name}} account.</p><p><a href="{{verification_url}}" style="display:inline-block;padding:12px 24px;background:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Verify Email Address</a></p><p>If you did not create this account, no further action is required.</p>',
            ],
            [
                'slug' => 'trial_started',
                'name' => 'Free Trial Started',
                'subject' => 'Your Free Trial Has Started - {{site_name}}',
                'body' => '<h2>Your {{trial_days}}-Day Free Trial is Active!</h2><p>Hi {{name}},</p><p>Your free trial has started on {{start_date}} and will remain active until <strong>{{expiry_date}}</strong>.</p><p>You can connect up to <strong>{{gmail_limit}} Gmail account(s)</strong> with conversational auto-replies, smart follow-ups, and queue automation.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Access Your Dashboard</a></p>',
            ],
            [
                'slug' => 'trial_expiring',
                'name' => 'Free Trial Expiring Reminder',
                'subject' => 'Reminder: Your Free Trial is Ending Soon',
                'body' => '<h2>Your Free Trial Ends on {{expiry_date}}</h2><p>Hi {{name}},</p><p>This is a reminder that your free trial for {{site_name}} will expire in {{days_left}} day(s) on <strong>{{expiry_date}}</strong>.</p><p>Upgrade your plan now to keep your email automation and follow-ups running uninterrupted.</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#f59e0b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Upgrade Plan Now</a></p>',
            ],
            [
                'slug' => 'trial_expired',
                'name' => 'Free Trial Expired',
                'subject' => 'Your Free Trial Has Expired - {{site_name}}',
                'body' => '<h2>Your Free Trial Has Ended</h2><p>Hi {{name}},</p><p>Your free trial for {{site_name}} expired on <strong>{{expiry_date}}</strong>.</p><p>To reactivate your automated replies, follow-ups, and connected accounts, please select a subscription plan.</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Choose a Subscription Plan</a></p>',
            ],
            [
                'slug' => 'payment_submitted',
                'name' => 'Payment Submitted (Pending Review)',
                'subject' => 'Payment Submitted: {{plan_name}} Plan (Pending Verification)',
                'body' => '<h2>Your Payment Has Been Submitted</h2><p>Hi {{name}},</p><p>We received your subscription payment request. Our team will verify the transaction details shortly.</p><ul><li><strong>Package:</strong> {{plan_name}}</li><li><strong>Amount:</strong> {{amount}}</li><li><strong>Gateway:</strong> {{gateway}}</li><li><strong>Transaction ID (TrxID):</strong> {{transaction_id}}</li><li><strong>Status:</strong> Pending Review</li></ul><p>Your subscription will be activated immediately once verified.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">View Dashboard</a></p>',
            ],
            [
                'slug' => 'admin_payment_alert',
                'name' => 'Admin Notification: New Payment Submitted',
                'subject' => '[Admin Alert] New Payment Verification Required - {{plan_name}}',
                'body' => '<h2>New Payment Submitted for Verification</h2><p>A user has submitted a payment on {{site_name}}:</p><ul><li><strong>User:</strong> {{name}} ({{email}})</li><li><strong>Plan:</strong> {{plan_name}}</li><li><strong>Amount:</strong> {{amount}}</li><li><strong>Gateway:</strong> {{gateway}}</li><li><strong>Sender Number:</strong> {{sender_number}}</li><li><strong>Transaction ID:</strong> {{transaction_id}}</li></ul><p><a href="{{review_url}}" style="display:inline-block;padding:12px 24px;background:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Review & Approve in Admin Panel</a></p>',
            ],
            [
                'slug' => 'payment_approved',
                'name' => 'Payment Approved / Package Activated',
                'subject' => 'Payment Approved — {{plan_name}} Package Activated!',
                'body' => '<h2>Payment Confirmed & Package Activated!</h2><p>Hi {{name}},</p><p>Great news! Your payment has been approved and your <strong>{{plan_name}} Plan</strong> is now active.</p><ul><li><strong>Package:</strong> {{plan_name}}</li><li><strong>Price:</strong> ${{plan_price}}</li><li><strong>Gmail Accounts Limit:</strong> {{gmail_limit}} accounts</li><li><strong>Start Date:</strong> {{start_date}}</li><li><strong>Expiry / Renewal Date:</strong> {{expiry_date}}</li><li><strong>Transaction ID:</strong> {{transaction_id}}</li></ul><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Go to Dashboard</a></p>',
            ],
            [
                'slug' => 'payment_rejected',
                'name' => 'Payment Rejected',
                'subject' => 'Payment Verification Issue — {{site_name}}',
                'body' => '<h2>Payment Verification Unsuccessful</h2><p>Hi {{name}},</p><p>We could not verify your payment submission for the <strong>{{plan_name}} Plan</strong>.</p><p><strong>Reason:</strong> {{rejection_reason}}</p><p><strong>Submitted TrxID:</strong> {{transaction_id}} ({{gateway}})</p><p>You can re-submit your transaction details or choose a different payment method below:</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#ef4444;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Re-submit Payment</a></p><p>If you have any questions, please contact us at {{support_email}}.</p>',
            ],
            [
                'slug' => 'package_expiring',
                'name' => 'Subscription Renewal / Expiring Reminder',
                'subject' => 'Important: Your Subscription is Expiring in {{days_left}} Day(s)',
                'body' => '<h2>Subscription Expiration Notice</h2><p>Hi {{name}},</p><p>Your <strong>{{plan_name}}</strong> subscription is scheduled to expire on <strong>{{expiry_date}}</strong>.</p><p>To ensure uninterrupted email auto-reply and follow-up sequences for your connected accounts, please renew your subscription:</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Renew Subscription</a></p>',
            ],
            [
                'slug' => 'package_expired',
                'name' => 'Subscription Expired',
                'subject' => 'Your Package Has Expired — {{site_name}}',
                'body' => '<h2>Your Package Has Expired</h2><p>Hi {{name}},</p><p>Your <strong>{{plan_name}}</strong> subscription expired on <strong>{{expiry_date}}</strong>.</p><p>Your automated replies and follow-up jobs have been paused. You can renew or upgrade at any time to resume automation instantly:</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Renew / Upgrade Plan</a></p>',
            ],
            [
                'slug' => 'account_suspended',
                'name' => 'Account Suspended',
                'subject' => 'Your Account Has Been Suspended — {{site_name}}',
                'body' => '<h2>Account Suspension Notice</h2><p>Hi {{name}},</p><p>Your {{site_name}} account has been suspended on {{suspension_date}}.</p><p><strong>Reason:</strong> {{suspension_reason}}</p><p>If you believe this is a mistake, please contact our support team at {{support_email}}.</p>',
            ],
            [
                'slug' => 'account_reactivated',
                'name' => 'Account Reactivated',
                'subject' => 'Your Account Has Been Reactivated — {{site_name}}',
                'body' => '<h2>Account Reactivated</h2><p>Hi {{name}},</p><p>Your {{site_name}} account has been restored and reactivated on {{reactivation_date}}.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Sign In to Dashboard</a></p>',
            ],
            [
                'slug' => 'account_deleted',
                'name' => 'Account Deleted',
                'subject' => 'Your Account Has Been Deleted — {{site_name}}',
                'body' => '<h2>Account Deleted</h2><p>Hi {{name}},</p><p>This is to confirm that your {{site_name}} account ({{email}}) was deleted on {{deletion_date}}.</p><p><strong>Reason:</strong> {{deletion_reason}}</p><p>Thank you for using {{site_name}}.</p>',
            ],
            [
                'slug' => 'password_reset',
                'name' => 'Password Reset Request',
                'subject' => 'Password Reset Request — {{site_name}}',
                'body' => '<h2>Password Reset Request</h2><p>Hi {{name}},</p><p>We received a request to reset your password. Click the link below to set a new password:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#ef4444;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Reset Password</a></p><p>If you did not request this change, please ignore this email.</p>',
            ],
            [
                'slug' => 'subscription_cancelled',
                'name' => 'Subscription Cancelled',
                'subject' => 'Subscription Cancelled — {{site_name}}',
                'body' => '<h2>Subscription Cancelled</h2><p>Hi {{name}},</p><p>Your subscription for the <strong>{{plan_name}}</strong> has been cancelled on {{cancellation_date}}.</p><p>You will retain access until the end of your current billing period.</p>',
            ],
        ];

        foreach ($emailTemplates as $tpl) {
            $exists = Database::first("SELECT id FROM email_templates WHERE slug = :slug", ['slug' => $tpl['slug']]);
            if (!$exists) {
                $driver = config('database.default', 'mysql');
                $now = date('Y-m-d H:i:s');
                Database::execute(
                    "INSERT INTO email_templates (slug, name, subject, body, is_enabled, created_at) 
                     VALUES (:slug, :name, :subject, :body, 1, :now)",
                    [
                        'slug' => $tpl['slug'],
                        'name' => $tpl['name'],
                        'subject' => $tpl['subject'],
                        'body' => $tpl['body'],
                        'now' => $now,
                    ]
                );
            }
        }

        // Initialize Global SEO Settings
        $seoDefaults = [
            'site_name' => 'Gmail Auto Reply & Follow-up',
            'site_url' => 'http://localhost:8000',
            'default_title' => 'Gmail Auto Reply & Follow-up Automation Software',
            'default_description' => 'Scale your outreach and customer response time with official Gmail API auto reply and intelligent multi-step follow-up automation.',
            'default_keywords' => 'Gmail auto reply, Gmail automation, email follow up software, automated email replies',
            'organization_name' => 'Gmail Automation Platform',
            'support_email' => 'support@2xbets.net',
            'support_phone' => '+8801611195794',
            'organization_address' => 'Dhaka, Bangladesh',
        ];

        foreach ($seoDefaults as $key => $val) {
            $exists = Database::first("SELECT id FROM seo_settings WHERE setting_key = :k LIMIT 1", ['k' => $key]);
            if (!$exists) {
                Database::execute(
                    "INSERT INTO seo_settings (setting_key, setting_value, created_at, updated_at) 
                     VALUES (:k, :v, :now, :now)",
                    ['k' => $key, 'v' => $val, 'now' => date('Y-m-d H:i:s')]
                );
            }
        }

        // Initialize Default SEO Pages
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
            $exists = Database::first("SELECT id FROM seo_pages WHERE route_path = :rp LIMIT 1", ['rp' => $dp['route_path']]);
            if (!$exists) {
                Database::execute("
                    INSERT INTO seo_pages (route_path, page_name, seo_title, meta_description, focus_keyword, secondary_keywords, is_indexable, is_followable, schema_type, created_at, updated_at)
                    VALUES (:rp, :pn, :st, :md, :fk, :sk, :idx, 1, :stype, :now, :now)
                ", [
                    'rp' => $dp['route_path'],
                    'pn' => $dp['page_name'],
                    'st' => $dp['seo_title'],
                    'md' => $dp['meta_description'],
                    'fk' => $dp['focus_keyword'],
                    'sk' => $dp['secondary_keywords'],
                    'idx' => $dp['is_indexable'],
                    'stype' => $dp['schema_type'],
                    'now' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Initialize FAQs
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
            $fExists = Database::first("SELECT id FROM seo_faqs WHERE question = :q LIMIT 1", ['q' => $df['q']]);
            if (!$fExists) {
                Database::execute("
                    INSERT INTO seo_faqs (question, answer, category, sort_order, is_active, created_at, updated_at)
                    VALUES (:q, :a, :c, :ord, 1, :now, :now)
                ", [
                    'q' => $df['q'],
                    'a' => $df['a'],
                    'c' => $df['cat'],
                    'ord' => $df['order'],
                    'now' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Initialize Blog Posts
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
            $bExists = Database::first("SELECT id FROM blog_posts WHERE slug = :s LIMIT 1", ['s' => $db['slug']]);
            if (!$bExists) {
                Database::execute("
                    INSERT INTO blog_posts (title, slug, excerpt, content, author_name, category, seo_title, meta_description, focus_keyword, status, published_at, created_at, updated_at)
                    VALUES (:t, :s, :ex, :cnt, :auth, :cat, :st, :md, :fk, 'published', :now, :now, :now)
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
                    'now' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
