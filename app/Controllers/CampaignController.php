<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Core\Database;
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

    /**
     * Helper to extract all uploaded lead files from $_FILES (supports single and multiple files)
     *
     * @return array<array{name: string, tmp_name: string, size: int, error: int}>
     */
    public function extractUploadedRecipientFiles(): array {
        $files = [];

        // Multiple files: recipient_files[]
        if (isset($_FILES['recipient_files']) && is_array($_FILES['recipient_files']['name'])) {
            $count = count($_FILES['recipient_files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_FILES['recipient_files']['name'][$i]) &&
                    $_FILES['recipient_files']['error'][$i] === UPLOAD_ERR_OK &&
                    $_FILES['recipient_files']['size'][$i] > 0) {
                    $files[] = [
                        'name' => $_FILES['recipient_files']['name'][$i],
                        'tmp_name' => $_FILES['recipient_files']['tmp_name'][$i],
                        'size' => (int)$_FILES['recipient_files']['size'][$i],
                        'error' => (int)$_FILES['recipient_files']['error'][$i],
                    ];
                }
            }
        }

        // Single file: recipient_file
        if (isset($_FILES['recipient_file'])) {
            if (is_array($_FILES['recipient_file']['name'])) {
                $count = count($_FILES['recipient_file']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (!empty($_FILES['recipient_file']['name'][$i]) &&
                        $_FILES['recipient_file']['error'][$i] === UPLOAD_ERR_OK &&
                        $_FILES['recipient_file']['size'][$i] > 0) {
                        $files[] = [
                            'name' => $_FILES['recipient_file']['name'][$i],
                            'tmp_name' => $_FILES['recipient_file']['tmp_name'][$i],
                            'size' => (int)$_FILES['recipient_file']['size'][$i],
                            'error' => (int)$_FILES['recipient_file']['error'][$i],
                        ];
                    }
                }
            } elseif (!empty($_FILES['recipient_file']['name']) &&
                      $_FILES['recipient_file']['error'] === UPLOAD_ERR_OK &&
                      $_FILES['recipient_file']['size'] > 0) {
                $files[] = [
                    'name' => $_FILES['recipient_file']['name'],
                    'tmp_name' => $_FILES['recipient_file']['tmp_name'],
                    'size' => (int)$_FILES['recipient_file']['size'],
                    'error' => (int)$_FILES['recipient_file']['error'],
                ];
            }
        }

        return $files;
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

        // Validate and extract uploaded recipient files (supports multiple files and drag-and-drop)
        $uploadedFiles = $this->extractUploadedRecipientFiles();
        if (empty($uploadedFiles)) {
            flash('danger', 'Please upload at least one valid recipient lead file (.txt, .csv, or .xlsx).');
            redirect('/campaigns/create');
        }

        $allowedExts = ['txt', 'csv', 'xlsx'];
        $tempUploadDir = storage_path('temp/uploads');
        if (!is_dir($tempUploadDir)) {
            mkdir($tempUploadDir, 0775, true);
        }

        $tempFiles = [];
        foreach ($uploadedFiles as $f) {
            $origName = $f['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                flash('danger', "Invalid file format for '{$origName}' (.{$ext}). Allowed formats: .txt, .csv, .xlsx");
                redirect('/campaigns/create');
            }

            if ($f['size'] > 50 * 1024 * 1024) {
                flash('danger', "File '{$origName}' exceeds the maximum allowed size (50 MB).");
                redirect('/campaigns/create');
            }

            $tempPath = $tempUploadDir . '/' . uniqid('recip_') . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $tempPath)) {
                if (file_exists($f['tmp_name']) && copy($f['tmp_name'], $tempPath)) {
                    // copied successfully in testing / CLI mode
                } else {
                    flash('danger', "Failed to store uploaded file '{$origName}' securely.");
                    redirect('/campaigns/create');
                }
            }

            $tempFiles[] = [
                'path' => $tempPath,
                'ext' => $ext,
                'name' => $origName,
            ];
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

            // 3. Process Multiple Files Import
            $importService = new RecipientImportService();
            $totals = [
                'total_rows' => 0,
                'valid_emails' => 0,
                'duplicates' => 0,
                'invalid_emails' => 0,
                'imported' => 0,
            ];

            foreach ($tempFiles as $tf) {
                $result = $importService->importFile($campaign->id, $userId, $tf['path'], $tf['ext']);
                $totals['total_rows'] += $result['total_rows'];
                $totals['valid_emails'] += $result['valid_emails'];
                $totals['duplicates'] += $result['duplicates'];
                $totals['invalid_emails'] += $result['invalid_emails'];
                $totals['imported'] += $result['imported'];
            }

            $filesCount = count($tempFiles);
            $fileLabel = $filesCount > 1 ? "{$filesCount} lead files" : "1 lead file";
            $msg = "Campaign '{$name}' created successfully! Imported {$totals['imported']} recipients from {$fileLabel}. (Total rows: {$totals['total_rows']}, Valid: {$totals['valid_emails']}, Duplicates: {$totals['duplicates']}, Invalid: {$totals['invalid_emails']})";
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
            foreach ($tempFiles as $tf) {
                if (file_exists($tf['path'])) {
                    @unlink($tf['path']);
                }
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
        if ($campaign && in_array($campaign->status, ['active', 'completed'])) {
            $campaign->update(['status' => 'paused']);
            flash('warning', "Campaign '{$campaign->name}' paused. No further emails will be sent until resumed.");
        }
        $redirect = $request->input('redirect_to') ?: ('/campaigns/' . $id);
        redirect($redirect);
    }

    public function resume(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign && in_array($campaign->status, ['paused', 'draft', 'cancelled'])) {
            $campaign->update(['status' => 'active']);
            flash('success', "Campaign '{$campaign->name}' resumed and active.");
        }
        $redirect = $request->input('redirect_to') ?: ('/campaigns/' . $id);
        redirect($redirect);
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
        $redirect = $request->input('redirect_to') ?: ('/campaigns/' . $id);
        redirect($redirect);
    }

    public function delete(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $campaign = EmailCampaign::findByUserAndId($userId, $id);
        if ($campaign) {
            $campaignName = $campaign->name;
            $campaign->delete();
            flash('success', "Campaign '{$campaignName}' deleted successfully.");
        }
        $redirect = $request->input('redirect_to') ?: '/campaigns';
        redirect($redirect);
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

        // Optional append more recipients if files are provided (multiple files & drag-and-drop supported)
        $uploadedFiles = $this->extractUploadedRecipientFiles();
        if (!empty($uploadedFiles)) {
            $allowedExts = ['txt', 'csv', 'xlsx'];
            $tempUploadDir = storage_path('temp/uploads');
            if (!is_dir($tempUploadDir)) {
                mkdir($tempUploadDir, 0775, true);
            }

            $importService = new RecipientImportService();
            $appendedCount = 0;
            $filesProcessed = 0;

            foreach ($uploadedFiles as $f) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $tempPath = $tempUploadDir . '/' . uniqid('recip_') . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $tempPath) || (file_exists($f['tmp_name']) && copy($f['tmp_name'], $tempPath))) {
                        try {
                            $importResult = $importService->importFile($campaign->id, $userId, $tempPath, $ext);
                            $appendedCount += $importResult['imported'];
                            $filesProcessed++;
                        } finally {
                            if (file_exists($tempPath)) {
                                @unlink($tempPath);
                            }
                        }
                    }
                }
            }

            if ($filesProcessed > 0) {
                $fileText = $filesProcessed > 1 ? "{$filesProcessed} files" : "1 file";
                flash('success', "Successfully appended {$appendedCount} new recipient(s) to campaign from {$fileText}!");
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

    /**
     * Export Campaign Recipients to CSV
     */
    public function exportRecipients(Request $request, int $id): void {
        if (!$this->authorizeBulkSender()) {
            return;
        }
        $userId = Auth::id();
        $user = Auth::user();
        $campaign = ($user && $user->role === 'admin')
            ? EmailCampaign::find($id)
            : EmailCampaign::findByUserAndId($userId, $id);

        if (!$campaign) {
            flash('error', 'Campaign not found.');
            redirect('/campaigns');
            return;
        }

        $recipients = Database::query("
            SELECT 
                ecr.id,
                ecr.email,
                ecr.first_name,
                ecr.last_name,
                ecr.company,
                ecr.custom_field_1,
                ecr.custom_field_2,
                ecr.status,
                ga.gmail_email as sent_via_gmail,
                ecr.sent_at,
                ecr.skip_reason,
                ecr.last_error,
                ecr.created_at
            FROM email_campaign_recipients ecr
            LEFT JOIN gmail_accounts ga ON ecr.sent_gmail_account_id = ga.id
            WHERE ecr.campaign_id = :cid
            ORDER BY ecr.id ASC
        ", ['cid' => $campaign->id]);

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $campaign->name);
        $filename = "campaign_{$campaign->id}_{$safeName}_leads_" . date('Y-m-d_His') . ".csv";

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
        }

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Lead ID',
            'Email',
            'First Name',
            'Last Name',
            'Company',
            'Custom Field 1',
            'Custom Field 2',
            'Status',
            'Sent Via Gmail',
            'Sent At',
            'Skip Reason',
            'Error Details',
            'Imported Date',
        ]);

        foreach ($recipients as $r) {
            fputcsv($output, [
                $r['id'],
                $r['email'],
                $r['first_name'] ?? '',
                $r['last_name'] ?? '',
                $r['company'] ?? '',
                $r['custom_field_1'] ?? '',
                $r['custom_field_2'] ?? '',
                $r['status'],
                $r['sent_via_gmail'] ?? 'N/A',
                $r['sent_at'] ?? 'N/A',
                $r['skip_reason'] ?? '',
                $r['last_error'] ?? '',
                $r['created_at'] ?? '',
            ]);
        }

        fclose($output);
        if (defined('TESTING') || (getenv('APP_ENV') === 'testing') || (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')) {
            return;
        }
        exit;
    }
}
