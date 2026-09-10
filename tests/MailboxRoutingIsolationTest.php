<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\ScheduledJob;
use App\Models\FollowupCampaign;
use App\Models\FollowupJob;
use App\Models\FollowupTemplate;
use App\Models\DailyUsage;
use App\Models\AutoReplyRecipient;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;
use App\Services\MailboxRoutingService;
use Database\MigrationRunner;

class MailboxRoutingIsolationTest extends TestCase {
    private User $user;
    private GmailAccount $accountA;
    private GmailAccount $accountB;
    private AutomationSetting $settingsA;
    private AutomationSetting $settingsB;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $sqlitePath = storage_path('database/test.sqlite');
        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        putenv("APP_ENV=testing");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';
        $_ENV['APP_ENV'] = 'testing';

        config('_reset_');
        Database::resetConnection();

        new App();
        MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();

        // Create test user and two isolated gmail accounts
        $this->user = User::create([
            'name' => 'Mailbox Isolation User',
            'email' => 'isolation_user_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->accountA = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'sales1_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->accountB = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'sales2_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->settingsA = AutomationSetting::createOrGet($this->accountA->id);
        $this->settingsA->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello from Sales 1! We received your message.', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 1,
            'stop_followup_on_reply' => 1,
        ]);

        $this->settingsB = AutomationSetting::createOrGet($this->accountB->id);
        $this->settingsB->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello from Sales 2! We received your message.', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 1,
            'stop_followup_on_reply' => 1,
        ]);

        // Clear usage
        Database::execute("DELETE FROM daily_usage WHERE gmail_account_id IN (?, ?)", [$this->accountA->id, $this->accountB->id]);
    }

    /**
     * Scenario 1: Gmail A receives → reply sent strictly from Gmail A
     */
    public function testScenario1_GmailAReceives_ReplySentFromGmailA(): void {
        $customerEmail = 'client_a_' . uniqid() . '@example.com';
        $incomingData = [
            'id' => 'msg_in_a_' . uniqid(),
            'threadId' => 'th_a_' . uniqid(),
            'sender' => "Customer A <{$customerEmail}>",
            'sender_email' => $customerEmail,
            'sender_name' => 'Customer A',
            'subject' => 'Inquiry for Sales 1',
            'snippet' => 'Hello Sales 1, please help me.',
            'body' => '<p>Hello Sales 1, please help me.</p>',
            'received_at' => date('Y-m-d H:i:s'),
        ];

        $engineA = new AutomationEngine($this->accountA);
        $engineA->processIncomingMessage($incomingData);

        // Thread must have source_mailbox_id = Account A
        $thread = EmailThread::findByAccountAndThreadId($this->accountA->id, $incomingData['threadId']);
        $this->assertNotNull($thread);
        $this->assertEquals($this->accountA->id, $thread->source_mailbox_id);
        $this->assertEquals($this->accountA->id, $thread->gmail_account_id);

        // Check scheduled auto-reply job
        $jobs = ScheduledJob::findPendingByThreadId($thread->id);
        $this->assertNotEmpty($jobs);
        $job = $jobs[0];
        $this->assertEquals($this->accountA->id, $job->source_mailbox_id);
        $this->assertEquals($this->accountA->id, $job->gmail_account_id);

        // Process job via QueueWorker
        $worker = new QueueWorker();
        $success = $worker->processJob($job);
        $this->assertTrue($success);

        // Verify outgoing message in DB
        $messages = EmailMessage::findByThreadId($thread->id);
        $outgoing = array_filter($messages, fn($m) => $m->direction === 'outgoing');
        $this->assertNotEmpty($outgoing);
        $lastOut = end($outgoing);

        $this->assertEquals($this->accountA->id, $lastOut->source_mailbox_id);
        $this->assertEquals($this->accountA->id, $lastOut->gmail_account_id);
        $this->assertEquals($this->accountA->gmail_email, $lastOut->sender);

        // Verify audit log in activity_logs
        $log = Database::first(
            "SELECT * FROM activity_logs WHERE message LIKE '%MailboxRouting%STATUS: SENT%' ORDER BY id DESC LIMIT 1"
        );
        $this->assertNotNull($log);
        $this->assertStringContainsString($this->accountA->gmail_email, $log['message']);
    }

    /**
     * Scenario 2: Gmail B receives → reply sent strictly from Gmail B
     */
    public function testScenario2_GmailBReceives_ReplySentFromGmailB(): void {
        $customerEmail = 'client_b_' . uniqid() . '@example.com';
        $incomingData = [
            'id' => 'msg_in_b_' . uniqid(),
            'threadId' => 'th_b_' . uniqid(),
            'sender' => "Customer B <{$customerEmail}>",
            'sender_email' => $customerEmail,
            'sender_name' => 'Customer B',
            'subject' => 'Inquiry for Sales 2',
            'snippet' => 'Hello Sales 2, pricing info please.',
            'body' => '<p>Hello Sales 2, pricing info please.</p>',
            'received_at' => date('Y-m-d H:i:s'),
        ];

        $engineB = new AutomationEngine($this->accountB);
        $engineB->processIncomingMessage($incomingData);

        // Thread must have source_mailbox_id = Account B
        $thread = EmailThread::findByAccountAndThreadId($this->accountB->id, $incomingData['threadId']);
        $this->assertNotNull($thread);
        $this->assertEquals($this->accountB->id, $thread->source_mailbox_id);

        $jobs = ScheduledJob::findPendingByThreadId($thread->id);
        $this->assertNotEmpty($jobs);
        $job = $jobs[0];
        $this->assertEquals($this->accountB->id, $job->source_mailbox_id);

        $worker = new QueueWorker();
        $success = $worker->processJob($job);
        $this->assertTrue($success);

        $messages = EmailMessage::findByThreadId($thread->id);
        $outgoing = array_filter($messages, fn($m) => $m->direction === 'outgoing');
        $this->assertNotEmpty($outgoing);
        $lastOut = end($outgoing);

        $this->assertEquals($this->accountB->id, $lastOut->source_mailbox_id);
        $this->assertEquals($this->accountB->gmail_email, $lastOut->sender);
    }

    /**
     * Scenario 3: Gmail A receives → Gmail B worker cannot send for A (Cross-account hard block)
     */
    public function testScenario3_GmailAReceives_GmailBWorkerCannotSendForA(): void {
        $customerEmail = 'client_mismatch_' . uniqid() . '@example.com';
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_mismatch_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => $customerEmail,
            'sender_name' => 'Mismatch Tester',
            'subject' => 'Help needed for Sales 1',
            'automation_status' => 'active',
        ]);

        // Intentionally create a fraudulent/mismatched job assigned to Account B
        $job = ScheduledJob::create([
            'gmail_account_id' => $this->accountB->id,
            'source_mailbox_id' => $this->accountB->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'status' => 'pending',
            'scheduled_at' => date('Y-m-d H:i:s'),
            'payload' => [
                'recipient_email' => $customerEmail,
                'subject' => $thread->subject,
                'body' => 'Should never be sent from Account B!',
            ],
        ]);

        $worker = new QueueWorker();
        $processed = $worker->processJob($job);

        // Job must be handled and cancelled
        $this->assertTrue($processed);

        // Reload job: status must be cancelled due to mailbox mismatch
        $reloadedJob = ScheduledJob::find($job->id);
        $this->assertEquals('cancelled', $reloadedJob->status);
        $this->assertStringContainsString('mailbox mismatch', strtolower($reloadedJob->last_error));

        // Ensure NO outgoing message was recorded for this thread
        $messages = EmailMessage::findByThreadId($thread->id);
        $outgoing = array_filter($messages, fn($m) => $m->direction === 'outgoing');
        $this->assertEmpty($outgoing);

        // Audit log must record status = 'blocked'
        $log = Database::first(
            "SELECT * FROM activity_logs WHERE message LIKE '%MailboxRouting%STATUS: BLOCKED%' ORDER BY id DESC LIMIT 1"
        );
        $this->assertNotNull($log);
        $this->assertStringContainsString('Mailbox mismatch', $log['message']);
    }

    /**
     * Scenario 4: Gmail A disconnected → sending blocked, no fallback to B
     */
    public function testScenario4_GmailADisconnected_SendingBlocked_NoFallbackToB(): void {
        $customerEmail = 'client_discon_' . uniqid() . '@example.com';
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_discon_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => $customerEmail,
            'sender_name' => 'Disconnected Test',
            'subject' => 'Inquiry for Account A',
            'automation_status' => 'active',
        ]);

        $job = ScheduledJob::create([
            'gmail_account_id' => $this->accountA->id,
            'source_mailbox_id' => $this->accountA->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'status' => 'pending',
            'scheduled_at' => date('Y-m-d H:i:s'),
            'payload' => [
                'recipient_email' => $customerEmail,
                'subject' => $thread->subject,
                'body' => 'Test body',
            ],
        ]);

        // Disconnect Account A
        $this->accountA->update(['status' => 'disconnected']);

        $worker = new QueueWorker();
        $processed = $worker->processJob($job);
        $this->assertTrue($processed);

        $reloadedJob = ScheduledJob::find($job->id);
        $this->assertEquals('cancelled', $reloadedJob->status);
        $this->assertStringContainsString('not active/connected', strtolower($reloadedJob->last_error));

        // Verify zero outgoing messages were sent
        $messages = EmailMessage::findByThreadId($thread->id);
        $this->assertEmpty($messages);

        // Verify zero sends from Account B
        $accountBMessages = Database::query(
            "SELECT * FROM email_messages WHERE (gmail_account_id = :bg OR source_mailbox_id = :bs) AND recipient = :cust",
            ['bg' => $this->accountB->id, 'bs' => $this->accountB->id, 'cust' => $customerEmail]
        );
        $this->assertEmpty($accountBMessages);

        // Restore Account A status for subsequent tests
        $this->accountA->update(['status' => 'connected']);
    }

    /**
     * Scenario 5: Follow-up #1, #2, #3 strictly stay with Gmail A
     */
    public function testScenario5_FollowupStepsStrictlyStayWithGmailA(): void {
        // Create follow-up templates for Account A
        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->accountA->id,
            'step_number' => 1,
            'delay_value' => 0,
            'delay_unit' => 'minutes',
            'name' => 'Step 1 Followup',
            'message' => 'Follow up step 1 from Sales 1',
            'status' => 'active',
        ]);

        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->accountA->id,
            'step_number' => 2,
            'delay_value' => 0,
            'delay_unit' => 'minutes',
            'name' => 'Step 2 Followup',
            'message' => 'Follow up step 2 from Sales 1',
            'status' => 'active',
        ]);

        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->accountA->id,
            'step_number' => 3,
            'delay_value' => 0,
            'delay_unit' => 'minutes',
            'name' => 'Step 3 Followup',
            'message' => 'Follow up step 3 from Sales 1',
            'status' => 'active',
        ]);

        $customerEmail = 'client_multi_' . uniqid() . '@example.com';
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_multi_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => $customerEmail,
            'sender_name' => 'Multi-step Client',
            'subject' => 'Multi-step conversation',
            'automation_status' => 'active',
        ]);

        $engineA = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();

        // 1. Trigger Step 1
        $job1 = $engineA->scheduleNextFollowupStep($thread, 0);
        $this->assertNotNull($job1);
        $this->assertEquals($this->accountA->id, $job1->source_mailbox_id);

        $worker->processJob($job1);
        $thread = EmailThread::find($thread->id);
        $this->assertEquals(1, $thread->followup_count);

        // 2. Trigger Step 2
        $job2 = $engineA->scheduleNextFollowupStep($thread, 1);
        $this->assertNotNull($job2);
        $this->assertEquals($this->accountA->id, $job2->source_mailbox_id);

        $worker->processJob($job2);
        $thread = EmailThread::find($thread->id);
        $this->assertEquals(2, $thread->followup_count);

        // 3. Trigger Step 3
        $job3 = $engineA->scheduleNextFollowupStep($thread, 2);
        $this->assertNotNull($job3);
        $this->assertEquals($this->accountA->id, $job3->source_mailbox_id);

        $worker->processJob($job3);
        $thread = EmailThread::find($thread->id);
        $this->assertEquals(3, $thread->followup_count);

        // Check campaign
        $campaign = FollowupCampaign::findByThreadId($thread->id);
        $this->assertNotNull($campaign);
        $this->assertEquals($this->accountA->id, $campaign->source_mailbox_id);
        $this->assertEquals(3, $campaign->current_step);

        // All outgoing messages MUST be from Account A
        $messages = EmailMessage::findByThreadId($thread->id);
        $outgoing = array_filter($messages, fn($m) => $m->direction === 'outgoing');
        $this->assertCount(3, $outgoing);

        foreach ($outgoing as $msg) {
            $this->assertEquals($this->accountA->id, $msg->source_mailbox_id);
            $this->assertEquals($this->accountA->gmail_email, $msg->sender);
        }
    }

    /**
     * Scenario 6: Concurrent jobs/workers avoid duplicate cross-account sends
     */
    public function testScenario6_ConcurrentWorkersAvoidDuplicateCrossAccountSends(): void {
        $customerEmail = 'client_concurr_' . uniqid() . '@example.com';
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_concurr_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => $customerEmail,
            'sender_name' => 'Concurrent Client',
            'subject' => 'Concurrent test',
            'automation_status' => 'active',
        ]);

        $job = ScheduledJob::create([
            'gmail_account_id' => $this->accountA->id,
            'source_mailbox_id' => $this->accountA->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'status' => 'pending',
            'scheduled_at' => date('Y-m-d H:i:s'),
            'payload' => [
                'recipient_email' => $customerEmail,
                'subject' => $thread->subject,
                'body' => 'Locking test body',
            ],
        ]);

        // Simulate Worker 1 claiming the job atomically
        $now = date('Y-m-d H:i:s');
        $affected1 = Database::executeUpdate(
            "UPDATE scheduled_jobs SET status = 'processing', attempts = attempts + 1, updated_at = :now 
             WHERE id = :id AND status = 'pending'",
            ['id' => $job->id, 'now' => $now]
        );
        $this->assertEquals(1, $affected1);

        // Simulate Worker 2 trying to claim the same job concurrently
        $worker2 = new QueueWorker();
        $claimed2 = $worker2->processJob($job);

        // Worker 2 must be unable to claim it and immediately return false
        $this->assertFalse($claimed2);

        // Verify only 1 attempt incremented
        $reloaded = ScheduledJob::find($job->id);
        $this->assertEquals(1, $reloaded->attempts);
        $this->assertEquals('processing', $reloaded->status);
    }

    /**
     * Scenario 7: Unassigned/null source_mailbox_id blocks with exact message
     */
    public function testScenario7_UnassignedSourceMailboxIdBlocksWithExactMessage(): void {
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_unassigned_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => 'unassigned@test.com',
            'subject' => 'Unassigned mailbox test',
        ]);

        // Deliberately clear source_mailbox_id in the DB
        Database::execute(
            "UPDATE email_threads SET source_mailbox_id = NULL WHERE id = :id",
            ['id' => $thread->id]
        );

        $res = MailboxRoutingService::validateAndResolveForSending($thread->id);
        $this->assertFalse($res['allowed']);
        $this->assertNull($res['account']);
        $this->assertEquals('Sending blocked: source mailbox is not assigned.', $res['reason']);
    }

    /**
     * Scenario 8: From address mismatch is blocked
     */
    public function testScenario8_SenderEmailMismatchBlocked(): void {
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_from_mismatch_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => 'client_from@test.com',
            'subject' => 'From check',
        ]);

        $res = MailboxRoutingService::validateAndResolveForSending(
            $thread->id,
            $this->accountA->id,
            'spoofed@hacker.com'
        );

        $this->assertFalse($res['allowed']);
        $this->assertNull($res['account']);
        $this->assertStringContainsString('sender email mismatch', strtolower($res['reason']));
    }

    /**
     * Test Manual Reply enforces source mailbox
     */
    public function testManualReply_EnforcesSourceMailbox(): void {
        $thread = EmailThread::createOrGet($this->accountA->id, 'th_manual_' . uniqid(), [
            'source_mailbox_id' => $this->accountA->id,
            'sender_email' => 'manual_cust@example.com',
            'subject' => 'Manual Reply Test',
        ]);

        // Verification through MailboxRoutingService
        $validation = MailboxRoutingService::validateAndResolveForSending($thread->id);
        $this->assertTrue($validation['allowed']);
        $this->assertEquals($this->accountA->id, $validation['account']->id);
        $this->assertEquals($this->accountA->gmail_email, $validation['account']->gmail_email);
    }
}
