<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\ScheduledJob;
use App\Models\DailyUsage;
use App\Models\EmailMessage;
use App\Models\AutoReplyRecipient;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;
use Database\MigrationRunner;

class HistoricalEmailBaselineTest extends TestCase {
    private User $user;
    private GmailAccount $account;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $sqlitePath = storage_path('database/test.sqlite');
        if (file_exists($sqlitePath)) {
            unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        config('_reset_');
        Database::resetConnection();

        new App();
        MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();

        // Create test user
        $this->user = User::create([
            'name' => 'Baseline Test User',
            'email' => 'baseline_test_' . uniqid() . '@example.com',
            'password' => 'secret123',
            'role' => 'user',
            'subscription_status' => 'active',
            'plan_id' => 'growth',
        ]);

        // Connect Gmail Account at a known time
        $now = date('Y-m-d H:i:s');
        $this->account = GmailAccount::createOrUpdate([
            'user_id' => $this->user->id,
            'gmail_email' => 'business_' . uniqid() . '@gmail.com',
            'google_user_id' => 'goog_' . uniqid(),
            'access_token' => 'mock_token',
            'refresh_token' => 'mock_refresh',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'connected',
        ]);

        // Enable auto reply
        $settings = $this->account->getSettings();
        $settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Hello, thank you for contacting us after we connected!',
            'reply_delay' => 0,
        ]);
    }

    public function testHistoricalEmailArrivedBeforeConnectionIsNeverRepliedTo(): void {
        $engine = new AutomationEngine($this->account);

        // Pre-existing historical email that arrived 2 hours BEFORE the account was connected
        $historicalDate = date('Y-m-d H:i:s', strtotime($this->account->connected_at) - 7200);

        $msgId = 'hist_msg_' . uniqid();
        $msgData = [
            'message_id' => $msgId,
            'id' => $msgId,
            'thread_id' => 'thread_hist_' . uniqid(),
            'sender_email' => 'oldcustomer@example.com',
            'sender_name' => 'Old Customer',
            'subject' => 'Inquiry from last week',
            'snippet' => 'Can you send pricing?',
            'body' => 'Can you send pricing for your services?',
            'date' => $historicalDate,
            'in_reply_to' => null,
            'is_reply' => false,
        ];

        $result = $engine->processIncomingMessage($msgData);

        // Assert skipped
        $this->assertEquals('skipped', $result['status']);
        $this->assertStringContainsString('Historical email', $result['reason']);

        // Verify message is saved as is_historical = 1
        $savedMsg = EmailMessage::findByAccountAndMessageId($this->account->id, $msgData['message_id']);
        $this->assertNotNull($savedMsg);
        $this->assertEquals(1, $savedMsg->is_historical);
        $this->assertEquals('historical', $savedMsg->status);

        // Assert NO scheduled jobs were created
        $jobs = ScheduledJob::findPendingByThreadId($savedMsg->thread_id);
        $this->assertCount(0, $jobs);

        // Assert NO auto reply recipient lead created
        $recipient = AutoReplyRecipient::findByUserAndSender($this->user->id, 'oldcustomer@example.com');
        $this->assertNull($recipient);

        // Assert Daily usage remains 0
        $usage = DailyUsage::getOrCreate($this->account->id);
        $this->assertEquals(0, $usage['reply_messages_count']);
    }

    public function testNewIncomingEmailArrivedAfterConnectionIsAutoReplied(): void {
        $engine = new AutomationEngine($this->account);

        // Genuinely NEW email that arrived 1 minute AFTER account connected
        $newDate = date('Y-m-d H:i:s', strtotime($this->account->connected_at) + 60);

        $newMsgId = 'new_msg_' . uniqid();
        $msgData = [
            'message_id' => $newMsgId,
            'id' => $newMsgId,
            'thread_id' => 'thread_new_' . uniqid(),
            'sender_email' => 'newlead@example.com',
            'sender_name' => 'New Lead',
            'subject' => 'Interested in your product',
            'snippet' => 'Please provide info',
            'body' => 'Please provide more details on your plans.',
            'date' => $newDate,
            'in_reply_to' => null,
            'is_reply' => false,
        ];

        $result = $engine->processIncomingMessage($msgData);

        // Assert scheduled or processed
        $this->assertContains($result['status'], ['scheduled', 'sent', 'processed']);

        // Verify message is NOT marked historical
        $savedMsg = EmailMessage::findByAccountAndMessageId($this->account->id, $msgData['message_id']);
        $this->assertNotNull($savedMsg);
        $this->assertEquals(0, $savedMsg->is_historical);

        // Assert scheduled job was created
        $jobs = ScheduledJob::findPendingByThreadId($savedMsg->thread_id);
        $this->assertGreaterThan(0, count($jobs));

        // Execute queue worker
        $worker = new QueueWorker();
        $worker->run(true, 10);

        // Assert Daily usage incremented
        $usage = DailyUsage::getOrCreate($this->account->id);
        $this->assertGreaterThanOrEqual(1, $usage['reply_messages_count']);
    }

    public function testClearLeadsDoesNotCauseHistoricalEmailsToBeProcessed(): void {
        $engine = new AutomationEngine($this->account);
        $historicalDate = date('Y-m-d H:i:s', strtotime($this->account->connected_at) - 3600);

        $msgId = 'hist_clear_test_' . uniqid();
        $msgData = [
            'message_id' => $msgId,
            'id' => $msgId,
            'thread_id' => 'thread_clear_' . uniqid(),
            'sender_email' => 'clientbefore@example.com',
            'sender_name' => 'Client Before',
            'subject' => 'Past discussion',
            'snippet' => 'Just following up on past discussion',
            'body' => 'Just following up on past discussion',
            'date' => $historicalDate,
            'in_reply_to' => null,
            'is_reply' => false,
        ];

        // 1. Process historical email
        $res1 = $engine->processIncomingMessage($msgData);
        $this->assertEquals('skipped', $res1['status']);

        // 2. Simulate User clicking "Clear All Leads" or deleting database leads/messages
        Database::execute("DELETE FROM auto_reply_recipients WHERE user_id = :uid", ['uid' => $this->user->id]);
        Database::execute("DELETE FROM email_messages WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);

        // 3. Re-poll the same historical message after leads cleared
        $res2 = $engine->processIncomingMessage($msgData);

        // Assert still blocked based on authoritative connected_at baseline!
        $this->assertEquals('skipped', $res2['status']);
        $this->assertStringContainsString('Historical email', $res2['reason']);
        $this->assertNull(AutoReplyRecipient::findByUserAndSender($this->user->id, 'clientbefore@example.com'));
    }

    public function testReconnectPreservesOriginalConnectedAtBaseline(): void {
        $originalConnectedAt = $this->account->connected_at;
        $this->assertNotEmpty($originalConnectedAt);

        // Simulate reconnecting Google OAuth
        $reconnected = GmailAccount::createOrUpdate([
            'user_id' => $this->user->id,
            'gmail_email' => $this->account->gmail_email,
            'google_user_id' => 'goog_' . uniqid(),
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'connected',
        ]);

        // Assert original connected_at was preserved
        $this->assertEquals($originalConnectedAt, $reconnected->connected_at);
        $this->assertEquals($this->account->id, $reconnected->id);
    }

    public function testQueueWorkerCancelsHistoricalThreadJobsBeforeSending(): void {
        // Create a historical thread
        $thread = \App\Models\EmailThread::createOrGet($this->account->id, 'hist_thread_' . uniqid(), [
            'sender_email' => 'oldie@example.com',
            'sender_name' => 'Oldie',
            'subject' => 'Old topic',
            'automation_status' => 'historical',
        ]);

        // Manually inject a scheduled job for this historical thread
        $job = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => json_encode(['reply_step' => 1, 'recipient_email' => 'oldie@example.com']),
            'scheduled_at' => date('Y-m-d H:i:s', time() - 60),
            'status' => 'pending',
            'max_attempts' => 3,
        ]);

        $worker = new QueueWorker();
        $processed = $worker->processJob($job);

        // Worker should catch it and cancel without sending
        $this->assertTrue($processed);
        $freshJob = ScheduledJob::find($job->id);
        $this->assertEquals('cancelled', $freshJob->status);
        $this->assertStringContainsString('historical baseline', $freshJob->last_error);
    }
}
