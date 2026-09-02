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
use App\Services\EmailNotificationService;
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

        return View::render('billing/index', [
            'user' => $user,
            'plans' => $plans,
            'payments' => $payments,
            'connectedAccountsCount' => $connectedAccountsCount,
            'trialEnabled' => $trialEnabled,
            'trialDays' => $trialDays,
            'trialLimit' => $trialLimit,
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
            EmailNotificationService::notifyTrialStarted($user, $trialDays, $trialLimit);
            logger("User {$user->email} started {$trialDays}-day free trial", 'info', $user->id);
        } else {
            flash('error', 'Unable to start trial. Please contact support.');
        }

        redirect('/billing');
    }

    /**
     * Show Checkout Gateway Selection Page (Stripe / bKash / Nagad)
     */
    public function checkout(Request $request, int $planId): string {
        $user = Auth::user();
        $plan = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            flash('error', 'Invalid subscription plan selected.');
            redirect('/billing');
        }

        // Gateways Configuration
        $stripeEnabled = (bool)(int)SystemSetting::get('stripe_enabled', '1');
        $stripePubKey = SystemSetting::get('stripe_publishable_key', '');

        // bKash
        $bkashEnabled = (bool)(int)SystemSetting::get('bkash_enabled', '1');
        $bkashType = SystemSetting::get('bkash_type', 'manual_number');
        $bkashNumber = SystemSetting::get('bkash_number', '01611195794');
        $bkashAccType = SystemSetting::get('bkash_account_type', 'Personal');
        $bkashRate = (float)SystemSetting::get('bkash_exchange_rate', '120');
        $bkashInstructions = SystemSetting::get('bkash_instructions', 'Send Money to bKash Personal Number: 01611195794. Enter your Sender Phone Number & TrxID below to submit verification.');
        $bkashAmountBdt = round($plan->price * $bkashRate, 2);

        // Nagad
        $nagadEnabled = (bool)(int)SystemSetting::get('nagad_enabled', '1');
        $nagadType = SystemSetting::get('nagad_type', 'manual_number');
        $nagadNumber = SystemSetting::get('nagad_number', '01611195794');
        $nagadAccType = SystemSetting::get('nagad_account_type', 'Personal');
        $nagadRate = (float)SystemSetting::get('nagad_exchange_rate', '120');
        $nagadInstructions = SystemSetting::get('nagad_instructions', 'Send Money to Nagad Personal Number: 01611195794. Enter your Sender Phone Number & TrxID below to submit verification.');
        $nagadAmountBdt = round($plan->price * $nagadRate, 2);

        return View::render('billing/checkout', [
            'plan' => $plan,
            'stripeEnabled' => $stripeEnabled,
            'stripePublishableKey' => $stripePubKey,
            'bkash' => [
                'enabled' => $bkashEnabled,
                'type' => $bkashType,
                'number' => $bkashNumber,
                'account_type' => $bkashAccType,
                'rate' => $bkashRate,
                'instructions' => $bkashInstructions,
                'amount_bdt' => $bkashAmountBdt,
            ],
            'nagad' => [
                'enabled' => $nagadEnabled,
                'type' => $nagadType,
                'number' => $nagadNumber,
                'account_type' => $nagadAccType,
                'rate' => $nagadRate,
                'instructions' => $nagadInstructions,
                'amount_bdt' => $nagadAmountBdt,
            ],
        ]);
    }

    /**
     * Process Stripe Checkout
     */
    public function processStripe(Request $request, int $planId): void {
        $user = Auth::user();
        $plan = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            flash('error', 'Invalid subscription plan selected.');
            redirect('/billing');
            return;
        }

        try {
            $session = StripeService::createCheckoutSession($user, $plan);
            header("Location: " . $session['checkout_url']);
            exit;
        } catch (\Throwable $e) {
            flash('error', 'Stripe Gateway Error: ' . $e->getMessage());
            redirect('/billing/checkout/' . $planId);
        }
    }

    /**
     * Submit Manual bKash Payment
     */
    public function submitBkash(Request $request, int $planId): void {
        $user = Auth::user();
        $plan = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            flash('error', 'Invalid subscription plan.');
            redirect('/billing');
            return;
        }

        $senderNumber = trim($request->input('sender_number', ''));
        $trxId = strtoupper(trim($request->input('transaction_id', '')));

        if (empty($senderNumber) || strlen($senderNumber) < 11) {
            flash('error', 'Please enter a valid bKash sender mobile number.');
            redirect('/billing/checkout/' . $planId);
            return;
        }

        if (empty($trxId) || strlen($trxId) < 6) {
            flash('error', 'Please enter a valid bKash Transaction ID (TrxID).');
            redirect('/billing/checkout/' . $planId);
            return;
        }

        $existing = Payment::findByTransactionId($trxId);
        if ($existing) {
            flash('error', "Transaction ID {$trxId} has already been submitted.");
            redirect('/billing/checkout/' . $planId);
            return;
        }

        $rate = (float)SystemSetting::get('bkash_exchange_rate', '120');
        $bdtAmount = round($plan->price * $rate, 2);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'bkash',
            'payment_method_type' => 'manual_number',
            'sender_number' => $senderNumber,
            'transaction_id' => $trxId,
            'amount' => $plan->price,
            'amount_bdt' => $bdtAmount,
            'currency' => 'usd',
            'status' => 'pending',
            'admin_notes' => "bKash Manual Submission by {$user->email}. Sender: {$senderNumber}, TrxID: {$trxId}",
        ]);

        // Dispatch Payment Submitted Notification (User + Admin)
        EmailNotificationService::notifyPaymentSubmitted($payment);

        logger("User {$user->email} submitted bKash payment for plan {$plan->name}. TrxID: {$trxId}", 'info', $user->id);
        flash('success', "Your bKash payment verification for {$plan->name} plan ({$bdtAmount} BDT) has been submitted! Admin will verify your Transaction ID ({$trxId}) and activate your plan.");
        redirect('/billing');
    }

    /**
     * Submit Manual Nagad Payment
     */
    public function submitNagad(Request $request, int $planId): void {
        $user = Auth::user();
        $plan = Plan::find($planId);

        if (!$plan || !$plan->is_active) {
            flash('error', 'Invalid subscription plan.');
            redirect('/billing');
            return;
        }

        $senderNumber = trim($request->input('sender_number', ''));
        $trxId = strtoupper(trim($request->input('transaction_id', '')));

        if (empty($senderNumber) || strlen($senderNumber) < 11) {
            flash('error', 'Please enter a valid Nagad sender mobile number.');
            redirect('/billing/checkout/' . $planId);
            return;
        }

        if (empty($trxId) || strlen($trxId) < 6) {
            flash('error', 'Please enter a valid Nagad Transaction ID (TrxID).');
            redirect('/billing/checkout/' . $planId);
            return;
        }

        $existing = Payment::findByTransactionId($trxId);
        if ($existing) {
            flash('error', "Transaction ID {$trxId} has already been submitted.");
            redirect('/billing/checkout/' . $planId);
            return;
        }

        $rate = (float)SystemSetting::get('nagad_exchange_rate', '120');
        $bdtAmount = round($plan->price * $rate, 2);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'nagad',
            'payment_method_type' => 'manual_number',
            'sender_number' => $senderNumber,
            'transaction_id' => $trxId,
            'amount' => $plan->price,
            'amount_bdt' => $bdtAmount,
            'currency' => 'usd',
            'status' => 'pending',
            'admin_notes' => "Nagad Manual Submission by {$user->email}. Sender: {$senderNumber}, TrxID: {$trxId}",
        ]);

        // Dispatch Payment Submitted Notification (User + Admin)
        EmailNotificationService::notifyPaymentSubmitted($payment);

        logger("User {$user->email} submitted Nagad payment for plan {$plan->name}. TrxID: {$trxId}", 'info', $user->id);
        flash('success', "Your Nagad payment verification for {$plan->name} plan ({$bdtAmount} BDT) has been submitted! Admin will verify your Transaction ID ({$trxId}) and activate your plan.");
        redirect('/billing');
    }
}
