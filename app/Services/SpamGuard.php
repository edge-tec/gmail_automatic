<?php
namespace App\Services;

/**
 * Detects spam / fake sign-ups (promo-link names, casino bonus spam, etc.)
 * and throttles registrations per IP.
 */
class SpamGuard {
    public const MAX_NAME_LENGTH = 60;

    /**
     * Return the list of reasons why a display name looks like spam (empty = looks real).
     */
    public static function nameReasons(string $name): array {
        $reasons = [];
        $name = trim($name);

        if ($name === '') {
            return ['empty name'];
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $reasons[] = 'name too long';
        }
        if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|xyz|info|top|club|site|online|jp|br|ru|cn|co|io)\b)~i', $name)) {
            $reasons[] = 'contains link/domain';
        }
        if (preg_match('~\b(bonus|promo|deposit|casino|slots?|jackpot|account alert|unlock(ed)?|claim|crypto|usdt|btc|payout|spots? left)\b~i', $name)
            || preg_match('~\$\s?\d~', $name)) {
            $reasons[] = 'promo/casino keywords';
        }
        if (preg_match('~[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]~u', $name) || str_contains($name, '* * *')) {
            $reasons[] = 'emoji/decoration spam';
        }

        return $reasons;
    }

    public static function isSpamName(string $name): bool {
        return !empty(self::nameReasons($name));
    }

    /**
     * File based per-IP throttle. Returns true when the request is ALLOWED.
     */
    public static function allowRegistration(string $ip, int $max = 5, int $windowSeconds = 3600): bool {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return true; // fail open, never block everybody because of a permissions issue
        }

        $file = $dir . '/.reg_throttle_' . md5($ip);
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $hits = array_filter(array_map('intval', explode(',', (string)@file_get_contents($file))), fn($t) => $t > $now - $windowSeconds);
        }
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, implode(',', $hits), LOCK_EX);
        return true;
    }
}
