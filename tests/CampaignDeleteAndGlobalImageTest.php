<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignMessage;
use App\Models\GlobalAutomationSetting;
use App\Models\GlobalAutoReplyMessage;
use App\Models\GlobalFollowupSequence;
use App\Models\GlobalFollowupMessage;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;
use App\Services\GmailService;
use App\Controllers\CampaignController;
use App\Controllers\GlobalAutomationController;
use Database\MigrationRunner;

class CampaignDeleteAndGlobalImageTest extends TestCase {
    private static string $sqlitePath;
    private User $user;
    private array $tempFiles = [];

    public static function setUpBeforeClass(): void {
        if (!defined('TESTING')) {
            define('TESTING', true);
        }
        self::$sqlitePath = sys_get_temp_dir() . '/test_campaign_delete_img_' . uniqid() . '.sqlite';
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=' . self::$sqlitePath);
        putenv('APP_ENV=testing');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = self::$sqlitePath;
        $_ENV['APP_ENV'] = 'testing';
    }

    public static function tearDownAfterClass(): void {
        if (file_exists(self::$sqlitePath)) {
            @unlink(self::$sqlitePath);
        }
    }

    protected function setUp(): void {
        if (file_exists(self::$sqlitePath)) {
            @unlink(self::$sqlitePath);
        }
        config('_reset_');
        Database::resetConnection();
        new App();
        MigrationRunner::run();

        $this->user = User::create([
            'name' => 'Test Marketer',
            'email' => 'marketer_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 1,
        ]);
        Session::set('user_id', $this->user->id);
    }

