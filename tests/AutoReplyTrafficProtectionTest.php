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
use App\Models\DailyUsage;
use App\Models\AutoReplyRecipient;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;
use Database\MigrationRunner;

class AutoReplyTrafficProtectionTest extends TestCase {
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
        new App();

        // Create test user and gmail accounts
        $this->user = User::create([
            'name' => 'Traffic Test User',
            'email' => 'traffic_user_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->accountA = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'support_a_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->accountB = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'support_b_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->settingsA = AutomationSetting::createOrGet($this->accountA->id);
        $this->settingsA->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello {{first_name}}, thank you for contacting Support A.', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 0,
            'daily_reply_limit' => 10,
        ]);

        $this->settingsB = AutomationSetting::createOrGet($this->accountB->id);
        $this->settingsB->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello {{first_name}}, thank you for contacting Support B.', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 0,
            'daily_reply_limit' => 10,
        ]);
    }

    /**
     * TEST 1: Same traffic source sends 5 emails to Gmail Account A.
     * Expected: Email #1 -> Scheduled/Sent, Emails #2..#5 -> Skipped (Duplicate traffic).
     * Total Auto Replies = 1.
     */
    public function testSingleTrafficSourceFiveEmailsGeneratesOneAutoReply(): void {
        $engine = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();
        $sender = 'customer_five_' . uniqid() . '@example.com';

        $results = [];
        for ($i = 1; $i <= 5; $i++) {
            $res = $engine->processIncomingMessage([
                'message_id' => 'msg_5_' . $i . '_' . uniqid(),
                'thread_id' => 'th_5_' . $i . '_' . uniqid(),
                'sender_email' => $sender,
                'sender_name' => 'Alice Customer',
                'subject' => "Inquiry #{$i}",
                'snippet' => "Inquiry body #{$i}",
                'body' => "Inquiry body #{$i}",
                'date' => date('Y-m-d H:i:s'),
            ]);
            $results[] = $res;
        }

        // First email must be scheduled
        $this->assertEquals('scheduled', $results[0]['status']);
        $this->assertNotEmpty($results[0]['job_id']);

        // Emails 2 to 5 must be skipped due to duplicate traffic
        for ($i = 1; $i < 5; $i++) {
            $this->assertEquals('skipped', $results[$i]['status']);
            $this->assertStringContainsString('Duplicate traffic', $results[$i]['reason']);
        }

        // Process queue jobs
        $readyJobs = ScheduledJob::getReadyJobs(10);
        $this->assertCount(1, $readyJobs, "Exactly 1 Auto Reply job must exist in queue");

        foreach ($readyJobs as $job) {
            $worker->processJob($job);
        }

        // Verify recipient status in database
        $recipient = AutoReplyRecipient::findByAccountAndSender($this->accountA->id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals('replied', $recipient->reply_status);
        $this->assertNotNull($recipient->reply_sent_at);

        // Daily usage reply_count must be exactly 1
        $usage = $this->accountA->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count']);
    }

    /**
     * TEST 2: Normalized email address protection.
     * Capitalization and formatted addresses (John@Example.com, JOHN@EXAMPLE.COM, "John Doe" <john@example.com>)
     * must be normalized and treated as the identical traffic source.
     */
    public function testNormalizedSenderEmailDuplicateProtection(): void {
        $engine = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();
        $baseUser = 'normalize_' . uniqid();

        // Email 1: lowercase
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_norm_1_' . uniqid(),
            'thread_id' => 'th_norm_1_' . uniqid(),
            'sender_email' => "{$baseUser}@example.com",
            'sender_name' => 'John Doe',
            'subject' => 'Question 1',
            'snippet' => 'Question 1',
            'body' => 'Question 1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res1['status']);

        // Email 2: Mixed Case
        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_norm_2_' . uniqid(),
            'thread_id' => 'th_norm_2_' . uniqid(),
            'sender_email' => strtoupper($baseUser) . '@EXAMPLE.COM',
            'sender_name' => 'John Doe',
            'subject' => 'Question 2',
            'snippet' => 'Question 2',
            'body' => 'Question 2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res2['status']);
        $this->assertStringContainsString('Duplicate traffic', $res2['reason']);

        // Email 3: Formatted RFC header "John Doe <...>"
        $res3 = $engine->processIncomingMessage([
            'message_id' => 'msg_norm_3_' . uniqid(),
            'thread_id' => 'th_norm_3_' . uniqid(),
            'sender_email' => "<{$baseUser}@example.com>",
            'sender_name' => 'John Doe',
            'subject' => 'Question 3',
            'snippet' => 'Question 3',
            'body' => 'Question 3',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res3['status']);
        $this->assertStringContainsString('Duplicate traffic', $res3['reason']);

        // Process queue
        foreach (ScheduledJob::getReadyJobs(10) as $job) {
            $worker->processJob($job);
        }

        $usage = $this->accountA->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count']);
    }

    /**
     * TEST 3: Different Connected Gmail Accounts Scoping.
     * Same sender sends to Account A and Account B.
     * Both Account A and Account B must independently send 1 Auto Reply each.
     */
    public function testDifferentGmailAccountsScopedIndependently(): void {
        $engineA = new AutomationEngine($this->accountA);
        $engineB = new AutomationEngine($this->accountB);
        $worker = new QueueWorker();
        $sender = 'cross_account_' . uniqid() . '@domain.com';

        // Send to Account A
        $resA1 = $engineA->processIncomingMessage([
            'message_id' => 'msg_acc_a_1_' . uniqid(),
            'thread_id' => 'th_acc_a_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Cross Lead',
            'subject' => 'Inquiry for A',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $resA1['status']);

        // Send second email to Account A -> must be skipped
        $resA2 = $engineA->processIncomingMessage([
            'message_id' => 'msg_acc_a_2_' . uniqid(),
            'thread_id' => 'th_acc_a_2_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Cross Lead',
            'subject' => 'Another Inquiry for A',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resA2['status']);

        // Send to Account B -> must be SCHEDULED because Account B is independent
        $resB1 = $engineB->processIncomingMessage([
            'message_id' => 'msg_acc_b_1_' . uniqid(),
            'thread_id' => 'th_acc_b_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Cross Lead',
            'subject' => 'Inquiry for B',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $resB1['status']);

        // Process jobs
        foreach (ScheduledJob::getReadyJobs(10) as $job) {
            $worker->processJob($job);
        }

        // Verify recipient records for both accounts
        $recA = AutoReplyRecipient::findByAccountAndSender($this->accountA->id, $sender);
        $recB = AutoReplyRecipient::findByAccountAndSender($this->accountB->id, $sender);

        $this->assertNotNull($recA);
        $this->assertEquals('replied', $recA->reply_status);
        $this->assertNotNull($recB);
        $this->assertEquals('replied', $recB->reply_status);
    }

    /**
     * TEST 4: Multiple messages arriving rapidly before the first auto reply is processed.
     * Expected: Only 1 pending job exists; all other rapid incoming messages are skipped.
     */
    public function testRapidIncomingEmailsBeforeProcessingCreateOnlyOneJob(): void {
        $engine = new AutomationEngine($this->accountA);
        $sender = 'rapid_' . uniqid() . '@example.com';

        // 3 rapid emails
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_rap_1',
            'thread_id' => 'th_rap_1',
            'sender_email' => $sender,
            'sender_name' => 'Rapid Sender',
            'subject' => 'Rapid 1',
            'snippet' => '1',
            'body' => '1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res1['status']);

        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_rap_2',
            'thread_id' => 'th_rap_2',
            'sender_email' => $sender,
            'sender_name' => 'Rapid Sender',
            'subject' => 'Rapid 2',
            'snippet' => '2',
            'body' => '2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res2['status']);

        $res3 = $engine->processIncomingMessage([
            'message_id' => 'msg_rap_3',
            'thread_id' => 'th_rap_3',
            'sender_email' => $sender,
            'sender_name' => 'Rapid Sender',
            'subject' => 'Rapid 3',
            'snippet' => '3',
            'body' => '3',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res3['status']);

        // Check scheduled_jobs table: exactly 1 auto_reply job for account
        $pendingJobs = ScheduledJob::getReadyJobs(10);
        $autoReplyJobs = array_filter($pendingJobs, fn($j) => $j->gmail_account_id === $this->accountA->id && $j->job_type === 'auto_reply');
        $this->assertCount(1, $autoReplyJobs);
    }

    /**
     * TEST 5: Concurrency Simulation.
     * 10 identical/concurrent emails processed with 5 workers.
     * Expected: Auto Reply Sent = 1, Duplicate Replies = 0.
     */
    public function testConcurrencyTenEmailsFiveWorkersSingleReply(): void {
        $sender = 'concur_traffic_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();

        $scheduledJobIds = [];

        for ($i = 1; $i <= 10; $i++) {
            $res = $engine->processIncomingMessage([
                'message_id' => "msg_concur_{$i}_" . uniqid(),
                'thread_id' => 'th_concur_' . uniqid(),
                'sender_email' => $sender,
                'sender_name' => 'Concurrent Lead',
                'subject' => "Inquiry {$i}",
                'snippet' => "Body {$i}",
                'body' => "Body {$i}",
                'date' => date('Y-m-d H:i:s'),
            ]);

            if ($res['status'] === 'scheduled') {
                $scheduledJobIds[] = $res['job_id'];
            }
        }

        $this->assertCount(1, $scheduledJobIds, "Exactly 1 Auto Reply job should be scheduled from 10 incoming emails");

        // Simulate 5 workers running concurrently
        for ($w = 1; $w <= 5; $w++) {
            $ready = ScheduledJob::getReadyJobs(10);
            foreach ($ready as $j) {
                $worker->processJob($j);
            }
        }

        // Verify sent outgoing messages in database
        $sentMessages = Database::query(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND recipient = :sender AND direction = 'outgoing'",
            ['acc' => $this->accountA->id, 'sender' => $sender]
        );
        $this->assertCount(1, $sentMessages, "Exactly 1 outgoing email message must exist in database");

        $usage = $this->accountA->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count']);
    }

    /**
     * TEST 6: Message Edit & Delete Live Validation.
     * User updates message -> new message sent.
     * User deletes message -> job cancelled, NO fallback sent.
     */
    public function testMessageLiveEditAndDeleteProtection(): void {
        $engine = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();
        $sender = 'edit_delete_' . uniqid() . '@test.com';

        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_ed_1_' . uniqid(),
            'thread_id' => 'th_ed_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Eve Editor',
            'subject' => 'Product questions',
            'snippet' => 'Tell me more',
            'body' => 'Tell me more',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
        $jobId = $res['job_id'];

        // User edits message before worker processes
        $this->settingsA->update([
            'reply_message' => json_encode([
                1 => ['message' => 'SPECIAL DISCOUNT CODE 999 FOR {{first_name}}', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $job = ScheduledJob::find($jobId);
        $worker->processJob($job);

        $sentMsg = Database::first(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND recipient = :sender ORDER BY id DESC LIMIT 1",
            ['acc' => $this->accountA->id, 'sender' => $sender]
        );
        $this->assertNotNull($sentMsg);
        $this->assertStringContainsString('SPECIAL DISCOUNT CODE 999 FOR Eve', $sentMsg['message_body']);

        // Now test empty/deleted message for a new sender
        $sender2 = 'empty_msg_' . uniqid() . '@test.com';
        // User deletes reply message
        $this->settingsA->update(['reply_message' => null]);

        $resEmpty = $engine->processIncomingMessage([
            'message_id' => 'msg_empty_1_' . uniqid(),
            'thread_id' => 'th_empty_1_' . uniqid(),
            'sender_email' => $sender2,
            'sender_name' => 'Empty Lead',
            'subject' => 'Hello',
            'snippet' => 'Hello',
            'body' => 'Hello',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resEmpty['status']);
        $this->assertStringContainsString('Message content is missing', $resEmpty['reason']);

        // Verify zero outgoing messages were sent for sender2
        $sentMsg2 = Database::first(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND recipient = :sender",
            ['acc' => $this->accountA->id, 'sender' => $sender2]
        );
        $this->assertNull($sentMsg2, "No fallback/default message must be sent when user configured message is empty");
    }

    /**
     * TEST 7: Daily Reply Limit counts unique traffic sources.
     * Limit = 3.
     * Traffic A (3 emails) -> 1 reply
     * Traffic B (2 emails) -> 1 reply
     * Traffic C (4 emails) -> 1 reply
     * Total = 3/3 daily limit reached.
     * Traffic D -> postponed to tomorrow.
     */
    public function testDailyReplyLimitEnforcedOnUniqueTrafficSources(): void {
        $this->settingsA->update(['daily_reply_limit' => 3]);
        $engine = new AutomationEngine($this->accountA);
        $worker = new QueueWorker();

        $trafficA = 'traffic_a_' . uniqid() . '@test.com';
        $trafficB = 'traffic_b_' . uniqid() . '@test.com';
        $trafficC = 'traffic_c_' . uniqid() . '@test.com';
        $trafficD = 'traffic_d_' . uniqid() . '@test.com';

        // Traffic A: 3 emails
        for ($i = 1; $i <= 3; $i++) {
            $engine->processIncomingMessage([
                'message_id' => "msg_ta_{$i}_" . uniqid(),
                'thread_id' => "th_ta_{$i}_" . uniqid(),
                'sender_email' => $trafficA,
                'sender_name' => 'Traffic A',
                'subject' => "Inquiry A{$i}",
                'snippet' => "A{$i}",
                'body' => "A{$i}",
                'date' => date('Y-m-d H:i:s'),
            ]);
        }

        // Traffic B: 2 emails
        for ($i = 1; $i <= 2; $i++) {
            $engine->processIncomingMessage([
                'message_id' => "msg_tb_{$i}_" . uniqid(),
                'thread_id' => "th_tb_{$i}_" . uniqid(),
                'sender_email' => $trafficB,
                'sender_name' => 'Traffic B',
                'subject' => "Inquiry B{$i}",
                'snippet' => "B{$i}",
                'body' => "B{$i}",
                'date' => date('Y-m-d H:i:s'),
            ]);
        }

        // Traffic C: 4 emails
        for ($i = 1; $i <= 4; $i++) {
            $engine->processIncomingMessage([
                'message_id' => "msg_tc_{$i}_" . uniqid(),
                'thread_id' => "th_tc_{$i}_" . uniqid(),
                'sender_email' => $trafficC,
                'sender_name' => 'Traffic C',
                'subject' => "Inquiry C{$i}",
                'snippet' => "C{$i}",
                'body' => "C{$i}",
                'date' => date('Y-m-d H:i:s'),
            ]);
        }

        // Process all ready jobs for A, B, C
        foreach (ScheduledJob::getReadyJobs(10) as $j) {
            $worker->processJob($j);
        }

        $usage = $this->accountA->getTodayUsage();
        $this->assertEquals(3, $usage['reply_count'], "Daily reply usage must be exactly 3 for 3 unique traffic sources");

        // Traffic D arrives after limit reached -> scheduled for tomorrow
        $resD = $engine->processIncomingMessage([
            'message_id' => "msg_td_1_" . uniqid(),
            'thread_id' => "th_td_1_" . uniqid(),
            'sender_email' => $trafficD,
            'sender_name' => 'Traffic D',
            'subject' => "Inquiry D",
            'snippet' => "D",
            'body' => "D",
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $resD['status']);
        $jobD = ScheduledJob::find($resD['job_id']);
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $this->assertStringContainsString($tomorrowDate, $jobD->scheduled_at, "4th unique traffic reply must be postponed to tomorrow");
    }
}
