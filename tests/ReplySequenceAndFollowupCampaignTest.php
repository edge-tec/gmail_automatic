<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
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

class ReplySequenceAndFollowupCampaignTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;
    private QueueWorker $worker;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $sqlitePath = storage_path('database/test.sqlite');
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        new App();
        Database::resetConnection();
        \Database\MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();

        $this->user = User::create([
            'name' => 'Sequence Tester',
            'email' => 'seq_test_' . uniqid() . '@example.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'gmail_seq_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_' . uniqid(),
            'access_token' => 'access_token_mock',
            'refresh_token' => 'refresh_token_mock',
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createDefault($this->account->id);
        $this->worker = new QueueWorker();
    }

    /**
     * Test 1 & 3: 5 Configured Replies -> 5 replies allowed, 6th email is duplicate
     */
    public function testFiveConfiguredRepliesProgressUntilCompletedThenDuplicate(): void {
        $replySteps = [
            1 => ['message' => 'User Reply #1', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'User Reply #2', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            3 => ['message' => 'User Reply #3', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            4 => ['message' => 'User Reply #4', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            5 => ['message' => 'User Reply #5', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 5,
            'daily_reply_limit' => 50,
        ]);

        $sender = 'lead_five_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // Emails #1 to #5 must each be scheduled
        for ($i = 1; $i <= 5; $i++) {
            $msg = [
                'message_id' => "msg_5seq_{$i}_" . uniqid(),
                'thread_id' => 'th_5seq_' . uniqid(),
                'sender_email' => $sender,
                'sender_name' => 'Five Step Lead',
                'subject' => "Inquiry Step {$i}",
                'snippet' => "Body {$i}",
                'body' => "Body {$i}",
                'date' => date('Y-m-d H:i:s'),
            ];

            $res = $engine->processIncomingMessage($msg);
            $this->assertEquals('scheduled', $res['status'], "Incoming email #{$i} must be scheduled for Reply #{$i}");
            $this->assertEquals($i, $res['reply_step']);

            // Run worker to dispatch and mark step sent
            $this->worker->run(true);
        }

        // Verify recipient sequence is now completed
        $recipient = AutoReplyRecipient::findByAccountAndSender($this->account->id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals(5, $recipient->reply_sequence_step);
        $this->assertEquals('completed', $recipient->reply_sequence_status);

        // Email #6 from same sender -> must be skipped as DUPLICATE
        $msg6 = [
            'message_id' => 'msg_5seq_6_' . uniqid(),
            'thread_id' => 'th_5seq_6_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Five Step Lead',
            'subject' => 'Inquiry Step 6 (Extra)',
            'snippet' => 'Extra email',
            'body' => 'Extra email',
            'date' => date('Y-m-d H:i:s'),
        ];
        $res6 = $engine->processIncomingMessage($msg6);
        $this->assertEquals('skipped', $res6['status']);
        $this->assertTrue($res6['is_duplicate_traffic']);
        $this->assertStringContainsString('5/5 steps', $res6['reason']);

        // Verify Daily Usage: 1 traffic sequence = 1 daily reply count, 5 actual messages
        $usage = $this->account->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count'], '5 replies to 1 traffic sequence must consume only 1 daily reply count');
        $this->assertEquals(5, $usage['reply_messages_count'], '5 actual outgoing messages must be recorded in reply_messages_count');
    }

    /**
     * Test 5, 6, 7: Daily Reply Limit applies to NEW traffic sequences and does NOT block active sequences
     */
    public function testDailyReplyLimitCountsOnePerTrafficSequenceAndDoesNotBlockActiveSequence(): void {
        $replySteps = [
            1 => ['message' => 'Step 1 Message', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'Step 2 Message', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            3 => ['message' => 'Step 3 Message', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        // Set daily limit to 1
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 3,
            'daily_reply_limit' => 1,
        ]);

        $trafficA = 'traffic_a_' . uniqid() . '@test.com';
        $trafficB = 'traffic_b_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // 1. Traffic A sends Email 1 -> consumes the 1 allowed daily reply quota
        $r1 = $engine->processIncomingMessage([
            'message_id' => 'msg_a_1_' . uniqid(),
            'thread_id' => 'th_a_1_' . uniqid(),
            'sender_email' => $trafficA,
            'sender_name' => 'Traffic A',
            'subject' => 'Inquiry A1',
            'snippet' => 'Body A1',
            'body' => 'Body A1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r1['status']);
        $this->assertEquals(1, $r1['reply_step']);
        $this->worker->run(true);

        $usage = $this->account->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count'], 'Daily reply count must be 1');

        // 2. Traffic A sends Email 2 & Email 3 -> MUST NOT BE BLOCKED BY DAILY LIMIT (already active sequence)
        $r2 = $engine->processIncomingMessage([
            'message_id' => 'msg_a_2_' . uniqid(),
            'thread_id' => 'th_a_2_' . uniqid(),
            'sender_email' => $trafficA,
            'sender_name' => 'Traffic A',
            'subject' => 'Inquiry A2',
            'snippet' => 'Body A2',
            'body' => 'Body A2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r2['status'], 'Active sequence step 2 must not be blocked by daily limit');
        $this->assertEquals(2, $r2['reply_step']);
        $this->worker->run(true);

        $r3 = $engine->processIncomingMessage([
            'message_id' => 'msg_a_3_' . uniqid(),
            'thread_id' => 'th_a_3_' . uniqid(),
            'sender_email' => $trafficA,
            'sender_name' => 'Traffic A',
            'subject' => 'Inquiry A3',
            'snippet' => 'Body A3',
            'body' => 'Body A3',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r3['status'], 'Active sequence step 3 must not be blocked by daily limit');
        $this->assertEquals(3, $r3['reply_step']);
        $this->worker->run(true);

        // Daily reply quota must STILL be 1 (Traffic A = 1 daily reply sequence)
        $usageAfter = $this->account->getTodayUsage();
        $this->assertEquals(1, $usageAfter['reply_count'], 'Traffic A must only consume 1 daily reply count');
        $this->assertEquals(3, $usageAfter['reply_messages_count'], '3 actual outgoing messages recorded');

        // 3. Traffic B (NEW traffic) sends Email 1 -> Daily limit (1/1) reached, must be postponed to tomorrow
        $rB = $engine->processIncomingMessage([
            'message_id' => 'msg_b_1_' . uniqid(),
            'thread_id' => 'th_b_1_' . uniqid(),
            'sender_email' => $trafficB,
            'sender_name' => 'Traffic B',
            'subject' => 'Inquiry B1',
            'snippet' => 'Body B1',
            'body' => 'Body B1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $rB['status']);
        // Scheduled time must be postponed to tomorrow because limit for new traffic is reached
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $this->assertStringContainsString($tomorrowDate, $rB['scheduled_at'], 'New traffic must be postponed to tomorrow when daily limit reached');
    }

    /**
     * Test 2 & 4: 7 Configured Replies -> 7 replies allowed, 8th email is duplicate
     */
    public function testSevenConfiguredRepliesProgressUntilCompletedThenDuplicate(): void {
        $replySteps = [];
        for ($s = 1; $s <= 7; $s++) {
            $replySteps[$s] = ['message' => "Custom Reply #{$s} Content", 'delay_value' => 0, 'delay_unit' => 'seconds'];
        }

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 7,
            'daily_reply_limit' => 50,
        ]);

        $sender = 'lead_seven_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // Emails #1 to #7 must each be scheduled
        for ($i = 1; $i <= 7; $i++) {
            $msg = [
                'message_id' => "msg_7seq_{$i}_" . uniqid(),
                'thread_id' => 'th_7seq_' . uniqid(),
                'sender_email' => $sender,
                'sender_name' => 'Seven Step Lead',
                'subject' => "Inquiry Step {$i}",
                'snippet' => "Body {$i}",
                'body' => "Body {$i}",
                'date' => date('Y-m-d H:i:s'),
            ];

            $res = $engine->processIncomingMessage($msg);
            $this->assertEquals('scheduled', $res['status'], "Incoming email #{$i} must be scheduled for Reply #{$i}");
            $this->assertEquals($i, $res['reply_step']);

            // Run worker to dispatch and mark step sent
            $this->worker->run(true);
        }

        // Email #8 from same sender -> DUPLICATE
        $msg8 = [
            'message_id' => 'msg_7seq_8_' . uniqid(),
            'thread_id' => 'th_7seq_8_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Seven Step Lead',
            'subject' => 'Inquiry Step 8 (Extra)',
            'snippet' => 'Extra email',
            'body' => 'Extra email',
            'date' => date('Y-m-d H:i:s'),
        ];
        $res8 = $engine->processIncomingMessage($msg8);
        $this->assertEquals('skipped', $res8['status']);
        $this->assertTrue($res8['is_duplicate_traffic']);
        $this->assertStringContainsString('7/7 steps', $res8['reason']);
    }

    /**
     * Test 7: Multiple Senders have Independent Sequences
     */
    public function testMultipleSendersHaveIndependentSequences(): void {
        $replySteps = [
            1 => ['message' => 'Reply 1', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'Reply 2', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            3 => ['message' => 'Reply 3', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 3,
            'daily_reply_limit' => 50,
        ]);

        $senderA = 'sender_a_' . uniqid() . '@test.com';
        $senderB = 'sender_b_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // Sender A sends Email #1 -> Reply #1
        $resA1 = $engine->processIncomingMessage([
            'message_id' => 'msg_a_1_' . uniqid(),
            'thread_id' => 'th_a_' . uniqid(),
            'sender_email' => $senderA,
            'sender_name' => 'Sender A',
            'subject' => 'A1',
            'snippet' => 'A1',
            'body' => 'A1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $resA1['status']);
        $this->assertEquals(1, $resA1['reply_step']);

        // Sender B sends Email #1 -> Reply #1 (Independent of A)
        $resB1 = $engine->processIncomingMessage([
            'message_id' => 'msg_b_1_' . uniqid(),
            'thread_id' => 'th_b_' . uniqid(),
            'sender_email' => $senderB,
            'sender_name' => 'Sender B',
            'subject' => 'B1',
            'snippet' => 'B1',
            'body' => 'B1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $resB1['status']);
        $this->assertEquals(1, $resB1['reply_step']);
    }

    /**
     * Test 11, 12, 13, 14: Follow-up Campaign Counting (1 Email/Conversation with 5 Follow-ups = 1 Daily Follow Count)
     */
    public function testOneEmailWithFiveFollowupsCountsAsOneDailyFollow(): void {
        // Create 5 follow-up templates
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        for ($step = 1; $step <= 5; $step++) {
            FollowupTemplate::create([
                'user_id' => $this->user->id,
                'gmail_account_id' => $this->account->id,
                'name' => "Follow-up #{$step}",
                'step_number' => $step,
                'subject_type' => 'same_thread',
                'message' => "Custom Follow-up Step #{$step} Message",
                'delay_value' => 0,
                'delay_unit' => 'seconds',
                'status' => 'active',
            ]);
        }

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Initial Auto Reply',
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
        ]);

        $sender = 'lead_fu_5_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // 1. Incoming Email -> Schedules Auto-Reply
        $msg = [
            'message_id' => 'msg_fu5_' . uniqid(),
            'thread_id' => 'th_fu5_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Follow-up Lead',
            'subject' => 'Lead with 5 Followups',
            'snippet' => 'Snippet',
            'body' => 'Body text',
            'date' => date('Y-m-d H:i:s'),
        ];
        $res = $engine->processIncomingMessage($msg);
        $this->assertEquals('scheduled', $res['status']);

        // Run worker to send Auto Reply -> this automatically creates Follow-up Campaign & schedules Step 1
        $this->worker->run(true);

        // Run worker consecutively to process all 5 follow-up steps
        for ($step = 1; $step <= 5; $step++) {
            $this->worker->run(true);
        }

        // Verify Daily Usage:
        // Daily Followup Campaigns = 1
        // Daily Followup Messages Sent = 5
        $usage = DailyUsage::getOrCreate($this->account->id, date('Y-m-d'));
        $this->assertEquals(1, (int)$usage['followup_count'], '1 Conversation with 5 follow-ups must equal 1 Daily Follow Campaign count');
        $this->assertEquals(5, (int)$usage['followup_messages_count'], '5 actual follow-up emails were sent');
    }

    /**
     * Test 13: Three separate campaigns with multiple follow-ups -> Daily Follow count = 3
     */
    public function testThreeCampaignsWithMultipleFollowupsEqualsThreeDailyFollows(): void {
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        for ($step = 1; $step <= 3; $step++) {
            FollowupTemplate::create([
                'user_id' => $this->user->id,
                'gmail_account_id' => $this->account->id,
                'name' => "Follow-up #{$step}",
                'step_number' => $step,
                'subject_type' => 'same_thread',
                'message' => "Follow-up #{$step} Message",
                'delay_value' => 0,
                'delay_unit' => 'seconds',
                'status' => 'active',
            ]);
        }

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Initial Auto Reply',
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
        ]);

        $engine = new AutomationEngine($this->account);

        // Create 3 separate conversations
        for ($c = 1; $c <= 3; $c++) {
            $engine->processIncomingMessage([
                'message_id' => "msg_c{$c}_" . uniqid(),
                'thread_id' => "th_c{$c}_" . uniqid(),
                'sender_email' => "client_{$c}_" . uniqid() . '@test.com',
                'sender_name' => "Client {$c}",
                'subject' => "Subject {$c}",
                'snippet' => "Snippet {$c}",
                'body' => "Body {$c}",
                'date' => date('Y-m-d H:i:s'),
            ]);
        }

        // Process all auto-replies and follow-ups
        for ($r = 1; $r <= 10; $r++) {
            $this->worker->run(true);
        }

        $usage = DailyUsage::getOrCreate($this->account->id, date('Y-m-d'));
        // Total follow-up campaigns started must be 3
        $this->assertEquals(3, (int)$usage['followup_count'], '3 separate campaigns must equal 3 Daily Follow Campaign count');
    }

    /**
     * Test 19: Real Recipient Reply Cancels Remaining Follow-ups
     */
    public function testRealRecipientReplyCancelsRemainingFollowups(): void {
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'name' => 'Follow-up #1',
            'step_number' => 1,
            'subject_type' => 'same_thread',
            'message' => 'Step 1 Followup',
            'delay_value' => 3600, // 1 hour delay
            'delay_unit' => 'seconds',
            'status' => 'active',
        ]);

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Initial Auto Reply',
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
        ]);

        $sender = 'cancel_fu_' . uniqid() . '@test.com';
        $threadId = 'th_cancel_' . uniqid();
        $engine = new AutomationEngine($this->account);

        // 1. Initial Email -> Scheduled Auto-Reply
        $engine->processIncomingMessage([
            'message_id' => 'msg_canc_1_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $sender,
            'sender_name' => 'Cancel Recipient',
            'subject' => 'Project Inquiry',
            'snippet' => 'Snippet',
            'body' => 'Body',
            'date' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        // Send Auto Reply -> Creates campaign and schedules Followup Step 1 in 1 hour
        $this->worker->run(true);

        $thread = EmailThread::findByAccountAndThreadId($this->account->id, $threadId);
        $campaign = FollowupCampaign::findByThreadId($thread->id);
        $this->assertNotNull($campaign);
        $this->assertEquals('active', $campaign->campaign_status);

        // 2. Recipient sends genuine reply AFTER our outgoing reply timestamp
        $replyDate = date('Y-m-d H:i:s', strtotime($thread->last_outgoing_at) + 120);
        $engine->processIncomingMessage([
            'message_id' => 'msg_canc_2_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => $sender,
            'sender_name' => 'Cancel Recipient',
            'subject' => 'Re: Project Inquiry',
            'snippet' => 'Yes I am interested, lets meet tomorrow.',
            'body' => 'Yes I am interested, lets meet tomorrow.',
            'date' => $replyDate,
        ]);

        // Verify Campaign is marked replied and pending jobs cancelled
        $campaignUpdated = FollowupCampaign::find($campaign->id);
        $this->assertEquals('replied', $campaignUpdated->campaign_status);

        $followupJobs = Database::query("SELECT * FROM scheduled_jobs WHERE thread_id = :tid AND job_type = 'follow_up'", ['tid' => $thread->id]);
        foreach ($followupJobs as $pj) {
            $this->assertEquals('cancelled', $pj['status']);
        }
    }

    /**
     * Test 16: Existing active campaign completes all follow-ups even if daily limit for new campaigns is reached
     */
    public function testExistingActiveCampaignCompletesEvenIfNewCampaignDailyLimitIsReached(): void {
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        for ($s = 1; $s <= 3; $s++) {
            FollowupTemplate::create([
                'user_id' => $this->user->id,
                'gmail_account_id' => $this->account->id,
                'name' => "Step #{$s}",
                'step_number' => $s,
                'subject_type' => 'same_thread',
                'message' => "Campaign 1 Step #{$s} Message",
                'delay_value' => 0,
                'delay_unit' => 'seconds',
                'status' => 'active',
            ]);
        }

        // Set daily follow-up limit to 1 campaign
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Initial Auto Reply',
            'followup_enabled' => 1,
            'daily_followup_limit' => 1,
        ]);

        $engine = new AutomationEngine($this->account);

        // 1. Start Campaign #1
        $engine->processIncomingMessage([
            'message_id' => 'msg_camp1_' . uniqid(),
            'thread_id' => 'th_camp1_' . uniqid(),
            'sender_email' => 'camp1_lead@test.com',
            'sender_name' => 'Camp 1 Lead',
            'subject' => 'Camp 1 Subject',
            'snippet' => 'Camp 1 Snippet',
            'body' => 'Camp 1 Body',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Send Auto Reply -> Creates Campaign 1 and schedules Follow-up 1
        $this->worker->run(true);

        // Process Follow-up Step 1 -> Campaign 1 is counted today (1/1 limit reached)
        $this->worker->run(true);

        $usage = DailyUsage::getOrCreate($this->account->id, date('Y-m-d'));
        $this->assertEquals(1, (int)$usage['followup_count'], 'Campaign 1 counted');

        // Process Follow-up Step 2 and Step 3 -> Both must execute successfully even though limit is 1/1
        $this->worker->run(true);
        $this->worker->run(true);

        $usageAfter = DailyUsage::getOrCreate($this->account->id, date('Y-m-d'));
        $this->assertEquals(1, (int)$usageAfter['followup_count'], 'Campaign count remains 1');
        $this->assertEquals(3, (int)$usageAfter['followup_messages_count'], 'All 3 follow-up steps executed');
    }

    /**
     * Test 20 & 22: Message delete / empty protection cancels job without sending fallback
     */
    public function testDeletedOrEmptyMessageJobIsCancelledWithoutFallback(): void {
        Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        $template = FollowupTemplate::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'name' => 'Step 1 Template',
            'step_number' => 1,
            'subject_type' => 'same_thread',
            'message' => 'Will be emptied',
            'delay_value' => 3600, // 1 hour delay so it stays pending after auto-reply
            'delay_unit' => 'seconds',
            'status' => 'active',
        ]);

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Initial Auto Reply',
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
        ]);

        $engine = new AutomationEngine($this->account);
        $threadId = 'th_empty_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'msg_emp_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'empty_lead@test.com',
            'sender_name' => 'Empty Lead',
            'subject' => 'Subject',
            'snippet' => 'Snippet',
            'body' => 'Body',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Send auto reply -> schedules follow-up step 1 with 1 hour delay
        $this->worker->run(true);

        // Now user empties or deletes the template before follow-up step executes
        $template->update(['message' => '   ', 'status' => 'inactive']);

        // Make the pending follow-up job ready to process
        Database::execute("UPDATE scheduled_jobs SET scheduled_at = datetime('now', '-1 minute') WHERE job_type = 'follow_up'");

        // Worker runs
        $this->worker->run(true);

        // Job must be cancelled and no email sent with fallback boilerplate
        $thread = EmailThread::findByAccountAndThreadId($this->account->id, $threadId);
        $this->assertEquals(0, $thread->followup_count, 'Follow-up email must not be sent if template is empty or inactive');
    }

    /**
     * Test 11-18: Admin Clear Duplicate Traffic Resets Sender State and Allows Brand New Sequence
     */
    public function testAdminClearResetsDuplicateHistoryAndAllowsSenderToBeNewAgain(): void {
        $replySteps = [
            1 => ['message' => 'Step 1 Message', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'Step 2 Message', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 2,
            'daily_reply_limit' => 50,
        ]);

        $sender = 'reset_lead_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);

        // 1. Sender sends Email 1 -> gets Step 1
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_rst_1_' . uniqid(),
            'thread_id' => 'th_rst_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Reset Lead',
            'subject' => 'Inquiry 1',
            'snippet' => 'Text 1',
            'body' => 'Text 1',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res1['status']);
        $this->worker->run(true);

        // 2. Sender sends Email 2 -> gets Step 2
        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_rst_2_' . uniqid(),
            'thread_id' => 'th_rst_2_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Reset Lead',
            'subject' => 'Inquiry 2',
            'snippet' => 'Text 2',
            'body' => 'Text 2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $res2['status']);
        $this->worker->run(true);

        // Verify sequence completed
        $recipient = AutoReplyRecipient::findByAccountAndSender($this->account->id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals(2, $recipient->reply_sequence_step);
        $this->assertEquals('completed', $recipient->reply_sequence_status);

        // 3. Sender sends Email 3 -> DUPLICATE
        $res3 = $engine->processIncomingMessage([
            'message_id' => 'msg_rst_3_' . uniqid(),
            'thread_id' => 'th_rst_3_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Reset Lead',
            'subject' => 'Inquiry 3',
            'snippet' => 'Text 3',
            'body' => 'Text 3',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $res3['status']);
        $this->assertTrue($res3['is_duplicate_traffic']);

        // 4. Admin performs Clear Duplicate Traffic Reset
        $adminController = new \App\Controllers\AdminController();
        $request = new \App\Core\Request([
            'account_id' => $this->account->id,
            'redirect_to' => '/admin/skipped-emails',
        ]);
        $adminController->clearDuplicateTraffic($request);

        // Verify duplicate history is cleared from database
        $recipientAfter = AutoReplyRecipient::findByAccountAndSender($this->account->id, $sender);
        $this->assertNull($recipientAfter, 'Recipient record must be purged');

        $logsCount = \App\Models\SkippedEmailLog::countAdmin(['account_id' => $this->account->id]);
        $this->assertEquals(0, $logsCount, 'Skipped logs must be purged');

        // Verify unrelated data is completely untouched
        $userCheck = User::find($this->user->id);
        $this->assertNotNull($userCheck, 'User must remain untouched');
        $accCheck = GmailAccount::find($this->account->id);
        $this->assertNotNull($accCheck, 'Gmail account must remain untouched');
        $this->assertEquals('connected', $accCheck->status);

        // 5. Sender sends a NEW email after Admin Clear -> MUST BE RECOGNIZED AS BRAND NEW TRAFFIC
        $resFresh = $engine->processIncomingMessage([
            'message_id' => 'msg_rst_fresh_' . uniqid(),
            'thread_id' => 'th_rst_fresh_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Reset Lead',
            'subject' => 'Brand New Inquiry',
            'snippet' => 'Fresh Text',
            'body' => 'Fresh Text',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $resFresh['status'], 'Sender must be treated as new traffic after admin clear');
        $this->assertEquals(1, $resFresh['reply_step'], 'Sequence must start from Reply #1 again');
    }

    /**
     * Test 20: Same Traffic Across Multiple Connected Gmail Accounts Maintains One Authoritative Sequence
     */
    public function testSameSenderAcrossMultipleConnectedGmailAccountsMaintainsOneGlobalSequence(): void {
        // Create 4 connected accounts for the same user
        $accA = $this->account;
        $accB = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'account_b_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_b_' . uniqid(),
            'access_token' => 'mock_token',
            'status' => 'connected',
        ]);
        $accC = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'account_c_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_c_' . uniqid(),
            'access_token' => 'mock_token',
            'status' => 'connected',
        ]);
        $accD = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'account_d_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_d_' . uniqid(),
            'access_token' => 'mock_token',
            'status' => 'connected',
        ]);

        $replySteps = [
            1 => ['message' => 'Reply Message 1', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'Reply Message 2', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            3 => ['message' => 'Reply Message 3', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            4 => ['message' => 'Reply Message 4', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            5 => ['message' => 'Reply Message 5', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        foreach ([$accA, $accB, $accC, $accD] as $acc) {
            $s = AutomationSetting::createDefault($acc->id);
            $s->update([
                'auto_reply_enabled' => 1,
                'reply_message' => json_encode($replySteps),
                'max_reply_per_thread' => 5,
                'daily_reply_limit' => 50,
            ]);
        }

        $engineA = new AutomationEngine($accA);
        $engineB = new AutomationEngine($accB);
        $engineC = new AutomationEngine($accC);
        $engineD = new AutomationEngine($accD);

        $sender = 'multi_acc_lead_' . uniqid() . '@test.com';

        // 1. Email 1 -> Account A -> Reply #1
        $r1 = $engineA->processIncomingMessage([
            'message_id' => 'msg_multi_1_' . uniqid(),
            'thread_id' => 'th_multi_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc A',
            'snippet' => 'Text A',
            'body' => 'Text A',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r1['status']);
        $this->assertEquals(1, $r1['reply_step']);
        $this->worker->run(true);

        // 2. Email 2 -> Account B -> Reply #2 (MUST NOT BE REPLY #1)
        $r2 = $engineB->processIncomingMessage([
            'message_id' => 'msg_multi_2_' . uniqid(),
            'thread_id' => 'th_multi_2_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc B',
            'snippet' => 'Text B',
            'body' => 'Text B',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r2['status']);
        $this->assertEquals(2, $r2['reply_step'], 'Email to Acc B must receive Reply #2');
        $this->worker->run(true);

        // 3. Email 3 -> Account C -> Reply #3 (MUST NOT BE REPLY #1)
        $r3 = $engineC->processIncomingMessage([
            'message_id' => 'msg_multi_3_' . uniqid(),
            'thread_id' => 'th_multi_3_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc C',
            'snippet' => 'Text C',
            'body' => 'Text C',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r3['status']);
        $this->assertEquals(3, $r3['reply_step'], 'Email to Acc C must receive Reply #3');
        $this->worker->run(true);

        // 4. Email 4 -> Account A -> Reply #4
        $r4 = $engineA->processIncomingMessage([
            'message_id' => 'msg_multi_4_' . uniqid(),
            'thread_id' => 'th_multi_4_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc A again',
            'snippet' => 'Text A2',
            'body' => 'Text A2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r4['status']);
        $this->assertEquals(4, $r4['reply_step']);
        $this->worker->run(true);

        // 5. Email 5 -> Account D -> Reply #5
        $r5 = $engineD->processIncomingMessage([
            'message_id' => 'msg_multi_5_' . uniqid(),
            'thread_id' => 'th_multi_5_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc D',
            'snippet' => 'Text D',
            'body' => 'Text D',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r5['status']);
        $this->assertEquals(5, $r5['reply_step']);
        $this->worker->run(true);

        // 6. Email 6 -> Account B -> DUPLICATE / SKIP (ALL 5 REPLIES COMPLETED)
        $r6 = $engineB->processIncomingMessage([
            'message_id' => 'msg_multi_6_' . uniqid(),
            'thread_id' => 'th_multi_6_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Multi Lead',
            'subject' => 'Inquiry to Acc B again',
            'snippet' => 'Text B2',
            'body' => 'Text B2',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $r6['status'], 'Email 6 must be skipped as duplicate');
        $this->assertTrue($r6['is_duplicate_traffic']);
    }

    /**
     * Test 21: Existing Duplicate Sequence Reconciliation
     */
    public function testReconciliationMergesLegacyDuplicateRecordsIntoOneAuthoritativeSequence(): void {
        $sender = 'reconcile_lead_' . uniqid() . '@test.com';

        // Insert legacy rows simulating previous multi-account bugs
        Database::execute("INSERT INTO auto_reply_recipients (user_id, gmail_account_id, normalized_sender_email, reply_sequence_step, reply_sequence_total, reply_sequence_status, reply_status) VALUES (:uid, :acc, :sender, 1, 5, 'active', 'replied')", ['uid' => $this->user->id, 'acc' => $this->account->id, 'sender' => $sender]);
        Database::execute("INSERT INTO auto_reply_recipients (user_id, gmail_account_id, normalized_sender_email, reply_sequence_step, reply_sequence_total, reply_sequence_status, reply_status) VALUES (:uid, :acc, :sender, 3, 5, 'active', 'replied')", ['uid' => $this->user->id, 'acc' => $this->account->id, 'sender' => $sender]);
        Database::execute("INSERT INTO auto_reply_recipients (user_id, gmail_account_id, normalized_sender_email, reply_sequence_step, reply_sequence_total, reply_sequence_status, reply_status) VALUES (:uid, :acc, :sender, 2, 5, 'active', 'replied')", ['uid' => $this->user->id, 'acc' => $this->account->id, 'sender' => $sender]);

        // Run reconciliation
        AutoReplyRecipient::reconcileExistingDuplicates();

        // Verify only 1 record exists and has max step = 3
        $rows = Database::query("SELECT * FROM auto_reply_recipients WHERE user_id = :uid AND normalized_sender_email = :sender", ['uid' => $this->user->id, 'sender' => $sender]);
        $this->assertCount(1, $rows, 'Only one authoritative record must remain');
        $this->assertEquals(3, (int)$rows[0]['reply_sequence_step'], 'Highest completed step (3) must be preserved');
    }

    /**
     * Test 22: Exact Gmail Message ID Deduplication
     */
    public function testExactGmailMessageIdDeduplicationIsSeparateFromSequence(): void {
        $engine = new AutomationEngine($this->account);
        $sameMsgId = 'msg_exact_duplicate_' . uniqid();
        $sender = 'exact_lead_' . uniqid() . '@test.com';

        // Process message first time
        $r1 = $engine->processIncomingMessage([
            'message_id' => $sameMsgId,
            'thread_id' => 'th_exact_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Exact Lead',
            'subject' => 'Hello',
            'snippet' => 'World',
            'body' => 'World',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $r1['status']);

        // Process same message ID again
        $r2 = $engine->processIncomingMessage([
            'message_id' => $sameMsgId,
            'thread_id' => 'th_exact_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Exact Lead',
            'subject' => 'Hello',
            'snippet' => 'World',
            'body' => 'World',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $r2['status']);
        $this->assertEquals('Message already processed', $r2['reason']);
    }

    /**
     * Test 23: Require Recipient Reply Before Sending Next Auto-Reply (Toggle ON)
     */
    public function testRequireRecipientReplyBeforeNextAutoReplyEnabledSkipsUntilRecipientReplies(): void {
        $replySteps = [
            1 => ['message' => 'Message A (Step 1)', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => 'Message B (Step 2)', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            3 => ['message' => 'Message C (Step 3)', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($replySteps),
            'max_reply_per_thread' => 3,
            'daily_reply_limit' => 50,
            'require_recipient_reply_before_next_reply' => 1,
        ]);

        $sender = 'reply_gate_lead_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);
        $threadId1 = 'th_gate_1_' . uniqid();

        // 1. First incoming email -> Eligible for Reply #1
        $r1 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_1_' . uniqid(),
            'thread_id' => $threadId1,
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Inquiry 1',
            'snippet' => 'Hello 1',
            'body' => 'Hello 1',
            'date' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ]);
        $this->assertEquals('scheduled', $r1['status']);
        $this->assertEquals(1, $r1['reply_step']);

        // Worker sends Reply #1
        $this->worker->run(true);

        $recipient = AutoReplyRecipient::findByUserAndSender($this->user->id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals(1, $recipient->reply_sequence_step);

        // 2. Sender sends Email #2 in a new thread without replying to Reply #1 -> MUST BE SKIPPED
        $r2 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_2_' . uniqid(),
            'thread_id' => 'th_gate_2_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Unrelated Inquiry 2',
            'snippet' => 'Hello again',
            'body' => 'Hello again',
            'date' => date('Y-m-d H:i:s', strtotime('-8 minutes')),
        ]);
        $this->assertEquals('skipped', $r2['status']);
        $this->assertEquals('awaiting_recipient_reply', $r2['skip_type'] ?? '');

        // Verify sequence step DID NOT ADVANCE (remains 1)
        $recipient->refresh();
        $this->assertEquals(1, $recipient->reply_sequence_step, 'Skipped email must not advance sequence step');

        // 3. Sender sends Email #3 without replying -> MUST BE SKIPPED
        $r3 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_3_' . uniqid(),
            'thread_id' => 'th_gate_3_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Unrelated Inquiry 3',
            'snippet' => 'Hello again 3',
            'body' => 'Hello again 3',
            'date' => date('Y-m-d H:i:s', strtotime('-6 minutes')),
        ]);
        $this->assertEquals('skipped', $r3['status']);
        $this->assertEquals('awaiting_recipient_reply', $r3['skip_type'] ?? '');
        $recipient->refresh();
        $this->assertEquals(1, $recipient->reply_sequence_step);

        // 4. Now recipient REPLIES to our Reply #1 in thread 1
        $rReply1 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_rep_1_' . uniqid(),
            'thread_id' => $threadId1, // same thread where Reply #1 was sent
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Re: Inquiry 1',
            'snippet' => 'Thank you, here is my response to Reply 1',
            'body' => 'Thank you, here is my response to Reply 1',
            'is_reply' => true,
            'date' => date('Y-m-d H:i:s', strtotime('-4 minutes')),
        ]);
        $this->assertEquals('scheduled', $rReply1['status'], 'Genuine recipient reply must unlock Reply #2');
        $this->assertEquals(2, $rReply1['reply_step']);

        // Worker sends Reply #2
        $this->worker->run(true);
        $recipient->refresh();
        $this->assertEquals(2, $recipient->reply_sequence_step);

        // 5. Sender sends another unreplied email -> Skipped (awaiting reply to Reply #2)
        $r4 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_4_' . uniqid(),
            'thread_id' => 'th_gate_4_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Inquiry 4',
            'snippet' => 'Just checking in',
            'body' => 'Just checking in',
            'date' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
        ]);
        $this->assertEquals('skipped', $r4['status']);
        $this->assertEquals('awaiting_recipient_reply', $r4['skip_type'] ?? '');

        // 6. Recipient replies to Reply #2 -> Unlocks Reply #3
        $rReply2 = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_rep_2_' . uniqid(),
            'thread_id' => $threadId1,
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Re: Inquiry 1',
            'snippet' => 'Here is my reply to your 2nd message',
            'body' => 'Here is my reply to your 2nd message',
            'is_reply' => true,
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('scheduled', $rReply2['status']);
        $this->assertEquals(3, $rReply2['reply_step']);

        // Worker sends Reply #3 (Final Step)
        $this->worker->run(true);
        $recipient->refresh();
        $this->assertEquals(3, $recipient->reply_sequence_step);
        $this->assertEquals('completed', $recipient->reply_sequence_status);

        // 7. Subsequent email after completion -> DUPLICATE TRAFFIC
        $rFinal = $engine->processIncomingMessage([
            'message_id' => 'msg_gate_after_done_' . uniqid(),
            'thread_id' => $threadId1,
            'sender_email' => $sender,
            'sender_name' => 'Gate Lead',
            'subject' => 'Re: Inquiry 1',
            'snippet' => 'Another email',
            'body' => 'Another email',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $rFinal['status']);
        $this->assertTrue($rFinal['is_duplicate_traffic'] ?? false);

        // Verify Daily Quota
        $usage = $this->account->getTodayUsage();
        $this->assertEquals(1, $usage['reply_count'], '1 traffic sequence must consume only 1 daily reply count');
        $this->assertEquals(3, $usage['reply_messages_count'], '3 actual outgoing messages recorded');
    }

    /**
     * Test large rich-text reply messages with embedded base64 images exceeding standard TEXT 64KB limit
     */
    public function testLargeRichTextReplyMessageWithImages(): void {
        // Generate a 120KB payload with rich text and base64 image data
        $largeBase64Image = '<p>Hello!</p><p><img src="data:image/png;base64,' . str_repeat('A', 120000) . '"></p>';
        $replySteps = [
            1 => ['message' => $largeBase64Image, 'delay_value' => 0, 'delay_unit' => 'seconds'],
            2 => ['message' => '<p>Second step with <a href="https://example.com">Link</a></p>', 'delay_value' => 0, 'delay_unit' => 'seconds'],
        ];

        $encoded = json_encode($replySteps, JSON_UNESCAPED_UNICODE);
        $this->assertGreaterThan(100000, strlen($encoded));

        $updated = $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => $encoded,
            'max_reply_per_thread' => 3,
            'daily_reply_limit' => 50,
        ]);
        $this->assertTrue($updated);

        $this->settings = AutomationSetting::findByAccountId($this->account->id);
        $this->assertNotNull($this->settings);
        $this->assertEquals(2, $this->settings->getTotalConfiguredReplySteps());
        $this->assertEquals($largeBase64Image, $this->settings->getReplyMessageForStep(1));

        // Ensure automation engine and queue worker can process the message without issue
        $sender = 'large_img_lead_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_large_img_' . uniqid(),
            'thread_id' => 'th_large_img_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Rich Text Lead',
            'subject' => 'Product Inquiry',
            'snippet' => 'Interested in your software',
            'body' => 'Interested in your software',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
        $this->worker->run(true);

        $recipient = AutoReplyRecipient::findByUserAndSender($this->account->user_id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals(1, $recipient->reply_sequence_step);
    }
}


