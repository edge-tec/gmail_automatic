<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use Database\MigrationRunner;
use App\Models\User;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\EmailTemplate;
use App\Models\EmailJob;
use App\Models\SystemSetting;
use App\Services\MailService;
use App\Services\StripeService;
use App\Services\QueueWorker;

class SaaSSubscriptionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        new App();
        MigrationRunner::run();
    }

    public function testPlansAreSeededAndDynamic(): void {
        $plans = Plan::getActivePlans();
        $this->assertNotEmpty($plans);
        
        $starter = Plan::findBySlug('starter');
        $this->assertNotNull($starter);
        $this->assertEquals(50.00, $starter->price);
        $this->assertEquals(100, $starter->gmail_limit);

        $pro = Plan::findBySlug('professional');
        $this->assertNotNull($pro);
        $this->assertEquals(100.00, $pro->price);
        $this->assertEquals(250, $pro->gmail_limit);
        $this->assertTrue($pro->is_popular);
    }

    public function testFreeTrialActivationAndEnforcement(): void {
        $email = 'trial_user_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Trial User',
            'email' => $email,
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
        ]);

        $this->assertTrue($user->canStartTrial());
        $this->assertEquals('not_started', $user->trial_status);

        // Start 14-day trial with 5 accounts limit
        $ok = $user->startTrial(14, 5);
        $this->assertTrue($ok);
        $this->assertTrue($user->isTrialActive());
        $this->assertEquals(5, $user->getMaxGmailAccounts());
        $this->assertEquals('trial', $user->plan_type);

        // Attempting to start second trial should fail
        $secondAttempt = $user->startTrial(14, 5);
        $this->assertFalse($secondAttempt);
    }

    public function testEmailJobTemplateDispatchAndDeduplication(): void {
        $email = 'notify_' . uniqid() . '@test.com';
        $eventKey = 'welcome_test:' . uniqid();

        $job1 = EmailJob::dispatchTemplate('welcome', $email, [
            'name' => 'John Doe',
        ], $eventKey, 1, 'John Doe');

        $this->assertNotNull($job1);
        $this->assertStringContainsString('John Doe', $job1->subject);
        $this->assertEquals('pending', $job1->status);

        // Duplicate dispatch with same event_key should return existing job without creating duplicate row
        $job2 = EmailJob::dispatchTemplate('welcome', $email, [
            'name' => 'John Doe Duplicate',
        ], $eventKey, 1, 'John Doe Duplicate');

        $this->assertEquals($job1->id, $job2->id);
    }

    public function testStripeWebhookActivation(): void {
        $email = 'customer_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Paying Customer',
            'email' => $email,
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
        ]);

        $starterPlan = Plan::findBySlug('starter');
        $sessionId = 'cs_test_' . uniqid();

        $sessionData = [
            'id' => $sessionId,
            'client_reference_id' => (string)$user->id,
            'metadata' => [
                'user_id' => (string)$user->id,
                'plan_id' => (string)$starterPlan->id,
                'plan_slug' => 'starter',
            ],
            'amount_total' => 5000,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_' . uniqid(),
            'customer' => 'cus_test_' . uniqid(),
        ];

        $result = StripeService::processCheckoutSessionCompleted($sessionData);
        $this->assertEquals('success', $result['status']);

        // Verify User was activated
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->hasActiveSubscription());
        $this->assertEquals('starter', $freshUser->plan_type);
        $this->assertEquals(100, $freshUser->getMaxGmailAccounts());

        // Verify Payment was recorded
        $payment = Payment::findBySessionId($sessionId);
        $this->assertNotNull($payment);
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals(50.00, $payment->amount);
    }

    public function testBkashManualPaymentSubmissionAndApproval(): void {
        $email = 'bkash_user_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'bKash Customer',
            'email' => $email,
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
        ]);

        $proPlan = Plan::findBySlug('professional');
        $trxId = 'BK' . strtoupper(uniqid());

        // Create pending manual bKash payment
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'gateway' => 'bkash',
            'payment_method_type' => 'manual_number',
            'sender_number' => '01711223344',
            'transaction_id' => $trxId,
            'amount' => 100.00,
            'amount_bdt' => 12000.00,
            'currency' => 'usd',
            'status' => 'pending',
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('bkash', $payment->gateway);
        $this->assertEquals('01711223344', $payment->sender_number);

        // Admin approves the payment
        $approved = $payment->approve(1, 'Verified via bKash statement');
        $this->assertTrue($approved);

        $freshPayment = Payment::find($payment->id);
        $this->assertEquals('paid', $freshPayment->status);

        // User plan should now be active with 250 Gmail limit
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->hasActiveSubscription());
        $this->assertEquals('professional', $freshUser->plan_type);
        $this->assertEquals(250, $freshUser->getMaxGmailAccounts());
    }

    public function testNagadManualPaymentSubmissionAndRejection(): void {
        $email = 'nagad_user_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Nagad Customer',
            'email' => $email,
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
        ]);

        $starterPlan = Plan::findBySlug('starter');
        $trxId = 'NG' . strtoupper(uniqid());

        // Create pending manual Nagad payment
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $starterPlan->id,
            'gateway' => 'nagad',
            'payment_method_type' => 'manual_number',
            'sender_number' => '01811223344',
            'transaction_id' => $trxId,
            'amount' => 50.00,
            'amount_bdt' => 6000.00,
            'currency' => 'usd',
            'status' => 'pending',
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);

        // Admin rejects the fake transaction
        $rejected = $payment->reject('Invalid TrxID');
        $this->assertTrue($rejected);

        $freshPayment = Payment::find($payment->id);
        $this->assertEquals('rejected', $freshPayment->status);
        $this->assertEquals('Invalid TrxID', $freshPayment->admin_notes);

        // User should remain inactive
        $freshUser = User::find($user->id);
        $this->assertFalse($freshUser->hasActiveSubscription());
    }

    public function testEmailNotificationServiceMatrix(): void {
        $email = 'matrix_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Matrix User',
            'email' => $email,
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
        ]);

        $proPlan = Plan::findBySlug('professional');

        // 1. Account Created
        $j1 = \App\Services\EmailNotificationService::notifyAccountCreated($user);
        $this->assertNotNull($j1);
        $this->assertEquals('welcome', $j1->template_slug);

        // 2. Email Verification
        $j2 = \App\Services\EmailNotificationService::notifyEmailVerification($user, 'https://2xbets.net/verify?token=123');
        $this->assertNotNull($j2);
        $this->assertEquals('email_verification', $j2->template_slug);

        // 3. Payment Submitted
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'gateway' => 'bkash',
            'amount' => 100.00,
            'status' => 'pending',
            'transaction_id' => 'BK' . uniqid(),
        ]);
        \App\Services\EmailNotificationService::notifyPaymentSubmitted($payment);
        $j3 = EmailJob::findByEventKey("payment_submitted:{$payment->id}");
        $this->assertNotNull($j3);
        $this->assertEquals('payment_submitted', $j3->template_slug);

        // 4. Account Suspended & Reactivated
        $j4 = \App\Services\EmailNotificationService::notifyAccountSuspended($user, 'Test reason');
        $this->assertNotNull($j4);
        $this->assertEquals('account_suspended', $j4->template_slug);

        $j5 = \App\Services\EmailNotificationService::notifyAccountReactivated($user);
        $this->assertNotNull($j5);
        $this->assertEquals('account_reactivated', $j5->template_slug);

        // 5. Account Deleted
        $j6 = \App\Services\EmailNotificationService::notifyAccountDeleted($user, 'User requested account closure');
        $this->assertNotNull($j6);
        $this->assertEquals('account_deleted', $j6->template_slug);

        // 6. Password Reset
        $j7 = \App\Services\EmailNotificationService::notifyPasswordReset($user, 'https://2xbets.net/reset?token=xyz');
        $this->assertNotNull($j7);
        $this->assertEquals('password_reset', $j7->template_slug);
    }

    public function testAdminCanImpersonateUserWithoutPassword(): void {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin_' . uniqid() . '@test.com',
            'password' => password_hash('AdminPass@123', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $targetUser = User::create([
            'name' => 'Regular Customer',
            'email' => 'customer_' . uniqid() . '@test.com',
            'password' => password_hash('CustomerPass@123', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Login as Admin
        \App\Core\Auth::login($admin);
        $this->assertEquals($admin->id, \App\Core\Auth::id());
        $this->assertFalse(\App\Core\Auth::isImpersonating());

        // Impersonate customer without password
        \App\Core\Auth::impersonate($targetUser);
        $this->assertEquals($targetUser->id, \App\Core\Auth::id());
        $this->assertTrue(\App\Core\Auth::isImpersonating());

        // Leave impersonation and return to admin
        $returnedAdmin = \App\Core\Auth::leaveImpersonation();
        $this->assertNotNull($returnedAdmin);
        $this->assertEquals($admin->id, $returnedAdmin->id);
        $this->assertEquals($admin->id, \App\Core\Auth::id());
        $this->assertFalse(\App\Core\Auth::isImpersonating());
    }
}
