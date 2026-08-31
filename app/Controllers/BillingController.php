<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\GmailAccount;
use App\Models\EmailJob;
use App\Models\SystemSetting;
use App\Services\StripeService;
use Exception;

class BillingController {
    public function index(Request $request): string {
        $user = Auth::user();
        $plans = Plan::getActivePlans();
        $payments = Payment::findByUserId($user->id, 20);
        $connectedAccountsCount = count(GmailAccount::findByUserId($user->id));

        $trialEnabled = (bool)(int)SystemSetting::get('trial_enabled', '1');
        $trialDays = (int)SystemSetting::get('trial_duration_days', '14');
        $trialLimit = (int)SystemSetting::get('trial_gmail_limit', '5');

        $stripeKey = SystemSetting::get('stripe_publishable_key', '');

        return View::render('billing/index', [
            'user' => $user,
            'plans' => $plans,
            'payments' => $payments,
            'connectedAccountsCount' => $connectedAccountsCount,
            'trialEnabled' => $trialEnabled,
            'trialDays' => $trialDays,
            'trialLimit' => $trialLimit,
            'stripeKey' => $stripeKey,
        ]);
    }

    /**
     * Start Free Trial
     */
    public function startTrial(Request $request): void {
        $user = Auth::user();

        if (!$user->canStartTrial()) {
            flash('warning', 'You are not eligible for a free trial or have already used your trial.');
            redirect('/billing');
        }

        $trialDays = (int)SystemSetting::get('trial_duration_days', '14');
        $trialLimit = (int)SystemSetting::get('trial_gmail_limit', '5');

        $ok = $user->startTrial($trialDays, $trialLimit);

        if ($ok) {
            flash('success', "Your {$trialDays}-day free trial has been activated! You can connect up to {$trialLimit} Gmail accounts.");
            
            // Dispatch Free Trial Started Email Notification
            $eventKey = "trial_started:{$user->id}";
            EmailJob::dispatchTemplate('trial_started', $user->email, [
                'name' => $user->name,
                'trial_days' => $trialDays,
                'gmail_limit' => $trialLimit,
                'start_date' => date('d M Y'),
                'expiry_date' => date('d M Y', strtotime("+{$trialDays} days")),
            ], $eventKey, $user->id, $user->name);

            logger("User {$user->email} started {$trialDays}-day free trial", 'info', $user->id);
        } else {
            flash('error', 'Unable to start trial. Please contact support.');
        }

        redirect('/billing');
    }

    /**
     * Initiate Stripe Checkout Session
     */
    public function checkout(Request $request, int $planId): void {
        $user = Auth::user();
        $plan = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            flash('error', 'Invalid subscription plan selected.');
            redirect('/billing');
        }

        try {
            $session = StripeService::createCheckoutSession($user, $plan);
            header("Location: " . $session['checkout_url']);
            exit;
        } catch (\Throwable $e) {
            flash('error', 'Payment gateway error: ' . $e->getMessage());
            redirect('/billing');
        }
    }
}
