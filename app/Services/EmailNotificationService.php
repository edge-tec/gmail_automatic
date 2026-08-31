<?php
namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\EmailJob;
use App\Models\SystemSetting;
use App\Core\Database;

class EmailNotificationService {
    /**
     * Get global variables accessible in all templates
     */
    public static function getGlobalVars(?User $user = null, array $extra = []): array {
        $fromEmail = SystemSetting::get('smtp_from_email', 'support@2xbets.net');
        $fromName = SystemSetting::get('smtp_from_name', 'Gmail Automation');

        $base = [
            'site_name' => $fromName,
            'support_email' => $fromEmail,
            'login_url' => url('/login'),
            'dashboard_url' => url('/dashboard'),
            'billing_url' => url('/billing'),
            'current_date' => date('d M Y'),
            'current_year' => date('Y'),
        ];

        if ($user) {
            $base['name'] = $user->name;
            $base['email'] = $user->email;
            $base['plan_type'] = ucfirst($user->plan_type ?? 'free');
            $base['gmail_limit'] = $user->getMaxGmailAccounts();
            $base['subscription_status'] = ucfirst($user->subscription_status ?? 'inactive');
            $base['trial_status'] = ucfirst($user->trial_status ?? 'not_started');
            $base['expiry_date'] = $user->subscription_expires_at ? date('d M Y', strtotime($user->subscription_expires_at)) : 'N/A';
            $base['trial_end'] = $user->trial_ends_at ? date('d M Y', strtotime($user->trial_ends_at)) : 'N/A';
        }

        return array_merge($base, $extra);
    }

