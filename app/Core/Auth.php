<?php
namespace App\Core;

use App\Models\User;

class Auth {
    private static ?User $user = null;
    public const REMEMBER_COOKIE = 'gmail_auto_remember_token';
    public const REMEMBER_LIFETIME = 2592000; // 30 days (30 * 24 * 3600)

    public static function attempt(string $email, string $password): bool {
        $user = User::findByEmail($email);
        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->password)) {
            return false;
        }

        if ($user->status !== 'active') {
            return false;
        }

        self::login($user);
        return true;
    }

    public static function login(User $user): void {
        Session::start();
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        Session::set('user_id', $user->id);
        self::$user = $user;

        // Generate persistent 30-day auto-login token
        $rememberToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::REMEMBER_LIFETIME);

        $user->update([
            'remember_token' => $rememberToken,
            'remember_token_expires_at' => $expiresAt,
        ]);

        self::setRememberCookie($user, $rememberToken);
    }

    public static function logout(): void {
        $user = self::user();
        if ($user) {
            $user->update([
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ]);
        }

        self::clearRememberCookie();
        self::$user = null;
        Session::remove('user_id');
        Session::destroy();
    }

    public static function check(): bool {
        return self::id() !== null;
    }

    public static function id(): ?int {
        Session::start();
        $id = Session::get('user_id');
        if ($id) {
            return (int)$id;
        }

        // Try restoring from persistent 30-day Remember Cookie
        if (!empty($_COOKIE[self::REMEMBER_COOKIE])) {
            $user = self::attemptRememberLogin($_COOKIE[self::REMEMBER_COOKIE]);
            if ($user) {
                return $user->id;
            }
        }

        return null;
    }

    public static function user(): ?User {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = self::id();
        if ($id) {
            self::$user = User::find($id);
        }

        return self::$user;
    }

    private static function setRememberCookie(User $user, string $token): void {
        $raw = $user->id . '|' . $token;
        $key = config('app.key', '32characterRandomSecretKeyForTesting==');
        $signature = hash_hmac('sha256', $raw, $key);
        $cookieValue = base64_encode($raw . '|' . $signature);

        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie(
                self::REMEMBER_COOKIE,
                $cookieValue,
                [
                    'expires' => time() + self::REMEMBER_LIFETIME,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        $_COOKIE[self::REMEMBER_COOKIE] = $cookieValue;
    }

    private static function clearRememberCookie(): void {
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie(
                self::REMEMBER_COOKIE,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    private static function attemptRememberLogin(string $cookieValue): ?User {
        $decoded = base64_decode($cookieValue, true);
        if (!$decoded) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return null;
        }

        [$userIdStr, $token, $signature] = $parts;
        $userId = (int)$userIdStr;
        if ($userId <= 0 || empty($token)) {
            return null;
        }

        $key = config('app.key', '32characterRandomSecretKeyForTesting==');
        $expectedSig = hash_hmac('sha256', $userId . '|' . $token, $key);
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $user = User::find($userId);
        if (!$user || $user->status !== 'active') {
            return null;
        }

        if (empty($user->remember_token) || !hash_equals($user->remember_token, $token)) {
            return null;
        }

        if ($user->remember_token_expires_at && strtotime($user->remember_token_expires_at) < time()) {
            return null;
        }

        // Seamless auto-login: restore session
        Session::set('user_id', $user->id);
        self::$user = $user;

        // Roll cookie forward for another 30 days
        self::setRememberCookie($user, $token);

        return $user;
    }

    public static function isAdmin(): bool {
        $user = self::user();
        return $user !== null && $user->role === 'admin';
    }

    /**
     * Admin Impersonation Helpers
     */
    public static function impersonate(User $targetUser): void {
        Session::start();
        $currentAdminId = self::id();
        if ($currentAdminId && !Session::has('impersonator_admin_id')) {
            Session::set('impersonator_admin_id', $currentAdminId);
        }
        self::$user = null;
        Session::set('user_id', $targetUser->id);
    }

    public static function leaveImpersonation(): ?User {
        Session::start();
        $adminId = Session::get('impersonator_admin_id');
        if ($adminId) {
            Session::remove('impersonator_admin_id');
            self::$user = null;
            $admin = User::find((int)$adminId);
            if ($admin) {
                Session::set('user_id', $admin->id);
                self::$user = $admin;
                return $admin;
            }
        }
        return null;
    }

    public static function isImpersonating(): bool {
        Session::start();
        return Session::has('impersonator_admin_id');
    }

    public static function getImpersonatorAdmin(): ?User {
        Session::start();
        $adminId = Session::get('impersonator_admin_id');
        return $adminId ? User::find((int)$adminId) : null;
    }
}
