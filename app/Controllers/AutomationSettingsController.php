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
            if (!$selectedAccount || $selectedAccount->user_id !== $user->id) {
                $selectedAccount = null;
            }
        }

        if (!$selectedAccount) {
            $selectedAccount = $accounts[0];
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
                    if (!empty($msg)) {
                        $cleanSteps[$stepNum] = [
                            'message' => $msg,
                            'delay_value' => max(0, (int)($stepData['delay_value'] ?? 0)),
                            'delay_unit' => in_array($stepData['delay_unit'] ?? '', ['seconds', 'minutes', 'hours', 'days']) ? $stepData['delay_unit'] : 'seconds'
                        ];
                    }
                }
            }
            if (empty($cleanSteps)) {
                $cleanSteps[1] = [
                    'message' => "Hello {{first_name}},\n\nThank you for reaching out! We have received your message regarding \"{{subject}}\" and our team will get back to you shortly.\n\nBest regards,\nAutomated Support",
                    'delay_value' => 0,
                    'delay_unit' => 'seconds'
                ];
            }
            $replyMessage = json_encode($cleanSteps, JSON_UNESCAPED_UNICODE);
        } elseif (is_array($replyMessagesInput)) {
            $cleanMessages = [];
            foreach ($replyMessagesInput as $step => $msg) {
                $stepNum = (int)$step;
                if (!empty(trim($msg))) {
                    $cleanMessages[$stepNum] = [
                        'message' => trim($msg),
                        'delay_value' => 0,
                        'delay_unit' => 'seconds'
                    ];
                }
            }
            if (empty($cleanMessages)) {
                $cleanMessages[1] = [
                    'message' => "Hello {{first_name}},\n\nThank you for reaching out! We have received your message regarding \"{{subject}}\" and our team will get back to you shortly.\n\nBest regards,\nAutomated Support",
                    'delay_value' => 0,
                    'delay_unit' => 'seconds'
                ];
            }
            $replyMessage = json_encode($cleanMessages, JSON_UNESCAPED_UNICODE);
        } else {
            $replyMessage = trim($request->input('reply_message', ''));
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

        $settings->update([
            'auto_reply_enabled' => $autoReplyEnabled ? 1 : 0,
            'reply_message' => $replyMessage,
            'max_reply_per_thread' => $maxReplyPerThread,
            'daily_reply_limit' => $dailyReplyLimit,
            'reply_delay' => $replyDelay,
            'followup_enabled' => $followupEnabled ? 1 : 0,
            'daily_followup_limit' => $dailyFollowupLimit,
            'timezone' => $timezone,
            'working_days' => $workingDaysStr,
            'working_start' => $workingStart,
            'working_end' => $workingEnd,
        ]);

        flash('success', "Automation settings updated successfully for {$account->gmail_email}!");
        redirect("/settings/automation/{$account->id}");
    }
}
