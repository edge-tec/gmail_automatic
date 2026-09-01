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
use App\Models\AutoReplyRecipient;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;

class ContinuousAutomationFixTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;
    private QueueWorker $worker;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $sqlitePath = storage_path('database/test.sqlite');
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        config('_reset_');
        \App\Core\Database::resetConnection();

        new App();
        MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();
        MigrationRunner::run();

        $this->user = User::create([
            'name' => 'Continuous Test User',
            'email' => 'continuous_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'business_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_' . uniqid(),
            'access_token' => 'access_token_mock',
            'refresh_token' => 'refresh_token_mock',
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createDefault($this->account->id);
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello {{first_name}}, we received your note and will review it.'],
            ]),
            'max_reply_per_thread' => 3,
            'daily_reply_limit' => 100,
            'followup_enabled' => 1,
            'daily_followup_limit' => 100,
            'working_days' => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'working_start' => '00:00',
            'working_end' => '23:59',
        ]);

        // Create 3 follow-up templates
        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'name' => 'Follow-up #1',
            'step_number' => 1,
            'delay_value' => 0,
            'delay_unit' => 'hours',
            'message' => 'Hi {{first_name}}, following up on our previous note.',
            'status' => 'active',
        ]);

        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'name' => 'Follow-up #2',
            'step_number' => 2,
            'delay_value' => 0,
            'delay_unit' => 'hours',
            'message' => 'Hi {{first_name}}, checking in once more.',
            'status' => 'active',
        ]);

        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'name' => 'Follow-up #3',
            'step_number' => 3,
            'delay_value' => 0,
            'delay_unit' => 'hours',
            'message' => 'Hi {{first_name}}, final note from our team.',
            'status' => 'active',
        ]);

        $this->worker = new QueueWorker();
    }

    /**
     * Test 1: 5 Different Senders each receive an Auto Reply (Multiple Senders Continuous Operation)
     */
    public function testMultipleUniqueSendersAllReceiveAutoReplies(): void {
        $engine = new AutomationEngine($this->account);
        $senders = [
            'lead1@example.com',
            'lead2@example.com',
            'lead3@example.com',
            'lead4@example.com',
            'lead5@example.com',
        ];

        foreach ($senders as $idx => $sEmail) {
            $res = $engine->processIncomingMessage([
                'message_id' => 'msg_' . uniqid(),
                'thread_id' => 'th_' . uniqid(),
                'sender_email' => $sEmail,
                'sender_name' => "Lead {$idx}",
                'subject' => "Inquiry from {$sEmail}",
                'snippet' => "Hello from {$sEmail}",
                'body' => "Hello from {$sEmail}",
                'date' => date('Y-m-d H:i:s'),
            ]);

            $this->assertEquals('scheduled', $res['status'], "Sender {$sEmail} should be scheduled for auto-reply");
        }

        // Process queue batch (sends auto-replies and any ready follow-ups)
        $this->worker->run(true, 50);

        // Verify that all 5 threads recorded reply_count >= 1
        $repliedThreadsCount = Database::first(
            "SELECT COUNT(*) as c FROM email_threads WHERE gmail_account_id = :acc AND reply_count >= 1",
            ['acc' => $this->account->id]
        )['c'];

        $this->assertEquals(5, $repliedThreadsCount, "All 5 unique leads should have received an auto-reply");
    }

    /**
     * Test 2: Duplicate Traffic - 1 sender sending 4 emails only gets 1 Auto Reply
     */
    public function testDuplicateTrafficReceivesOnlyOneAutoReply(): void {
        $engine = new AutomationEngine($this->account);
        $sameSender = 'duplicate_traffic_' . uniqid() . '@example.com';
        $threadId = 'th_dup_' . uniqid();

        // Email 1 -> Scheduled
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_d1_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $sameSender,
            'sender_name' => 'Duplicate Sender',
            'subject' => 'Inquiry 1',
            'snippet' => 'Text',
            'body' => 'Text',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res1['status']);

        // Process Email 1
        $this->worker->run(true, 50);

        // Email 2 -> Skipped duplicate
        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_d2_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $sameSender,
            'sender_name' => 'Duplicate Sender',
            'subject' => 'Inquiry 2',
            'snippet' => 'Text',
            'body' => 'Text',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res2['status']);
        $this->assertTrue($res2['is_duplicate_traffic'] ?? false);

        // Email 3 -> Skipped duplicate
        $res3 = $engine->processIncomingMessage([
            'message_id' => 'msg_d3_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $sameSender,
            'sender_name' => 'Duplicate Sender',
            'subject' => 'Inquiry 3',
            'snippet' => 'Text',
            'body' => 'Text',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res3['status']);

        // Verify only 1 auto reply in DB for this recipient
        $recipientRecord = AutoReplyRecipient::findByAccountAndSender($this->account->id, $sameSender);
        $this->assertNotNull($recipientRecord);
        $this->assertEquals('replied', $recipientRecord->reply_status);
    }

    /**
     * Test 3: Follow-up sequence runs completely without requiring new incoming emails
     * Auto Reply -> Follow-up #1 -> Follow-up #2 -> Follow-up #3
     */
    public function testFollowupSequenceRunsAllStepsContinuously(): void {
        $engine = new AutomationEngine($this->account);
        $leadEmail = 'followup_test_' . uniqid() . '@example.com';
        $threadId = 'th_fup_' . uniqid();

        // 1. Initial email received
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_init_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $leadEmail,
            'sender_name' => 'Followup Lead',
            'subject' => 'Need Info',
            'snippet' => 'Need pricing info',
            'body' => 'Need pricing info',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res['status']);

        // Queue worker processes auto-reply and sequential follow-up steps
        $this->worker->run(true, 50);

        $thread = EmailThread::findByAccountAndThreadId($this->account->id, $threadId);
        $this->assertNotNull($thread);
        $this->assertEquals(1, $thread->reply_count, "Auto reply should be sent");
        $this->assertEquals(3, $thread->followup_count, "All 3 follow-ups should be sent sequentially");

        // Campaign should now be completed
        $campaign = FollowupCampaign::findByThreadId($thread->id);
        $this->assertNotNull($campaign);
        $this->assertEquals('completed', $campaign->campaign_status);
    }

    /**
     * Test 4: Stale 'processing' jobs are auto-recovered
     */
    public function testStaleProcessingJobRecovery(): void {
        $thread = EmailThread::createOrGet($this->account->id, 'th_stale_' . uniqid(), [
            'sender_email' => 'stale@test.com',
            'subject' => 'Stale Test',
        ]);

        $staleJob = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => ['recipient_email' => 'stale@test.com'],
            'scheduled_at' => date('Y-m-d H:i:s', time() - 600),
            'status' => 'pending',
        ]);

        // Manually force into 'processing' with old updated_at
        Database::execute(
            "UPDATE scheduled_jobs SET status = 'processing', updated_at = :old WHERE id = :id",
            ['id' => $staleJob->id, 'old' => date('Y-m-d H:i:s', time() - 600)]
        );

        // Run recovery
        $recoveredCount = QueueWorker::recoverStaleJobs(5);
        $this->assertGreaterThanOrEqual(1, $recoveredCount);

        $refreshed = ScheduledJob::find($staleJob->id);
        $this->assertEquals('pending', $refreshed->status);

        $staleJob->update(['status' => 'cancelled']);
    }
}
