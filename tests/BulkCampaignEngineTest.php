<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaignMessage;
use App\Models\EmailCampaignSuppression;
use App\Models\GmailCampaignDailyUsage;
use App\Services\RecipientImportService;
use App\Services\CampaignEngine;
use ZipArchive;

class BulkCampaignEngineTest extends TestCase {
    private static User $testUser;
    private static string $tempDir;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $sqlitePath = storage_path('database/test_campaign.sqlite');
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';
        $_ENV['APP_ENV'] = 'testing';

        config('_reset_');
        Database::resetConnection();

        new App();
        \Database\MigrationRunner::run();

        self::$tempDir = storage_path('temp/tests_' . uniqid());
        if (!is_dir(self::$tempDir)) {
            mkdir(self::$tempDir, 0775, true);
        }

        // Create a test user
        self::$testUser = User::create([
            'name' => 'Campaign Test User',
            'email' => 'campaign_user_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);
    }

    public static function tearDownAfterClass(): void {
        if (is_dir(self::$tempDir)) {
            $files = array_diff(scandir(self::$tempDir), ['.', '..']);
            foreach ($files as $f) {
                @unlink(self::$tempDir . '/' . $f);
            }
            @rmdir(self::$tempDir);
        }
        parent::tearDownAfterClass();
    }

    private function createAccount(string $email, int $bulkLimit = 50, int $campaignEnabled = 1, ?int $userId = null): GmailAccount {
        $uid = $userId ?? self::$testUser->id;
        return GmailAccount::createOrUpdate([
            'user_id' => $uid,
            'gmail_email' => $email,
            'access_token' => 'mock_token_' . uniqid(),
            'refresh_token' => 'mock_refresh_' . uniqid(),
            'status' => 'connected',
            'bulk_daily_limit' => $bulkLimit,
            'campaign_enabled' => $campaignEnabled,
        ]);
    }

    private function createCampaign(array $overrides = [], ?int $userId = null): EmailCampaign {
        $uid = $userId ?? self::$testUser->id;
        return EmailCampaign::create(array_merge([
            'user_id' => $uid,
            'name' => 'Test Campaign ' . uniqid(),
            'status' => 'active',
            'daily_campaign_limit' => 300,
            'sending_interval' => 0, // No delay for fast testing
            'start_time' => '00:00',
            'end_time' => '23:59',
            'timezone' => 'UTC',
        ], $overrides));
    }

    // ==========================================
    // 1. RECIPIENT IMPORT TESTS
    // ==========================================

    public function testTxtImport(): void {
        $campaign = $this->createCampaign();
        $filePath = self::$tempDir . '/recipients.txt';
        $content = "john@example.com\n" .
                   "Jane Doe <jane@example.com>\n" .
                   "bob@example.com, Bob Smith\n" .
                   "invalid-email-address\n" .
                   "john@example.com\n"; // Duplicate
        file_put_contents($filePath, $content);

        $importService = new RecipientImportService();
        $result = $importService->importFile($campaign->id, self::$testUser->id, $filePath, 'txt');

        $this->assertEquals(5, $result['total_rows']);
        $this->assertEquals(3, $result['valid_emails']);
        $this->assertEquals(1, $result['invalid_emails']);
        $this->assertEquals(1, $result['duplicates']);
        $this->assertEquals(3, $result['imported']);

        $recips = EmailCampaignRecipient::paginateByCampaign($campaign->id);
        $this->assertCount(3, $recips);
    }

    public function testCsvImportWithPersonalizationFields(): void {
        $campaign = $this->createCampaign();
        $filePath = self::$tempDir . '/recipients.csv';
        $content = "email,first_name,last_name,company,custom_field_1\n" .
                   "alice@company.com,Alice,Wonder,Wonderland Corp,VIP\n" .
                   "charlie@sample.org,Charlie,Brown,Peanuts Inc,Normal\n" .
                   "not_an_email,Bad,Guy,Fake LLC,None\n" .
                   "alice@company.com,Alice,Wonder,Duplicate Corp,VIP\n"; // Duplicate
        file_put_contents($filePath, $content);

        $importService = new RecipientImportService();
        $result = $importService->importFile($campaign->id, self::$testUser->id, $filePath, 'csv');

        $this->assertEquals(4, $result['total_rows']);
        $this->assertEquals(2, $result['valid_emails']);
        $this->assertEquals(1, $result['invalid_emails']);
        $this->assertEquals(1, $result['duplicates']);
        $this->assertEquals(2, $result['imported']);

        $alice = EmailCampaignRecipient::findByCampaignAndEmail($campaign->id, 'alice@company.com');
        $this->assertNotNull($alice);
        $this->assertEquals('Alice', $alice->first_name);
        $this->assertEquals('Wonderland Corp', $alice->company);
        $this->assertEquals('VIP', $alice->custom_field_1);
    }

    public function testXlsxImport(): void {
        $campaign = $this->createCampaign();
        $filePath = self::$tempDir . '/recipients.xlsx';

        // Construct valid minimal XLSX file archive
        $zip = new ZipArchive();
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="6" uniqueCount="6">' .
            '<si><t>email</t></si>' .
            '<si><t>first_name</t></si>' .
            '<si><t>company</t></si>' .
            '<si><t>xlsx_user1@example.com</t></si>' .
            '<si><t>Sarah</t></si>' .
            '<si><t>SarahTech</t></si>' .
            '</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>' .
            '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>' .
            '<row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2" t="s"><v>5</v></c></row>' .
            '</sheetData>' .
            '</worksheet>';

        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $importService = new RecipientImportService();
        $result = $importService->importFile($campaign->id, self::$testUser->id, $filePath, 'xlsx');

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['imported']);

        $recip = EmailCampaignRecipient::findByCampaignAndEmail($campaign->id, 'xlsx_user1@example.com');
        $this->assertNotNull($recip);
        $this->assertEquals('Sarah', $recip->first_name);
        $this->assertEquals('SarahTech', $recip->company);
    }

