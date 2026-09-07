<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\App;
use App\Core\Database;
use App\Models\User;
use App\Models\GmailAccount;
use App\Models\DailyUsage;
use App\Models\EmailThread;
use App\Models\ScheduledJob;
use App\Controllers\ReplyReportController;
use App\Core\Request;
use App\Core\Auth;

class ReplyReportTest extends TestCase {
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

        // Create test user and connected Gmail account
        $email = 'reply_report_user_' . uniqid() . '@example.com';
        Database::execute(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Reply Report User', :e, 'hash', 'user', datetime('now'))",
            ['e' => $email]
        );
        $userId = (int)Database::lastInsertId();
        $this->user = User::find($userId);

        $gmail = 'rep_acc_' . uniqid() . '@gmail.com';
        Database::execute(
            "INSERT INTO gmail_accounts (user_id, gmail_email, access_token, refresh_token, status, created_at) 
             VALUES (:uid, :gemail, 'mock_token', 'mock_refresh', 'connected', datetime('now'))",
            ['uid' => $userId, 'gemail' => $gmail]
        );
        $accId = (int)Database::lastInsertId();
        $this->account = GmailAccount::find($accId);

        // Authenticate as this user
        Auth::login($this->user);
    }

    public function testReportDefaultsToLastSevenDaysAndRenders(): void {
        // Seed some daily usage for the last 7 days
        $today = date('Y-m-d');
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

        Database::execute(
            "INSERT INTO daily_usage (gmail_account_id, usage_date, reply_count, reply_messages_count, followup_count, followup_messages_count, total_sent, created_at)
             VALUES (:acc, :dt, 3, 5, 2, 4, 9, datetime('now'))",
            ['acc' => $this->account->id, 'dt' => $today]
        );

        Database::execute(
            "INSERT INTO daily_usage (gmail_account_id, usage_date, reply_count, reply_messages_count, followup_count, followup_messages_count, total_sent, created_at)
             VALUES (:acc, :dt, 2, 2, 1, 1, 3, datetime('now'))",
            ['acc' => $this->account->id, 'dt' => $twoDaysAgo]
        );

        $thread = EmailThread::createOrGet($this->account->id, 'th_test_' . uniqid(), [
            'sender_email' => 'lead1@client.com',
            'sender_name' => 'Lead One',
            'subject' => 'Thanks for reaching out',
        ]);

        // Seed scheduled jobs (auto-reply and follow-up)
        ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => [
                'recipient_email' => 'lead1@client.com',
                'recipient_name' => 'Lead One',
                'subject' => 'Thanks for reaching out',
                'reply_body' => 'Hello Lead One, thank you for your email.',
                'reply_step' => 1,
                'total_steps' => 2,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        Database::execute("UPDATE scheduled_jobs SET processed_at = datetime('now') WHERE id = " . Database::lastInsertId());

        ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => [
                'recipient_email' => 'lead2@client.com',
                'recipient_name' => 'Lead Two',
                'subject' => 'Follow up on proposal',
                'reply_body' => 'Hi Lead Two, just following up.',
                'step_number' => 2,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ]);

        $controller = new ReplyReportController();
        $request = new Request([], [], [], [], ['REQUEST_URI' => '/reports/replies']);
        $html = $controller->index($request);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Replies &amp; Follow-ups Report', $html);
        $this->assertStringContainsString('Last 7 Days', $html);
        $this->assertStringContainsString('lead1@client.com', $html);
        $this->assertStringContainsString('lead2@client.com', $html);
        $this->assertStringContainsString('Day-by-Day Activity Summary', $html);
    }

    public function testFilterByTypeAndStatus(): void {
        $thread = EmailThread::createOrGet($this->account->id, 'th_filter_' . uniqid(), [
            'sender_email' => 'filter_lead@client.com',
            'subject' => 'Filter Test Subject',
        ]);

        ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => [
                'recipient_email' => 'only_reply@client.com',
                'subject' => 'Only Reply Subject',
                'reply_body' => 'Auto reply test body',
                'reply_step' => 1,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        Database::execute("UPDATE scheduled_jobs SET processed_at = datetime('now') WHERE id = " . Database::lastInsertId());

        ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'follow_up',
            'payload' => [
                'recipient_email' => 'only_followup@client.com',
                'subject' => 'Only Followup Subject',
                'reply_body' => 'Followup test body',
                'step_number' => 1,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ]);

        $controller = new ReplyReportController();

        // Filter: auto_reply only
        $requestReply = new Request(['type' => 'auto_reply'], [], [], [], []);
        $htmlReply = $controller->index($requestReply);
        $this->assertStringContainsString('only_reply@client.com', $htmlReply);
        $this->assertStringNotContainsString('only_followup@client.com', $htmlReply);

        // Filter: follow_up only
        $requestFollow = new Request(['type' => 'follow_up'], [], [], [], []);
        $htmlFollow = $controller->index($requestFollow);
        $this->assertStringContainsString('only_followup@client.com', $htmlFollow);
        $this->assertStringNotContainsString('only_reply@client.com', $htmlFollow);

        // Filter: status completed only
        $requestCompleted = new Request(['status' => 'completed'], [], [], [], []);
        $htmlCompleted = $controller->index($requestCompleted);
        $this->assertStringContainsString('only_reply@client.com', $htmlCompleted);
        $this->assertStringNotContainsString('only_followup@client.com', $htmlCompleted);
    }

    public function testExportCsvGeneratesValidOutput(): void {
        $thread = EmailThread::createOrGet($this->account->id, 'th_csv_' . uniqid(), [
            'sender_email' => 'csv_lead@client.com',
            'subject' => 'CSV Thread Subject',
        ]);

        ScheduledJob::create([
            'gmail_account_id' => $this->account->id,
            'thread_id' => $thread->id,
            'job_type' => 'auto_reply',
            'payload' => [
                'recipient_email' => 'csv_test@client.com',
                'recipient_name' => 'CSV Tester',
                'subject' => 'CSV Export Subject',
                'reply_body' => 'CSV export content test',
                'reply_step' => 1,
                'total_steps' => 1,
            ],
            'scheduled_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
        ]);
        Database::execute("UPDATE scheduled_jobs SET processed_at = datetime('now') WHERE id = " . Database::lastInsertId());

        $controller = new ReplyReportController();
        $request = new Request(['date_range' => 'all'], [], [], [], []);

        ob_start();
        try {
            $controller->exportCsv($request);
        } catch (\Throwable $t) {
            // catch exit
        }
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('Job ID,Type,Step', $csvOutput);
        $this->assertStringContainsString('csv_test@client.com', $csvOutput);
        $this->assertStringContainsString('CSV Tester', $csvOutput);
        $this->assertStringContainsString('CSV Export Subject', $csvOutput);
    }

    public function testLastSevenDaysBreakdownCalculatesCorrectTotalsForMultipleDates(): void {
        $dates = [
            date('Y-m-d') => [7, 3], // 7 auto-replies, 3 follow-ups
            date('Y-m-d', strtotime('-1 day')) => [4, 2],
            date('Y-m-d', strtotime('-3 days')) => [10, 5],
            date('Y-m-d', strtotime('-5 days')) => [2, 1],
        ];

        foreach ($dates as $dt => [$replies, $followups]) {
            Database::execute(
                "INSERT INTO daily_usage (gmail_account_id, usage_date, reply_count, reply_messages_count, followup_count, followup_messages_count, total_sent, created_at)
                 VALUES (:acc, :dt, :rc, :rmc, :fc, :fmc, :ts, datetime('now'))",
                [
                    'acc' => $this->account->id,
                    'dt' => $dt,
                    'rc' => $replies,
                    'rmc' => $replies,
                    'fc' => $followups,
                    'fmc' => $followups,
                    'ts' => ($replies + $followups),
                ]
            );
        }

        $controller = new ReplyReportController();
        $request = new Request(['date_range' => '7days'], [], [], [], []);
        $html = $controller->index($request);

        // Verify total sum across the last 7 days: 7+4+10+2 = 23 replies, 3+2+5+1 = 11 followups, 34 total
        $this->assertStringContainsString('23', $html);
        $this->assertStringContainsString('11', $html);
        $this->assertStringContainsString('34', $html);
    }
}
