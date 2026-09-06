<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\GlobalAutomationSetting;
use App\Models\GlobalAutoReplyMessage;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\ScheduledJob;
use App\Models\SkippedEmailLog;
use App\Services\AutomationEngine;
use Database\MigrationRunner;

class SpamFilterProtectionTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;

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

        $this->user = User::create([
            'name' => 'Spam Test User',
            'email' => 'spam_test_' . uniqid() . '@test.com',
            'password' => password_hash('Pass@123', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'business_' . uniqid() . '@gmail.com',
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createOrGet($this->account->id);
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'use_account_override' => 1,
            'skip_spam_emails' => 1,
            'reply_message' => json_encode([
                1 => ['message' => 'Hello {{first_name}}, thank you for contacting us!', 'delay_value' => 0, 'delay_unit' => 'minutes'],
            ], JSON_UNESCAPED_UNICODE),
            'followup_enabled' => 0,
            'daily_reply_limit' => 100,
        ]);
    }

    public function testGmailSpamLabelIsSkippedWhenFilterEnabled(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'spam_msg_' . uniqid(),
            'thread_id' => 'spam_th_' . uniqid(),
            'sender_email' => 'spammer_' . uniqid() . '@random.com',
            'sender_name' => 'Spammer',
            'to' => $this->account->gmail_email,
            'subject' => 'Win cash now',
            'snippet' => 'Click here to claim',
            'body' => 'Click here to claim prize',
            'label_ids' => ['SPAM', 'UNREAD'],
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res['status']);
        $this->assertStringContainsString('Gmail classified message with \'SPAM\' label', $res['reason']);
    }

    public function testListUnsubscribeNewsletterIsSkipped(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'newsletter_msg_' . uniqid(),
            'thread_id' => 'newsletter_th_' . uniqid(),
            'sender_email' => 'news_' . uniqid() . '@marketingblast.com',
            'sender_name' => 'Newsletter Team',
            'to' => $this->account->gmail_email,
            'subject' => 'Weekly Digest #42',
            'snippet' => 'Here are your updates',
            'body' => 'Lots of stories...',
            'list_unsubscribe' => '<mailto:unsubscribe@marketingblast.com>',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res['status']);
        $this->assertStringContainsString('List-Unsubscribe header present', $res['reason']);
    }

    public function testBotAndSystemSenderIsSkipped(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'bot_msg_' . uniqid(),
            'thread_id' => 'bot_th_' . uniqid(),
            'sender_email' => 'noreply@notification-service.com',
            'sender_name' => 'Automated Bot',
            'to' => $this->account->gmail_email,
            'subject' => 'System Alert: Password reset requested',
            'snippet' => 'Your security code is 123456',
            'body' => 'Your security code is 123456',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res['status']);
        $this->assertStringContainsString('Bot/System email skipped', $res['reason']);
    }

    public function testDeliveryFailureAndBounceIsSkipped(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'bounce_msg_' . uniqid(),
            'thread_id' => 'bounce_th_' . uniqid(),
            'sender_email' => 'postmaster@mail.server.net',
            'sender_name' => 'Mail Delivery Subsystem',
            'to' => $this->account->gmail_email,
            'subject' => 'Delivery Status Notification (Failure)',
            'snippet' => 'Message could not be delivered',
            'body' => 'Diagnostic code 550 User not found',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res['status']);
        $this->assertStringContainsString('skipped', $res['status']);
    }

    public function testOutOfOfficeAutoReplyIsSkipped(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'ooo_msg_' . uniqid(),
            'thread_id' => 'ooo_th_' . uniqid(),
            'sender_email' => 'colleague_' . uniqid() . '@clientcorp.com',
            'sender_name' => 'Client Colleague',
            'to' => $this->account->gmail_email,
            'subject' => 'Automatic Reply: Out of office until Monday',
            'snippet' => 'I am currently away with limited access to email',
            'body' => 'I am currently away with limited access to email',
            'auto_submitted' => 'auto-replied',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('skipped', $res['status']);
        $this->assertTrue(str_contains($res['reason'], 'Auto-Submitted') || str_contains($res['reason'], 'notice skipped'));
    }

    public function testLegitimateCustomerEmailIsScheduled(): void {
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'legit_msg_' . uniqid(),
            'thread_id' => 'legit_th_' . uniqid(),
            'sender_email' => 'customer_' . uniqid() . '@clientcompany.com',
            'sender_name' => 'John Customer',
            'to' => $this->account->gmail_email,
            'subject' => 'Inquiry regarding your enterprise pricing',
            'snippet' => 'Hello, I would like to know more about your plans.',
            'body' => 'Hello, I would like to know more about your plans.',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
    }

    public function testDisabledSpamFilterAllowsAllIncoming(): void {
        // Explicitly disable skip_spam_emails on the account
        $this->settings->update(['skip_spam_emails' => 0]);

        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'bypassed_spam_' . uniqid(),
            'thread_id' => 'bypassed_th_' . uniqid(),
            'sender_email' => 'promotions_' . uniqid() . '@megastore.com',
            'sender_name' => 'Promotions',
            'to' => $this->account->gmail_email,
            'subject' => 'Special discount coupon inside',
            'snippet' => 'Check our weekend sale',
            'body' => 'Check our weekend sale',
            'list_unsubscribe' => '<mailto:unsub@megastore.com>',
            'label_ids' => ['SPAM'],
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Because skip_spam_emails = 0, it does not get filtered out by anti-spam!
        $this->assertEquals('scheduled', $res['status']);
    }
}
