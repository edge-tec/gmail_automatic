<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\DatabaseSanitizer;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;
use App\Models\GlobalAutomationSetting;
use App\Models\GlobalAutoReplyMessage;
use App\Models\GlobalFollowupSequence;
use App\Models\GlobalFollowupMessage;
use App\Services\AutomationEngine;
use App\Services\QueueWorker;

class GlobalAutomationSystemTest extends TestCase {
    private static User $testUser;
    private static GmailAccount $account1;
    private static GmailAccount $account2;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $sqlitePath = storage_path('database/test_global_automation.sqlite');
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';
        $_ENV['APP_ENV'] = 'testing';

        config('_reset_');
        Database::resetConnection();

        new App();
        \Database\MigrationRunner::run();
        DatabaseSanitizer::runOnce();

        // Create test user
        self::$testUser = User::create([
            'name' => 'Global Auto User',
            'email' => 'global_user_' . uniqid() . '@example.com',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        // Create two accounts for this user
        self::$account1 = GmailAccount::create([
            'user_id' => self::$testUser->id,
            'gmail_email' => 'sender1_' . uniqid() . '@gmail.com',
            'access_token' => 'dummy_token_1',
            'refresh_token' => 'dummy_refresh_1',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'connected',
            'connected_at' => date('Y-m-d H:i:s', time() - 3600),
            'baseline_message_date' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        self::$account2 = GmailAccount::create([
            'user_id' => self::$testUser->id,
            'gmail_email' => 'sender2_' . uniqid() . '@gmail.com',
            'access_token' => 'dummy_token_2',
            'refresh_token' => 'dummy_refresh_2',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'status' => 'connected',
            'connected_at' => date('Y-m-d H:i:s', time() - 3600),
            'baseline_message_date' => date('Y-m-d H:i:s', time() - 3600),
        ]);
    }

    public function testGlobalSettingsInitializationAndPersistence(): void {
        $settings = GlobalAutomationSetting::getForUser(self::$testUser->id);
        $this->assertNotNull($settings);
        $this->assertEquals(self::$testUser->id, $settings->user_id);
        $this->assertTrue($settings->auto_reply_enabled);
        $this->assertFalse($settings->require_recipient_reply_before_next_reply);

        // Update settings
        $settings->daily_reply_limit_per_account = 250;
        $settings->working_start = '08:00';
        $settings->require_recipient_reply_before_next_reply = true;
        $settings->bumpVersion();
        $settings->save();

        $reloaded = GlobalAutomationSetting::getForUser(self::$testUser->id);
        $this->assertEquals(250, $reloaded->daily_reply_limit_per_account);
        $this->assertEquals('08:00', $reloaded->working_start);
        $this->assertTrue($reloaded->require_recipient_reply_before_next_reply);
        $this->assertGreaterThan(1, $reloaded->version);
    }

    public function testMultiStepAutoReplyVariationsAndRandomSelection(): void {
        $userId = self::$testUser->id;

        // Add 3 variations for Step 1
        $varA = new GlobalAutoReplyMessage([
            'user_id' => $userId,
            'step_number' => 1,
            'delay_minutes' => 5,
            'variation_name' => 'Variation A',
            'body_html' => '<p>Hello {{first_name}}, this is Variation A</p>',
            'is_active' => 1,
        ]);
        $varA->save();

        $varB = new GlobalAutoReplyMessage([
            'user_id' => $userId,
            'step_number' => 1,
            'delay_minutes' => 5,
            'variation_name' => 'Variation B',
            'body_html' => '<p>Hi {{first_name}}, this is Variation B</p>',
            'is_active' => 1,
        ]);
        $varB->save();

        $varC = new GlobalAutoReplyMessage([
            'user_id' => $userId,
            'step_number' => 1,
            'delay_minutes' => 5,
            'variation_name' => 'Variation C',
            'body_html' => '<p>Greetings {{first_name}}, this is Variation C</p>',
            'is_active' => 1,
        ]);
        $varC->save();

        // Add 1 variation for Step 2
        $varStep2 = new GlobalAutoReplyMessage([
            'user_id' => $userId,
            'step_number' => 2,
            'delay_minutes' => 10,
            'variation_name' => 'Step 2 Alpha',
            'body_html' => '<p>Welcome back {{first_name}}, reply step 2</p>',
            'is_active' => 1,
        ]);
        $varStep2->save();

        // Test random selection
        $selectedNames = [];
        for ($i = 0; $i < 30; $i++) {
            $randomVar = GlobalAutoReplyMessage::getRandomVariation($userId, 1);
            $this->assertNotNull($randomVar);
            $this->assertEquals(1, $randomVar->step_number);
            $this->assertContains($randomVar->variation_name, ['Variation A', 'Variation B', 'Variation C']);
            $selectedNames[$randomVar->variation_name] = true;
        }

        // Over 30 trials, at least 2 distinct variations should be selected
        $this->assertGreaterThanOrEqual(2, count($selectedNames), "Random variations should select multiple distinct variations");

        // Step 2 should only return Step 2 Alpha
        $step2Var = GlobalAutoReplyMessage::getRandomVariation($userId, 2);
        $this->assertNotNull($step2Var);
        $this->assertEquals('Step 2 Alpha', $step2Var->variation_name);

        // Step 99 does not exist, should return null
        $emptyVar = GlobalAutoReplyMessage::getRandomVariation($userId, 99);
        $this->assertNull($emptyVar);
    }

    public function testAccountInheritanceAndOverrideLogic(): void {
        // Account 1 has default settings (use_account_override = false)
        $sett1 = self::$account1->getSettings();
        if (!$sett1) {
            $sett1 = AutomationSetting::createDefault(self::$account1->id);
        }
        $sett1->use_account_override = false;
        $sett1->save();

        $this->assertTrue($sett1->isUsingGlobal());

        $engine1 = new AutomationEngine(self::$account1);
        $effective1 = $engine1->getEffectiveSettings();
        $this->assertNotNull($effective1);
        $this->assertEquals(250, $effective1['daily_reply_limit']);

        // Total reply steps configured globally should be at least 2 (Step 1 and Step 2)
        $totalSteps = $engine1->getTotalConfiguredReplySteps();
        $this->assertEquals(2, $totalSteps);

        // Account 2 overrides settings
        $sett2 = self::$account2->getSettings();
        if (!$sett2) {
            $sett2 = AutomationSetting::createDefault(self::$account2->id);
        }
        $sett2->use_account_override = true;
        $sett2->daily_reply_limit = 45;
        $sett2->save();

        $this->assertFalse($sett2->isUsingGlobal());

        $engine2 = new AutomationEngine(self::$account2);
        $effective2 = $engine2->getEffectiveSettings();
        $this->assertEquals(45, $effective2['daily_reply_limit']);
    }

    public function testFollowupSequencesAndVariations(): void {
        $userId = self::$testUser->id;

        // Configure Global Follow-up Step 1
        $seq1 = new GlobalFollowupSequence([
            'user_id' => $userId,
            'step_number' => 1,
            'delay_value' => 3,
            'delay_unit' => 'days',
            'is_active' => 1,
        ]);
        $seq1->save();

        // Add 2 variations for follow-up step 1
        $fVar1 = new GlobalFollowupMessage([
            'user_id' => $userId,
            'step_number' => 1,
            'variation_name' => 'FU Var 1',
            'subject' => 'Checking in',
            'body_html' => '<p>Did you see my earlier message?</p>',
            'is_active' => 1,
        ]);
        $fVar1->save();

        $fVar2 = new GlobalFollowupMessage([
            'user_id' => $userId,
            'step_number' => 1,
            'variation_name' => 'FU Var 2',
            'subject' => 'Quick question',
            'body_html' => '<p>Wanted to follow up on this.</p>',
            'is_active' => 1,
        ]);
        $fVar2->save();

        $nextSeq = GlobalFollowupSequence::findNextStep($userId, 0);
        $this->assertNotNull($nextSeq);
        $this->assertEquals(1, $nextSeq->step_number);
        $this->assertEquals(3, $nextSeq->delay_value);
        $this->assertEquals('days', $nextSeq->delay_unit);

        $selectedFU = GlobalFollowupMessage::getRandomVariation($userId, 1);
        $this->assertNotNull($selectedFU);
        $this->assertContains($selectedFU->variation_name, ['FU Var 1', 'FU Var 2']);
    }

    public function testStrictZeroFallbackPolicy(): void {
        $userId = self::$testUser->id;
        $engine = new AutomationEngine(self::$account1);

        // Step 10 has no variations configured
        $noVar = $engine->getRandomReplyMessageForStep(10);
        $this->assertEmpty($noVar, "Zero Fallback Policy: non-existent variation step must return empty string");

        // Deactivated variation should not be returned
        $inactiveVar = new GlobalAutoReplyMessage([
            'user_id' => $userId,
            'step_number' => 15,
            'delay_minutes' => 0,
            'variation_name' => 'Inactive Only',
            'body_html' => '<p>Should not send</p>',
            'is_active' => 0,
        ]);
        $inactiveVar->save();

        $resultInactive = $engine->getRandomReplyMessageForStep(15);
        $this->assertEmpty($resultInactive, "Zero Fallback Policy: inactive variation must return empty and never send");
    }

    public function testVariableRenderingWithEmailAndCompany(): void {
        $engine = new AutomationEngine(self::$account1);
        $template = "Hello {{first_name}} {{last_name}}, your email is {{email}} at {{company}} regarding {{subject}}.";
        $context = [
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@company.com',
            'company' => 'Acme Corp',
            'subject' => 'Project Inquiry',
        ];

        $rendered = $engine->renderVariables($template, $context);
        $this->assertEquals("Hello Alice Smith, your email is alice@company.com at Acme Corp regarding Project Inquiry.", $rendered);
    }
}
