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
        $settings->update(['auto_reply_enabled' => 1]);

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
}