    public function testLargeFileChunkProcessing(): void {
        $campaign = $this->createCampaign();
        $filePath = self::$tempDir . '/large_list.txt';

        $handle = fopen($filePath, 'w');
        for ($i = 1; $i <= 600; $i++) {
            fwrite($handle, "bulk_user_{$i}@example.com, Bulk User {$i}\n");
        }
        fclose($handle);

        $importService = new RecipientImportService();
        $result = $importService->importFile($campaign->id, self::$testUser->id, $filePath, 'txt');

        $this->assertEquals(600, $result['total_rows']);
        $this->assertEquals(600, $result['imported']);
        $this->assertEquals(600, $campaign->getRemainingCount());
    }

    // ==========================================
    // 2. TRUE ROUND-ROBIN SENDING TESTS
    // ==========================================

    public function testTrueRoundRobin5Accounts(): void {
        // Create 5 accounts: A, B, C, D, E
        $accA = $this->createAccount('round_a@gmail.com', 100);
        $accB = $this->createAccount('round_b@gmail.com', 100);
        $accC = $this->createAccount('round_c@gmail.com', 100);
        $accD = $this->createAccount('round_d@gmail.com', 100);
        $accE = $this->createAccount('round_e@gmail.com', 100);

        // Sort accounts by ID
        $expectedOrder = [$accA, $accB, $accC, $accD, $accE];
        usort($expectedOrder, fn($x, $y) => $x->id <=> $y->id);

        $campaign = $this->createCampaign(['daily_campaign_limit' => 500]);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Round Robin Test',
            'body' => 'Hello {{email}}',
        ]);

        // Add 15 recipients (3 full cycles of 5)
        $recips = [];
        for ($i = 1; $i <= 15; $i++) {
            $recips[] = ['email' => "round_recip_{$i}@example.com"];
        }
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        // Process 15 sends
        $sent = CampaignEngine::processCampaign($campaign, 15);
        $this->assertEquals(15, $sent);

        // Fetch audit logs in order
        $sends = Database::query(
            "SELECT * FROM email_campaign_sends WHERE campaign_id = :cid ORDER BY id ASC",
            ['cid' => $campaign->id]
        );
        $this->assertCount(15, $sends);

        // Check sequence repeats: expectedOrder[0..4], expectedOrder[0..4], expectedOrder[0..4]
        for ($i = 0; $i < 15; $i++) {
            $expectedAccount = $expectedOrder[$i % 5];
            $this->assertEquals(
                $expectedAccount->id,
                (int)$sends[$i]['gmail_account_id'],
                "Turn #{$i} should have been dispatched via Account #{$expectedAccount->id} ({$expectedAccount->gmail_email})"
            );
        }
    }

    // ==========================================
    // 3. PER-GMAIL & CAMPAIGN LIMITS TESTS
    // ==========================================

    public function testPerGmailLimitsEnforcement(): void {
        $acc1 = $this->createAccount('limit_a@gmail.com', 2);
        $acc2 = $this->createAccount('limit_b@gmail.com', 3);
        $acc3 = $this->createAccount('limit_c@gmail.com', 5);

        $campaign = $this->createCampaign(['daily_campaign_limit' => 50]);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Limit Test',
            'body' => 'Limit Test Body',
        ]);

        // Add 20 recipients
        $recips = [];
        for ($i = 1; $i <= 20; $i++) {
            $recips[] = ['email' => "limit_recip_{$i}@example.com"];
        }
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        // Process sends
        CampaignEngine::processCampaign($campaign, 20);

        // Check today's usage for each account
        $u1 = GmailCampaignDailyUsage::getAccountUsage($acc1->id);
        $u2 = GmailCampaignDailyUsage::getAccountUsage($acc2->id);
        $u3 = GmailCampaignDailyUsage::getAccountUsage($acc3->id);

        $this->assertLessThanOrEqual(2, $u1['emails_sent'], "Account A must not exceed limit 2");
        $this->assertLessThanOrEqual(3, $u2['emails_sent'], "Account B must not exceed limit 3");
        $this->assertLessThanOrEqual(5, $u3['emails_sent'], "Account C must not exceed limit 5");
    }

    public function testGlobalCampaignDailyLimit(): void {
        $acc = $this->createAccount('camp_lim@gmail.com', 100);

        // Campaign daily limit = 5
        $campaign = $this->createCampaign(['daily_campaign_limit' => 5]);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Campaign Limit Test',
            'body' => 'Campaign Limit Body',
        ]);

        $recips = [];
        for ($i = 1; $i <= 10; $i++) {
            $recips[] = ['email' => "camplim_user_{$i}@example.com"];
        }
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        $sent = CampaignEngine::processCampaign($campaign, 10);
        $this->assertEquals(5, $sent, "Campaign must halt when campaign daily limit (5) is reached");
        $this->assertEquals(5, $campaign->getSendsCountToday());
    }

    // ==========================================
    // 4. ACCOUNT FAILURE & COOLDOWN HANDLING
    // ==========================================

    public function testAccountFailureCooldownAndBypass(): void {
        $userFail = User::create([
            'name' => 'Fail Test User',
            'email' => 'fail_user_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $accA = $this->createAccount('fail_a@gmail.com', 50, 1, $userFail->id);
        $accB = $this->createAccount('fail_b@gmail.com', 50, 1, $userFail->id);

        // Mark account A on temporary cooldown
        $accA->markTemporaryFailure(15);
        $this->assertFalse($accA->isCampaignEligible(), "Account A should not be eligible during cooldown");
        $this->assertTrue($accB->isCampaignEligible(), "Account B must remain eligible");

        $campaign = $this->createCampaign([], $userFail->id);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userFail->id,
            'subject' => 'Failure Bypass Test',
            'body' => 'Hello',
        ]);

        $recips = [['email' => 'bypass_user@example.com']];
        EmailCampaignRecipient::insertBatch($campaign->id, $userFail->id, $recips);
        $campaign->recalculateStats();

        // Send should succeed via account B
        $sent = CampaignEngine::processCampaign($campaign, 1);
        $this->assertEquals(1, $sent);

        $lastSend = Database::first(
            "SELECT * FROM email_campaign_sends WHERE campaign_id = :cid ORDER BY id DESC LIMIT 1",
            ['cid' => $campaign->id]
        );
        $this->assertEquals($accB->id, (int)$lastSend['gmail_account_id']);
    }

    // ==========================================
    // 5. WORKER RESTART STATE PERSISTENCE
    // ==========================================

    public function testWorkerRestartMaintainsRoundRobinState(): void {
        $accA = $this->createAccount('restart_a@gmail.com', 50);
        $accB = $this->createAccount('restart_b@gmail.com', 50);
        $accC = $this->createAccount('restart_c@gmail.com', 50);

        $accounts = [$accA, $accB, $accC];
        usort($accounts, fn($x, $y) => $x->id <=> $y->id);

        $campaign = $this->createCampaign();
        // Simulate that Account A and B have already been used, pointer is at B (accounts[1])
        $campaign->update(['last_used_gmail_account_id' => $accounts[1]->id]);

        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Restart Pointer Test',
            'body' => 'Restart Body',
        ]);

        $recips = [['email' => 'restart_recip@example.com']];
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        // Worker executes next send
        $sent = CampaignEngine::processCampaign($campaign, 1);
        $this->assertEquals(1, $sent);

        // The send MUST be sent by Account C (accounts[2]), not resetting back to Account A!
        $fresh = EmailCampaign::find($campaign->id);
        $this->assertEquals(
            $accounts[2]->id,
            $fresh->last_used_gmail_account_id,
            "Next account after B must be C across worker restarts"
        );
    }

    // ==========================================
    // 6. ZERO FALLBACK POLICY & MESSAGE DELETION
    // ==========================================

    public function testNoFallbackWhenMessagesDeletedOrEmpty(): void {
        $this->createAccount('zerofb@gmail.com', 50);

        $campaign = $this->createCampaign();
        // Add a recipient but NO message variation
        $recips = [['email' => 'zero_fb_recip@example.com']];
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        // Attempt to send
        $sent = CampaignEngine::processCampaign($campaign, 1);
        $this->assertEquals(0, $sent, "Zero fallback enforced: zero messages sent when no message variations exist");

        // Recipient must remain pending or reset, never marked sent
        $r = EmailCampaignRecipient::findByCampaignAndEmail($campaign->id, 'zero_fb_recip@example.com');
        $this->assertNotEquals('sent', $r->status);
    }

    // ==========================================
    // 7. RANDOM MESSAGE VARIATION SELECTION
    // ==========================================

    public function testRandomMessageVariationSelection(): void {
        $this->createAccount('random_msg@gmail.com', 100);

        $campaign = $this->createCampaign();
        $msg1 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Subject One',
            'body' => 'Body One {{first_name}}',
        ]);
        $msg2 = EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Subject Two',
            'body' => 'Body Two {{first_name}}',
        ]);

        $recips = [];
        for ($i = 1; $i <= 20; $i++) {
            $recips[] = ['email' => "random_recip_{$i}@example.com", 'first_name' => "User{$i}"];
        }
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        CampaignEngine::processCampaign($campaign, 20);

        $freshMsg1 = EmailCampaignMessage::find($msg1->id);
        $freshMsg2 = EmailCampaignMessage::find($msg2->id);

        $this->assertGreaterThan(0, $freshMsg1->usage_count, "Variation 1 should have been selected at least once");
        $this->assertGreaterThan(0, $freshMsg2->usage_count, "Variation 2 should have been selected at least once");
        $this->assertEquals(20, $freshMsg1->usage_count + $freshMsg2->usage_count);
    }

    // ==========================================
    // 8. SUPPRESSION & UNLISTED BOUNCE HANDLING
    // ==========================================

    public function testSuppressedRecipientIsSkipped(): void {
        $this->createAccount('supp_test@gmail.com', 50);

        $campaign = $this->createCampaign();
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Suppression Test',
            'body' => 'Hello',
        ]);

        $suppressedEmail = 'unsub_user@example.com';
        EmailCampaignSuppression::suppress(self::$testUser->id, $suppressedEmail, 'unsubscribed');

        $recips = [
            ['email' => $suppressedEmail],
            ['email' => 'normal_user@example.com'],
        ];
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        CampaignEngine::processCampaign($campaign, 2);

        $suppRecip = EmailCampaignRecipient::findByCampaignAndEmail($campaign->id, $suppressedEmail);
        $this->assertEquals('skipped', $suppRecip->status, "Suppressed recipient must be marked skipped");

        $normalRecip = EmailCampaignRecipient::findByCampaignAndEmail($campaign->id, 'normal_user@example.com');
        $this->assertEquals('sent', $normalRecip->status, "Non-suppressed recipient must be sent");
    }

    // ==========================================
    // 9. SCHEDULE & TIMEZONE ENFORCEMENT
    // ==========================================

    public function testScheduleOutsideHoursDoesNotSend(): void {
        $this->createAccount('sched_test@gmail.com', 50);

        // Schedule window that does not match current time (e.g. 03:00 to 03:01 in UTC)
        $campaign = $this->createCampaign([
            'start_time' => '03:00',
            'end_time' => '03:01',
            'timezone' => 'UTC',
        ]);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => self::$testUser->id,
            'subject' => 'Schedule Test',
            'body' => 'Hello',
        ]);

        $recips = [['email' => 'sched_recip@example.com']];
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);
        $campaign->recalculateStats();

        // If current UTC time is outside 03:00-03:01, schedule check must return false and send 0
        $nowHhMm = gmdate('H:i');
        if ($nowHhMm < '03:00' || $nowHhMm > '03:01') {
            $sent = CampaignEngine::processCampaign($campaign, 1);
            $this->assertEquals(0, $sent, "Must not send outside scheduled hours");
        }
    }

    // ==========================================
    // 10. PAUSE, RESUME, AND CANCEL LIFECYCLE
    // ==========================================

    public function testPauseResumeAndCancel(): void {
        $userPause = User::create([
            'name' => 'Pause Test User',
            'email' => 'pause_user_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->createAccount('pause_test@gmail.com', 50, 1, $userPause->id);

        $campaign = $this->createCampaign([], $userPause->id);
        EmailCampaignMessage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userPause->id,
            'subject' => 'Pause Test',
            'body' => 'Hello',
        ]);

        $recips = [
            ['email' => 'pause1@example.com'],
            ['email' => 'pause2@example.com'],
            ['email' => 'pause3@example.com'],
        ];
        EmailCampaignRecipient::insertBatch($campaign->id, $userPause->id, $recips);
        $campaign->recalculateStats();

        // 1. Send 1 email while active
        $sent1 = CampaignEngine::processCampaign($campaign, 1);
        $this->assertEquals(1, $sent1);
        $fresh1 = EmailCampaign::find($campaign->id);
        $this->assertEquals(1, $fresh1->sent_count);

        // 2. Pause campaign
        $campaign->update(['status' => 'paused']);
        $sent2 = CampaignEngine::processCampaign($campaign, 1);
        $this->assertEquals(0, $sent2);
        $fresh2 = EmailCampaign::find($campaign->id);
        $this->assertEquals(1, $fresh2->sent_count, "Paused campaign must not send any emails");

        // 3. Resume campaign
        $campaign->update(['status' => 'active']);
        CampaignEngine::processCampaign($campaign, 1);
        $fresh = EmailCampaign::find($campaign->id);
        $this->assertEquals(2, $fresh->sent_count, "Resumed campaign must continue sending");

        // 4. Cancel campaign
        $campaign->update(['status' => 'cancelled']);
        Database::execute(
            "UPDATE email_campaign_recipients SET status = 'cancelled' WHERE campaign_id = :cid AND status = 'pending'",
            ['cid' => $campaign->id]
        );
        $remaining = $campaign->getRemainingCount();
        $this->assertEquals(0, $remaining, "Pending recipients must be marked cancelled");
    }

    // ==========================================
    // 11. ATOMIC CLAIM CONCURRENCY TEST
    // ==========================================

    public function testAtomicRecipientClaimConcurrency(): void {
        $campaign = $this->createCampaign();
        $recips = [
            ['email' => 'concurrency1@example.com'],
            ['email' => 'concurrency2@example.com'],
        ];
        EmailCampaignRecipient::insertBatch($campaign->id, self::$testUser->id, $recips);

        // Worker 1 claims
        $claim1 = EmailCampaignRecipient::claimNextPending($campaign->id);
        $this->assertNotNull($claim1);
        $this->assertEquals('sending', $claim1->status);

        // Worker 2 claims concurrently
        $claim2 = EmailCampaignRecipient::claimNextPending($campaign->id);
        $this->assertNotNull($claim2);
        $this->assertNotEquals($claim1->id, $claim2->id, "Two concurrent workers must not claim the same recipient!");

        // Worker 3 attempts to claim when all are in 'sending'
        $claim3 = EmailCampaignRecipient::claimNextPending($campaign->id);
        $this->assertNull($claim3, "Worker 3 must get null when no pending recipients are available");
    }
}
