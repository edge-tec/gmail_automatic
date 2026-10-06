<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\Lead;
use App\Models\LeadFile;
use App\Models\UserLeadPermission;
use App\Models\LeadDeletionOperation;
use App\Models\LeadStorageCleanup;
use App\Models\LeadAuditLog;
use App\Services\LeadManagementService;
use App\Services\LeadStorageService;
use App\Services\LeadAuditService;
use Database\MigrationRunner;
use Exception;

class LeadManagementSystemTest extends TestCase {

    private static User $adminUser;
    private static User $userA;
    private static User $userB;

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

        // Ensure self-healing schemas are verified
        Lead::ensureSchema();
        LeadFile::ensureSchema();
        UserLeadPermission::ensureSchema();
        LeadDeletionOperation::ensureSchema();
        LeadStorageCleanup::ensureSchema();
        LeadAuditLog::ensureSchema();

        self::$adminUser = User::create([
            'name' => 'Admin Test User',
            'email' => 'admin_lead_test_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active'
        ]);

        self::$userA = User::create([
            'name' => 'Tenant User A',
            'email' => 'tenant_a_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active'
        ]);

        self::$userB = User::create([
            'name' => 'Tenant User B',
            'email' => 'tenant_b_' . uniqid() . '@example.com',
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active'
        ]);
    }

    protected function setUp(): void {
        parent::setUp();
        new App();
    }

    public function testRbacAndGranularPermissions(): void {
        $user = self::$userA;

        // Admin has all permissions automatically
        $this->assertTrue(self::$adminUser->hasLeadPermission('leads.delete'));
        $this->assertTrue(self::$adminUser->hasLeadPermission('leads.clear'));

        // Fresh regular user does not have destructive permissions initially
        $this->assertFalse($user->hasLeadPermission('leads.delete'));
        $this->assertFalse($user->hasLeadPermission('leads.clear'));

        // Apply Viewer Preset
        $user->applyLeadRolePreset('lead_viewer');
        $this->assertTrue($user->hasLeadPermission('leads.view'));
        $this->assertTrue($user->hasLeadPermission('lead_files.view'));
        $this->assertFalse($user->hasLeadPermission('leads.create'));
        $this->assertFalse($user->hasLeadPermission('leads.delete'));

        // Apply Manager Preset
        $user->applyLeadRolePreset('lead_manager');
        $this->assertTrue($user->hasLeadPermission('leads.create'));
        $this->assertTrue($user->hasLeadPermission('leads.edit'));
        $this->assertTrue($user->hasLeadPermission('leads.delete'));
        $this->assertTrue($user->hasLeadPermission('leads.bulk_delete'));
        $this->assertFalse($user->hasLeadPermission('leads.clear'));

        // Explicitly Revoke bulk_delete
        $user->setLeadPermission('leads.bulk_delete', false);
        $this->assertFalse($user->hasLeadPermission('leads.bulk_delete'));

        // Explicitly Re-grant bulk_delete
        $user->setLeadPermission('leads.bulk_delete', true);
        $this->assertTrue($user->hasLeadPermission('leads.bulk_delete'));
    }

    public function testTenantBoundaryIsolation(): void {
        // User A creates a lead
        $leadA = Lead::create([
            'user_id' => self::$userA->id,
            'email' => 'alpha_' . uniqid() . '@acme.com',
            'first_name' => 'Alice',
            'company' => 'Acme Corp',
            'status' => 'active'
        ]);

        // User B creates a lead
        $leadB = Lead::create([
            'user_id' => self::$userB->id,
            'email' => 'beta_' . uniqid() . '@globex.com',
            'first_name' => 'Bob',
            'company' => 'Globex',
            'status' => 'active'
        ]);

        // User A cannot find User B's lead
        $this->assertNotNull(Lead::findForUser($leadA->id, self::$userA->id));
        $this->assertNull(Lead::findForUser($leadB->id, self::$userA->id));

        // User B query leads returns only their leads
        $bLeads = LeadManagementService::queryLeads(self::$userB->id, ['search' => '']);
        $bEmails = array_map(fn($l) => $l->email, $bLeads['items']);
        $this->assertContains($leadB->email, $bEmails);
        $this->assertNotContains($leadA->email, $bEmails);

        // User B cannot permanently delete User A's lead
        $deleteResult = LeadManagementService::deleteLeadPermanently(self::$userB->id, $leadA->id);
        $this->assertFalse($deleteResult['success']);
        $this->assertNotNull(Lead::find($leadA->id));
    }

    public function testLeadLifecycleAndSoftDeleteArchive(): void {
        $lead = Lead::create([
            'user_id' => self::$userA->id,
            'email' => 'lifecycle_' . uniqid() . '@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active'
        ]);

        $this->assertEquals('active', $lead->status);
        $this->assertEquals('John Doe', $lead->getFullName());

        // Archive
        $lead->archive();
        $reloaded = Lead::find($lead->id);
        $this->assertEquals('archived', $reloaded->status);

        // Restore
        $reloaded->restore();
        $restored = Lead::find($lead->id);
        $this->assertEquals('active', $restored->status);
    }

    public function testSharedFileProtectionDuringDeletion(): void {
        $storageDir = storage_path('app/leads/' . self::$userA->id);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $content = "email,first_name,last_name\nshared1@test.com,Shared,One\nshared2@test.com,Shared,Two";
        $tmpFile = tempnam(sys_get_temp_dir(), 'lead_test_');
        file_put_contents($tmpFile, $content);
        $hash = hash_file('sha256', $tmpFile);

        $uploadData = [
            'name' => 'shared_contacts.csv',
            'tmp_name' => $tmpFile,
            'size' => filesize($tmpFile),
            'error' => UPLOAD_ERR_OK
        ];

        // Store first file reference
        $res1 = LeadStorageService::storeUploadedLeadFile(self::$userA->id, $uploadData);
        $this->assertTrue($res1['success']);
        $file1 = $res1['file'];
        $filePath1 = $file1->getAbsolutePath();
        $this->assertFileExists($filePath1);

        // Store second file record pointing to the exact same hash (simulating re-upload or shared asset)
        $res2 = LeadStorageService::storeUploadedLeadFile(self::$userA->id, $uploadData);
        $this->assertTrue($res2['success']);
        $file2 = $res2['file'];

        // Associate file 1 with Lead 1
        $lead1 = Lead::create([
            'user_id' => self::$userA->id,
            'email' => 'lead1_' . uniqid() . '@test.com',
            'file_ids' => [$file1->id],
            'status' => 'active'
        ]);

        // Associate file 2 with Lead 2
        $lead2 = Lead::create([
            'user_id' => self::$userA->id,
            'email' => 'lead2_' . uniqid() . '@test.com',
            'file_ids' => [$file2->id],
            'status' => 'active'
        ]);

        // Both file records share the same hash
        $refCount = LeadFile::countActiveReferencesByHash($hash);
        $this->assertGreaterThanOrEqual(2, $refCount);

        // Delete File Record 1 directly
        $delFile1 = LeadStorageService::deleteFileRecordAndStorage($file1->id, self::$userA->id);
        $this->assertTrue($delFile1['success']);
        $this->assertTrue($delFile1['shared']); // Shared protection triggered!
        // Physical file on disk MUST still exist!
        $this->assertFileExists($filePath1);

        // Now delete File Record 2 (last remaining reference)
        $delFile2 = LeadStorageService::deleteFileRecordAndStorage($file2->id, self::$userA->id);
        $this->assertTrue($delFile2['success']);
        $this->assertFalse($delFile2['shared']); // Not shared anymore!

        // Outbox cleanup was queued
        $pending = LeadStorageCleanup::getPending(5);
        $this->assertNotEmpty($pending);

        // Process storage cleanup outbox
        $processed = LeadStorageService::processPendingStorageCleanups(10);
        $this->assertGreaterThan(0, $processed['success']);

        // Now physical file has been unlinked from disk safely
        $this->assertFileDoesNotExist($filePath1);

        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    public function testClearAllLeadsSafetyAndConfirmation(): void {
        // Seed 3 leads for User A
        for ($i = 0; $i < 3; $i++) {
            Lead::create([
                'user_id' => self::$userA->id,
                'email' => "clear_test_{$i}_" . uniqid() . "@test.com",
                'status' => 'active'
            ]);
        }

        $countBefore = Lead::countForUser(self::$userA->id);
        $this->assertGreaterThanOrEqual(3, $countBefore);

        // 1. Calling clearAllLeads without the required phrase fails
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Clear leads operation requires explicit confirmation phrase');
        LeadManagementService::clearAllLeads(self::$userA->id, [
            'confirmation_text' => 'wrong confirmation'
        ]);
    }

    public function testClearAllLeadsWithCorrectConfirmation(): void {
        // Seed 3 leads for User A
        for ($i = 0; $i < 3; $i++) {
            Lead::create([
                'user_id' => self::$userA->id,
                'email' => "wipetest_{$i}_" . uniqid() . "@test.com",
                'status' => 'active'
            ]);
        }

        // Grant permission to execute clear leads
        self::$userA->setLeadPermission('leads.clear', true);

        // Execute clear with exact phrase "DELETE ALL LEADS"
        $result = LeadManagementService::clearAllLeads(self::$userA->id, [
            'confirmation_text' => 'DELETE ALL LEADS',
            'ip' => '127.0.0.1'
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['deleted_count']);

        // Verify count is 0 for User A
        $countAfter = Lead::countForUser(self::$userA->id);
        $this->assertEquals(0, $countAfter);

        // Verify User B's leads were NOT affected (tenant isolation)
        $countB = Lead::countForUser(self::$userB->id);
        $this->assertGreaterThan(0, $countB);

        // Verify audit log was recorded
        $recentLogs = LeadAuditLog::forUser(self::$userA->id, 5);
        $actions = array_map(fn($l) => $l->action, $recentLogs['items']);
        $this->assertContains('lead.clear', $actions);
    }

    public function testOrphanStorageScanEngine(): void {
        $storageDir = LeadStorageService::getStorageDir(self::$userB->id);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Create an untracked disk file (orphan)
        $orphanFile = $storageDir . '/untracked_orphan_' . uniqid() . '.csv';
        file_put_contents($orphanFile, "email\norphan@test.com");

        $scan = LeadStorageService::scanStorageOrphans(self::$userB->id);

        $this->assertIsArray($scan['disk_orphans']);
        $relativeOrphanPath = 'leads/' . self::$userB->id . '/' . basename($orphanFile);
        $this->assertContains($relativeOrphanPath, $scan['disk_orphans']);

        @unlink($orphanFile);
    }
}
