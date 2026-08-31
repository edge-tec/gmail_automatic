<?php
namespace App\Services;

use App\Models\SystemSetting;
use App\Models\EmailJob;
use Exception;

class MailService {
    /**
     * Test SMTP Connection & Authentication without sending an email
     */
    public static function testConnection(?array $config = null): array {
        $cfg = $config ?? self::getConfig();

        if (empty($cfg['host']) || empty($cfg['port'])) {
            return ['success' => false, 'message' => 'SMTP Host and Port are required.'];
        }

        try {
            $encryption = strtolower($cfg['encryption'] ?? 'tls');
            $socket = self::openSocket($cfg['host'], (int)$cfg['port'], $encryption, 12);
            self::readResponse($socket);

            self::sendCommand($socket, "EHLO " . gethostname());

            if ($encryption === 'tls') {
                self::sendCommand($socket, "STARTTLS");
                self::enableTls($socket);
                self::sendCommand($socket, "EHLO " . gethostname());
            }

            if (!empty($cfg['username']) && !empty($cfg['password'])) {
                self::sendCommand($socket, "AUTH LOGIN");
                self::sendCommand($socket, base64_encode($cfg['username']));
                self::sendCommand($socket, base64_encode($cfg['password']));
            }

            self::sendCommand($socket, "QUIT");
            fclose($socket);

            return ['success' => true, 'message' => 'SMTP Server connection, TLS handshake, and credentials verified successfully!'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SMTP Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send email directly via SMTP socket
     */
    public static function send(string $toEmail, string $subject, string $htmlBody, ?string $toName = null, ?array $customConfig = null): bool {
        $cfg = $customConfig ?? self::getConfig();

        if (empty($cfg['enabled']) && empty($customConfig)) {
            logger("SMTP is disabled in System Settings. Email to {$toEmail} skipped.", 'warning');
            return false;
        }

        $fromEmail = $cfg['from_email'] ?? 'support@2xbets.net';
        $fromName = $cfg['from_name'] ?? 'Gmail Automation';
        $encryption = strtolower($cfg['encryption'] ?? 'tls');

        try {
            $socket = self::openSocket($cfg['host'], (int)$cfg['port'], $encryption, 15);
            self::readResponse($socket);

            self::sendCommand($socket, "EHLO " . gethostname());

            if ($encryption === 'tls') {
                self::sendCommand($socket, "STARTTLS");
                self::enableTls($socket);
                self::sendCommand($socket, "EHLO " . gethostname());
            }

            if (!empty($cfg['username']) && !empty($cfg['password'])) {
                self::sendCommand($socket, "AUTH LOGIN");
                self::sendCommand($socket, base64_encode($cfg['username']));
                self::sendCommand($socket, base64_encode($cfg['password']));
            }

            self::sendCommand($socket, "MAIL FROM: <{$fromEmail}>");
            self::sendCommand($socket, "RCPT TO: <{$toEmail}>");
            self::sendCommand($socket, "DATA");

            $boundary = "----=_NextPart_" . md5(time() . uniqid());
            $headers = [];
            $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
            $headers[] = "To: " . ($toName ? "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>" : "<{$toEmail}>");
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = "Date: " . date('r');
            $headers[] = "Message-ID: <" . time() . "." . uniqid() . "@" . gethostname() . ">";

            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            $bodyData = implode("\r\n", $headers) . "\r\n\r\n";
            $bodyData .= "--{$boundary}\r\n";
            $bodyData .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $bodyData .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $bodyData .= chunk_split(base64_encode($plainText)) . "\r\n";

            $bodyData .= "--{$boundary}\r\n";
            $bodyData .= "Content-Type: text/html; charset=UTF-8\r\n";
            $bodyData .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $bodyData .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $bodyData .= "--{$boundary}--\r\n";
            $bodyData .= ".\r\n";

            fwrite($socket, $bodyData);
            self::readResponse($socket);

            self::sendCommand($socket, "QUIT");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            logger("MailService Send Error to {$toEmail}: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Process an asynchronous EmailJob record
     */
    public static function processEmailJob(EmailJob $job): bool {
        $job->update(['status' => 'processing', 'attempts' => $job->attempts + 1]);

        try {
            self::send($job->recipient_email, $job->subject, $job->body, $job->recipient_name);
            $job->update([
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
            return true;
        } catch (\Throwable $e) {
            $isFinal = ($job->attempts >= $job->max_attempts);
            $job->update([
                'status' => $isFinal ? 'failed' : 'pending',
                'last_error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public static function getConfig(): array {
        return [
            'enabled' => (bool)(int)SystemSetting::get('smtp_enabled', '0'),
            'host' => SystemSetting::get('smtp_host', ''),
            'port' => (int)SystemSetting::get('smtp_port', '587'),
            'username' => SystemSetting::get('smtp_username', ''),
            'password' => SystemSetting::get('smtp_password', ''),
            'encryption' => SystemSetting::get('smtp_encryption', 'tls'),
            'from_name' => SystemSetting::get('smtp_from_name', 'Gmail Automation'),
            'from_email' => SystemSetting::get('smtp_from_email', 'support@2xbets.net'),
        ];
    }

    private static function openSocket(string $host, int $port, string $encryption, int $timeout = 15) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $protocol = ($encryption === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $protocol . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new Exception("Could not connect to SMTP server ({$host}:{$port}): {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, $timeout);
        return $socket;
    }

    private static function enableTls($socket): void {
        $cryptoMethods = [
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ];

        $cryptoOk = false;
        foreach ($cryptoMethods as $method) {
            if (@stream_socket_enable_crypto($socket, true, $method) === true) {
                $cryptoOk = true;
                break;
            }
        }

        if (!$cryptoOk) {
            $lastErr = error_get_last();
            $detail = !empty($lastErr['message']) ? " ({$lastErr['message']})" : "";
            throw new Exception("STARTTLS negotiation failed. Please check port / encryption mode (Port 587 uses TLS, Port 465 uses SSL)" . $detail);
        }
    }

    private static function sendCommand($socket, string $cmd): string {
        fwrite($socket, $cmd . "\r\n");
        return self::readResponse($socket);
    }

    private static function readResponse($socket): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Error: " . trim($response));
        }
        return $response;
    }
}
