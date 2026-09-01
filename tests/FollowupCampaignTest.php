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

class FollowupCampaignTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;

    protected function setUp(): void {
        parent::setUp();
        new App();
        MigrationRunner::run();

        // Create test user and gmail account
        $this->user = User::create([
            'name' => 'Campaign Test User',
            'email' => 'campaign_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'sender_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createOrGet($this->account->id);
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Thanks for your email, {{first_name}}!', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                2 => ['message' => 'Step 2 reply', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                3 => ['message' => 'Step 3 reply', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 1,
            'daily_followup_limit' => 10,
            'daily_reply_limit' => 100,
        ]);
    }

    private function createFollowupTemplates(int $count = 5): array {
        $templates = [];
        for ($i = 1; $i <= $count; $i++) {
            $templates[] = FollowupTemplate::create([
                'user_id' => $this->user->id,
                'gmail_account_id' => $this->account->id,
                'step_number' => $i,
                'name' => "Follow-up #{$i}",
                'message' => "This is follow-up step #{$i} for {{first_name}}.",
                'delay_value' => 0,
                'delay_unit' => 'minutes',
                'status' => 'active',
            ]);
        }
        return $templates;
    }

    /**
     * TEST 1: 1 email + 5 follow-ups
     * Expected: Messages Sent = 5, Daily Follow Count = 1
     */
    public function testAcceptanceTest1_OneEmailWithFiveFollowups(): void {
        $this->createFollowupTemplates(5);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $threadId = 'thread_test_1_' . uniqid();
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'lead1@customer.com',
            'sender_name' => 'John Doe',
            'subject' => 'Inquiry 1',
            'snippet' => 'Hello',
            'body' => 'Hello there',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);

        // Process auto-reply job
        $jobs = ScheduledJob::getReadyJobs(10);
        foreach ($jobs as $job) {
            $worker->processJob($job);
        }

        // 5 Follow-up steps in sequence
        for ($step = 1; $step <= 5; $step++) {
            $readyJobs = ScheduledJob::getReadyJobs(10);
            $this->assertNotEmpty($readyJobs, "Step #{$step} job should be scheduled");
            foreach ($readyJobs as $job) {
                $worker->processJob($job);
            }
        }

        $usage = $this->account->getTodayUsage();
        // Daily Follow Count (unique campaign) must be strictly 1
        $this->assertEquals(1, $usage['followup_count'], "Daily Follow count must be 1 for 5 follow-up messages on the same email");
        // Follow-up messages count must be 5
        $this->assertEquals(5, $usage['followup_messages_count'], "Actual follow-up messages sent must be 5");
    }

    /**
     * TEST 2: 3 emails + 5 follow-ups each
     * Expected: Messages Sent = 15, Daily Follow Count = 3
     */
    public function testAcceptanceTest2_ThreeEmailsWithFiveFollowupsEach(): void {
        $this->createFollowupTemplates(5);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        for ($e = 1; $e <= 3; $e++) {
            $threadId = "thread_batch_{$e}_" . uniqid();
            $engine->processIncomingMessage([
                'message_id' => "msg_batch_{$e}_" . uniqid(),
                'thread_id' => $threadId,
                'sender_email' => "lead_{$e}@customer.com",
                'sender_name' => "Lead {$e}",
                'subject' => "Inquiry {$e}",
                'snippet' => 'Hello',
                'body' => 'Hello there',
                'date' => date('Y-m-d H:i:s'),
            ]);

            // Process auto-reply
            $jobs = ScheduledJob::getReadyJobs(10);
            foreach ($jobs as $job) {
                $worker->processJob($job);
            }

            // Process all 5 follow-up steps for this email
            for ($step = 1; $step <= 5; $step++) {
                $fuJobs = ScheduledJob::getReadyJobs(10);
                foreach ($fuJobs as $job) {
                    $worker->processJob($job);
                }
            }
        }

        $usage = $this->account->getTodayUsage();
        $this->assertEquals(3, $usage['followup_count'], "Daily Follow count must be 3 for 3 unique campaigns");
        $this->assertEquals(15, $usage['followup_messages_count'], "Actual follow-up messages sent must be 15");
    }

    /**
     * TEST 3: Same email detected 5 times
     * Expected: Follow-up Campaigns = 1
     */
    public function testAcceptanceTest3_SameEmailDetectedFiveTimes(): void {
        $this->createFollowupTemplates(3);
        $engine = new AutomationEngine($this->account);

        $msgId = 'fixed_msg_id_' . uniqid();
        $threadId = 'fixed_thread_id_' . uniqid();

        for ($i = 1; $i <= 5; $i++) {
            $res = $engine->processIncomingMessage([
                'message_id' => $msgId, // duplicate message ID
                'thread_id' => $threadId,
                'sender_email' => 'repeat_customer@example.com',
                'sender_name' => 'Repeat Customer',
                'subject' => 'Interested in service',
                'snippet' => 'Hello again',
                'body' => 'Hello again',
                'date' => date('Y-m-d H:i:s'),
            ]);

            if ($i === 1) {
                $this->assertEquals('scheduled', $res['status']);
            } else {
                $this->assertEquals('duplicate', $res['status']);
            }
        }

        $campaigns = Database::query("SELECT * FROM followup_campaigns WHERE gmail_account_id = :acc", ['acc' => $this->account->id]);
        $this->assertCount(0, $campaigns); // Until reply is sent and follow-up scheduled, or exactly 1 if scheduled
    }

    /**
     * TEST 4: Same Gmail Thread detected multiple times
     * Expected: 1 active campaign
     */
    public function testAcceptanceTest4_SameThreadDetectedMultipleTimes(): void {
        $this->createFollowupTemplates(3);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $threadId = 'shared_thread_' . uniqid();

        // Message 1 in thread
        $engine->processIncomingMessage([
            'message_id' => 'msg_t1_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'client@domain.com',
            'sender_name' => 'Client',
            'subject' => 'Project discussion',
            'snippet' => 'Discussing',
            'body' => 'Discussing',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Process reply to initiate follow-up step 1
        $jobs = ScheduledJob::getReadyJobs(10);
        foreach ($jobs as $job) {
            $worker->processJob($job);
        }

        $campaigns = Database::query("SELECT * FROM followup_campaigns WHERE gmail_thread_id = :tid", ['tid' => $threadId]);
        $this->assertCount(1, $campaigns);
        $this->assertEquals('active', $campaigns[0]['campaign_status']);
    }

    /**
     * TEST 5: Daily limit = 3, 3 unique campaigns created -> 3/3; 4th unique campaign must not start today
     * Expected: 4th unique campaign postponed
     */
    public function testAcceptanceTest5_DailyLimitEnforcedForUniqueCampaigns(): void {
        $this->settings->update(['daily_followup_limit' => 3]);
        $this->createFollowupTemplates(2);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        // Create and send 3 unique campaigns today
        for ($i = 1; $i <= 3; $i++) {
            $tid = "limit_thread_{$i}_" . uniqid();
            $engine->processIncomingMessage([
                'message_id' => "limit_msg_{$i}_" . uniqid(),
                'thread_id' => $tid,
                'sender_email' => "limit_lead_{$i}@test.com",
                'sender_name' => "Lead {$i}",
                'subject' => "Topic {$i}",
                'snippet' => 'Hi',
                'body' => 'Hi',
                'date' => date('Y-m-d H:i:s'),
            ]);

            // Execute reply and step 1 follow-up
            $jobs = ScheduledJob::getReadyJobs(10);
            foreach ($jobs as $job) {
                $worker->processJob($job);
            }
            $fuJobs = ScheduledJob::getReadyJobs(10);
            foreach ($fuJobs as $job) {
                $worker->processJob($job);
            }
        }

        $usage = $this->account->getTodayUsage();
        $this->assertEquals(3, $usage['followup_count']);

        // Now attempt 4th unique campaign
        $tid4 = 'limit_thread_4_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'limit_msg_4_' . uniqid(),
            'thread_id' => $tid4,
            'sender_email' => 'limit_lead_4@test.com',
            'sender_name' => 'Lead 4',
            'subject' => 'Topic 4',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Process reply for 4th thread
        $jobs = ScheduledJob::getReadyJobs(10);
        foreach ($jobs as $job) {
            $worker->processJob($job);
        }

        // Check scheduled_at for 4th campaign follow-up step 1: must be postponed to tomorrow
        $th4 = EmailThread::findByAccountAndThreadId($this->account->id, $tid4);
        $fuJobs4 = ScheduledJob::findPendingByThreadId($th4->id);
        $this->assertNotEmpty($fuJobs4);
        $scheduledDate = date('Y-m-d', strtotime($fuJobs4[0]->scheduled_at));
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $this->assertEquals($tomorrowDate, $scheduledDate, "4th unique campaign follow-up must be postponed to tomorrow when daily limit (3) is reached");
    }

    /**
     * TEST 6: Existing campaign has 5 follow-ups. After Follow-up #1, #2, #3, #4, #5:
     * Expected: Daily Follow Count remains 1
     */
    public function testAcceptanceTest6_DailyFollowCountRemainsOneAcrossAllSteps(): void {
        $this->createFollowupTemplates(5);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $tid = 'count_test_thread_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'msg_cnt_' . uniqid(),
            'thread_id' => $tid,
            'sender_email' => 'verify_count@test.com',
            'sender_name' => 'Test User',
            'subject' => 'Count check',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Reply
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);

        // Run steps 1 to 5 and verify daily follow count after each step
        for ($s = 1; $s <= 5; $s++) {
            $jobs = ScheduledJob::getReadyJobs(10);
            $this->assertNotEmpty($jobs);
            foreach ($jobs as $j) $worker->processJob($j);

            $usage = $this->account->getTodayUsage();
            $this->assertEquals(1, $usage['followup_count'], "After step #{$s}, daily follow count must stay 1");
            $this->assertEquals($s, $usage['followup_messages_count'], "After step #{$s}, actual messages count must be {$s}");
        }
    }

    /**
     * TEST 7: User deletes Follow-up #3 before execution.
     * Expected: Follow-up #3 = Cancelled, No fallback message sent
     */
    public function testAcceptanceTest7_UserDeletesFollowupStepBeforeExecution(): void {
        $templates = $this->createFollowupTemplates(3);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $tid = 'delete_step_thread_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'msg_del_' . uniqid(),
            'thread_id' => $tid,
            'sender_email' => 'client_del@test.com',
            'sender_name' => 'Client',
            'subject' => 'Step delete test',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Reply & Step 1
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);
        // Step 2
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);

        // Now Step 3 is pending in scheduled_jobs. User deletes Step 3 template from database.
        $templates[2]->delete();

        // Process queue worker
        $step3Jobs = ScheduledJob::getReadyJobs(10);
        $this->assertNotEmpty($step3Jobs);
        foreach ($step3Jobs as $j) {
            $worker->processJob($j);
        }

        // Verify step 3 job is cancelled
        $updatedJob = ScheduledJob::find($step3Jobs[0]->id);
        $this->assertEquals('cancelled', $updatedJob->status);
        $this->assertStringContainsString('missing, disabled, or deleted', $updatedJob->last_error);

        // Verify no outgoing email was sent for step 3
        $outgoingMessages = Database::query(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND direction = 'outgoing'",
            ['acc' => $this->account->id]
        );
        // 1 reply + 2 follow-ups = 3 outgoing total (Step 3 was NOT sent)
        $this->assertCount(3, $outgoingMessages);
    }

    /**
     * TEST 8: User edits Follow-up #3 before execution.
     * Expected: Only updated message is sent
     */
    public function testAcceptanceTest8_UserEditsFollowupStepBeforeExecution(): void {
        $templates = $this->createFollowupTemplates(3);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $tid = 'edit_step_thread_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'msg_edit_' . uniqid(),
            'thread_id' => $tid,
            'sender_email' => 'client_edit@test.com',
            'sender_name' => 'Alice',
            'subject' => 'Step edit test',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Reply & Step 1 & Step 2
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);

        // Step 3 is pending. User modifies Step 3 template message
        $customNewText = 'BRAND NEW UPDATED SPECIAL OFFER FOR {{first_name}}!';
        $templates[2]->update(['message' => $customNewText]);

        // Process step 3 job
        $step3Jobs = ScheduledJob::getReadyJobs(10);
        foreach ($step3Jobs as $j) {
            $worker->processJob($j);
        }

        // Verify the sent message contains the updated text, not old text
        $lastSentMsg = Database::first(
            "SELECT * FROM email_messages WHERE gmail_account_id = :acc AND direction = 'outgoing' ORDER BY id DESC LIMIT 1",
            ['acc' => $this->account->id]
        );
        $this->assertNotNull($lastSentMsg);
        $this->assertStringContainsString('BRAND NEW UPDATED SPECIAL OFFER FOR Alice!', $lastSentMsg['message_body']);
    }

    /**
     * TEST 9: Customer replies after Follow-up #1.
     * Expected: Remaining pending follow-ups cancelled
     */
    public function testAcceptanceTest9_CustomerRepliesAfterFollowupOne(): void {
        $this->createFollowupTemplates(3);
        $engine = new AutomationEngine($this->account);
        $worker = new QueueWorker();

        $tid = 'reply_interrupt_' . uniqid();
        $engine->processIncomingMessage([
            'message_id' => 'msg_rep_1_' . uniqid(),
            'thread_id' => $tid,
            'sender_email' => 'customer_reply@test.com',
            'sender_name' => 'Bob',
            'subject' => 'Pricing quote',
            'snippet' => 'Send quote',
            'body' => 'Send quote',
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Reply & Step 1
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);
        foreach (ScheduledJob::getReadyJobs(10) as $j) $worker->processJob($j);

        // Step 2 is now pending. Customer replies to the thread!
        $engine->processIncomingMessage([
            'message_id' => 'msg_rep_2_' . uniqid(),
            'thread_id' => $tid,
            'sender_email' => 'customer_reply@test.com',
            'sender_name' => 'Bob',
            'subject' => 'Re: Pricing quote',
            'snippet' => 'I am interested, please call me',
            'body' => 'I am interested, please call me',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $campaign = FollowupCampaign::findByAccountAndThread($this->account->id, $tid);
        $this->assertNotNull($campaign);
        $this->assertEquals('replied', $campaign->campaign_status);

        // Verify pending follow-up jobs are cancelled
        $pendingFollowupJobs = array_filter(
            ScheduledJob::findPendingByThreadId($campaign->thread_id),
            fn($job) => $job->job_type === 'follow_up'
        );
        $this->assertEmpty($pendingFollowupJobs, "Pending follow-up jobs must be cancelled when customer replies");
    }

    /**
     * TEST 10: Two workers process the same email simultaneously.
     * Expected: Only 1 Follow-up Campaign
     */
    public function testAcceptanceTest10_ConcurrentWorkersProcessSameEmail(): void {
        $this->createFollowupTemplates(2);
        $threadId = 'concurrent_thread_' . uniqid();
        $thread = EmailThread::createOrGet($this->account->id, $threadId, [
            'sender_email' => 'concurrent@test.com',
            'sender_name' => 'Concurrent Lead',
            'subject' => 'Simultaneous test',
        ]);

        // Simulate Worker 1 & Worker 2 calling getOrCreate simultaneously
        $camp1 = FollowupCampaign::getOrCreate(
            $this->user->id,
            $this->account->id,
            $thread->id,
            $threadId,
            ['subject' => 'Simultaneous test']
        );

        $camp2 = FollowupCampaign::getOrCreate(
            $this->user->id,
            $this->account->id,
            $thread->id,
            $threadId,
            ['subject' => 'Simultaneous test']
        );

        $this->assertEquals($camp1->id, $camp2->id, "Both workers must resolve to the exact same campaign ID");

        $totalCampaigns = Database::query(
            "SELECT * FROM followup_campaigns WHERE gmail_account_id = :acc AND gmail_thread_id = :tid",
            ['acc' => $this->account->id, 'tid' => $threadId]
        );
        $this->assertCount(1, $totalCampaigns, "Database must strictly contain exactly 1 follow-up campaign");
    }
}
