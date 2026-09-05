<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailCampaignMessage;
use App\Models\GmailAccount;
use App\Models\GmailCampaignDailyUsage;
use App\Services\RecipientImportService;
use App\Services\CampaignEngine;
use Exception;

class CampaignController {

    private function authorizeBulkSender(): bool {
        $user = Auth::user();
        if (!$user || !$user->canBulkSend()) {
            flash('warning', 'Bulk Email Campaign feature is not enabled for your account. Please contact the administrator to grant bulk sender access.');
            redirect('/dashboard');
            return false;
        }
        return true;
    }

    public function index(Request $request): string {
        if (!$this->authorizeBulkSender()) {
            return '';
        }
        $userId = Auth::id();
        $campaigns = EmailCampaign::findByUserId($userId);
        $accounts = GmailAccount::findByUserId($userId);

        $totalRecipients = 0;
        $totalSent = 0;
        $totalPending = 0;
        $activeCount = 0;

        foreach ($campaigns as $c) {
            $totalRecipients += $c->total_recipients;
            $totalSent += $c->sent_count;
            $totalPending += $c->getRemainingCount();
            if ($c->status === 'active') {
                $activeCount++;
            }
        }

        return View::render('campaigns/index', [
            'campaigns' => $campaigns,
            'accounts' => $accounts,
            'stats' => [
                'total_campaigns' => count($campaigns),
                'active_campaigns' => $activeCount,
                'total_recipients' => $totalRecipients,
                'total_sent' => $totalSent,
                'total_pending' => $totalPending,
            ],
        ]);
    }

    public function create(Request $request): string {
        if (!$this->authorizeBulkSender()) {
            return '';
        }
        $userId = Auth::id();
        $accounts = GmailAccount::findByUserId($userId);

        if (empty($accounts)) {
            flash('warning', 'Please connect at least one Gmail account before creating a bulk email campaign.');
            redirect('/accounts');
        }

        $timezones = [
            'Asia/Dhaka', 'UTC', 'America/New_York', 'America/Chicago',
            'America/Los_Angeles', 'Europe/London', 'Europe/Paris',
            'Asia/Dubai', 'Asia/Kolkata', 'Asia/Singapore', 'Asia/Tokyo', 'Australia/Sydney',
        ];

        return View::render('campaigns/create', [
            'accounts' => $accounts,
            'timezones' => $timezones,
        ]);
    }

    public function store(Request $request): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $name = trim($request->input('name', ''));
        $dailyLimit = max(1, (int)$request->input('daily_campaign_limit', 300));
        $interval = max(5, (int)$request->input('sending_interval', 60));
        $scheduleMode = $request->input('schedule_mode', 'instant');
        if ($scheduleMode === 'instant') {
            $startTime = '00:00';
            $endTime = '23:59';
        } else {
            $startTime = trim($request->input('start_time', '00:00')) ?: '00:00';
            $endTime = trim($request->input('end_time', '23:59')) ?: '23:59';
        }
        $timezone = trim($request->input('timezone', 'Asia/Dhaka')) ?: 'Asia/Dhaka';
        $status = $request->input('status', 'active');
        if (!in_array($status, ['active', 'draft'])) {
            $status = 'active';
        }

        if (empty($name)) {
            flash('danger', 'Campaign name is required.');
            redirect('/campaigns/create');
        }

        // Validate message variations
        $subjects = $request->input('subjects', []);
        $bodies = $request->input('bodies', []);
        $globalSubject = trim($request->input('global_subject', ''));
        $globalBody = trim($request->input('global_message', ''));

        $validVariations = [];

        // Check global message fallback
        if (!empty($globalBody)) {
            $validVariations[] = [
                'subject' => $globalSubject ?: '(No Subject)',
                'body' => $globalBody,
            ];
        }

        // Check variation lists
        if (is_array($bodies)) {
            foreach ($bodies as $idx => $bText) {
                $bClean = trim(strip_tags($bText, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
                if (!empty($bClean)) {
                    $sText = trim($subjects[$idx] ?? '') ?: $globalSubject ?: '(No Subject)';
                    $validVariations[] = [
                        'subject' => $sText,
                        'body' => $bText,
                    ];
                }
            }
        }

        if (empty($validVariations)) {
            flash('danger', 'At least one valid message variation is required. Zero fallback policy prevents sending empty messages.');
            redirect('/campaigns/create');
        }

        // Validate File Upload
        if (!isset($_FILES['recipient_file']) || $_FILES['recipient_file']['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Please upload a valid recipient list (.txt, .csv, or .xlsx).');
            redirect('/campaigns/create');
        }

        $file = $_FILES['recipient_file'];
        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['txt', 'csv', 'xlsx'])) {
            flash('danger', "Invalid file format (.{$ext}). Allowed formats: .txt, .csv, .xlsx");
            redirect('/campaigns/create');
        }

