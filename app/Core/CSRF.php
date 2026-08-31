<?php
namespace App\Core;

class CSRF {
    public static function token(): string {
        Session::start();
        $token = Session::get('_csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
        }
        return $token;
    }

    public static function field(): string {
        $token = self::token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token = null): bool {
        Session::start();
        $stored = Session::get('_csrf_token');
        if (!$stored) {
            return false;
        }

        if ($token === null) {
            $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$token || !is_string($token)) {
            return false;
        }

        return hash_equals($stored, $token);
    }
}
