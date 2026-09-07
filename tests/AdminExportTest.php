<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Auth;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailThread;
use App\Models\ScheduledJob;
use App\Controllers\AdminController;
use App\Controllers\CampaignController;

class AdminExportTest extends TestCase {
    private static User $adminUser;
    private static User $regularUser;
    private static GmailAccount $account;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $sqlitePath = storage_path('database/test_admin_export.sqlite');
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        putenv("APP_ENV=testing");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';
        $_ENV['APP_ENV'] = 'testing';
        if (!defined('TESTING')) {
            define('TESTING', true);
        }

        config('_reset_');
        Database::resetConnection();
        new App();
        \Database\MigrationRunner::run();

        // Create Admin user
        self::$adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin_export_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        // Create Regular user
        self::$regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'regular_user_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);
        self::$regularUser->update(['can_bulk_send' => 1]);

        // Create Gmail Account
        self::$account = GmailAccount::create([
            'user_id' => self::$regularUser->id,
            'gmail_email' => 'lead_sender_' . uniqid() . '@gmail.com',
            'access_token' => 'dummy_token',
            'refresh_token' => 'dummy_refresh',
            'status' => 'active',
            'history_id' => '100',
        ]);
    }

    public function testAdminExportAllCampaignLeadsCsv(): void {
        Auth::login(self::$adminUser);

        // Create Campaign 1 with leads
        $campaign1 = EmailCampaign::create([
            'user_id' => self::$regularUser->id,
            'name' => 'Winter Promo 2026',
            'status' => 'active',
            'daily_campaign_limit' => 200,
            'sending_interval' => 30,
        ]);
        EmailCampaignRecipient::insertBatch($campaign1->id, self::$regularUser->id, [
            ['email' => 'lead_winter1@example.com', 'first_name' => 'Winter', 'last_name' => 'Lead', 'company' => 'Winter Co'],
            ['email' => 'lead_winter2@example.com', 'first_name' => 'Snow', 'last_name' => 'Lead', 'company' => 'Ice Corp'],
        ]);

        // Create Campaign 2 with leads
        $campaign2 = EmailCampaign::create([
            'user_id' => self::$regularUser->id,
            'name' => 'Spring Launch 2026',
            'status' => 'completed',
            'daily_campaign_limit' => 500,
            'sending_interval' => 10,
        ]);
        EmailCampaignRecipient::insertBatch($campaign2->id, self::$regularUser->id, [
            ['email' => 'lead_spring@example.com', 'first_name' => 'Spring', 'last_name' => 'User', 'company' => 'Flower Ltd'],
        ]);

        $controller = new AdminController();
        $request = new Request([], [], [], [], []);

        ob_start();
        $controller->exportCampaignLeads($request);
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('Lead ID', $csvOutput);
        $this->assertStringContainsString('Campaign Name', $csvOutput);
        $this->assertStringContainsString('Winter Promo 2026', $csvOutput);
        $this->assertStringContainsString('Spring Launch 2026', $csvOutput);
        $this->assertStringContainsString('lead_winter1@example.com', $csvOutput);
        $this->assertStringContainsString('lead_winter2@example.com', $csvOutput);
        $this->assertStringContainsString('lead_spring@example.com', $csvOutput);
        $this->assertStringContainsString('Winter Co', $csvOutput);
    }

    public function testAdminExportSingleCampaignLeadsCsv(): void {
        Auth::login(self::$adminUser);

        $campaign = EmailCampaign::create([
            'user_id' => self::$regularUser->id,
            'name' => 'Targeted Niche Campaign',
            'status' => 'active',
            'daily_campaign_limit' => 100,
            'sending_interval' => 45,
        ]);
        EmailCampaignRecipient::insertBatch($campaign->id, self::$regularUser->id, [
            ['email' => 'targeted_only@example.com', 'first_name' => 'UniqueTarget', 'company' => 'Target Inc'],
        ]);

        $controller = new AdminController();
        $request = new Request(['campaign_id' => (string)$campaign->id], [], [], [], []);

        ob_start();
        $controller->exportCampaignLeads($request);
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('Targeted Niche Campaign', $csvOutput);
        $this->assertStringContainsString('targeted_only@example.com', $csvOutput);
        $this->assertStringContainsString('UniqueTarget', $csvOutput);
        $this->assertStringNotContainsString('lead_winter1@example.com', $csvOutput);
    }

    public function testCampaignControllerExportRecipients(): void {
        Auth::login(self::$regularUser);

        $campaign = EmailCampaign::create([
            'user_id' => self::$regularUser->id,
            'name' => 'User Leads Export Test',
            'status' => 'active',
        ]);
        EmailCampaignRecipient::insertBatch($campaign->id, self::$regularUser->id, [
            ['email' => 'client_export@test.com', 'first_name' => 'Client', 'last_name' => 'One'],
        ]);

        $controller = new CampaignController();
        $request = new Request([], [], [], [], []);

        ob_start();
        $controller->exportRecipients($request, $campaign->id);
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('Lead ID', $csvOutput);
        $this->assertStringContainsString('First Name', $csvOutput);
        $this->assertStringContainsString('client_export@test.com', $csvOutput);
        $this->assertStringContainsString('Client', $csvOutput);
    }

    public function testAdminExportAutoRepliedEmailsCsv(): void {
        Auth::login(self::$adminUser);

        // 1. Create a scheduled auto_reply job marked as completed
        $thread = EmailThread::createOrGet(self::$account->id, 'th_auto_' . uniqid(), [
            'sender_email' => 'auto_replied_recipient@client.com',
            'sender_name' => 'John Customer',
            'subject' => 'Inquiry about pricing',
        ]);

        ScheduledJob::create([
            'gmail_account_id' => self::$account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => [
                'recipient_email' => 'auto_replied_recipient@client.com',
                'recipient_name' => 'John Customer',
                'subject' => 'Re: Inquiry about pricing',
                'reply_step' => 1,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        Database::execute("UPDATE scheduled_jobs SET processed_at = datetime('now') WHERE id = " . Database::lastInsertId());

        // 2. Also insert into auto_reply_recipients
        Database::execute("
            INSERT INTO auto_reply_recipients (
                user_id, gmail_account_id, normalized_sender_email, 
                reply_sequence_step, reply_status, reply_sent_at, created_at
            ) VALUES (
                :uid, :acc, 'tracked_auto_sender@domain.com', 
                1, 'replied', datetime('now'), datetime('now')
            )
        ", [
            'uid' => self::$regularUser->id,
            'acc' => self::$account->id,
        ]);

        $controller = new AdminController();
        $request = new Request([], [], [], [], []);

        ob_start();
        $controller->exportAutoRepliedEmails($request);
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('Record ID', $csvOutput);
        $this->assertStringContainsString('Recipient Email', $csvOutput);
        $this->assertStringContainsString('auto_replied_recipient@client.com', $csvOutput);
        $this->assertStringContainsString('John Customer', $csvOutput);
        $this->assertStringContainsString('tracked_auto_sender@domain.com', $csvOutput);
        $this->assertStringContainsString(self::$account->gmail_email, $csvOutput);
    }
}
