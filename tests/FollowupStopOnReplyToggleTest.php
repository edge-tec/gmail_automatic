<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Auth;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\ScheduledJob;
use App\Models\FollowupCampaign;
use App\Models\AutomationSetting;
use App\Models\GlobalAutomationSetting;
use App\Services\AutomationEngine;
use App\Controllers\FollowupController;

class FollowupStopOnReplyToggleTest extends TestCase {
    private User $user;
    private GmailAccount $account;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        $sqlitePath = storage_path('database/test.sqlite');
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        new App();
        Database::resetConnection();
        \Database\MigrationRunner::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new App();

        $email = 'toggle_user_' . uniqid() . '@example.com';
        Database::execute(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Toggle Test User', :e, 'hash', 'user', datetime('now'))",
            ['e' => $email]
        );
        $userId = (int)Database::lastInsertId();
        $this->user = User::find($userId);

        $gmail = 'toggle_acc_' . uniqid() . '@gmail.com';
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
        Database::execute(
            "INSERT INTO gmail_accounts (user_id, gmail_email, access_token, refresh_token, status, connected_at, created_at) 
             VALUES (:uid, :gemail, 'mock_token', 'mock_refresh', 'connected', :past, :past)",
            ['uid' => $userId, 'gemail' => $gmail, 'past' => $pastDate]
        );
        $accId = (int)Database::lastInsertId();
        $this->account = GmailAccount::find($accId);

