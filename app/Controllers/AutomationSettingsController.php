<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\AutomationSetting;

class AutomationSettingsController {
    public function show(Request $request, ?int $accountId = null): string {
        $user = Auth::user();
        $accounts = GmailAccount::findByUserId($user->id);

        if (empty($accounts)) {
            flash('warning', 'Please connect a Gmail account first to configure automation settings.');
            redirect('/accounts');
        }

        $selectedAccount = null;
        if ($accountId) {
            $selectedAccount = GmailAccount::find($accountId);
            if ($selectedAccount && $selectedAccount->user_id === $user->id) {
                \App\Core\Session::set('selected_account_id', $selectedAccount->id);
            } else {
                $selectedAccount = null;
            }
        }

        if (!$selectedAccount && \App\Core\Session::has('selected_account_id')) {
            $sessId = (int)\App\Core\Session::get('selected_account_id');
            $found = GmailAccount::find($sessId);
            if ($found && $found->user_id === $user->id) {
                $selectedAccount = $found;
            }
        }

        if (!$selectedAccount) {
            $selectedAccount = $accounts[0];
            \App\Core\Session::set('selected_account_id', $selectedAccount->id);
        }

        $settings = $selectedAccount->getSettings();
        if (!$settings) {
            $settings = AutomationSetting::createDefault($selectedAccount->id);
        }

        // Available timezones
        $timezones = [
            'Asia/Dhaka' => '(UTC+06:00) Dhaka / Bangladesh',
            'UTC' => '(UTC+00:00) UTC / London',
            'America/New_York' => '(UTC-05:00) New York / Eastern Time',
            'America/Chicago' => '(UTC-06:00) Chicago / Central Time',
            'America/Los_Angeles' => '(UTC-08:00) Los Angeles / Pacific Time',
            'Asia/Dubai' => '(UTC+04:00) Dubai / Gulf Standard Time',
            'Asia/Kolkata' => '(UTC+05:30) India Standard Time',
            'Asia/Singapore' => '(UTC+08:00) Singapore Time',
            'Europe/Berlin' => '(UTC+01:00) Berlin / Central European Time',
        ];

        return View::render('settings/automation', [
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'settings' => $settings,
            'replySteps' => $settings->getReplyStepsData(),
            'timezones' => $timezones,
        ]);
    }

