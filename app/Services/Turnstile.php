<?php
namespace App\Services;

/**
 * Cloudflare Turnstile (bot check) for public forms.
 * Enabled only when BOTH TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are set in .env,
 * so the site keeps working normally until the keys are added.
 */
class Turnstile {
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function siteKey(): string {
        return trim((string)env('TURNSTILE_SITE_KEY', ''));
    }

    private static function secretKey(): string {
        return trim((string)env('TURNSTILE_SECRET_KEY', ''));
    }

    public static function enabled(): bool {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    /**
     * HTML widget (+ script) to place inside the <form>. Empty string when disabled.
     */
    public static function widget(): string {
        if (!self::enabled()) {
            return '';
        }
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
            . '<div class="cf-turnstile mb-3" data-sitekey="' . htmlspecialchars(self::siteKey(), ENT_QUOTES, 'UTF-8') . '" data-theme="light"></div>';
    }

    /**
     * Returns true when the check passes (or Turnstile is disabled).
     * Fails CLOSED when enabled and the token is missing/invalid or Cloudflare is unreachable.
     */
    public static function verify(?string $token, ?string $ip = null): bool {
        if (!self::enabled()) {
            return true;
        }
        $token = trim((string)$token);
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }

        $fields = ['secret' => self::secretKey(), 'response' => $token];
        if ($ip) {
            $fields['remoteip'] = $ip;
        }

        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init(self::VERIFY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($fields),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            $res = curl_exec($ch);
            unset($ch);
            $body = is_string($res) ? $res : null;
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($fields),
                'timeout' => 8,
            ]]);
            $res = @file_get_contents(self::VERIFY_URL, false, $ctx);
            $body = is_string($res) ? $res : null;
        }

        if ($body === null) {
            return false;
        }
        $json = json_decode($body, true);
        return is_array($json) && !empty($json['success']);
    }
}