        if ($file['size'] > 25 * 1024 * 1024) {
            flash('danger', 'Recipient file exceeds the maximum allowed size (25 MB).');
            redirect('/campaigns/create');
        }

        // Move to safe temporary storage
        $tempUploadDir = storage_path('temp/uploads');
        if (!is_dir($tempUploadDir)) {
            mkdir($tempUploadDir, 0775, true);
        }
        $tempPath = $tempUploadDir . '/' . uniqid('recip_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            flash('danger', 'Failed to store uploaded file securely.');
            redirect('/campaigns/create');
        }

        try {
            // 1. Create EmailCampaign
            $campaign = EmailCampaign::create([
                'user_id' => $userId,
                'name' => $name,
                'status' => $status,
                'daily_campaign_limit' => $dailyLimit,
                'sending_interval' => $interval,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $timezone,
            ]);

            // 2. Insert Message Variations
            foreach ($validVariations as $var) {
                EmailCampaignMessage::create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'subject' => $var['subject'],
                    'body' => $var['body'],
                    'status' => 'active',
                ]);
            }

            // 3. Process Streaming Import
            $importService = new RecipientImportService();
            $result = $importService->importFile($campaign->id, $userId, $tempPath, $ext);

            $msg = "Campaign '{$name}' created successfully! Imported {$result['imported']} recipients. (Total rows: {$result['total_rows']}, Valid: {$result['valid_emails']}, Duplicates: {$result['duplicates']}, Invalid: {$result['invalid_emails']})";
            flash('success', $msg);

            // Kick off immediate sending batch if campaign is active
            if ($status === 'active') {
                try {
                    CampaignEngine::processBatch(5);
                } catch (\Throwable $t) {
                    // Worker continues processing in background
                }
            }

            redirect('/campaigns/' . $campaign->id);

        } catch (\Throwable $e) {
            flash('danger', 'Error creating campaign: ' . $e->getMessage());
            redirect('/campaigns/create');
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function show(Request $request, int $id): string {
        if (!$this->authorizeBulkSender()) {
            return '';
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);

        if (!$campaign) {
            flash('danger', 'Campaign not found or unauthorized access.');
            redirect('/campaigns');
        }

        $campaign->recalculateStats();

        // Connected Accounts for this user with per-account sending telemetry
        $accounts = GmailAccount::findByUserId($userId);
        $accountStats = [];
        foreach ($accounts as $acc) {
            $usage = GmailCampaignDailyUsage::getAccountUsage($acc->id);
            $limit = $acc->bulk_daily_limit > 0 ? $acc->bulk_daily_limit : 50;
            $accountStats[] = [
                'account' => $acc,
                'limit' => $limit,
                'sent' => $usage['emails_sent'],
                'failed' => $usage['emails_failed'],
                'remaining' => max(0, $limit - $usage['emails_sent']),
                'eligible' => $acc->isCampaignEligible(),
            ];
        }

        // Message Variations Stats
        $messages = EmailCampaignMessage::findByCampaignId($campaign->id);

        // Recipients list with filtering & pagination
        $page = max(1, (int)$request->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $statusFilter = $request->input('status');
        $searchQuery = trim($request->input('q', ''));

        $totalRecipientsFiltered = EmailCampaignRecipient::countByCampaign($campaign->id, $statusFilter);
        $recipients = EmailCampaignRecipient::paginateByCampaign($campaign->id, $limit, $offset, $statusFilter, $searchQuery);
        $totalPages = max(1, (int)ceil($totalRecipientsFiltered / $limit));

        // Recent sends audit trail
        $auditLogs = \App\Core\Database::query(
            "SELECT s.*, g.gmail_email, r.email as recipient_email 
             FROM email_campaign_sends s
             JOIN gmail_accounts g ON s.gmail_account_id = g.id
             JOIN email_campaign_recipients r ON s.recipient_id = r.id
             WHERE s.campaign_id = :cid
             ORDER BY s.id DESC LIMIT 25",
            ['cid' => $campaign->id]
        );

        return View::render('campaigns/show', [
            'campaign' => $campaign,
            'accounts' => $accountStats,
            'messages' => $messages,
            'recipients' => $recipients,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'total_count' => $totalRecipientsFiltered,
                'status' => $statusFilter,
                'q' => $searchQuery,
            ],
            'auditLogs' => $auditLogs,
        ]);
    }

    public function pause(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign && $campaign->status === 'active') {
            $campaign->update(['status' => 'paused']);
            flash('warning', "Campaign '{$campaign->name}' paused. No further emails will be sent until resumed.");
        }
        redirect('/campaigns/' . $id);
    }

    public function resume(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign && in_array($campaign->status, ['paused', 'draft'])) {
            $campaign->update(['status' => 'active']);
            flash('success', "Campaign '{$campaign->name}' resumed and active.");
        }
        redirect('/campaigns/' . $id);
    }

    public function cancel(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign) {
            $campaign->update(['status' => 'cancelled']);
            \App\Core\Database::execute(
                "UPDATE email_campaign_recipients 
                 SET status = 'cancelled' 
                 WHERE campaign_id = :cid AND status IN ('pending', 'queued', 'sending')",
                ['cid' => $campaign->id]
            );
            $campaign->recalculateStats();
            flash('info', "Campaign '{$campaign->name}' cancelled permanently.");
        }
        redirect('/campaigns/' . $id);
    }

    public function delete(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign) {
            $campaign->delete();
            flash('success', 'Campaign deleted successfully.');
        }
        redirect('/campaigns');
    }

    public function accounts(Request $request): string {
        if (!$this->authorizeBulkSender()) {
            return '';
        }
        $userId = Auth::id();
        $accounts = GmailAccount::findByUserId($userId);

        $accountData = [];
        foreach ($accounts as $acc) {
            $usage = GmailCampaignDailyUsage::getAccountUsage($acc->id);
            $limit = $acc->bulk_daily_limit > 0 ? $acc->bulk_daily_limit : 50;
            $accountData[] = [
                'account' => $acc,
                'limit' => $limit,
                'sent' => $usage['emails_sent'],
                'failed' => $usage['emails_failed'],
                'remaining' => max(0, $limit - $usage['emails_sent']),
                'eligible' => $acc->isCampaignEligible(),
            ];
        }

        return View::render('campaigns/accounts', [
            'accounts' => $accountData,
        ]);
    }

    public function updateAccountLimits(Request $request): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $limits = $request->input('limits', []);
        $enabled = $request->input('enabled', []);

        $accounts = GmailAccount::findByUserId($userId);
        foreach ($accounts as $acc) {
            $newLimit = isset($limits[$acc->id]) ? max(1, (int)$limits[$acc->id]) : $acc->bulk_daily_limit;
            $isEnabled = isset($enabled[$acc->id]) ? 1 : 0;
            $acc->update([
                'bulk_daily_limit' => $newLimit,
                'campaign_enabled' => $isEnabled,
            ]);
        }

        flash('success', 'Per-Gmail sending limits and campaign status updated successfully.');
        redirect('/campaigns/accounts');
    }

    public function edit(Request $request, int $id): string {
        if (!$this->authorizeBulkSender()) {
            return '';
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);

        if (!$campaign) {
            flash('danger', 'Campaign not found.');
            redirect('/campaigns');
        }

        $messages = EmailCampaignMessage::findByCampaignId($campaign->id);
        $accounts = GmailAccount::findByUserId($userId);
        $timezones = \DateTimeZone::listIdentifiers();

        return View::render('campaigns/edit', [
            'campaign' => $campaign,
            'messages' => $messages,
            'accounts' => $accounts,
            'timezones' => $timezones,
        ]);
    }

    public function update(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);

        if (!$campaign) {
            flash('danger', 'Campaign not found.');
            redirect('/campaigns');
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            flash('danger', 'Campaign name is required.');
            redirect('/campaigns/' . $id . '/edit');
        }

        $dailyLimit = max(1, (int)$request->input('daily_campaign_limit', 300));
        $interval = max(5, (int)$request->input('sending_interval', 60));

        $scheduleMode = $request->input('schedule_mode', 'instant');
        if ($scheduleMode === 'instant') {
            $startTime = '00:00';
            $endTime = '23:59';
        } else {
            $startTime = trim($request->input('start_time', '00:00')) ?: '00:00';
            $endTime = trim($request->input('end_time', '23:59')) ?: '23:59';
        }

        $timezone = trim($request->input('timezone', 'Asia/Dhaka')) ?: 'Asia/Dhaka';
        $status = $request->input('status', $campaign->status);
        if (!in_array($status, ['active', 'paused', 'draft', 'cancelled'])) {
            $status = $campaign->status;
        }

        $campaign->update([
            'name' => $name,
            'daily_campaign_limit' => $dailyLimit,
            'sending_interval' => $interval,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'status' => $status,
        ]);

        // Message Variations update/add
        $messageIds = $request->input('message_ids', []);
        $subjects = $request->input('subjects', []);
        $bodies = $request->input('bodies', []);

        if (is_array($bodies)) {
            foreach ($bodies as $idx => $bodyText) {
                $bClean = trim(strip_tags($bodyText, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>'));
                if (empty($bClean)) continue;

                $mId = (int)($messageIds[$idx] ?? 0);
                $sText = trim($subjects[$idx] ?? '') ?: '(No Subject)';

                if ($mId > 0) {
                    $msg = EmailCampaignMessage::find($mId);
                    if ($msg && (int)$msg->campaign_id === (int)$campaign->id) {
                        $msg->update([
                            'subject' => $sText,
                            'body' => $bodyText,
                        ]);
                    }
                } else {
                    EmailCampaignMessage::create([
                        'campaign_id' => $campaign->id,
                        'user_id' => $userId,
                        'subject' => $sText,
                        'body' => $bodyText,
                        'status' => 'active',
                    ]);
                }
            }
        }

        // Optional append more recipients if a file is provided
        if (isset($_FILES['recipient_file']) && $_FILES['recipient_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['recipient_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['txt', 'csv', 'xlsx'])) {
                $tempUploadDir = storage_path('temp/uploads');
                if (!is_dir($tempUploadDir)) mkdir($tempUploadDir, 0775, true);
                $tempPath = $tempUploadDir . '/' . uniqid('recip_') . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                    $importService = new RecipientImportService();
                    $importResult = $importService->importFile($campaign->id, $userId, $tempPath, $ext);
                    @unlink($tempPath);
                    flash('success', "Appended {$importResult['imported']} new recipient(s) to campaign!");
                }
            }
        }

        $campaign->recalculateStats();

        // If active, kick off an immediate batch
        if ($status === 'active') {
            try {
                CampaignEngine::processBatch(5);
            } catch (\Throwable $t) {
                // Background worker handles rest
            }
        }

        flash('success', 'Campaign settings updated successfully!');
        redirect('/campaigns/' . $campaign->id);
    }

    public function sendBatchNow(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);

        if (!$campaign) {
            flash('danger', 'Campaign not found.');
            redirect('/campaigns');
        }

        if ($campaign->status !== 'active') {
            flash('warning', 'Campaign must be Active to send emails. Please resume or activate it first.');
            redirect('/campaigns/' . $campaign->id);
        }

        try {
            // Check schedule hours
            if (!$campaign->isWithinSendingSchedule()) {
                flash('warning', "Cannot send now: Current time is outside campaign active hours ({$campaign->start_time} – {$campaign->end_time} {$campaign->timezone}). Please edit the campaign to 'Instant Send (No Schedule)' or adjust the hours to send right now.");
                redirect('/campaigns/' . $campaign->id);
            }

            // Process next batch for this specific campaign with interval bypass
            $sentCount = CampaignEngine::processCampaign($campaign, 5, true);
            $campaign->recalculateStats();

            if ($sentCount > 0) {
                flash('success', "Dispatched {$sentCount} campaign email(s) successfully!");
            } else {
                $remaining = $campaign->getRemainingCount();
                if ($remaining === 0) {
                    flash('info', 'All recipients have already been processed for this campaign.');
                } else {
                    flash('warning', 'No emails were sent in this run. Please verify your Gmail accounts daily limits, OAuth connection, or campaign limits.');
                }
            }
        } catch (\Throwable $e) {
            flash('danger', 'Error sending campaign batch: ' . $e->getMessage());
            logger("Error in sendBatchNow for Campaign #{$campaign->id}: " . $e->getMessage(), 'error', $userId);
        }

        redirect('/campaigns/' . $campaign->id);
    }
}
