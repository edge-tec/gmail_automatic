<?php
namespace Tests;

use App\Core\Database;
use App\Core\DatabaseSanitizer;
use App\Controllers\AutomationSettingsController;
use App\Models\AutomationSetting;
use App\Models\AutoReplyRecipient;
use App\Models\GmailAccount;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;
use PHPUnit\Framework\TestCase;

class RichTextAndSchemaAuditTest extends TestCase {
    private User $user;
    private GmailAccount $account;
    private AutomationSetting $settings;
    private QueueWorker $worker;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $sqlitePath = storage_path('database/test.sqlite');
        putenv("APP_ENV=testing");
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        new \App\Core\App();
        Database::resetConnection();
        \Database\MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new \App\Core\App();
        DatabaseSanitizer::reset();
        DatabaseSanitizer::run();

        Database::execute("DELETE FROM scheduled_jobs");
        Database::execute("DELETE FROM auto_reply_recipients");
        Database::execute("DELETE FROM email_threads");
        Database::execute("DELETE FROM email_messages");

        $this->user = User::create([
            'name' => 'Audit Tester',
            'email' => 'audit_' . uniqid() . '@test.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'subscription_status' => 'active',
            'gmail_limit' => 10,
        ]);

        $this->account = GmailAccount::create([
            'user_id' => $this->user->id,
            'gmail_email' => 'audit_acc_' . uniqid() . '@gmail.com',
            'google_user_id' => 'gid_' . uniqid(),
            'access_token' => 'access_tok_' . uniqid(),
            'refresh_token' => 'refresh_tok_' . uniqid(),
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'connected',
        ]);

