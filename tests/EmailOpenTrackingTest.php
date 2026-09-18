<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Auth;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailOpenTracking;
use App\Models\EmailOpenEvent;
use App\Services\EmailOpenTrackingService;
use App\Controllers\TrackingController;
use App\Controllers\ReplyReportController;

class EmailOpenTrackingTest extends TestCase {
    private User $userA;
    private User $userB;
    private GmailAccount $accountA;
    private GmailAccount $accountB;

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
        EmailOpenTracking::ensureSchema();
        EmailOpenEvent::ensureSchema();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();
        EmailOpenTracking::ensureSchema();
        EmailOpenEvent::ensureSchema();

        // Create User A
        $emailA = 'track_user_a_' . uniqid() . '@example.com';
        Database::execute(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Track User A', :e, 'hash', 'user', datetime('now'))",
            ['e' => $emailA]
        );
        $this->userA = User::find((int)Database::lastInsertId());

        $gmailA = 'track_acc_a_' . uniqid() . '@gmail.com';
        Database::execute(
            "INSERT INTO gmail_accounts (user_id, gmail_email, access_token, refresh_token, status, created_at) 
             VALUES (:uid, :gemail, 'tokA', 'refA', 'connected', datetime('now'))",
            ['uid' => $this->userA->id, 'gemail' => $gmailA]
        );
        $this->accountA = GmailAccount::find((int)Database::lastInsertId());

        // Create User B (for tenant isolation tests)
        $emailB = 'track_user_b_' . uniqid() . '@example.com';
        Database::execute(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Track User B', :e, 'hash', 'user', datetime('now'))",
            ['e' => $emailB]
        );
        $this->userB = User::find((int)Database::lastInsertId());

        $gmailB = 'track_acc_b_' . uniqid() . '@gmail.com';
        Database::execute(
            "INSERT INTO gmail_accounts (user_id, gmail_email, access_token, refresh_token, status, created_at) 
             VALUES (:uid, :gemail, 'tokB', 'refB', 'connected', datetime('now'))",
            ['uid' => $this->userB->id, 'gemail' => $gmailB]
        );
        $this->accountB = GmailAccount::find((int)Database::lastInsertId());

