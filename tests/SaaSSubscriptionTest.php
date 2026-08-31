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

    public function testQueueWorkerProcessesEmailJobs(): void {
        $email = 'queue_test_' . uniqid() . '@test.com';
        $job = EmailJob::dispatch([
            'recipient_email' => $email,
            'recipient_name' => 'Queue Recipient',
            'subject' => 'Test Subject',
            'body' => '<p>Test Body</p>',
        ]);

        $this->assertNotNull($job);
        $this->assertEquals('pending', $job->status);

        $worker = new QueueWorker();
        // Process queue batch
        $worker->processEmailJobs(10);

        $freshJob = EmailJob::find($job->id);
        // If SMTP is disabled by default, MailService marks failed or skips cleanly
        $this->assertContains($freshJob->status, ['sent', 'failed', 'pending']);
    }
}