    public function update(Request $request, int $accountId): void {
        $user = Auth::user();
        $account = GmailAccount::find($accountId);

        if (!$account || $account->user_id !== $user->id) {
            flash('error', 'Account not found.');
            redirect('/settings/automation');
            return;
        }

        $settings = $account->getSettings();
        if (!$settings) {
            $settings = AutomationSetting::createDefault($account->id);
        }

        $replyStepsInput = $request->input('reply_steps');
        $replyMessagesInput = $request->input('reply_messages');

        if (is_array($replyStepsInput)) {
            $cleanSteps = [];
            foreach ($replyStepsInput as $step => $stepData) {
                $stepNum = (int)$step;
                if (is_array($stepData)) {
                    $msg = trim($stepData['message'] ?? '');
                    $isMeaningful = !empty(trim(strip_tags($msg))) || !empty(trim(strip_tags($msg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>')));
                    $isPlaceholder = in_array($msg, ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
                    if ($isMeaningful && !$isPlaceholder) {
                        $cleanSteps[$stepNum] = [
                            'message' => $msg,
                            'delay_value' => max(0, (int)($stepData['delay_value'] ?? 0)),
                            'delay_unit' => in_array($stepData['delay_unit'] ?? '', ['seconds', 'minutes', 'hours', 'days']) ? $stepData['delay_unit'] : 'seconds'
                        ];
                    }
                }
            }

            $cleanSteps['_blacklist'] = [
                'emails' => trim($request->input('blacklist_emails', '')),
                'domains' => trim($request->input('blacklist_domains', '')),
                'keywords' => trim($request->input('blacklist_keywords', '')),
            ];

            $stepsOnly = array_filter(array_keys($cleanSteps), fn($k) => $k !== '_blacklist');
            if (empty($stepsOnly) && empty($cleanSteps['_blacklist']['emails']) && empty($cleanSteps['_blacklist']['domains']) && empty($cleanSteps['_blacklist']['keywords'])) {
                $replyMessage = null;
            } else {
                $replyMessage = json_encode($cleanSteps, JSON_UNESCAPED_UNICODE);
            }

            $stepsCount = count($stepsOnly);
            logger("Updated auto-reply message sequence ({$stepsCount} steps configured) successfully", 'info', $account->user_id, $account->id);
        } elseif (is_array($replyMessagesInput)) {
            $cleanMessages = [];
            foreach ($replyMessagesInput as $step => $msg) {
                $stepNum = (int)$step;
                $msgStr = trim($msg);
                $isMeaningful = !empty(trim(strip_tags($msgStr))) || !empty(trim(strip_tags($msgStr, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>')));
                $isPlaceholder = in_array($msgStr, ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
                if ($isMeaningful && !$isPlaceholder) {
                    $cleanMessages[$stepNum] = [
                        'message' => $msgStr,
                        'delay_value' => 0,
                        'delay_unit' => 'seconds'
                    ];
                }
            }

            $cleanMessages['_blacklist'] = [
                'emails' => trim($request->input('blacklist_emails', '')),
                'domains' => trim($request->input('blacklist_domains', '')),
                'keywords' => trim($request->input('blacklist_keywords', '')),
            ];

            $stepsOnly = array_filter(array_keys($cleanMessages), fn($k) => $k !== '_blacklist');
            if (empty($stepsOnly) && empty($cleanMessages['_blacklist']['emails']) && empty($cleanMessages['_blacklist']['domains']) && empty($cleanMessages['_blacklist']['keywords'])) {
                $replyMessage = null;
            } else {
                $replyMessage = json_encode($cleanMessages, JSON_UNESCAPED_UNICODE);
            }
        } else {
            $rawMsg = trim($request->input('reply_message', ''));
            $isMeaningful = !empty(trim(strip_tags($rawMsg))) || !empty(trim(strip_tags($rawMsg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>')));
            $isPlaceholder = in_array($rawMsg, ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
            $replyMessage = ($isMeaningful && !$isPlaceholder) ? $rawMsg : null;
        }

        $autoReplyEnabled = (bool)$request->input('auto_reply_enabled', 0);
        $followupEnabled = (bool)$request->input('followup_enabled', 0);
        $dailyFollowupLimit = max(1, (int)$request->input('daily_followup_limit', 100));
        $maxReplyPerThread = max(1, (int)$request->input('max_reply_per_thread', 3));
        $dailyReplyLimit = max(1, (int)$request->input('daily_reply_limit', 100));
        $scheduleMode = $request->input('schedule_mode', 'instant');
        $timezone = $request->input('timezone', 'Asia/Dhaka');

        if ($scheduleMode === 'instant') {
            $workingStart = '00:00';
            $workingEnd = '23:59';
            $workingDaysStr = 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
            $replyDelay = 0;
        } else {
            $workingDays = $request->input('working_days', []);
            $workingDaysStr = is_array($workingDays) && !empty($workingDays) ? implode(',', $workingDays) : 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday';
            $workingStart = $request->input('working_start', '00:00');
            $workingEnd = $request->input('working_end', '23:59');
            $replyDelay = max(0, (int)$request->input('reply_delay', 0));
        }

        try {
            $requireRecipientReply = (bool)$request->input('require_recipient_reply_before_next_reply', 0);

            $settings->update([
                'auto_reply_enabled' => $autoReplyEnabled ? 1 : 0,
                'reply_message' => $replyMessage,
                'max_reply_per_thread' => $maxReplyPerThread,
                'daily_reply_limit' => $dailyReplyLimit,
                'reply_delay' => $replyDelay,
                'followup_enabled' => $followupEnabled ? 1 : 0,
                'daily_followup_limit' => $dailyFollowupLimit,
                'require_recipient_reply_before_next_reply' => $requireRecipientReply ? 1 : 0,
                'timezone' => $timezone,
                'working_days' => $workingDaysStr,
                'working_start' => $workingStart,
                'working_end' => $workingEnd,
            ]);

            if (!$autoReplyEnabled || empty($replyMessage)) {
                \App\Models\ScheduledJob::cancelPendingJobsByAccountAndType($account->id, 'auto_reply', 'Auto-reply was disabled or all messages cleared');
            } else {
                // Update or cancel pending queued auto-reply jobs based on the new customized step messages
                $pendingJobs = \App\Core\Database::query(
                    "SELECT * FROM scheduled_jobs WHERE gmail_account_id = :acc AND status = 'pending' AND job_type = 'auto_reply'",
                    ['acc' => $account->id]
                );
                $updatedSettings = $account->getSettings();
                if ($updatedSettings) {
                    foreach ($pendingJobs as $pj) {
                        $payload = json_decode($pj['payload'], true);
                        if (is_array($payload) && isset($payload['reply_step'])) {
                            $step = (int)$payload['reply_step'];
                            $latestStepMsg = $updatedSettings->getReplyMessageForStep($step);
                            $isMeaningful = !empty(trim(strip_tags($latestStepMsg))) || !empty(trim(strip_tags($latestStepMsg, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>')));
                            $isPlaceholder = in_array(trim($latestStepMsg), ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);
                            if (!$isMeaningful || $isPlaceholder) {
                                // Message for this step was deleted by user -> cancel job immediately
                                \App\Core\Database::execute(
                                    "UPDATE scheduled_jobs SET status = 'cancelled', last_error = :err WHERE id = :id",
                                    ['err' => "Auto-reply message for Step #{$step} was deleted by user", 'id' => $pj['id']]
                                );
                            } else {
                                $payload['reply_body'] = $latestStepMsg;
                                \App\Core\Database::execute(
                                    "UPDATE scheduled_jobs SET payload = :p WHERE id = :id",
                                    ['p' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'id' => $pj['id']]
                                );
                            }
                        }
                    }
                }
            }
            if (!$followupEnabled) {
                \App\Models\ScheduledJob::cancelPendingJobsByAccountAndType($account->id, 'follow_up', 'Follow-up automation was disabled in settings');
            }

            flash('success', "Automation settings updated successfully for {$account->gmail_email}!");
        } catch (\Throwable $e) {
            logger("Failed to update automation settings: " . $e->getMessage(), 'error', $account->user_id, $account->id);
            flash('error', 'Failed to update automation settings: ' . $e->getMessage());
        }

        redirect("/settings/automation/{$account->id}");
    }

    public function clearAll(Request $request, int $accountId): void {
        $user = Auth::user();
        $account = GmailAccount::find($accountId);
        if (!$account || $account->user_id !== $user->id) {
            flash('error', 'Account not found.');
            redirect('/settings/automation');
            return;
        }

        $settings = $account->getSettings();
        if ($settings) {
            $settings->update(['reply_message' => null]);
        }

        \App\Models\ScheduledJob::cancelPendingJobsByAccountAndType(
            $account->id,
            'auto_reply',
            'All auto-reply messages were deleted by user'
        );

        logger("Permanently deleted all auto-reply messages and cancelled all pending queue jobs for {$account->gmail_email}", 'info', $account->user_id, $account->id);
        flash('success', 'All auto-reply messages have been permanently deleted and all pending jobs cancelled.');
        redirect("/settings/automation/{$account->id}");
    }
}
