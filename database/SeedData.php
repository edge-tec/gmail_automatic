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
