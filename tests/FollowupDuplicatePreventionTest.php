<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use Database\MigrationRunner;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\FollowupTemplate;
use App\Models\FollowupCampaign;
use App\Models\FollowupJob;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;

class FollowupDuplicatePreventionTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $sqlitePath = storage_path('database/test.sqlite');
        putenv("APP_ENV=testing");
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        new App();
        Database::resetConnection();
        MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();
        MigrationRunner::run();

        $this->user = User::create([
            'name' => 'Dedup Test User',
            'email' => 'dedup_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'sender_dedup_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createOrGet($this->account->id);
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Auto-reply Step 1', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                2 => ['message' => 'Auto-reply Step 2', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
            'daily_reply_limit' => 100,
        ]);
    }

    private function createTemplates(int $count = 3): void {
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        for ($i = 1; $i <= $count; $i++) {
            FollowupTemplate::create([
                'user_id' => $this->user->id,
                'gmail_account_id' => $this->account->id,
                'step_number' => $i,
                'name' => "Follow-up Step #{$i}",
                'message' => "This is follow-up message #{$i}",
                'delay_value' => 0,
                'delay_unit' => 'minutes',
                'status' => 'active',
            ]);
        }
    }

    /**
     * Test 1: Repeated calls to scheduleNextFollowupStep do not create duplicate ScheduledJob rows
     */
    public function testScheduleNextFollowupStepIsIdempotent(): void {
        $this->createTemplates(3);
        $thread = EmailThread::createOrGet($this->account->id, 'th_dedup_1_' . uniqid(), [
            'sender_email' => 'lead_dedup1@customer.com',
            'sender_name' => 'Lead One',
            'subject' => 'Test Subject',
            'source_mailbox_id' => $this->account->id,
        ]);

        $engine = new AutomationEngine($this->account);

        // Call 1: schedules follow-up step 1
        $job1 = $engine->scheduleNextFollowupStep($thread, 0);
        $this->assertNotNull($job1);

        // Call 2: repeated call for step 1 should return existing job without inserting duplicate
        $job2 = $engine->scheduleNextFollowupStep($thread, 0);
        $this->assertNotNull($job2);
        $this->assertEquals($job1->id, $job2->id, "Repeated call must return the same job ID");

        // Count pending follow-up jobs for this thread
        $count = Database::first(
            "SELECT COUNT(*) as cnt FROM scheduled_jobs WHERE thread_id = :tid AND job_type = 'follow_up' AND status = 'pending'",
            ['tid' => $thread->id]
        );
        $this->assertEquals(1, (int)$count['cnt'], "Exactly 1 pending follow-up job must exist, never duplicates");
    }

    /**
     * Test 2: If duplicate ScheduledJob records exist, QueueWorker cancels the duplicate and sends exactly once
     */
    public function testQueueWorkerCancelsDuplicateFollowupJobWithoutSendingTwice(): void {
        $this->createTemplates(2);
        $thread = EmailThread::createOrGet($this->account->id, 'th_dedup_2_' . uniqid(), [
            'sender_email' => 'lead_dedup2@customer.com',
            'sender_name' => 'Lead Two',
            'subject' => 'Test Subject 2',
            'source_mailbox_id' => $this->account->id,
        ]);

        $engine = new AutomationEngine($this->account);
        $job1 = $engine->scheduleNextFollowupStep($thread, 0);
        $this->assertNotNull($job1);

        // Intentionally create a duplicate ScheduledJob simulating race condition
        $payload = $job1->getPayloadArray();
        $job2 = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'source_mailbox_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => $payload,
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        $worker = new QueueWorker();

        // Process first job -> sends email
        $res1 = $worker->processJob($job1);
        $this->assertTrue($res1);

        // Process duplicate job -> must be cancelled, not sent again!
        $res2 = $worker->processJob($job2);
        $this->assertTrue($res2);

        $reloadedJob2 = ScheduledJob::find($job2->id);
        $this->assertEquals('cancelled', $reloadedJob2->status, "Duplicate job must be cancelled");

        // Thread follow-up count must be 1, not 2
        $reloadedThread = EmailThread::find($thread->id);
        $this->assertEquals(1, $reloadedThread->followup_count, "Follow-up count must strictly be 1");

        // Outgoing messages in thread must be 1
        $sentMessages = Database::query(
            "SELECT * FROM email_messages WHERE thread_id = :tid AND direction = 'outgoing'",
            ['tid' => $thread->id]
        );
        $this->assertCount(1, $sentMessages, "Exactly 1 outgoing follow-up email must be recorded");
    }

    /**
     * Test 3: Auto-reply disabled mode with multiple incoming messages never duplicates follow-up jobs
     */
    public function testAutoReplyDisabledMultipleIncomingMessagesDoNotDuplicateFollowup(): void {
        $this->createTemplates(2);
        $this->settings->update(['auto_reply_enabled' => 0]);

        $engine = new AutomationEngine($this->account);
        $threadId = 'th_incoming_multi_' . uniqid();

        // Message 1 arrives
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_in_1_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'in_lead@test.com',
            'sender_name' => 'Incoming Lead',
            'subject' => 'Hello Multi',
            'snippet' => 'Msg 1',
            'body' => 'Msg 1 Body',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('followup_scheduled', $res1['status']);

        // Message 2 arrives in same thread
        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_in_2_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'in_lead@test.com',
            'sender_name' => 'Incoming Lead',
            'subject' => 'Hello Multi 2',
            'snippet' => 'Msg 2',
            'body' => 'Msg 2 Body',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res2['status'], "Second incoming message must not schedule duplicate follow-up");

        $thread = EmailThread::findByAccountAndThreadId($this->account->id, $threadId);
        $fuJobs = Database::query(
            "SELECT * FROM scheduled_jobs WHERE thread_id = :tid AND job_type = 'follow_up' AND status = 'pending'",
            ['tid' => $thread->id]
        );
        $this->assertCount(1, $fuJobs, "Only 1 pending follow-up job should exist");
    }
}
