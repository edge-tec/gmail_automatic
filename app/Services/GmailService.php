<?php
namespace App\Services;

use App\Models\GmailAccount;
use App\Models\SystemSetting;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmail;
use Google\Service\Gmail\Message as GoogleMessage;
use Google\Service\Gmail\WatchRequest;
use Google\Service\Oauth2 as GoogleOauth2;
use Exception;

class GmailService {
    private GoogleClient $client;
    private ?GmailAccount $account = null;
    private ?GoogleGmail $gmailService = null;

    public function __construct(?GmailAccount $account = null) {
        $this->account = $account;
        $this->client = new GoogleClient();

        // Load credentials from System Settings first, fallback to config
        $clientId = SystemSetting::get('google_client_id') ?: config('google.client_id', '');
        $clientSecret = SystemSetting::get('google_client_secret') ?: config('google.client_secret', '');
        $redirectUri = SystemSetting::get('google_redirect_uri') ?: config('google.redirect_uri', url('/auth/google/callback'));

        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        $this->client->setAccessType(config('google.access_type', 'offline'));
        $this->client->setPrompt(config('google.prompt', 'consent select_account'));
        $this->client->setIncludeGrantedScopes(true);

        foreach (config('google.scopes', []) as $scope) {
            $this->client->addScope($scope);
        }

        if ($this->account !== null) {
            $this->authenticateAccount();
        }
    }

    public function getClient(): GoogleClient {
        return $this->client;
    }

    public function getAuthUrl(string $state = ''): string {
        if ($state) {
            $this->client->setState($state);
        }
        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code): array {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new Exception("OAuth Error: " . ($token['error_description'] ?? $token['error']));
        }

        $this->client->setAccessToken($token);
        $oauth2 = new GoogleOauth2($this->client);
        $userInfo = $oauth2->userinfo->get();

