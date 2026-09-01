<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\AutoReplyRecipient;
use App\Models\SkippedEmailLog;
use App\Services\AutomationEngine;
use App\Controllers\SkippedEmailController;
use App\Controllers\AdminController;
use App\Core\Request;

class DuplicateSkippedEmailReportTest extends TestCase {
    private User $user;
    private GmailAccount $account;

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

        // Setup clean test user and account
        $email = 'report_test_' . uniqid() . '@example.com';
        Database::execute(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Report Tester', :e, 'hash', 'user', datetime('now'))",
            ['e' => $email]
        );
        $userId = (int)Database::lastInsertId();
        $this->user = User::find($userId);

        $gmail = 'gmail_rep_' . uniqid() . '@gmail.com';
        Database::execute(
            "INSERT INTO gmail_accounts (user_id, gmail_email, access_token, refresh_token, status, created_at) 
             VALUES (:uid, :gemail, 'mock_token', 'mock_refresh', 'connected', datetime('now'))",
            ['uid' => $userId, 'gemail' => $gmail]
        );
        $accId = (int)Database::lastInsertId();
        $this->account = GmailAccount::find($accId);

        // Configure automation settings
        $settings = AutomationSetting::createDefault($accId);
        $settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => 'Hello there, thanks for your email!',
            'reply_delay' => 0,
            'max_reply_per_thread' => 1,
            'daily_reply_limit' => 50,
        ]);
    }

    public function testDuplicateEmailIsLoggedToSkippedEmailReport(): void {
        $engine = new AutomationEngine($this->account);
        $sender = 'customer_' . uniqid() . '@lead.com';

        // 1. First Email -> Scheduled/Processed
        $msg1 = [
            'message_id' => 'msg_1_' . uniqid(),
            'thread_id' => 'th_1_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Customer One',
            'subject' => 'Lead Inquiry #1',
            'snippet' => 'Looking for pricing details...',
            'body' => 'Hello, I want to inquire about pricing.',
            'date' => date('Y-m-d H:i:s'),
        ];
        $res1 = $engine->processIncomingMessage($msg1);
        $this->assertEquals('scheduled', $res1['status']);

        // 2. Second Email from same sender -> Duplicate Traffic Protection triggers
        $msg2 = [
            'message_id' => 'msg_2_' . uniqid(),
            'thread_id' => 'th_2_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Customer One',
            'subject' => 'Lead Inquiry #2 Follow up',
            'snippet' => 'Did you get my previous email?',
            'body' => 'Just following up on my previous message.',
            'date' => date('Y-m-d H:i:s', time() + 300),
        ];
        $res2 = $engine->processIncomingMessage($msg2);
        $this->assertEquals('skipped', $res2['status']);
        $this->assertTrue($res2['is_duplicate_traffic']);

        // Verify skipped email log was created
        $logs = SkippedEmailLog::findByUserId($this->user->id, ['skip_type' => 'duplicate_traffic']);
        $this->assertNotEmpty($logs);
        $found = false;
        foreach ($logs as $l) {
            if ($l['sender_email'] === $sender) {
                $found = true;
                $this->assertEquals('duplicate_traffic', $l['skip_type']);
                $this->assertStringContainsString('Duplicate traffic', $l['skip_reason']);
                $this->assertEquals($this->account->id, (int)$l['gmail_account_id']);
                break;
            }
        }
        $this->assertTrue($found, 'Duplicate traffic email was logged to skipped_email_logs');

        // Verify stats aggregation
        $stats = SkippedEmailLog::getStatsByUserId($this->user->id);
        $this->assertGreaterThanOrEqual(1, $stats['total_skipped']);
        $this->assertGreaterThanOrEqual(1, $stats['duplicate_traffic_skipped']);
    }

    public function testBlacklistEmailIsLoggedToSkippedReport(): void {
        // Add a blacklist filter for spammer domain
        \App\Models\SystemSetting::set('blacklist_domains', 'baddomain.com');

        $engine = new AutomationEngine($this->account);
        $sender = 'spammer_' . uniqid() . '@baddomain.com';

        $msg = [
            'message_id' => 'msg_bl_' . uniqid(),
            'thread_id' => 'th_bl_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Spam Source',
            'subject' => 'Free Money Opportunity',
            'snippet' => 'Click this link to win...',
            'body' => 'Spam content here',
            'date' => date('Y-m-d H:i:s'),
        ];
        $res = $engine->processIncomingMessage($msg);
        $this->assertEquals('skipped', $res['status']);
        $this->assertStringContainsStringIgnoringCase('blacklisted', $res['reason']);

        // Verify logged in skipped_email_logs
        $logs = SkippedEmailLog::findByUserId($this->user->id, ['skip_type' => 'blacklist']);
        $this->assertNotEmpty($logs);
        $this->assertEquals('blacklist', $logs[0]['skip_type']);
    }

    public function testSkippedEmailControllerViewAndFilters(): void {
        // Authenticate user
        \App\Core\Auth::login($this->user);

        // Manually create some logs
        SkippedEmailLog::create([
            'user_id' => $this->user->id,
            'gmail_account_id' => $this->account->id,
            'sender_email' => 'client_abc@example.com',
            'sender_name' => 'Client ABC',
            'recipient_email' => $this->account->gmail_email,
            'subject' => 'Project inquiry',
            'snippet' => 'Snippet text here',
            'skip_reason' => 'Duplicate traffic: 1 Auto Reply already sent to this sender',
            'skip_type' => 'duplicate_traffic',
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        $controller = new SkippedEmailController();
        $request = new Request([], ['search' => 'client_abc']);
        $html = $controller->index($request);

        $this->assertStringContainsString('Duplicate &amp; Skipped Emails Report', $html);
        $this->assertStringContainsString('client_abc@example.com', $html);
        $this->assertStringContainsString('Duplicate Traffic', $html);
    }
}