        Auth::login($this->userA);
    }

    public function testTokenGenerationProperties(): void {
        $token1 = EmailOpenTrackingService::generateToken();
        $token2 = EmailOpenTrackingService::generateToken();

        $this->assertEquals(64, strlen($token1));
        $this->assertEquals(64, strlen($token2));
        $this->assertNotEquals($token1, $token2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token1);
    }

    public function testPixelInjectionInHtmlAndPlainText(): void {
        $token = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';

        // 1. HTML with </body>
        $htmlBody = '<html><body><p>Hello world!</p></body></html>';
        $injectedHtml = EmailOpenTrackingService::injectPixel($htmlBody, $token);
        $this->assertStringContainsString('/tracking/open/' . $token, $injectedHtml);
        $this->assertStringContainsString('width="1" height="1"', $injectedHtml);
        $this->assertStringContainsString('style="display:none', $injectedHtml);
        $this->assertStringEndsWith('</body></html>', trim($injectedHtml));

        // 2. Plain text / no closing body tag
        $plainBody = 'Plain email text without HTML body tag.';
        $injectedPlain = EmailOpenTrackingService::injectPixel($plainBody, $token);
        $this->assertStringContainsString('/tracking/open/' . $token, $injectedPlain);
        $this->assertStringStartsWith('Plain email text without HTML body tag.', $injectedPlain);
    }

    public function testPrepareAndFinalizeTrackingPipeline(): void {
        $body = '<p>Thank you for reaching out.</p>';
        [$trackedBody, $record] = EmailOpenTrackingService::prepareTracking($body, [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'client@acme.com',
            'source_type' => 'auto_reply',
            'subject' => 'Re: Inquiry about services',
        ]);

        $this->assertInstanceOf(EmailOpenTracking::class, $record);
        $this->assertEquals('client@acme.com', $record->recipient_email);
        $this->assertEquals(0, $record->open_count);
        $this->assertNull($record->message_id);
        $this->assertStringContainsString($record->tracking_token, $trackedBody);

        // Finalize upon send
        $sentMsgId = 'gmail_msg_id_' . uniqid();
        $sentAt = date('Y-m-d H:i:s');
        EmailOpenTrackingService::finalizeTracking($record, $sentMsgId, $sentAt);

        $refreshed = EmailOpenTracking::findByToken($record->tracking_token);
        $this->assertNotNull($refreshed);
        $this->assertEquals($sentMsgId, $refreshed->message_id);
        $this->assertEquals($sentAt, $refreshed->created_at);
    }

    public function testTrackingControllerServesTransparentGifAndRecordsOpen(): void {
        [$body, $record] = EmailOpenTrackingService::prepareTracking('<p>Test email</p>', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'buyer@target.com',
            'source_type' => 'auto_reply',
            'subject' => 'Product Catalog',
        ]);
        EmailOpenTrackingService::finalizeTracking($record, 'msg_test_1', date('Y-m-d H:i:s'));

        $controller = new TrackingController();
        $request = new Request([], [], [
            'REMOTE_ADDR' => '93.184.216.34',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        ob_start();
        $controller->track($request, $record->tracking_token);
        $output = ob_get_clean();

        // 43-byte GIF validation
        $this->assertEquals(43, strlen($output));
        $this->assertStringStartsWith('GIF89a', $output);

        // Verify open record in DB
        $updated = EmailOpenTracking::findByToken($record->tracking_token);
        $this->assertNotNull($updated);
        $this->assertEquals(1, $updated->open_count);
        $this->assertNotNull($updated->first_opened_at);
        $this->assertNotNull($updated->last_opened_at);

        // Verify event record in DB
        $events = EmailOpenEvent::findByTrackingId($updated->id);
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals('93.184.216.34', $event->ip_address);
        $this->assertEquals('macOS', $event->os);
        $this->assertStringContainsString('Chrome', $event->browser);
        $this->assertEquals('Desktop', $event->device_type);
        $this->assertEquals(0, $event->is_proxy);
    }

    public function testInvalidTokenReturnsGifWithoutErrorOrEventCreation(): void {
        $initialCount = count(EmailOpenEvent::findByTrackingId(999999));
        $controller = new TrackingController();
        $request = new Request([], [], [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_USER_AGENT' => 'curl/8.1.2',
        ]);

        ob_start();
        $controller->track($request, 'invalid_token_which_does_not_exist_in_database');
        $output = ob_get_clean();

        $this->assertEquals(43, strlen($output));
        $this->assertStringStartsWith('GIF89a', $output);
        $this->assertEquals(0, count(EmailOpenEvent::findByTrackingId(999999)));
    }

    public function testProxyAndBotDetection(): void {
        // Google Image Proxy test
        [$body1, $record1] = EmailOpenTrackingService::prepareTracking('<p>Hi</p>', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'gmailuser@gmail.com',
            'source_type' => 'auto_reply',
            'subject' => 'Re: Info',
        ]);
        $reqProxy = new Request([], [], [
            'REMOTE_ADDR' => '66.249.80.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36 GoogleImageProxy',
        ]);
        EmailOpenTrackingService::recordOpen($record1->tracking_token, $reqProxy);
        $events1 = EmailOpenEvent::findByTrackingId($record1->id);
        $this->assertCount(1, $events1);
        $this->assertEquals(1, $events1[0]->is_proxy);
        $this->assertEquals('Google Image Proxy', $events1[0]->mail_client);

        // Apple Mail test
        [$body2, $record2] = EmailOpenTrackingService::prepareTracking('<p>Hi</p>', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'iclouduser@icloud.com',
            'source_type' => 'follow_up',
            'subject' => 'Follow up',
        ]);
        $reqApple = new Request([], [], [
            'REMOTE_ADDR' => '17.0.0.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        ]);
        EmailOpenTrackingService::recordOpen($record2->tracking_token, $reqApple);
        $events2 = EmailOpenEvent::findByTrackingId($record2->id);
        $this->assertCount(1, $events2);
        $this->assertEquals('Apple Mail', $events2[0]->mail_client);
        $this->assertEquals('Mobile', $events2[0]->device_type);
        $this->assertEquals('iOS', $events2[0]->os);
    }

    public function testMultiOpenFromSameRecipientCalculatesOneUniqueAndTenTotal(): void {
        [$body, $record] = EmailOpenTrackingService::prepareTracking('<p>Hi</p>', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'repeat_opener@partner.org',
            'source_type' => 'campaign',
            'subject' => 'Partnership Update',
        ]);

        $request = new Request([], [], [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Thunderbird/115.0',
        ]);

        // Simulate 10 opens
        for ($i = 0; $i < 10; $i++) {
            EmailOpenTrackingService::recordOpen($record->tracking_token, $request);
        }

        $refreshed = EmailOpenTracking::findByToken($record->tracking_token);
        $this->assertEquals(10, $refreshed->open_count);

        $events = EmailOpenEvent::findByTrackingId($record->id);
        $this->assertCount(10, $events);

        $stats = EmailOpenTrackingService::getOpenRateStats($this->userA->id, [
            'account_id' => $this->accountA->id,
        ]);

        $this->assertEquals(1, $stats['unique_recipients_opened']);
        $this->assertEquals(10, $stats['total_open_events']);
        $this->assertEquals(1, $stats['total_delivered']);
        $this->assertEquals(100.0, $stats['open_rate_percent']);
    }

    public function testOpenRateFormulaWithMultipleRecipients(): void {
        // Recipient 1: opened
        [$b1, $r1] = EmailOpenTrackingService::prepareTracking('text', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'user1@test.com',
            'source_type' => 'auto_reply',
            'subject' => 'Test 1',
        ]);
        // Recipient 2: opened twice
        [$b2, $r2] = EmailOpenTrackingService::prepareTracking('text', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'user2@test.com',
            'source_type' => 'auto_reply',
            'subject' => 'Test 2',
        ]);
        // Recipient 3: never opened
        [$b3, $r3] = EmailOpenTrackingService::prepareTracking('text', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'user3@test.com',
            'source_type' => 'auto_reply',
            'subject' => 'Test 3',
        ]);

        $req = new Request([], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'TestAgent']);
        EmailOpenTrackingService::recordOpen($r1->tracking_token, $req);
        EmailOpenTrackingService::recordOpen($r2->tracking_token, $req);
        EmailOpenTrackingService::recordOpen($r2->tracking_token, $req);

        $stats = EmailOpenTrackingService::getOpenRateStats($this->userA->id, [
            'account_id' => $this->accountA->id,
        ]);

        // 2 unique opened / 3 delivered * 100 = 66.67% -> 66.7%
        $this->assertEquals(3, $stats['total_delivered']);
        $this->assertEquals(2, $stats['unique_recipients_opened']);
        $this->assertEquals(3, $stats['total_open_events']);
        $this->assertEquals(66.7, $stats['open_rate_percent']);
        $this->assertEquals(1, $stats['total_unopened']);
    }

    public function testTenantIsolationMultiUserSecurity(): void {
        // Create tracking for User A
        [$bA, $rA] = EmailOpenTrackingService::prepareTracking('text A', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'secretA@private.com',
            'source_type' => 'auto_reply',
            'subject' => 'Confidential A',
        ]);
        $req = new Request([], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'AgentA']);
        EmailOpenTrackingService::recordOpen($rA->tracking_token, $req);

        // Create tracking for User B
        [$bB, $rB] = EmailOpenTrackingService::prepareTracking('text B', [
            'user_id' => $this->userB->id,
            'gmail_account_id' => $this->accountB->id,
            'recipient_email' => 'secretB@private.com',
            'source_type' => 'auto_reply',
            'subject' => 'Confidential B',
        ]);

        // Verify User A stats do not include User B
        $statsA = EmailOpenTrackingService::getOpenRateStats($this->userA->id);
        $this->assertEquals(1, $statsA['total_delivered']);
        $this->assertEquals(1, $statsA['unique_recipients_opened']);

        // Verify User B stats do not include User A
        $statsB = EmailOpenTrackingService::getOpenRateStats($this->userB->id);
        $this->assertEquals(1, $statsB['total_delivered']);
        $this->assertEquals(0, $statsB['unique_recipients_opened']);

        // Verify API endpoint permission check: User B attempting to view User A's open event details
        Auth::login($this->userB);
        $reportController = new ReplyReportController();
        $apiReq = new Request([], [], ['HTTP_ACCEPT' => 'application/json']);

        $jsonOut = $reportController->openEventDetails($apiReq, (int)$rA->id);
        $res = json_decode($jsonOut, true);

        $this->assertFalse($res['success']);
        $this->assertEquals('Unauthorized or record not found', $res['error']);
    }

    public function testOpenRateStatsApiEndpointReturnsAccurateJson(): void {
        Auth::login($this->userA);
        [$b, $r] = EmailOpenTrackingService::prepareTracking('text', [
            'user_id' => $this->userA->id,
            'gmail_account_id' => $this->accountA->id,
            'recipient_email' => 'api_test@customer.com',
            'source_type' => 'auto_reply',
            'subject' => 'API Subject',
        ]);
        $req = new Request([], [], ['REMOTE_ADDR' => '1.1.1.1', 'HTTP_USER_AGENT' => 'TestAPI']);
        EmailOpenTrackingService::recordOpen($r->tracking_token, $req);

        $reportController = new ReplyReportController();
        $apiReq = new Request(['date_range' => 'all'], [], ['HTTP_ACCEPT' => 'application/json']);

        $jsonOut = $reportController->openRateStats($apiReq);
        $data = json_decode($jsonOut, true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('recipients', $data);
        $this->assertGreaterThanOrEqual(1, $data['stats']['unique_recipients_opened']);
        $this->assertNotEmpty($data['recipients']);
        $this->assertEquals('api_test@customer.com', $data['recipients'][0]['recipient_email']);
    }
}