    /**
     * 1. Account Created / Welcome Notification
     */
    public static function notifyAccountCreated(User $user): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'registration_date' => date('d M Y, H:i', strtotime($user->created_at ?? 'now')),
        ]);

        return EmailJob::dispatchTemplate(
            'welcome',
            $user->email,
            $vars,
            "welcome:{$user->id}",
            $user->id,
            $user->name
        );
    }

    /**
     * 2. Email Verification Notification
     */
    public static function notifyEmailVerification(User $user, string $verifyUrl): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'verification_url' => $verifyUrl,
        ]);

        return EmailJob::dispatchTemplate(
            'email_verification',
            $user->email,
            $vars,
            "verify:{$user->id}:" . substr(md5($verifyUrl), 0, 8),
            $user->id,
            $user->name
        );
    }

    /**
     * 3. Free Trial Started
     */
    public static function notifyTrialStarted(User $user, int $trialDays, int $gmailLimit): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'trial_days' => $trialDays,
            'gmail_limit' => $gmailLimit,
            'start_date' => date('d M Y'),
            'expiry_date' => date('d M Y', strtotime("+{$trialDays} days")),
            'trial_end' => date('d M Y', strtotime("+{$trialDays} days")),
        ]);

        return EmailJob::dispatchTemplate(
            'trial_started',
            $user->email,
            $vars,
            "trial_started:{$user->id}",
            $user->id,
            $user->name
        );
    }

    /**
     * 4. Free Trial Expiring Soon Reminder
     */
    public static function notifyTrialExpiring(User $user, int $daysLeft): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'days_left' => $daysLeft,
            'days_remaining' => $daysLeft,
            'expiry_date' => $user->trial_ends_at ? date('d M Y', strtotime($user->trial_ends_at)) : date('d M Y'),
        ]);

        $dateKey = date('Y-m-d');
        return EmailJob::dispatchTemplate(
            'trial_expiring',
            $user->email,
            $vars,
            "trial_expiring:{$user->id}:{$daysLeft}d:{$dateKey}",
            $user->id,
            $user->name
        );
    }

    /**
     * 5. Free Trial Expired
     */
    public static function notifyTrialExpired(User $user): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'expiry_date' => $user->trial_ends_at ? date('d M Y', strtotime($user->trial_ends_at)) : date('d M Y'),
        ]);

        return EmailJob::dispatchTemplate(
            'trial_expired',
            $user->email,
            $vars,
            "trial_expired:{$user->id}",
            $user->id,
            $user->name
        );
    }

    /**
     * 6. Payment Submitted (Pending Verification)
     */
    public static function notifyPaymentSubmitted(Payment $payment): void {
        $user = $payment->getUser();
        $plan = $payment->getPlan();
        if (!$user) return;

        $planName = $plan ? $plan->name : 'Subscription';
        $amountStr = '$' . number_format($payment->amount, 2) . ' USD';
        if ($payment->amount_bdt) {
            $amountStr .= " (৳ " . number_format($payment->amount_bdt, 2) . " BDT)";
        }

        $vars = self::getGlobalVars($user, [
            'plan_name' => $planName,
            'amount' => $amountStr,
            'plan_price' => number_format($payment->amount, 2),
            'gateway' => strtoupper($payment->gateway),
            'transaction_id' => $payment->transaction_id ?? $payment->stripe_session_id ?? 'N/A',
            'sender_number' => $payment->sender_number ?? 'N/A',
            'payment_status' => 'Pending Review',
            'payment_date' => date('d M Y, H:i', strtotime($payment->created_at)),
        ]);

        // Send to User
        EmailJob::dispatchTemplate(
            'payment_submitted',
            $user->email,
            $vars,
            "payment_submitted:{$payment->id}",
            $user->id,
            $user->name
        );

        // Send to Admin
        self::notifyAdminPaymentAlert($payment, $user, $planName, $amountStr);
    }

    /**
     * Admin Notification: New payment needs verification
     */
    public static function notifyAdminPaymentAlert(Payment $payment, User $user, string $planName, string $amountStr): void {
        $adminEmail = SystemSetting::get('admin_notification_email', SystemSetting::get('smtp_from_email', 'support@2xbets.net'));
        if (empty($adminEmail)) return;

        $vars = self::getGlobalVars($user, [
            'plan_name' => $planName,
            'amount' => $amountStr,
            'gateway' => strtoupper($payment->gateway),
            'transaction_id' => $payment->transaction_id ?? 'N/A',
            'sender_number' => $payment->sender_number ?? 'N/A',
            'review_url' => url('/admin/payments'),
        ]);

        EmailJob::dispatchTemplate(
            'admin_payment_alert',
            $adminEmail,
            $vars,
            "admin_payment_alert:{$payment->id}",
            null,
            'System Administrator'
        );
    }

    /**
     * 7. Payment Approved & Package Activated
     */
    public static function notifyPaymentApproved(Payment $payment): ?EmailJob {
        $user = $payment->getUser();
        $plan = $payment->getPlan();
        if (!$user) return null;

        $planName = $plan ? $plan->name : 'Subscription';
        $expiryDate = $user->subscription_expires_at ? date('d M Y', strtotime($user->subscription_expires_at)) : date('d M Y', strtotime('+1 month'));

        $vars = self::getGlobalVars($user, [
            'plan_name' => $planName,
            'plan_price' => number_format($payment->amount, 2),
            'billing_period' => $plan ? $plan->billing_period : 'monthly',
            'gmail_limit' => $plan ? $plan->gmail_limit : $user->getMaxGmailAccounts(),
            'start_date' => date('d M Y'),
            'renewal_date' => $expiryDate,
            'expiry_date' => $expiryDate,
            'transaction_id' => $payment->transaction_id ?? $payment->stripe_session_id ?? 'N/A',
            'payment_status' => 'Paid & Active',
        ]);

        return EmailJob::dispatchTemplate(
            'payment_approved',
            $user->email,
            $vars,
            "payment_approved:{$payment->id}",
            $user->id,
            $user->name
        );
    }

    /**
     * 8. Payment Rejected
     */
    public static function notifyPaymentRejected(Payment $payment, string $reason = ''): ?EmailJob {
        $user = $payment->getUser();
        $plan = $payment->getPlan();
        if (!$user) return null;

        $vars = self::getGlobalVars($user, [
            'plan_name' => $plan ? $plan->name : 'Subscription',
            'plan_price' => number_format($payment->amount, 2),
            'rejection_reason' => !empty($reason) ? $reason : 'Transaction verification failed. Invalid TrxID or sender amount.',
            'transaction_id' => $payment->transaction_id ?? 'N/A',
            'gateway' => strtoupper($payment->gateway),
            'payment_date' => date('d M Y, H:i', strtotime($payment->created_at)),
        ]);

        return EmailJob::dispatchTemplate(
            'payment_rejected',
            $user->email,
            $vars,
            "payment_rejected:{$payment->id}:" . time(),
            $user->id,
            $user->name
        );
    }

    /**
     * 9. Package Expiring Reminder
     */
    public static function notifyPackageExpiring(User $user, Plan $plan, int $daysLeft): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'plan_name' => $plan->name,
            'days_left' => $daysLeft,
            'days_remaining' => $daysLeft,
            'renewal_date' => $user->subscription_expires_at ? date('d M Y', strtotime($user->subscription_expires_at)) : date('d M Y'),
            'expiry_date' => $user->subscription_expires_at ? date('d M Y', strtotime($user->subscription_expires_at)) : date('d M Y'),
        ]);

        $dateKey = date('Y-m-d');
        return EmailJob::dispatchTemplate(
            'package_expiring',
            $user->email,
            $vars,
            "package_expiring:{$user->id}:{$daysLeft}d:{$dateKey}",
            $user->id,
            $user->name
        );
    }

    /**
     * 10. Package Expired
     */
    public static function notifyPackageExpired(User $user, ?Plan $plan = null): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'plan_name' => $plan ? $plan->name : 'Previous Subscription',
            'expiry_date' => $user->subscription_expires_at ? date('d M Y', strtotime($user->subscription_expires_at)) : date('d M Y'),
        ]);

        return EmailJob::dispatchTemplate(
            'package_expired',
            $user->email,
            $vars,
            "package_expired:{$user->id}:" . date('Y-m-d'),
            $user->id,
            $user->name
        );
    }

    /**
     * 11. Account Suspended
     */
    public static function notifyAccountSuspended(User $user, string $reason = ''): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'suspension_reason' => !empty($reason) ? $reason : 'Violation of Terms of Service or administrative review.',
            'suspension_date' => date('d M Y, H:i'),
        ]);

        return EmailJob::dispatchTemplate(
            'account_suspended',
            $user->email,
            $vars,
            "account_suspended:{$user->id}:" . time(),
            $user->id,
            $user->name
        );
    }

    /**
     * 12. Account Reactivated / Unsuspended
     */
    public static function notifyAccountReactivated(User $user): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'reactivation_date' => date('d M Y, H:i'),
        ]);

        return EmailJob::dispatchTemplate(
            'account_reactivated',
            $user->email,
            $vars,
            "account_reactivated:{$user->id}:" . time(),
            $user->id,
            $user->name
        );
    }

    /**
     * 13. Account Deleted (Sent before DB row removal)
     */
    public static function notifyAccountDeleted(User $user, string $reason = ''): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'deletion_reason' => !empty($reason) ? $reason : 'Account closed upon administrative request.',
            'deletion_date' => date('d M Y, H:i'),
        ]);

        return EmailJob::dispatchTemplate(
            'account_deleted',
            $user->email,
            $vars,
            "account_deleted:{$user->id}:" . time(),
            $user->id,
            $user->name
        );
    }

    /**
     * 14. Password Reset Notification
     */
    public static function notifyPasswordReset(User $user, string $resetUrl): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'reset_url' => $resetUrl,
        ]);

        return EmailJob::dispatchTemplate(
            'password_reset',
            $user->email,
            $vars,
            "password_reset:{$user->id}:" . substr(md5($resetUrl), 0, 8),
            $user->id,
            $user->name
        );
    }

    /**
     * 15. Subscription Cancelled
     */
    public static function notifySubscriptionCancelled(User $user, ?Plan $plan = null): ?EmailJob {
        $vars = self::getGlobalVars($user, [
            'plan_name' => $plan ? $plan->name : 'Subscription Plan',
            'cancellation_date' => date('d M Y, H:i'),
        ]);

        return EmailJob::dispatchTemplate(
            'subscription_cancelled',
            $user->email,
            $vars,
            "sub_cancelled:{$user->id}:" . time(),
            $user->id,
            $user->name
        );
    }

    /**
     * Background Cron Task: Check expiring trials & packages
     */
    public static function checkExpiringAndExpiredSubscriptions(): void {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // 1. Trial Expiring Reminders (3 days & 1 day before)
        $trialsExpiring = Database::query(
            "SELECT * FROM users 
             WHERE trial_status = 'active' 
             AND trial_ends_at IS NOT NULL 
             AND trial_ends_at > :now",
            ['now' => $now]
        );

        foreach ($trialsExpiring as $uData) {
            $user = User::fromRow($uData);
            $diffDays = (int)ceil((strtotime($user->trial_ends_at) - time()) / 86400);
            if ($diffDays === 3 || $diffDays === 1) {
                self::notifyTrialExpiring($user, $diffDays);
            }
        }

        // 2. Trial Expired
        $trialsExpired = Database::query(
            "SELECT * FROM users 
             WHERE trial_status = 'active' 
             AND trial_ends_at IS NOT NULL 
             AND trial_ends_at <= :now",
            ['now' => $now]
        );

        foreach ($trialsExpired as $uData) {
            $user = User::fromRow($uData);
            $user->update([
                'trial_status' => 'expired',
                'plan_type' => 'free',
                'gmail_limit' => 1,
            ]);
            self::notifyTrialExpired($user);
            logger("User #{$user->id} ({$user->email}) Free Trial expired.", 'info', $user->id);
        }

        // 3. Paid Subscription Expiring Reminders (7 days, 3 days, 1 day)
        $subsExpiring = Database::query(
            "SELECT * FROM users 
             WHERE subscription_status = 'active' 
             AND subscription_expires_at IS NOT NULL 
             AND subscription_expires_at > :now",
            ['now' => $now]
        );

        foreach ($subsExpiring as $uData) {
            $user = User::fromRow($uData);
            $diffDays = (int)ceil((strtotime($user->subscription_expires_at) - time()) / 86400);
            if ($diffDays === 7 || $diffDays === 3 || $diffDays === 1) {
                $plan = Plan::find((int)$user->plan_id) ?? Plan::findBySlug($user->plan_type ?? 'starter');
                if ($plan) {
                    self::notifyPackageExpiring($user, $plan, $diffDays);
                }
            }
        }

        // 4. Paid Subscription Expired
        $subsExpired = Database::query(
            "SELECT * FROM users 
             WHERE subscription_status = 'active' 
             AND subscription_expires_at IS NOT NULL 
             AND subscription_expires_at <= :now",
            ['now' => $now]
        );

        foreach ($subsExpired as $uData) {
            $user = User::fromRow($uData);
            $plan = Plan::find((int)$user->plan_id) ?? Plan::findBySlug($user->plan_type ?? 'starter');
            $user->update([
                'subscription_status' => 'expired',
                'plan_type' => 'free',
                'gmail_limit' => 1,
            ]);
            self::notifyPackageExpired($user, $plan);
            logger("User #{$user->id} ({$user->email}) Subscription expired.", 'warning', $user->id);
        }
    }
}
