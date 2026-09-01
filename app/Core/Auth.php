<?php
namespace App\Core;

use App\Models\User;

class Auth {
    private static ?User $user = null;

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
        session_regenerate_id(true);
        Session::set('user_id', $user->id);
        self::$user = $user;
    }

    public static function logout(): void {
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
        return $id ? (int)$id : null;
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
