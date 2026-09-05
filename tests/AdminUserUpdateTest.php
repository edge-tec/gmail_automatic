<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\DatabaseSanitizer;
use App\Core\Auth;
use App\Core\Request;
use App\Models\User;
use App\Controllers\AdminController;

class AdminUserUpdateTest extends TestCase {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $sqlitePath = storage_path('database/test_admin_update.sqlite');
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

    public function testAdminUpdateUserSuccessfully(): void {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
        ]);
        Auth::login($admin);

        $targetUser = User::create([
            'name' => 'Original Name',
            'email' => 'target_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'can_bulk_send' => 0,
        ]);

        $_POST = [
            'name' => 'Updated User Name',
            'email' => $targetUser->email,
            'role' => 'user',
            'status' => 'active',
            'plan_id' => '0',
            'gmail_limit' => '10',
            'subscription_status' => 'active',
            'trial_status' => 'not_started',
            'can_bulk_send' => '1',
        ];

        $request = new Request();
        $controller = new AdminController();

        // Should not throw any exception or error
        $controller->updateUser($request, $targetUser->id);

        $refreshed = User::find($targetUser->id);
        $this->assertEquals('Updated User Name', $refreshed->name);
        $this->assertEquals(1, $refreshed->can_bulk_send);
        $this->assertEquals(10, $refreshed->gmail_limit);
        $this->assertEquals('active', $refreshed->subscription_status);
        $this->assertNotEmpty($_SESSION['_flash']['success'] ?? null);
    }

    public function testAdminUpdateDuplicateEmailFailsGracefully(): void {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
        ]);
        Auth::login($admin);

        $existingUser = User::create([
            'name' => 'Existing User',
            'email' => 'already_taken_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $userToEdit = User::create([
            'name' => 'User To Edit',
            'email' => 'to_edit_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $_POST = [
            'name' => 'User To Edit',
            'email' => $existingUser->email, // Attempting to steal existing email
            'role' => 'user',
            'status' => 'active',
        ];

        $request = new Request();
        $controller = new AdminController();

        $controller->updateUser($request, $userToEdit->id);

        $this->assertNotEmpty($_SESSION['_flash']['error'] ?? null);
        $this->assertStringContainsString('already in use', $_SESSION['_flash']['error']);

        // Confirm email was not changed
        $refreshed = User::find($userToEdit->id);
        $this->assertEquals('to_edit_', substr($refreshed->email, 0, 8));
    }

    public function testUserUpdateGracefulHandling(): void {
        $user = User::create([
            'name' => 'Safety User',
            'email' => 'safety_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        // Updating with can_bulk_send works cleanly
        $updated = $user->update([
            'name' => 'Safety User 2',
            'can_bulk_send' => 1,
        ]);

        $this->assertTrue($updated);
        $this->assertEquals(1, $user->can_bulk_send);
        $this->assertEquals('Safety User 2', $user->name);
    }
}