        Auth::login($this->user);
    }

    public function testSettingDefaultsToStopOnReplyTrue(): void {
        $settings = $this->account->getSettings();
        $this->assertTrue($settings->stop_followup_on_reply, 'Account setting should default to stop_followup_on_reply = true');

        $global = GlobalAutomationSetting::getForUser($this->user->id);
        $this->assertTrue($global->stop_followup_on_reply, 'Global setting should default to stop_followup_on_reply = true');
    }

    public function testToggleAccountSettingPersists(): void {
        $settings = $this->account->getSettings();
        $settings->stop_followup_on_reply = false;
        $settings->save();

        $reloaded = AutomationSetting::findByAccountId($this->account->id);
        $this->assertFalse($reloaded->stop_followup_on_reply, 'Account setting should persist false');

        $reloaded->stop_followup_on_reply = true;
        $reloaded->save();

        $reloaded2 = AutomationSetting::findByAccountId($this->account->id);
        $this->assertTrue($reloaded2->stop_followup_on_reply, 'Account setting should persist true');
    }

    public function testAutomationEngineStopsFollowupWhenToggleOn(): void {
        $settings = $this->account->getSettings();
        $settings->use_account_override = true;
        $settings->stop_followup_on_reply = true;
        $settings->auto_reply_enabled = false;
        $settings->followup_enabled = true;
        $settings->save();

        $threadId = 'th_stop_on_' . uniqid();
        $thread = EmailThread::createOrGet($this->account->id, $threadId, [
            'sender_email' => 'lead@example.com',
            'sender_name' => 'Lead Name',
            'subject' => 'Follow up Inquiry',
        ]);
        $thread->update([
            'reply_count' => 1,
            'last_outgoing_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            'automation_status' => 'active',
        ]);

        $campaign = FollowupCampaign::getOrCreate(
            $this->user->id,
            $this->account->id,
            $thread->id,
            $threadId,
            ['sender_email' => 'lead@example.com', 'recipient_email' => $this->account->gmail_email, 'subject' => 'Follow up Inquiry', 'total_steps' => 3]
        );
        $campaign->update(['campaign_status' => 'active']);

        $job = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => ['step_number' => 1, 'campaign_id' => $campaign->id],
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'status' => 'pending',
        ]);

        $engine = new AutomationEngine($this->account);
        $this->assertTrue($engine->shouldStopFollowupOnReply(), 'Engine should indicate stop_followup_on_reply is enabled');

        // Incoming reply from lead
        $res = $engine->processIncomingMessage([
            'id' => 'msg_' . uniqid(),
            'thread_id' => $threadId,
            'threadId' => $threadId,
            'sender_email' => 'lead@example.com',
            'sender_name' => 'Lead Name',
            'from' => 'Lead Name <lead@example.com>',
            'to' => $this->account->gmail_email,
            'subject' => 'Re: Follow up Inquiry',
            'date' => date('Y-m-d H:i:s'),
            'body' => 'I am interested in your offer, let us talk!',
            'snippet' => 'I am interested in your offer',
            'in_reply_to' => 'prev_message_id',
        ]);

        $threadReloaded = EmailThread::find($thread->id);
        $this->assertEquals('replied', $threadReloaded->automation_status, 'Thread automation_status should be replied');
        $this->assertNull($threadReloaded->next_followup_at, 'Next followup timestamp should be cleared');

        $jobReloaded = ScheduledJob::find($job->id);
        $this->assertEquals('cancelled', $jobReloaded->status, 'Pending follow-up job should be cancelled');

        $campaignReloaded = FollowupCampaign::find($campaign->id);
        $this->assertEquals('replied', $campaignReloaded->campaign_status, 'Campaign status should be marked replied');
    }

    public function testAutomationEngineKeepsFollowupActiveWhenToggleOff(): void {
        $settings = $this->account->getSettings();
        $settings->use_account_override = true;
        $settings->stop_followup_on_reply = false; // Toggle turned OFF
        $settings->auto_reply_enabled = false;
        $settings->followup_enabled = true;
        $settings->save();

        $threadId = 'th_keep_on_' . uniqid();
        $thread = EmailThread::createOrGet($this->account->id, $threadId, [
            'sender_email' => 'lead2@example.com',
            'sender_name' => 'Lead 2',
            'subject' => 'Sequence Continuation',
        ]);
        $thread->update([
            'reply_count' => 1,
            'last_outgoing_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
            'automation_status' => 'active',
        ]);

        $campaign = FollowupCampaign::getOrCreate(
            $this->user->id,
            $this->account->id,
            $thread->id,
            $threadId,
            ['sender_email' => 'lead2@example.com', 'recipient_email' => $this->account->gmail_email, 'subject' => 'Sequence Continuation', 'total_steps' => 3]
        );
        $campaign->update(['campaign_status' => 'active']);

        $job = ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => ['step_number' => 1, 'campaign_id' => $campaign->id],
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'status' => 'pending',
        ]);

        $engine = new AutomationEngine($this->account);
        $this->assertFalse($engine->shouldStopFollowupOnReply(), 'Engine should indicate stop_followup_on_reply is disabled');

        // Incoming reply from lead
        $engine->processIncomingMessage([
            'id' => 'msg_' . uniqid(),
            'thread_id' => $threadId,
            'threadId' => $threadId,
            'sender_email' => 'lead2@example.com',
            'sender_name' => 'Lead 2',
            'from' => 'Lead 2 <lead2@example.com>',
            'to' => $this->account->gmail_email,
            'subject' => 'Re: Sequence Continuation',
            'date' => date('Y-m-d H:i:s'),
            'body' => 'Thanks for your email, but keep sending details.',
            'snippet' => 'Thanks for your email',
            'in_reply_to' => 'prev_message_id_2',
        ]);

        $threadReloaded = EmailThread::find($thread->id);
        $this->assertEquals('active', $threadReloaded->automation_status, 'Thread automation_status should remain active');

        $jobReloaded = ScheduledJob::find($job->id);
        $this->assertEquals('pending', $jobReloaded->status, 'Pending follow-up job should NOT be cancelled');

        $campaignReloaded = FollowupCampaign::find($campaign->id);
        $this->assertEquals('active', $campaignReloaded->campaign_status, 'Campaign status should remain active');
    }

    public function testFollowupControllerToggleEndpoint(): void {
        $controller = new FollowupController();

        // Initially true
        $settings = $this->account->getSettings();
        $this->assertTrue($settings->stop_followup_on_reply);

        // Call toggle endpoint with stop_followup_on_reply = 0
        $req = new Request([], ['stop_followup_on_reply' => '0'], ['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest']);
        ob_start();
        $controller->toggleStopOnReply($req, $this->account->id);
        $out = ob_get_clean();

        $json = json_decode($out, true);
        $this->assertTrue($json['success']);
        $this->assertFalse($json['stop_followup_on_reply']);

        $settingsReloaded = AutomationSetting::findByAccountId($this->account->id);
        $this->assertFalse($settingsReloaded->stop_followup_on_reply);

        // Toggle back to 1
        $req2 = new Request([], ['stop_followup_on_reply' => '1'], ['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest']);
        ob_start();
        $controller->toggleStopOnReply($req2, $this->account->id);
        $out2 = ob_get_clean();

        $json2 = json_decode($out2, true);
        $this->assertTrue($json2['success']);
        $this->assertTrue($json2['stop_followup_on_reply']);

        $settingsReloaded2 = AutomationSetting::findByAccountId($this->account->id);
        $this->assertTrue($settingsReloaded2->stop_followup_on_reply);
    }
}