    protected function tearDown(): void {
        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $userUploadDir = public_path('uploads/automation/' . $this->user->id);
        if (is_dir($userUploadDir)) {
            foreach (glob($userUploadDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($userUploadDir);
        }
    }

    public function testCampaignEditDeletesRemovedVariationAndKeepsRemaining(): void {
        $campaign = EmailCampaign::create([
            'user_id' => $this->user->id,
            'name' => 'Variation Delete Test',
            'status' => 'paused',
        ]);

        $m1 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'subject' => 'Subject 1',
            'body' => 'Body 1',
            'status' => 'active',
        ]);
        $m2 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'subject' => 'Subject 2',
            'body' => 'Body 2',
            'status' => 'active',
        ]);
        $m3 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'subject' => 'Subject 3',
            'body' => 'Body 3',
            'status' => 'active',
        ]);

        // Submit edit keeping m1, removing m2 and m3, and adding a new variation
        $req = new Request([], [
            'name' => 'Variation Delete Test Updated',
            'daily_campaign_limit' => '100',
            'sending_interval' => '30',
            'schedule_mode' => 'instant',
            'timezone' => 'Asia/Dhaka',
            'status' => 'paused',
            'message_ids' => [(string)$m1->id, '0'],
            'subjects' => ['Subject 1 Updated', 'Subject 4 New'],
            'bodies' => ['Body 1 Updated', 'Body 4 New'],
        ]);

        $controller = new CampaignController();
        $controller->update($req, $campaign->id);

        $remaining = EmailCampaignMessage::findByCampaignId($campaign->id);
        $this->assertCount(2, $remaining);
        $this->assertEquals('Subject 1 Updated', $remaining[0]->subject);
        $this->assertEquals('Subject 4 New', $remaining[1]->subject);
        $this->assertNull(EmailCampaignMessage::find($m2->id));
        $this->assertNull(EmailCampaignMessage::find($m3->id));
    }

    public function testCampaignEditRefusesDeletingAllVariations(): void {
        $campaign = EmailCampaign::create([
            'user_id' => $this->user->id,
            'name' => 'Keep At Least One',
            'status' => 'paused',
        ]);
        $m1 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $this->user->id,
            'subject' => 'Only Subject',
            'body' => 'Only Body',
            'status' => 'active',
        ]);

        $req = new Request([], [
            'name' => 'Keep At Least One',
            'daily_campaign_limit' => '100',
            'sending_interval' => '30',
            'schedule_mode' => 'instant',
            'timezone' => 'Asia/Dhaka',
            'status' => 'paused',
            'message_ids' => [],
            'subjects' => [],
            'bodies' => [],
        ]);

        $controller = new CampaignController();
        $controller->update($req, $campaign->id);

        $remaining = EmailCampaignMessage::findByCampaignId($campaign->id);
        $this->assertCount(1, $remaining);
    }

    public function testStoreUploadedImageAndInlineMimeEmbedding(): void {
        // Create a valid 1x1 PNG file in /tmp
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpPng = sys_get_temp_dir() . '/test_upload_' . uniqid() . '.png';
        file_put_contents($tmpPng, $pngBytes);
        $this->tempFiles[] = $tmpPng;

        $saved = GlobalAutomationController::storeUploadedImage($this->user->id, [
            'name' => 'promo.png',
            'tmp_name' => $tmpPng,
            'size' => strlen($pngBytes),
            'error' => UPLOAD_ERR_OK,
        ]);

        $this->assertTrue($saved['success']);
        $this->assertStringStartsWith('/uploads/automation/' . $this->user->id . '/img_', $saved['path']);
        $this->assertFileExists(public_path(ltrim($saved['path'], '/')));

        // Verify non-image file is rejected
        $tmpTxt = sys_get_temp_dir() . '/test_bad_' . uniqid() . '.txt';
        file_put_contents($tmpTxt, '<?php echo "hack"; ?>');
        $this->tempFiles[] = $tmpTxt;

        $rejected = false;
        try {
            GlobalAutomationController::storeUploadedImage($this->user->id, [
                'name' => 'bad.php',
                'tmp_name' => $tmpTxt,
                'size' => 22,
                'error' => UPLOAD_ERR_OK,
            ]);
        } catch (\Throwable $e) {
            $rejected = true;
        }
        $this->assertTrue($rejected, 'Non-image file upload must be rejected.');

        // Verify GmailService embeds uploaded image + base64 image as multipart/related CID,
        // while keeping tracking pixel URL untouched
        $html = '<p>Hello!</p><img src="' . $saved['url'] . '" alt="Flyer"><img src="https://2xbets.net/tracking/open/' . str_repeat('a', 64) . '" width="1" height="1" />';
        $mime = GmailService::buildMimePayload($html, true);

        $this->assertSame(1, $mime['inline_count']);
        $this->assertStringContainsString('multipart/related; boundary="', $mime['headers'][0]);
        $this->assertStringContainsString('Content-Disposition: inline;', $mime['body']);
        $this->assertStringContainsString('Content-ID: <img_', $mime['body']);

        // Decode HTML part and ensure CID replaced the local upload image but NOT the tracking pixel
        preg_match('/Content-Type: text\/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+)/', $mime['body'], $m);
        $this->assertNotEmpty($m[1] ?? '');
        $decodedHtml = base64_decode(str_replace(["\r", "\n"], '', $m[1]));
        $this->assertStringContainsString('src="cid:img_', $decodedHtml);
        $this->assertStringContainsString('/tracking/open/', $decodedHtml);
    }

    public function testGlobalAutoReplyAndFollowupWithImageSendEndToEnd(): void {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpPng = sys_get_temp_dir() . '/test_global_' . uniqid() . '.png';
        file_put_contents($tmpPng, $pngBytes);
        $this->tempFiles[] = $tmpPng;

        $saved = GlobalAutomationController::storeUploadedImage($this->user->id, [
            'name' => 'banner.png',
            'tmp_name' => $tmpPng,
            'size' => strlen($pngBytes),
            'error' => UPLOAD_ERR_OK,
        ]);

        $account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'sender_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_' . uniqid(),
            'access_token' => 'access_tok_mock',
            'refresh_token' => 'refresh_tok_mock',
            'status' => 'connected',
        ]);

        $settings = GlobalAutomationSetting::getForUser($this->user->id);
        $settings->auto_reply_enabled = true;
        $settings->followup_enabled = true;
        $settings->save();

        GlobalAutoReplyMessage::create([
            'user_id' => $this->user->id,
            'step_number' => 1,
            'variation_name' => 'Reply With Image',
            'body_html' => '<p>Hi {{first_name}}, see attached image:</p><img src="' . $saved['url'] . '">',
            'delay_minutes' => 0,
            'is_active' => 1,
        ]);

        GlobalFollowupSequence::create([
            'user_id' => $this->user->id,
            'step_number' => 1,
            'delay_value' => 1,
            'delay_unit' => 'minutes',
            'is_active' => 1,
        ]);

        GlobalFollowupMessage::create([
            'user_id' => $this->user->id,
            'step_number' => 1,
            'variation_name' => 'Followup With Image',
            'body_html' => '<p>Following up with our image:</p><img src="' . $saved['url'] . '">',
            'is_active' => 1,
        ]);

        $engine = new AutomationEngine($account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'in_' . uniqid(),
            'thread_id' => 'th_' . uniqid(),
            'sender_email' => 'prospect@example.com',
            'sender_name' => 'Alex Lead',
            'subject' => 'Question about service',
            'body' => 'Can you send details?',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);

        $jobRow = Database::first("SELECT * FROM scheduled_jobs WHERE id = :id", ['id' => $res['job_id']]);
        $this->assertNotNull($jobRow);

        $worker = new QueueWorker();
        $job = \App\Models\ScheduledJob::fromRow($jobRow);
        $this->assertTrue($worker->processJob($job));

        $outgoing = Database::first("SELECT * FROM email_messages WHERE direction = 'outgoing' AND gmail_account_id = :aid", ['aid' => $account->id]);
        $this->assertNotNull($outgoing);
        $this->assertStringContainsString('<img src="' . $saved['url'] . '"', $outgoing['message_body']);

        // Follow-up job should now also be scheduled with the follow-up image
        $followupJob = Database::first("SELECT * FROM scheduled_jobs WHERE job_type = 'follow_up' AND gmail_account_id = :aid", ['aid' => $account->id]);
        $this->assertNotNull($followupJob);
        $payload = json_decode($followupJob['payload'], true);
        $this->assertStringContainsString('<img src="' . $saved['url'] . '"', $payload['reply_body']);
    }
}
