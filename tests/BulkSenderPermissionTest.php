<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\DatabaseSanitizer;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Controllers\CampaignController;
use App\Controllers\AdminController;

class BulkSenderPermissionTest extends TestCase {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $sqlitePath = storage_path('database/test_permission.sqlite');
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
        DatabaseSanitizer::runOnce();
    }

    protected function setUp(): void {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testStandardUserDefaultHasNoBulkPermission(): void {
        $user = User::create([
            'name' => 'Standard User',
            'email' => 'standard_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);

        $this->assertFalse($user->canBulkSend());
    }

    public function testAdminAlwaysHasBulkPermission(): void {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);

        $this->assertTrue($admin->canBulkSend());
    }

    public function testAdminCanGrantAndRevokeBulkPermission(): void {
        $user = User::create([
            'name' => 'Target User',
            'email' => 'target_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);

        $this->assertFalse($user->canBulkSend());

        // Grant permission
        $user->update(['can_bulk_send' => 1]);
        $fresh = User::find($user->id);
        $this->assertTrue($fresh->canBulkSend());

        // Revoke permission
        $user->update(['can_bulk_send' => 0]);
        $fresh = User::find($user->id);
        $this->assertFalse($fresh->canBulkSend());
    }

    public function testAdminControllerToggleBulkPermission(): void {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
        ]);
        Auth::login($admin);

        $target = User::create([
            'name' => 'Staff Member',
            'email' => 'staff_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);

        $adminController = new AdminController();
        $request = new Request([], [], [], [], []);

        // Toggle ON
        try {
            $adminController->toggleBulkPermission($request, $target->id);
        } catch (\Throwable $e) {
            // redirect may exit or throw depending on environment
        }

        $fresh = User::find($target->id);
        $this->assertTrue($fresh->canBulkSend());

        // Toggle OFF
        try {
            $adminController->toggleBulkPermission($request, $target->id);
        } catch (\Throwable $e) {
        }

        $fresh = User::find($target->id);
        $this->assertFalse($fresh->canBulkSend());
    }

    public function testUnauthorizedUserRedirectedFromCampaigns(): void {
        $user = User::create([
            'name' => 'Restricted User',
            'email' => 'restricted_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);
        Auth::login($user);

        $campaignController = new CampaignController();
        $request = new Request([], [], [], [], []);

        // When non-permitted user visits campaigns, redirect is called
        $redirected = false;
        try {
            $campaignController->index($request);
        } catch (\Throwable $e) {
            $redirected = true;
        }

        // Verify flash message was set
        $warning = Session::getFlash('warning') ?? ($_SESSION['_flash']['warning'] ?? '');
        $this->assertStringContainsString('Bulk Email Campaign feature is not enabled', $warning);
    }
}