        $this->settings = AutomationSetting::createDefault($this->account->id);
        $this->settings->update([
            'timezone' => date_default_timezone_get(),
            'working_days' => 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'working_start' => '00:00',
            'working_end' => '23:59',
        ]);
        $this->worker = new QueueWorker();
    }

    /**
     * Test A: Normal small plain-text reply saves and executes successfully.
     */
    public function testA_NormalSmallPlainTextReply(): void {
        $msg = 'Hello! Thank you for contacting our team. We will respond promptly.';
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => $msg, 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $retrieved = AutomationSetting::findByAccountId($this->account->id);
        $this->assertEquals($msg, $retrieved->getReplyMessageForStep(1));

        $sender = 'lead_a_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_a_' . uniqid(),
            'thread_id' => 'th_a_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Lead A',
            'subject' => 'General Inquiry',
            'snippet' => 'Need help',
            'body' => 'Need help',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
        $this->assertNotEmpty($res['job_id']);
        $job = ScheduledJob::find($res['job_id']);
        $this->assertNotNull($job);
        $this->worker->processJob($job);

        $recipient = AutoReplyRecipient::findByAccountAndSender($this->account->id, $sender);
        $this->assertNotNull($recipient);
        $this->assertEquals(1, $recipient->reply_sequence_step);
    }

    /**
     * Test B: Large HTML (100+ KB rich-text message with formatting, headers, lists, tables, links).
     */
    public function testB_LargeRichTextHtmlPayload(): void {
        $largeParagraphs = '';
        for ($i = 1; $i <= 500; $i++) {
            $largeParagraphs .= "<h3>Section {$i} Title</h3><p>This is paragraph {$i} explaining our automated cloud infrastructure in comprehensive detail with <strong>bold features</strong> and <em>italics</em>. <a href=\"https://example.com/item/{$i}\">View Resource {$i}</a></p>";
        }

        $this->assertGreaterThan(100000, strlen($largeParagraphs));

        $replyConfig = [
            1 => ['message' => $largeParagraphs, 'delay_value' => 0, 'delay_unit' => 'seconds']
        ];
        $json = json_encode($replyConfig, JSON_UNESCAPED_UNICODE);

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => $json,
        ]);

        $retrieved = AutomationSetting::findByAccountId($this->account->id);
        $this->assertNotNull($retrieved);
        $this->assertEquals(1, $retrieved->getTotalConfiguredReplySteps());
        $this->assertEquals($largeParagraphs, $retrieved->getReplyMessageForStep(1));
    }

    /**
     * Test C: Large Base64 Image with Exact Byte-for-Byte SHA256 Integrity Proof.
     */
    public function testC_LargeBase64ImageExactIntegrity(): void {
        // Create 150KB synthetic base64 image data
        $base64Raw = base64_encode(str_repeat('PNG_PIXEL_DATA_BLOCK_', 6000));
        $originalHtml = '<p>Company Header</p><p><img src="data:image/png;base64,' . $base64Raw . '" alt="Logo" style="max-width:100%;"></p><p>Signature line</p>';
        
        $originalHash = hash('sha256', $originalHtml);
        $this->assertGreaterThan(120000, strlen($originalHtml));

        $config = [
            1 => ['message' => $originalHtml, 'delay_value' => 0, 'delay_unit' => 'seconds']
        ];
        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE);

        // 1. Save to Database
        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => $encoded,
        ]);

        // 2. Retrieve from Database
        $freshSettings = AutomationSetting::findByAccountId($this->account->id);
        $storedMessage = $freshSettings->getReplyMessageForStep(1);
        $retrievedHash = hash('sha256', $storedMessage);

        // Exact integrity assertion
        $this->assertSame($originalHash, $retrievedHash, 'Retrieved payload SHA256 hash must exactly match original payload hash');
        $this->assertSame($originalHtml, $storedMessage, 'Retrieved payload string must be byte-for-byte identical');
    }

    /**
     * Test D: Multiple inline Base64 images in a single rich-text step.
     */
    public function testD_MultipleInlineImagesPreserved(): void {
        $img1 = 'data:image/png;base64,' . base64_encode(str_repeat('IMAGE_ONE_DATA_', 2000));
        $img2 = 'data:image/jpeg;base64,' . base64_encode(str_repeat('IMAGE_TWO_DATA_', 2000));
        $img3 = 'data:image/webp;base64,' . base64_encode(str_repeat('IMAGE_THREE_DATA_', 2000));

        $htmlWithThreeImages = "<p>Intro</p><img src=\"{$img1}\"><p>Middle</p><img src=\"{$img2}\"><p>End</p><img src=\"{$img3}\">";

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => $htmlWithThreeImages, 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $retrieved = AutomationSetting::findByAccountId($this->account->id);
        $msg = $retrieved->getReplyMessageForStep(1);

        $this->assertStringContainsString($img1, $msg);
        $this->assertStringContainsString($img2, $msg);
        $this->assertStringContainsString($img3, $msg);
    }

    /**
     * Test E: Unicode, Multilingual, Bengali, Emojis, and Special Characters.
     */
    public function testE_UnicodeBengaliEmojiAndSpecialCharacters(): void {
        $unicodeContent = "<h2>স্বাগতম! 🎉🚀</h2><p>আমাদের স্বয়ংক্রিয় সিস্টেমে আপনাকে স্বাগতম। আপনার বার্তাটি সফলভাবে গৃহীত হয়েছে &amp; প্রক্রিয়াধীন আছে।</p><p>Special chars: &lt;tag&gt; \"Quotes\" 'Single' © ® € £ ¥ 🌟 💡</p>";

        $originalHash = hash('sha256', $unicodeContent);

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => $unicodeContent, 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $fresh = AutomationSetting::findByAccountId($this->account->id);
        $retrieved = $fresh->getReplyMessageForStep(1);

        $this->assertSame($originalHash, hash('sha256', $retrieved));
        $this->assertSame($unicodeContent, $retrieved);
        $this->assertStringContainsString('স্বাগতম', $retrieved);
        $this->assertStringContainsString('🎉🚀', $retrieved);
    }

    /**
     * Test F: Multi-step reply sequence (Step 1, Step 2, Step 3, Step 4, Step 5) with large rich text.
     */
    public function testF_MultiStepReplySequenceWithLargeRichText(): void {
        $steps = [];
        for ($i = 1; $i <= 5; $i++) {
            $steps[$i] = [
                'message' => "<p><strong>Reply Step #{$i}</strong></p><p>" . str_repeat("Detailed rich-text message for step {$i}. ", 50) . "</p>",
                'delay_value' => $i * 5,
                'delay_unit' => 'minutes',
            ];
        }

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode($steps, JSON_UNESCAPED_UNICODE),
            'max_reply_per_thread' => 5,
        ]);

        $fresh = AutomationSetting::findByAccountId($this->account->id);
        $this->assertEquals(5, $fresh->getTotalConfiguredReplySteps());

        for ($i = 1; $i <= 5; $i++) {
            $msg = $fresh->getReplyMessageForStep($i);
            $this->assertStringContainsString("Reply Step #{$i}", $msg);
            $this->assertEquals($i * 5 * 60, $fresh->getReplyDelaySecondsForStep($i));
        }
    }

    /**
     * Test G: Queue worker full lifecycle (Database -> Queue -> Worker -> Processed).
     */
    public function testG_QueueWorkerFullLifecycleWithLargePayload(): void {
        $largeBody = '<div style="color: #333;"><p>Hello {{sender_name}},</p><p>' . str_repeat('Queue worker transmission test. ', 300) . '</p></div>';

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => $largeBody, 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $sender = 'queue_test_' . uniqid() . '@test.com';
        $engine = new AutomationEngine($this->account);
        $res = $engine->processIncomingMessage([
            'message_id' => 'msg_q_' . uniqid(),
            'thread_id' => 'th_q_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'Queue Tester',
            'subject' => 'Queue Test Inquiry',
            'snippet' => 'Queue test body',
            'body' => 'Queue test body',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $res['status']);
        $this->assertNotEmpty($res['job_id']);

        $targetJob = ScheduledJob::find($res['job_id']);
        $this->assertNotNull($targetJob);
        $payload = json_decode($targetJob->payload, true);
        $this->assertStringContainsString('Queue Tester', $payload['reply_body']);

        // Execute worker
        $this->worker->processJob($targetJob);

        $refreshedJob = ScheduledJob::find($targetJob->id);
        $this->assertEquals('completed', $refreshedJob->status);
    }

    /**
     * Test H: Security & XSS sanitization - strips dangerous tags / events while preserving base64 images & formatting.
     */
    public function testH_SecuritySanitizationNeutralizesXSS(): void {
        $dirtyPayload = '<p>Legitimate text</p><script>alert("XSS")</script><img src="data:image/png;base64,iVBORw0KGgoAAA==" alt="Logo"><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">Click Me</a><iframe src="http://evil.com"></iframe>';

        $clean = AutomationSettingsController::sanitizeRichText($dirtyPayload);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert("XSS")', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('<p>Legitimate text</p>', $clean);
        $this->assertStringContainsString('data:image/png;base64,iVBORw0KGgoAAA==', $clean);
        $this->assertStringContainsString('<a href="#"', $clean);
    }

    /**
     * Test I: Database upgrade idempotency and safe execution.
     */
    public function testI_DatabaseUpgradeIdempotency(): void {
        // Running DatabaseSanitizer multiple times must be completely safe, non-destructive, and idempotent
        DatabaseSanitizer::reset();
        DatabaseSanitizer::run();
        DatabaseSanitizer::reset();
        DatabaseSanitizer::run();

        $fresh = AutomationSetting::findByAccountId($this->account->id);
        $this->assertNotNull($fresh);

        $updated = $fresh->update([
            'reply_message' => json_encode([
                1 => ['message' => 'Post-upgrade test reply', 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $this->assertTrue($updated);
    }

    /**
     * Test J: 10MB payload safeguard and 25MB Gmail API RFC 2822 payload limit check.
     */
    public function testJ_PayloadLimitAndGmailAPILimitEnforcement(): void {
        // Test Gmail API 25MB check
        $gmailService = new \App\Services\GmailService($this->account);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds Google Gmail API maximum limit (25 MB)');

        // Generate synthetic oversized payload (>25MB)
        $oversizedMimeBody = str_repeat('OVERSIZED_CHUNK_', 1700000);
        $this->assertGreaterThan(26000000, strlen($oversizedMimeBody));

        $gmailService->sendThreadReply(
            'lead_oversize@test.com',
            'Oversized Subject',
            $oversizedMimeBody,
            'thread_oversize_123'
        );
    }

    /**
     * Test K: Production Smoke Test - End-to-end rich text with formatting, links, Bengali, emojis, and inline image.
     */
    public function testK_ProductionSmokeTestCompletePipeline(): void {
        $logoBase64 = 'data:image/png;base64,' . base64_encode('SMOKE_TEST_PNG_BYTE_STREAM');
        $smokeHtml = '<h2>স্বাগতম! 🎉 Welcome to Our Service</h2><p>Dear <strong>{{sender_name}}</strong>,</p><p>We have received your project details regarding <em>{{subject}}</em> on {{date}}. <a href="https://example.com/demo" target="_blank">Access Portal</a></p><p><img src="' . $logoBase64 . '" alt="Official Logo"></p>';

        $this->settings->update([
            'auto_reply_enabled' => 1,
            'reply_message' => json_encode([
                1 => ['message' => $smokeHtml, 'delay_value' => 0, 'delay_unit' => 'seconds']
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->account = GmailAccount::find($this->account->id);
        $sender = 'smoke_recipient_' . uniqid() . '@company.com';
        $engine = new AutomationEngine($this->account);
        $result = $engine->processIncomingMessage([
            'message_id' => 'msg_smoke_' . uniqid(),
            'thread_id' => 'th_smoke_' . uniqid(),
            'sender_email' => $sender,
            'sender_name' => 'John Doe',
            'subject' => 'Project Kickoff',
            'snippet' => 'Let us start the project',
            'body' => 'Let us start the project',
            'date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertEquals('scheduled', $result['status']);
        $this->assertNotEmpty($result['job_id']);

        $job = ScheduledJob::find($result['job_id']);
        $this->assertNotNull($job);

        // Process job
        $success = $this->worker->processJob($job);
        $this->assertTrue($success);

        // Verify scheduled job marked completed
        $refreshedJob = ScheduledJob::find($job->id);
        $this->assertEquals('completed', $refreshedJob->status);

        // Verify outgoing message recorded in email_messages
        $messages = \App\Core\Database::query(
            "SELECT * FROM email_messages WHERE recipient = :to AND direction = 'outgoing' ORDER BY id DESC LIMIT 1",
            ['to' => $sender]
        );
        $this->assertNotEmpty($messages);
        $recordedBody = $messages[0]['message_body'];

        $this->assertStringContainsString('স্বাগতম!', $recordedBody);
        $this->assertStringContainsString('🎉', $recordedBody);
        $this->assertStringContainsString('John Doe', $recordedBody);
        $this->assertStringContainsString('Project Kickoff', $recordedBody);
        $this->assertStringContainsString($logoBase64, $recordedBody);
        $this->assertStringContainsString('https://example.com/demo', $recordedBody);
    }
}