        $expiresIn = $token['expires_in'] ?? 3600;
        $tokenExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        return [
            'email' => $userInfo->getEmail(),
            'google_user_id' => $userInfo->getId(),
            'name' => $userInfo->getName(),
            'picture' => $userInfo->getPicture(),
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => $tokenExpiresAt,
            'raw_token' => $token,
        ];
    }

    private function authenticateAccount(): void {
        if (!$this->account) {
            return;
        }

        $accessToken = $this->account->getDecryptedAccessToken();
        $refreshToken = $this->account->getDecryptedRefreshToken();

        $tokenData = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];

        if ($this->account->token_expires_at) {
            $tokenData['created'] = strtotime($this->account->token_expires_at) - 3600;
            $tokenData['expires_in'] = 3600;
        }

        $this->client->setAccessToken($tokenData);

        // Refresh token if expired or about to expire in 5 minutes
        if ($this->client->isAccessTokenExpired() || (strtotime($this->account->token_expires_at ?? '') < (time() + 300))) {
            if ($refreshToken) {
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    if (isset($newToken['error'])) {
                        throw new Exception("Token Refresh Error: " . ($newToken['error_description'] ?? $newToken['error']));
                    }

                    $expiresIn = $newToken['expires_in'] ?? 3600;
                    $tokenExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

                    $this->account->update([
                        'access_token' => \App\Core\Encryption::encrypt($newToken['access_token']),
                        'token_expires_at' => $tokenExpiresAt,
                        'status' => 'connected',
                        'last_error' => null,
                    ]);
                } catch (\Throwable $e) {
                    $this->account->update([
                        'status' => 'disconnected',
                        'last_error' => "Token refresh failed: " . $e->getMessage(),
                    ]);
                    throw $e;
                }
            } else {
                $this->account->update([
                    'status' => 'disconnected',
                    'last_error' => 'Missing refresh token. Please re-authenticate.',
                ]);
                throw new Exception('Missing refresh token');
            }
        }

        $this->gmailService = new GoogleGmail($this->client);
    }

    public function getGmail(): GoogleGmail {
        if ($this->gmailService === null) {
            $this->gmailService = new GoogleGmail($this->client);
        }
        return $this->gmailService;
    }

    /**
     * List incoming unread messages or recent messages from INBOX
     */
    public function listInboxMessages(int $maxResults = 20, ?string $query = 'label:INBOX'): array {
        $gmail = $this->getGmail();
        $params = [
            'maxResults' => $maxResults,
            'q' => $query,
        ];

        try {
            $list = $gmail->users_messages->listUsersMessages('me', $params);
            return $list->getMessages() ?? [];
        } catch (\Throwable $e) {
            logger("Failed to list Gmail messages: " . $e->getMessage(), 'error', null, $this->account?->id);
            return [];
        }
    }

    /**
     * Get detailed message by ID
     */
    public function getMessage(string $messageId): ?array {
        $gmail = $this->getGmail();
        try {
            $msg = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
            return $this->parseMessage($msg);
        } catch (\Throwable $e) {
            logger("Failed to get message {$messageId}: " . $e->getMessage(), 'error', null, $this->account?->id);
            return null;
        }
    }

    /**
     * Parse Google Gmail Message into normalized array
     */
    public function parseMessage(GoogleMessage $msg): array {
        $payload = $msg->getPayload();
        $headers = $payload ? $payload->getHeaders() : [];
        
        $headerMap = [];
        foreach ($headers as $h) {
            $headerMap[strtolower($h->getName())] = $h->getValue();
        }

        $body = $this->extractBody($payload);
        $snippet = $msg->getSnippet();

        $from = $headerMap['from'] ?? '';
        $senderEmail = $from;
        $senderName = '';
        if (preg_match('/(.*)<(.+)>/', $from, $matches)) {
            $senderName = trim(trim($matches[1]), '"\'');
            $senderEmail = trim($matches[2]);
        }

        $dateStr = $headerMap['date'] ?? null;
        $receivedAt = $dateStr ? date('Y-m-d H:i:s', strtotime($dateStr)) : date('Y-m-d H:i:s');

        return [
            'message_id' => $msg->getId(),
            'thread_id' => $msg->getThreadId(),
            'history_id' => $msg->getHistoryId(),
            'from_raw' => $from,
            'sender_email' => $senderEmail,
            'sender_name' => $senderName ?: $senderEmail,
            'to' => $headerMap['to'] ?? '',
            'cc' => $headerMap['cc'] ?? '',
            'bcc' => $headerMap['bcc'] ?? '',
            'subject' => $headerMap['subject'] ?? '(No Subject)',
            'date' => $receivedAt,
            'message_id_header' => $headerMap['message-id'] ?? '',
            'references' => $headerMap['references'] ?? '',
            'in_reply_to' => $headerMap['in-reply-to'] ?? '',
            'auto_submitted' => $headerMap['auto-submitted'] ?? '',
            'precedence' => $headerMap['precedence'] ?? '',
            'snippet' => $snippet,
            'body' => $body,
            'label_ids' => $msg->getLabelIds() ?? [],
        ];
    }

    private function extractBody($part): string {
        if (!$part) {
            return '';
        }

        $bodyData = $part->getBody() ? $part->getBody()->getData() : null;
        if ($bodyData) {
            return $this->decodeBase64Url($bodyData);
        }

        $parts = $part->getParts();
        if ($parts) {
            // Prefer text/plain or text/html
            foreach ($parts as $subPart) {
                if ($subPart->getMimeType() === 'text/plain') {
                    $subData = $subPart->getBody() ? $subPart->getBody()->getData() : null;
                    if ($subData) return $this->decodeBase64Url($subData);
                }
            }
            foreach ($parts as $subPart) {
                if ($subPart->getMimeType() === 'text/html') {
                    $subData = $subPart->getBody() ? $subPart->getBody()->getData() : null;
                    if ($subData) return strip_tags($this->decodeBase64Url($subData));
                }
            }
            foreach ($parts as $subPart) {
                $subText = $this->extractBody($subPart);
                if ($subText) return $subText;
            }
        }

        return '';
    }

    private function decodeBase64Url(string $data): string {
        $sanitized = strtr($data, '-_', '+/');
        return base64_decode($sanitized) ?: '';
    }

    private function encodeBase64Url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send email in response to a thread (MIME RFC 2822 reply format)
     */
    public function sendThreadReply(
        string $toEmail,
        string $subject,
        string $bodyText,
        string $threadId,
        ?string $inReplyToMessageIdHeader = null,
        ?string $referencesHeader = null
    ): array {
        $fromEmail = $this->account->gmail_email;

        // Clean subject: ensure Re: prefix
        if (!preg_match('/^Re:/i', trim($subject))) {
            $replySubject = 'Re: ' . trim($subject);
        } else {
            $replySubject = trim($subject);
        }

        $isHtml = (strip_tags($bodyText) !== $bodyText || str_contains($bodyText, '<') || str_contains($bodyText, 'http'));
        
        $replyHeaders = [];
        $replyHeaders[] = "From: <{$fromEmail}>";
        $replyHeaders[] = "To: <{$toEmail}>";
        $replyHeaders[] = "Subject: =?UTF-8?B?" . base64_encode($replySubject) . "?=";
        $replyHeaders[] = "MIME-Version: 1.0";
        
        if ($isHtml) {
            $replyHeaders[] = "Content-Type: text/html; charset=UTF-8";
            // Ensure basic HTML wrapper if not present
            if (!str_contains($bodyText, '<html')) {
                $htmlBody = "<div style=\"font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #222222;\">" . $bodyText . "</div>";
            } else {
                $htmlBody = $bodyText;
            }
            $formattedBody = $htmlBody;
        } else {
            $replyHeaders[] = "Content-Type: text/plain; charset=UTF-8";
            $formattedBody = $bodyText;
        }
        
        $replyHeaders[] = "Content-Transfer-Encoding: 8bit";

        if ($inReplyToMessageIdHeader) {
            $replyHeaders[] = "In-Reply-To: {$inReplyToMessageIdHeader}";
            $ref = trim(($referencesHeader ? $referencesHeader . ' ' : '') . $inReplyToMessageIdHeader);
            $replyHeaders[] = "References: {$ref}";
        }

        $rawMime = implode("\r\n", $replyHeaders) . "\r\n\r\n" . $formattedBody;

        $msg = new GoogleMessage();
        $msg->setRaw($this->encodeBase64Url($rawMime));
        $msg->setThreadId($threadId);

        $gmail = $this->getGmail();
        $sentMessage = $gmail->users_messages->send('me', $msg);

        return [
            'id' => $sentMessage->getId(),
            'thread_id' => $sentMessage->getThreadId(),
            'label_ids' => $sentMessage->getLabelIds() ?? [],
        ];
    }

    /**
     * Setup Google Pub/Sub Watch for Gmail Push Notifications
     */
    public function setupWatch(string $topicName): array {
        $gmail = $this->getGmail();
        $watchRequest = new WatchRequest();
        $watchRequest->setTopicName($topicName);
        $watchRequest->setLabelIds(['INBOX']);

        $response = $gmail->users->watch('me', $watchRequest);
        return [
            'historyId' => $response->getHistoryId(),
            'expiration' => $response->getExpiration(),
        ];
    }

    /**
     * Revoke OAuth Token and Disconnect
     */
    public function disconnect(): bool {
        try {
            $token = $this->account?->getDecryptedAccessToken();
            if ($token) {
                $this->client->revokeToken($token);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
