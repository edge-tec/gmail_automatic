<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Session;
use App\Core\Auth;
use App\Models\User;
use Database\MigrationRunner;
use App\Core\App;

class SessionAuthTest extends TestCase {
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
        \App\Core\Database::resetConnection();

        new App();
        MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();
    }

    public function testSessionLifetimeIs30Days(): void {
        $this->assertEquals(2592000, Session::LIFETIME);
        $this->assertEquals(30 * 24 * 60 * 60, Session::LIFETIME);
    }

    public function testSessionStartConfiguresParams(): void {
        Session::start();
        $this->assertEquals('2592000', ini_get('session.gc_maxlifetime'));
        $this->assertEquals('2592000', ini_get('session.cookie_lifetime'));
        $this->assertEquals('1', ini_get('session.cookie_httponly'));
        $this->assertEquals('1', ini_get('session.use_only_cookies'));
        $this->assertDirectoryExists(storage_path('sessions'));
    }

    public function testUserSessionPersistsLogin(): void {
        $email = 'persist_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Persistent User',
            'email' => $email,
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'status' => 'active'
        ]);

        Auth::login($user);
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals($user->email, Auth::user()->email);

        // Session retains user_id
        $this->assertEquals($user->id, Session::get('user_id'));

        // Logout cleans up
        Auth::logout();
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::id());
    }

    public function testPersistentRememberTokenAutoLoginWhenSessionCleared(): void {
        $email = 'remember_' . uniqid() . '@test.com';
        $user = User::create([
            'name' => 'Remember Me User',
            'email' => $email,
            'password' => password_hash('Secret123', PASSWORD_BCRYPT),
            'status' => 'active'
        ]);

        // Login user
        Auth::login($user);
        $this->assertTrue(Auth::check());
        $this->assertNotEmpty($_COOKIE[Auth::REMEMBER_COOKIE]);

        // Verify token saved in DB
        $freshUser = User::find($user->id);
        $this->assertNotEmpty($freshUser->remember_token);
        $this->assertNotEmpty($freshUser->remember_token_expires_at);

        // Simulate browser restart / session expiration / server restart:
        $_SESSION = [];
        Session::remove('user_id');

        // Reset memory cache
        $reflector = new \ReflectionClass(Auth::class);
        $prop = $reflector->getProperty('user');
        $prop->setValue(null, null);

        // Auth::check() must seamlessly auto-login using the 30-day remember cookie!
        $this->assertTrue(Auth::check(), "Auth::check() must restore login automatically via 30-day remember cookie");
        $this->assertEquals($user->id, Auth::id());
        $this->assertEquals($user->email, Auth::user()->email);

        // When user explicitly logs out, database token and cookie are purged
        Auth::logout();
        $this->assertFalse(Auth::check());
        $this->assertEmpty($_COOKIE[Auth::REMEMBER_COOKIE] ?? '');

        $loggedOutUser = User::find($user->id);
        $this->assertNull($loggedOutUser->remember_token);
    }
}
