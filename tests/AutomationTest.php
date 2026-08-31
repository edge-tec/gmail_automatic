<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\Encryption;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\FollowupTemplate;
use App\Models\ScheduledJob;
use App\Services\AutomationEngine;

class AutomationTest extends TestCase {
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
        \Database\MigrationRunner::run();
    }

    public function testEncryptionRoundTrip(): void {
        $secretToken = "ya29.a0AfH6SMD_SampleRefreshToken_GoogleOAuth2_XYZ_123456789";
        
        $encrypted = Encryption::encrypt($secretToken);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($secretToken, $encrypted);

        $decrypted = Encryption::decrypt($encrypted);
        $this->assertEquals($secretToken, $decrypted);
    }

    public function testVariableSubstitution(): void {
        $account = new GmailAccount();
        $account->id = 1;
        $account->gmail_email = 'test@example.com';

        $engine = new AutomationEngine($account);

        $template = "Hello {{first_name}} {{last_name}},\nThank you for email about {{subject}}. Your email is {{sender_email}}.";
        $data = [
            'sender_name' => 'Jane Doe',
            'sender_email' => 'jane@domain.com',
            'subject' => 'Inquiry regarding pricing',
            'date' => '2026-08-31',
        ];

        $rendered = $engine->renderVariables($template, $data);

        $this->assertStringContainsString('Hello Jane Doe,', $rendered);
        $this->assertStringContainsString('about Inquiry regarding pricing.', $rendered);
        $this->assertStringContainsString('Your email is jane@domain.com.', $rendered);
    }

    public function testIncomingMessageAndReplyScheduling(): void {
        // Create user
        $user = User::create([
            'name' => 'Tester',
            'email' => 'testuser_' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        // Create connected Gmail account
        $account = GmailAccount::createOrUpdate([
            'user_id' => $user->id,
            'gmail_email' => 'connected_' . uniqid() . '@gmail.com',
            'access_token' => 'access_tok_123',
            'refresh_token' => 'refresh_tok_123',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $settings = $account->getSettings() ?? AutomationSetting::createDefault($account->id);
        $settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'CUSTOM-AUTO-REPLY-TEST-98765: This is a manually configured user message.', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                2 => ['message' => 'Custom Step 2: Contact on WhatsApp', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                3 => ['message' => 'Custom Step 3: Here is the link', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE)
        ]);

        $engine = new AutomationEngine($account);

        $msgId = 'msg_' . uniqid();
        $threadId = 'thread_' . uniqid();

        $msgData = [
            'message_id' => $msgId,
            'thread_id' => $threadId,
            'sender_email' => 'customer@client.com',
            'sender_name' => 'John Customer',
            'subject' => 'Need product information',
            'snippet' => 'Hello, please send details...',
            'body' => 'Hello, please send details about your product.',
            'date' => date('Y-m-d H:i:s'),
            'message_id_header' => '<msg-header-1@mail.gmail.com>',
            'references' => null,
        ];

        $result = $engine->processIncomingMessage($msgData);

        $this->assertEquals('scheduled', $result['status']);
        $this->assertNotEmpty($result['job_id']);

        // Test Deduplication
        $duplicateResult = $engine->processIncomingMessage($msgData);
        $this->assertEquals('duplicate', $duplicateResult['status']);

        // Verify thread in database
        $thread = EmailThread::findByAccountAndThreadId($account->id, $threadId);
        $this->assertNotNull($thread);
        $this->assertEquals('active', $thread->automation_status);

        // Verify pending jobs
        $pending = ScheduledJob::findPendingByThreadId($thread->id);
        $this->assertCount(1, $pending);
        $this->assertEquals('auto_reply', $pending[0]->job_type);

        // Test Multi-Step Sequential Reply: Contact sends follow-up email in same thread
        $replyMsgId = 'msg_reply_' . uniqid();
        $replyMsgData = [
            'message_id' => $replyMsgId,
            'thread_id' => $threadId,
            'sender_email' => 'customer@client.com',
            'sender_name' => 'John Customer',
            'subject' => 'Re: Need product information',
            'snippet' => 'Thanks, got it!',
            'body' => 'Thanks for the quick response! Here are my questions.',
            'date' => date('Y-m-d H:i:s'),
            'message_id_header' => '<msg-header-2@mail.gmail.com>',
            'references' => '<msg-header-1@mail.gmail.com>',
        ];

        // Simulate that 1 automated reply had already been sent
        $thread->update(['reply_count' => 1]);

        $replyResult = $engine->processIncomingMessage($replyMsgData);
        $this->assertEquals('scheduled', $replyResult['status']);
        $this->assertEquals(2, $replyResult['reply_step']);

        // Check thread status remains active for next conversation
        $updatedThread = EmailThread::find($thread->id);
        $this->assertEquals('active', $updatedThread->automation_status);

        // When per-thread limit is reached (e.g., 3 replies)
        $thread->update(['reply_count' => 3]);
        $limitResult = $engine->processIncomingMessage([
            'message_id' => 'msg_reply_3_' . uniqid(),
            'thread_id' => $threadId,
            'sender_email' => 'customer@client.com',
            'sender_name' => 'John Customer',
            'subject' => 'Re: Still need info',
            'snippet' => 'One more thing',
            'body' => 'One more thing',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('limit_reached', $limitResult['status']);
    }

    public function testAdminBlacklistFilters(): void {
        $user = User::create([
            'name' => 'Admin Tester',
            'email' => 'admin_test_' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $account = GmailAccount::createOrUpdate([
            'user_id' => $user->id,
            'gmail_email' => 'acc_bl_' . uniqid() . '@gmail.com',
            'access_token' => 'access_tok_bl',
            'refresh_token' => 'refresh_tok_bl',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $settings = $account->getSettings() ?? AutomationSetting::createDefault($account->id);
        $settings->update(['auto_reply_enabled' => 1]);

        $engine = new AutomationEngine($account);

        // 1. Test Blacklisted Email
        \App\Models\SystemSetting::set('blacklist_emails', 'spammer@block.com, badguy@evil.com');
        $resEmail = $engine->processIncomingMessage([
            'message_id' => 'msg_bl_1',
            'thread_id' => 'th_bl_1',
            'sender_email' => 'spammer@block.com',
            'sender_name' => 'Spammer',
            'subject' => 'Hello there',
            'snippet' => 'Buy now',
            'body' => 'Buy now',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resEmail['status']);
        $this->assertStringContainsString('blacklisted by admin', $resEmail['reason']);

        // 2. Test Blacklisted Domain
        \App\Models\SystemSetting::set('blacklist_domains', 'spamdomain.org, junkmail.net');
        $resDomain = $engine->processIncomingMessage([
            'message_id' => 'msg_bl_2',
            'thread_id' => 'th_bl_2',
            'sender_email' => 'anyone@spamdomain.org',
            'sender_name' => 'Anyone',
            'subject' => 'Important proposal',
            'snippet' => 'Hey',
            'body' => 'Hey',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resDomain['status']);
        $this->assertStringContainsString('blacklisted by admin', $resDomain['reason']);

        // 3. Test Blacklisted Keyword / Content
        \App\Models\SystemSetting::set('blacklist_keywords', 'unsubscribe, out of office, delivery failure');
        $resContent = $engine->processIncomingMessage([
            'message_id' => 'msg_bl_3',
            'thread_id' => 'th_bl_3',
            'sender_email' => 'normal@client.com',
            'sender_name' => 'Normal User',
            'subject' => 'Automatic Reply: Out of Office until Monday',
            'snippet' => 'I am out of office',
            'body' => 'I am currently out of office.',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resContent['status']);
        $this->assertStringContainsString('blacklisted keyword', $resContent['reason']);

        // 4. Test User Account-Level Blacklisted Email
        $settings->update([
            'reply_message' => json_encode([
                1 => ['message' => 'Hello', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                '_blacklist' => [
                    'emails' => 'blockeduser@test.com',
                    'domains' => 'blockedhost.com',
                    'keywords' => 'casino bonus',
                ]
            ])
        ]);

        $resUserEmail = $engine->processIncomingMessage([
            'message_id' => 'msg_bl_4',
            'thread_id' => 'th_bl_4',
            'sender_email' => 'blockeduser@test.com',
            'sender_name' => 'Blocked User',
            'subject' => 'Hello',
            'snippet' => 'Test',
            'body' => 'Test',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resUserEmail['status']);
        $this->assertStringContainsString('in account blacklist', $resUserEmail['reason']);

        // 5. Test User Account-Level Blacklisted Keyword
        $resUserKeyword = $engine->processIncomingMessage([
            'message_id' => 'msg_bl_5',
            'thread_id' => 'th_bl_5',
            'sender_email' => 'regular@client.com',
            'sender_name' => 'Regular User',
            'subject' => 'Claim your casino bonus today!',
            'snippet' => 'Claim now',
            'body' => 'Claim now',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resUserKeyword['status']);
        $this->assertStringContainsString('account blacklisted keyword', $resUserKeyword['reason']);

        // 6. Test Custom Automation Rule (Domain Filter -> Skip)
        \App\Models\AutomationRule::create([
            'user_id' => $user->id,
            'gmail_account_id' => $account->id,
            'rule_type' => 'sender_domain',
            'rule_value' => 'customfilter.com',
            'action' => 'skip',
            'status' => 'active',
        ]);

        $resRuleSkip = $engine->processIncomingMessage([
            'message_id' => 'msg_rule_1',
            'thread_id' => 'th_rule_1',
            'sender_email' => 'contact@customfilter.com',
            'sender_name' => 'Custom Filter Lead',
            'subject' => 'Inquiry',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resRuleSkip['status']);
        $this->assertStringContainsString('filter rule', $resRuleSkip['reason']);

        // 7. Test Anti-Spam (Multiple recipients in To header)
        $resMultiTo = $engine->processIncomingMessage([
            'message_id' => 'msg_spam_1',
            'thread_id' => 'th_spam_1',
            'sender_email' => 'blast@spammer.com',
            'sender_name' => 'Blast Spammer',
            'to' => 'victim1@test.com, victim2@test.com, ' . $account->gmail_email,
            'subject' => 'Huge discounts',
            'snippet' => 'Buy now',
            'body' => 'Buy now',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resMultiTo['status']);
        $this->assertStringContainsString('Multiple recipients', $resMultiTo['reason']);

        // 8. Test Anti-Spam (CC recipients detected)
        $resCc = $engine->processIncomingMessage([
            'message_id' => 'msg_spam_2',
            'thread_id' => 'th_spam_2',
            'sender_email' => 'blast2@spammer.com',
            'sender_name' => 'Blast Spammer 2',
            'to' => $account->gmail_email,
            'cc' => 'other1@test.com, other2@test.com',
            'subject' => 'Special promotion',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resCc['status']);
        $this->assertStringContainsString('CC', $resCc['reason']);

        // 9. Test Anti-Spam (BCC recipients detected)
        $resBcc = $engine->processIncomingMessage([
            'message_id' => 'msg_spam_3',
            'thread_id' => 'th_spam_3',
            'sender_email' => 'blast3@spammer.com',
            'sender_name' => 'Blast Spammer 3',
            'to' => $account->gmail_email,
            'bcc' => 'hidden_lead@test.com',
            'subject' => 'Secret deal',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resBcc['status']);
        $this->assertStringContainsString('BCC', $resBcc['reason']);

        // 10. Test Domain Extension Blacklist (.xyz, .bi, .net)
        \App\Models\SystemSetting::set('blacklist_domains', 'spamdomain.org, .xyz, .bi, .net');
        
        $resXyz = $engine->processIncomingMessage([
            'message_id' => 'msg_tld_xyz',
            'thread_id' => 'th_tld_xyz',
            'sender_email' => 'spammer@promo.xyz',
            'sender_name' => 'XYZ Sender',
            'to' => $account->gmail_email,
            'subject' => 'Offers for you',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resXyz['status']);
        $this->assertStringContainsString('domain extension', $resXyz['reason']);

        $resBi = $engine->processIncomingMessage([
            'message_id' => 'msg_tld_bi',
            'thread_id' => 'th_tld_bi',
            'sender_email' => 'lead@company.bi',
            'sender_name' => 'BI Sender',
            'to' => $account->gmail_email,
            'subject' => 'Hello',
            'snippet' => 'Details',
            'body' => 'Details',
            'date' => date('Y-m-d H:i:s'),
        ]);
        $this->assertEquals('skipped', $resBi['status']);
        $this->assertStringContainsString('domain extension', $resBi['reason']);
    }

    public function testStrictUserConfiguredMessageEnforcement(): void {
        $user = User::create([
            'name' => 'Strict Test User',
            'email' => 'stricttest_' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $account = GmailAccount::createOrUpdate([
            'user_id' => $user->id,
            'gmail_email' => 'strict_' . uniqid() . '@gmail.com',
            'access_token' => 'access_tok_strict',
            'refresh_token' => 'refresh_tok_strict',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $settings = AutomationSetting::createDefault($account->id);
        $settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello, this is my exact customized message 1.', 'delay_value' => 0, 'delay_unit' => 'seconds'],
                2 => ['message' => 'Hello, this is my exact customized message 2.', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $engine = new AutomationEngine($account);

        // 1. First incoming email: must schedule exact user message 1
        $res1 = $engine->processIncomingMessage([
            'message_id' => 'msg_strict_1',
            'thread_id' => 'th_strict_1',
            'sender_email' => 'lead1@customer.com',
            'sender_name' => 'Lead One',
            'to' => $account->gmail_email,
            'subject' => 'Inquiry',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res1['status']);
        $job1 = ScheduledJob::find($res1['job_id']);
        $this->assertNotNull($job1);
        $payload1 = $job1->getPayloadArray();
        $this->assertEquals('Hello, this is my exact customized message 1.', $payload1['reply_body']);

        // 2. Second reply in thread: must schedule exact user message 2
        $thread = EmailThread::findByAccountAndThreadId($account->id, 'th_strict_1');
        $thread->update(['reply_count' => 1]);

        $res2 = $engine->processIncomingMessage([
            'message_id' => 'msg_strict_2',
            'thread_id' => 'th_strict_1',
            'sender_email' => 'lead1@customer.com',
            'sender_name' => 'Lead One',
            'to' => $account->gmail_email,
            'subject' => 'Re: Inquiry',
            'snippet' => 'Follow up',
            'body' => 'Follow up',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res2['status']);
        $job2 = ScheduledJob::find($res2['job_id']);
        $this->assertNotNull($job2);
        $payload2 = $job2->getPayloadArray();
        $this->assertEquals('Hello, this is my exact customized message 2.', $payload2['reply_body']);

        // 3. Third reply in thread: Step 3 is NOT configured by user, must SKIP and NEVER send fallback text
        $thread->update(['reply_count' => 2]);
        $res3 = $engine->processIncomingMessage([
            'message_id' => 'msg_strict_3',
            'thread_id' => 'th_strict_1',
            'sender_email' => 'lead1@customer.com',
            'sender_name' => 'Lead One',
            'to' => $account->gmail_email,
            'subject' => 'Re: Inquiry 2',
            'snippet' => 'Another follow up',
            'body' => 'Another follow up',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res3['status']);
        $this->assertStringContainsString('Message content is missing for Step #3', $res3['reason']);

        // 4. Test QueueWorker cancels job if user emptied message before sending
        $settings->update([
            'reply_message' => json_encode([
                1 => ['message' => '', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $worker = new \App\Services\QueueWorker();
        // Execute job1: since user deleted/emptied step 1 message, worker must cancel the job
        $workerResult = $worker->processJob($job1);
        $this->assertTrue($workerResult);
        $job1Updated = ScheduledJob::find($job1->id);
        $this->assertEquals('cancelled', $job1Updated->status);
        $this->assertStringContainsString('Message content is missing', $job1Updated->last_error);
    }

    public function testMessageUpdateAndDeletionWorkflow(): void {
        $user = User::create([
            'name' => 'Update Test User',
            'email' => 'updatetest_' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $account = GmailAccount::createOrUpdate([
            'user_id' => $user->id,
            'gmail_email' => 'update_' . uniqid() . '@gmail.com',
            'access_token' => 'access_tok_upd',
            'refresh_token' => 'refresh_tok_upd',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $settings = AutomationSetting::createDefault($account->id);
        $settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Original Message 123', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $engine = new AutomationEngine($account);

        // Schedule auto-reply
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_upd_1',
            'thread_id' => 'th_upd_1',
            'sender_email' => 'user@domain.com',
            'sender_name' => 'User Domain',
            'to' => $account->gmail_email,
            'subject' => 'Hello',
            'snippet' => 'Hi',
            'body' => 'Hi',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
        $jobId = $res['job_id'];

        // User edits message to 'NEW CUSTOM MESSAGE 12345'
        $settings->update([
            'reply_message' => json_encode([
                1 => ['message' => 'NEW CUSTOM MESSAGE 12345', 'delay_value' => 0, 'delay_unit' => 'seconds'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // When queue worker processes the job, it must verify and fetch 'NEW CUSTOM MESSAGE 12345'
        $freshJob = ScheduledJob::find($jobId);
        $this->assertNotNull($freshJob);
        
        $liveMsg = $settings->getReplyMessageForStep(1);
        $this->assertEquals('NEW CUSTOM MESSAGE 12345', $liveMsg);

        // User then deletes all messages (sets reply_message to null)
        $settings->update(['reply_message' => null]);
        ScheduledJob::cancelPendingJobsByAccountAndType($account->id, 'auto_reply', 'All auto-reply messages were deleted by user');

        $cancelledJob = ScheduledJob::find($jobId);
        $this->assertEquals('cancelled', $cancelledJob->status);
        $this->assertStringContainsString('deleted by user', $cancelledJob->last_error);
    }

    public function testFollowupDeletionCancelsPendingJob(): void {
        $user = User::create([
            'name' => 'Followup User',
            'email' => 'fu_' . uniqid() . '@example.com',
            'password' => password_hash('secret', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $account = GmailAccount::createOrUpdate([
            'user_id' => $user->id,
            'gmail_email' => 'fu_' . uniqid() . '@gmail.com',
            'access_token' => 'access_tok_fu',
            'refresh_token' => 'refresh_tok_fu',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $template = FollowupTemplate::create([
            'user_id' => $user->id,
            'gmail_account_id' => $account->id,
            'step_number' => 1,
            'name' => 'Follow-up #1',
            'message' => 'Checking in on you!',
            'delay_value' => 2,
            'delay_unit' => 'days',
            'status' => 'active',
        ]);

        $thread = EmailThread::createOrGet($account->id, 'th_fu_' . uniqid(), [
            'sender_email' => 'contact@corp.com',
            'sender_name' => 'Contact Corp',
            'subject' => 'Our Project',
        ]);
        $thread->update(['reply_count' => 1, 'automation_status' => 'active']);

        $settings = AutomationSetting::createDefault($account->id);
        $settings->update(['followup_enabled' => 1]);

        $engine = new AutomationEngine($account);
        $fuJob = $engine->scheduleNextFollowupStep($thread, 0);
        $this->assertNotNull($fuJob);
        $this->assertEquals('pending', $fuJob->status);

        // Delete template
        $template->delete();

        // Worker executes: since template was deleted, worker must cancel the job
        $worker = new \App\Services\QueueWorker();
        $workerResult = $worker->processJob($fuJob);
        $this->assertTrue($workerResult);

        $fuJobUpdated = ScheduledJob::find($fuJob->id);
        $this->assertEquals('cancelled', $fuJobUpdated->status);
        $this->assertStringContainsString('Follow-up template is missing', $fuJobUpdated->last_error);
    }
}
