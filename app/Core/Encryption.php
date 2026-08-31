<?php
namespace App\Core;

/**
 * AES-256-GCM Authenticated Encryption Helper
 * Used for encrypting sensitive OAuth Refresh Tokens and Access Tokens in database
 */
class Encryption {
    private static string $cipher = 'aes-256-gcm';

    private static function getKey(): string {
        $key = config('app.key', env('APP_KEY', ''));
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        if (empty($key)) {
            $key = hash('sha256', 'gmail-auto-reply-default-secret-salt-key-2026', true);
        }
        if (strlen($key) !== 32) {
            $key = hash('sha256', $key, true);
        }
        return $key;
    }

    public static function encrypt(?string $value): ?string {
        if ($value === null || $value === '') {
            return $value;
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $value,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Store as base64: IV + Tag + Ciphertext
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function decrypt(?string $payload): ?string {
        if ($payload === null || $payload === '') {
            return $payload;
        }

        $data = base64_decode($payload, true);
        if ($data === false) {
            return $payload; // Fallback if already plaintext
        }

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16;

        if (strlen($data) < ($ivLength + $tagLength)) {
            return $payload;
        }

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $ciphertext = substr($data, $ivLength + $tagLength);
        $key = self::getKey();

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            // Might be plaintext in older records
            return $payload;
        }

        return $decrypted;
    }
}
