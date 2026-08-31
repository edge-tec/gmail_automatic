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
}
