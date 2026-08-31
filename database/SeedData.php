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
                'subject' => 'Welcome to Gmail Automation, {{name}}!',
                'body' => '<h2>Welcome to Gmail Automation!</h2><p>Hi {{name}},</p><p>Thank you for creating an account with Gmail Automation Platform. Your account has been registered with <strong>{{email}}</strong>.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Go to Dashboard</a></p><p>If you need any assistance, contact our support team at {{support_email}}.</p>',
            ],
            [
                'slug' => 'email_verification',
                'name' => 'Email Verification',
                'subject' => 'Verify Your Email Address - Gmail Automation',
                'body' => '<h2>Verify Your Email</h2><p>Hi {{name}},</p><p>Please verify your email address to activate your Gmail Automation account.</p><p><a href="{{verification_url}}" style="display:inline-block;padding:12px 24px;background:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Verify Email Address</a></p><p>If you did not create this account, no further action is required.</p>',
            ],
            [
                'slug' => 'trial_started',
                'name' => 'Free Trial Started',
                'subject' => 'Your Free Trial Has Started - Gmail Automation',
                'body' => '<h2>Your {{trial_days}}-Day Free Trial is Active!</h2><p>Hi {{name}},</p><p>Your free trial has started on {{start_date}} and will be active until <strong>{{expiry_date}}</strong>.</p><p>You can connect up to <strong>{{gmail_limit}} Gmail account(s)</strong> and experience full conversational auto-replies, smart follow-ups, and queue automation.</p><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Access Your Dashboard</a></p>',
            ],
            [
                'slug' => 'trial_expiring',
                'name' => 'Free Trial Expiring Reminder',
                'subject' => 'Reminder: Your Free Trial is Ending Soon',
                'body' => '<h2>Your Free Trial Ends on {{expiry_date}}</h2><p>Hi {{name}},</p><p>This is a friendly reminder that your free trial for Gmail Automation will expire in {{days_left}} day(s) on <strong>{{expiry_date}}</strong>.</p><p>Upgrade to a Starter or Professional plan now to keep your email automation and follow-ups running without interruption.</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#f59e0b;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Upgrade Plan Now</a></p>',
            ],
            [
                'slug' => 'trial_expired',
                'name' => 'Free Trial Expired',
                'subject' => 'Your Free Trial Has Expired - Gmail Automation',
                'body' => '<h2>Your Free Trial Has Ended</h2><p>Hi {{name}},</p><p>Your free trial for Gmail Automation expired on <strong>{{expiry_date}}</strong>.</p><p>To reactivate your automated replies, follow-ups, and connected accounts, please select a subscription plan.</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Choose a Subscription Plan</a></p>',
            ],
            [
                'slug' => 'purchase_confirmation',
                'name' => 'Package Purchase Confirmation',
                'subject' => 'Your Package Has Been Activated: {{plan_name}} Plan',
                'body' => '<h2>Payment Confirmed & Package Activated!</h2><p>Hi {{name}},</p><p>Thank you for subscribing to the <strong>{{plan_name}} Plan</strong> (${{plan_price}}/{{billing_period}}).</p><p><strong>Subscription Details:</strong></p><ul><li>Plan: {{plan_name}}</li><li>Price: ${{plan_price}}</li><li>Gmail Accounts Limit: {{gmail_limit}} accounts</li><li>Activation Date: {{start_date}}</li><li>Next Renewal / Expiry: {{renewal_date}}</li><li>Transaction ID: {{transaction_id}}</li></ul><p><a href="{{dashboard_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Open Dashboard</a></p>',
            ],
            [
                'slug' => 'package_expiring',
                'name' => 'Subscription Renewal / Expiring Reminder',
                'subject' => 'Important: Your Subscription is Expiring Soon',
                'body' => '<h2>Subscription Renewal Notice</h2><p>Hi {{name}},</p><p>Your <strong>{{plan_name}}</strong> subscription is scheduled to renew/expire on <strong>{{renewal_date}}</strong>.</p><p><a href="{{billing_url}}" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Manage Billing & Subscription</a></p>',
            ],
            [
                'slug' => 'password_reset',
                'name' => 'Password Reset Request',
                'subject' => 'Password Reset Request - Gmail Automation',
                'body' => '<h2>Password Reset</h2><p>Hi {{name}},</p><p>We received a request to reset your password. Click the link below to set a new password:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#ef4444;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Reset Password</a></p><p>If you did not request this, you can safely ignore this email.</p>',
            ],
        ];

        foreach ($emailTemplates as $tpl) {
            $exists = Database::first("SELECT id FROM email_templates WHERE slug = :slug", ['slug' => $tpl['slug']]);
            if (!$exists) {
                $driver = config('database.default', 'mysql');
                $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
                Database::execute(
                    "INSERT INTO email_templates (slug, name, subject, body, is_enabled, created_at) 
                     VALUES (:slug, :name, :subject, :body, 1, {$now})",
                    [
                        'slug' => $tpl['slug'],
                        'name' => $tpl['name'],
                        'subject' => $tpl['subject'],
                        'body' => $tpl['body'],
                    ]
                );
            }
        }
    }
}
