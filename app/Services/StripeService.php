<?php
namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\EmailJob;
use App\Models\SystemSetting;
use Exception;

class StripeService {
    /**
     * Create a Stripe Checkout Session for a plan purchase/subscription
     */
    public static function createCheckoutSession(User $user, Plan $plan): array {
        $secretKey = SystemSetting::get('stripe_secret_key', '');
        if (empty($secretKey)) {
            throw new Exception("Stripe Secret Key is not configured in Admin settings.");
        }

        $successUrl = url('/billing?session_id={CHECKOUT_SESSION_ID}&status=success');
        $cancelUrl = url('/billing?status=cancelled');

        $params = [
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'mode' => 'payment', // payment or subscription
            'customer_email' => $user->email,
            'client_reference_id' => (string)$user->id,
            'metadata[user_id]' => (string)$user->id,
            'metadata[plan_id]' => (string)$plan->id,
            'metadata[plan_slug]' => $plan->slug,
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (int)($plan->price * 100),
            'line_items[0][price_data][product_data][name]' => $plan->name . ' Plan - Gmail Automation',
            'line_items[0][price_data][product_data][description]' => "Up to {$plan->gmail_limit} Gmail accounts with conversational auto-replies and follow-ups.",
            'line_items[0][quantity]' => '1',
        ];

        // If plan has a recurring Stripe price ID, we can use mode=subscription
        if (!empty($plan->stripe_price_id)) {
            unset($params['line_items[0][price_data][currency]']);
            unset($params['line_items[0][price_data][unit_amount]']);
            unset($params['line_items[0][price_data][product_data][name]']);
            unset($params['line_items[0][price_data][product_data][description]']);
            $params['line_items[0][price]'] = $plan->stripe_price_id;
            $params['mode'] = 'subscription';
        }

        $response = self::apiCall('POST', '/v1/checkout/sessions', $params, $secretKey);
        
        if (empty($response['id']) || empty($response['url'])) {
            throw new Exception($response['error']['message'] ?? 'Failed to create Stripe Checkout session.');
        }

        // Create pending payment record
        Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_session_id' => $response['id'],
            'amount' => $plan->price,
            'currency' => 'usd',
            'status' => 'pending',
        ]);

        return [
            'session_id' => $response['id'],
            'checkout_url' => $response['url'],
        ];
    }

    /**
     * Handle Stripe Webhook event with signature verification
     */
    public static function handleWebhook(string $payload, string $sigHeader): array {
        $secret = SystemSetting::get('stripe_webhook_secret', '');
        
        if (!empty($secret)) {
            if (!self::verifySignature($payload, $sigHeader, $secret)) {
                throw new Exception("Invalid Stripe webhook signature.");
            }
        }

        $event = json_decode($payload, true);
        if (!$event || empty($event['type'])) {
            throw new Exception("Invalid JSON webhook payload.");
        }

        $eventType = $event['type'];
        $eventData = $event['data']['object'] ?? [];

        switch ($eventType) {
            case 'checkout.session.completed':
                return self::processCheckoutSessionCompleted($eventData, $event['id'] ?? null);

            case 'invoice.payment_succeeded':
                return self::processInvoicePaymentSucceeded($eventData, $event['id'] ?? null);

            case 'customer.subscription.deleted':
                return self::processSubscriptionCancelled($eventData);

            default:
                return ['status' => 'ignored', 'event_type' => $eventType];
        }
    }

    /**
     * Activate user subscription on checkout.session.completed
     */
    public static function processCheckoutSessionCompleted(array $session, ?string $eventId = null): array {
        $sessionId = $session['id'] ?? null;
        $userId = (int)($session['client_reference_id'] ?? ($session['metadata']['user_id'] ?? 0));
        $planId = (int)($session['metadata']['plan_id'] ?? 0);
        $planSlug = $session['metadata']['plan_slug'] ?? null;

        if (!$userId || !$sessionId) {
            return ['status' => 'error', 'message' => 'Missing user_id or session_id in webhook payload'];
        }

        $user = User::find($userId);
        if (!$user) {
            return ['status' => 'error', 'message' => "User ID {$userId} not found"];
        }

        $plan = $planId ? Plan::find($planId) : ($planSlug ? Plan::findBySlug($planSlug) : null);
        if (!$plan) {
            $plan = Plan::findBySlug('starter');
        }

        $amount = (float)(($session['amount_total'] ?? ($plan ? $plan->price * 100 : 0)) / 100);
        $currency = strtolower($session['currency'] ?? 'usd');
        $paymentIntent = $session['payment_intent'] ?? null;
        $customerId = $session['customer'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        // 1. Record or update Payment
        $payment = Payment::findBySessionId($sessionId);
        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'amount' => $amount,
                'currency' => $currency,
                'stripe_payment_intent_id' => $paymentIntent,
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_id' => $plan ? $plan->id : null,
                'stripe_session_id' => $sessionId,
                'stripe_payment_intent_id' => $paymentIntent,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 2. Activate User Plan
        $startDate = date('Y-m-d H:i:s');
        $expiryDate = date('Y-m-d H:i:s', strtotime('+1 month'));

        $user->update([
            'plan_id' => $plan ? $plan->id : null,
            'plan_type' => $plan ? $plan->slug : 'starter',
            'subscription_status' => 'active',
            'gmail_limit' => $plan ? $plan->gmail_limit : 100,
            'subscription_started_at' => $startDate,
            'subscription_expires_at' => $expiryDate,
            'stripe_customer_id' => $customerId ?? $user->stripe_customer_id,
            'stripe_subscription_id' => $subscriptionId ?? $user->stripe_subscription_id,
        ]);

        logger("Activated {$plan->name} subscription for {$user->email} (Session: {$sessionId})", 'info', $user->id);

        // 3. Dispatch Purchase Confirmation Email
        $eventKey = 'purchase:' . ($eventId ?? $sessionId);
        EmailJob::dispatchTemplate('purchase_confirmation', $user->email, [
            'name' => $user->name,
            'plan_name' => $plan->name,
            'plan_price' => number_format($plan->price, 2),
            'billing_period' => $plan->billing_period,
            'gmail_limit' => $plan->gmail_limit,
            'start_date' => date('d M Y'),
            'renewal_date' => date('d M Y', strtotime('+1 month')),
            'transaction_id' => $paymentIntent ?? $sessionId,
        ], $eventKey, $user->id, $user->name);

        return ['status' => 'success', 'user_id' => $user->id, 'plan' => $plan->name];
    }

    private static function processInvoicePaymentSucceeded(array $invoice, ?string $eventId = null): array {
        $customerId = $invoice['customer'] ?? null;
        if (!$customerId) return ['status' => 'ignored'];

        $userRow = \App\Core\Database::first("SELECT id FROM users WHERE stripe_customer_id = :cid LIMIT 1", ['cid' => $customerId]);
        if (!$userRow) return ['status' => 'ignored'];

        $user = User::find((int)$userRow['id']);
        if ($user) {
            $user->update([
                'subscription_status' => 'active',
                'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
            ]);
        }

        return ['status' => 'success'];
    }

    private static function processSubscriptionCancelled(array $subscription): array {
        $subId = $subscription['id'] ?? null;
        if (!$subId) return ['status' => 'ignored'];

        $userRow = \App\Core\Database::first("SELECT id FROM users WHERE stripe_subscription_id = :sid LIMIT 1", ['sid' => $subId]);
        if (!$userRow) return ['status' => 'ignored'];

        $user = User::find((int)$userRow['id']);
        if ($user) {
            $user->update([
                'subscription_status' => 'cancelled',
            ]);
            logger("Subscription cancelled for {$user->email}", 'warning', $user->id);
        }

        return ['status' => 'success'];
    }

    /**
     * Verify Stripe Webhook HMAC-SHA256 signature
     */
    public static function verifySignature(string $payload, string $sigHeader, string $secret): bool {
        $items = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance (e.g. 5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }

        return false;
    }

    private static function apiCall(string $method, string $endpoint, array $data, string $secretKey): array {
        $url = 'https://api.stripe.com' . $endpoint;
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($result, true);
        return is_array($json) ? $json : ['error' => ['message' => 'Invalid HTTP response from Stripe']];
    }
}
