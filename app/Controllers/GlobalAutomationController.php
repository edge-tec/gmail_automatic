<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\GlobalAutomationSetting;
use App\Models\GlobalAutoReplyMessage;
use App\Models\GlobalFollowupSequence;
use App\Models\GlobalFollowupMessage;

class GlobalAutomationController {
    /**
     * Show the Global Automation configuration dashboard
     */
    public function show(Request $request): string {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $settings = GlobalAutomationSetting::getForUser($user->id);
        $autoRepliesByStep = GlobalAutoReplyMessage::getForUserGroupedByStep($user->id);
        $followupSequences = GlobalFollowupSequence::getForUser($user->id);
        $followupVariationsByStep = GlobalFollowupMessage::getForUserGroupedByStep($user->id);
        $accounts = GmailAccount::findByUserId($user->id);

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
            'Europe/London' => '(UTC+00:00) London / GMT',
            'Asia/Tokyo' => '(UTC+09:00) Tokyo / Japan',
            'Australia/Sydney' => '(UTC+10:00) Sydney / Australia',
        ];

        return View::render('settings/global_automation', [
            'settings' => $settings,
            'autoRepliesByStep' => $autoRepliesByStep,
            'followupSequences' => $followupSequences,
            'followupVariationsByStep' => $followupVariationsByStep,
            'accounts' => $accounts,
            'timezones' => $timezones,
        ]);
    }

    /**
     * Save/update Global Auto-Reply multi-step messages and variations
     */
    public function saveAutoReply(Request $request): void {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $stepsInput = $request->input('steps');
        if (!is_array($stepsInput)) {
            flash('danger', 'Invalid auto-reply submission.');
            redirect('/settings/automation/global#tab-autoreply');
        }

        $settings = GlobalAutomationSetting::getForUser($user->id);
        $savedCount = 0;

        foreach ($stepsInput as $stepKey => $stepData) {
            $stepNumber = (int)($stepData['step_number'] ?? $stepKey);
            if ($stepNumber < 1) {
                continue;
            }

            $delayMinutes = max(0, (int)($stepData['delay_minutes'] ?? 0));
            $variations = $stepData['variations'] ?? [];

            // Existing variation IDs for this step
            $existingVariations = GlobalAutoReplyMessage::getForStep($user->id, $stepNumber);
            $existingIds = array_map(fn($v) => $v->id, $existingVariations);
            $keptIds = [];

            if (is_array($variations)) {
                foreach ($variations as $varData) {
                    $varId = !empty($varData['id']) ? (int)$varData['id'] : null;
                    $varName = trim($varData['variation_name'] ?? 'Variation A');
                    $bodyHtml = trim($varData['body_html'] ?? '');
                    $isActive = !empty($varData['is_active']) ? 1 : 0;

                    // Skip completely empty bodies
                    $textOnly = trim(strip_tags($bodyHtml, '<img>'));
                    if (empty($textOnly) && !str_contains($bodyHtml, '<img')) {
                        continue;
                    }

                    if ($varId && in_array($varId, $existingIds, true)) {
                        $model = GlobalAutoReplyMessage::find($varId);
                        if ($model && $model->user_id === $user->id) {
                            $model->step_number = $stepNumber;
                            $model->delay_minutes = $delayMinutes;
                            $model->variation_name = $varName;
                            $model->body_html = $bodyHtml;
                            $model->is_active = $isActive;
                            $model->save();
                            $keptIds[] = $model->id;
                            $savedCount++;
                        }
                    } else {
                        $model = new GlobalAutoReplyMessage([
                            'user_id' => $user->id,
                            'step_number' => $stepNumber,
                            'delay_minutes' => $delayMinutes,
                            'variation_name' => $varName,
                            'body_html' => $bodyHtml,
                            'is_active' => $isActive,
                        ]);
                        $model->save();
                        $keptIds[] = $model->id;
                        $savedCount++;
                    }
                }
            }

            // Remove variations that were deleted by user in this step
            foreach ($existingVariations as $ex) {
                if (!in_array($ex->id, $keptIds, true)) {
                    $ex->delete();
                }
            }
        }

        $settings->bumpVersion();
        flash('success', 'Global Auto-Reply configurations saved successfully!');
        redirect('/settings/automation/global#tab-autoreply');
    }

    /**
     * Delete an entire Auto-Reply step and all its variations
     */
    public function deleteAutoReplyStep(Request $request, int $step): void {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        GlobalAutoReplyMessage::deleteStep($user->id, $step);
        $settings = GlobalAutomationSetting::getForUser($user->id);
        $settings->bumpVersion();

        flash('success', "Auto-Reply Step #{$step} and all its variations have been removed.");
        redirect('/settings/automation/global#tab-autoreply');
    }

    /**
     * Save/update Global Follow-up steps and their variations
     */
    public function saveFollowups(Request $request): void {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $stepsInput = $request->input('followup_steps');
        if (!is_array($stepsInput)) {
            flash('danger', 'Invalid follow-up configuration submission.');
            redirect('/settings/automation/global#tab-followup');
        }

        $settings = GlobalAutomationSetting::getForUser($user->id);
        $savedSteps = 0;

        foreach ($stepsInput as $stepKey => $stepData) {
            $stepNumber = (int)($stepData['step_number'] ?? $stepKey);
            if ($stepNumber < 1) {
                continue;
            }

            $delayValue = max(1, (int)($stepData['delay_value'] ?? 1));
            $delayUnit = in_array($stepData['delay_unit'] ?? '', ['minutes', 'hours', 'days']) ? $stepData['delay_unit'] : 'days';
            $isActive = !empty($stepData['is_active']) ? 1 : 0;

            // Find or create sequence entry
            $seq = GlobalFollowupSequence::findByStep($user->id, $stepNumber);
            if (!$seq) {
                $seq = new GlobalFollowupSequence([
                    'user_id' => $user->id,
                    'step_number' => $stepNumber,
                    'delay_value' => $delayValue,
                    'delay_unit' => $delayUnit,
                    'is_active' => $isActive,
                ]);
            } else {
                $seq->delay_value = $delayValue;
                $seq->delay_unit = $delayUnit;
                $seq->is_active = $isActive;
            }
            $seq->save();
            $savedSteps++;

            // Handle variations for this step
            $variations = $stepData['variations'] ?? [];
            $existingVariations = GlobalFollowupMessage::getForStep($user->id, $stepNumber);
            $existingIds = array_map(fn($v) => $v->id, $existingVariations);
            $keptIds = [];

            if (is_array($variations)) {
                foreach ($variations as $varData) {
                    $varId = !empty($varData['id']) ? (int)$varData['id'] : null;
                    $varName = trim($varData['variation_name'] ?? 'Variation A');
                    $subject = trim($varData['subject'] ?? '');
                    $bodyHtml = trim($varData['body_html'] ?? '');
                    $varActive = !empty($varData['is_active']) ? 1 : 0;

                    $textOnly = trim(strip_tags($bodyHtml, '<img>'));
                    if (empty($textOnly) && !str_contains($bodyHtml, '<img')) {
                        continue;
                    }

                    if ($varId && in_array($varId, $existingIds, true)) {
                        $model = GlobalFollowupMessage::find($varId);
                        if ($model && $model->user_id === $user->id) {
                            $model->step_number = $stepNumber;
                            $model->variation_name = $varName;
                            $model->subject = $subject;
                            $model->body_html = $bodyHtml;
                            $model->is_active = $varActive;
                            $model->save();
                            $keptIds[] = $model->id;
                        }
                    } else {
                        $model = new GlobalFollowupMessage([
                            'user_id' => $user->id,
                            'step_number' => $stepNumber,
                            'variation_name' => $varName,
                            'subject' => $subject,
                            'body_html' => $bodyHtml,
                            'is_active' => $varActive,
                        ]);
                        $model->save();
                        $keptIds[] = $model->id;
                    }
                }
            }

            // Remove variations not retained
            foreach ($existingVariations as $ex) {
                if (!in_array($ex->id, $keptIds, true)) {
                    $ex->delete();
                }
            }
        }

        $settings->bumpVersion();
        flash('success', 'Global Follow-up Sequence configuration saved successfully!');
        redirect('/settings/automation/global#tab-followup');
    }

    /**
     * Delete an entire Follow-up step and all its variations
     */
    public function deleteFollowupStep(Request $request, int $step): void {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        GlobalFollowupSequence::deleteStep($user->id, $step);
        GlobalFollowupMessage::deleteStep($user->id, $step);

        $settings = GlobalAutomationSetting::getForUser($user->id);
        $settings->bumpVersion();

        flash('success', "Global Follow-up Step #{$step} and all its variations have been removed.");
        redirect('/settings/automation/global#tab-followup');
    }

    /**
     * Save global execution settings, schedules, and limits
     */
    public function saveSettings(Request $request): void {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }

        $settings = GlobalAutomationSetting::getForUser($user->id);

        $settings->auto_reply_enabled = (bool)$request->input('auto_reply_enabled', false);
        $settings->followup_enabled = (bool)$request->input('followup_enabled', false);
        $settings->require_recipient_reply_before_next_reply = (bool)$request->input('require_recipient_reply_before_next_reply', true);

        $replyTimeType = $request->input('reply_time_type', 'instant');
        $settings->reply_time_type = in_array($replyTimeType, ['instant', 'working_hours'], true) ? $replyTimeType : 'instant';

        $workingDays = $request->input('working_days');
        if (is_array($workingDays)) {
            $settings->working_days = implode(',', array_map('intval', $workingDays));
        } elseif (is_string($workingDays)) {
            $settings->working_days = $workingDays;
        }

        $settings->working_start = trim($request->input('working_start', '09:00'));
        $settings->working_end = trim($request->input('working_end', '18:00'));
        $settings->timezone = trim($request->input('timezone', 'UTC'));

        $settings->daily_reply_limit_per_account = max(1, (int)$request->input('daily_reply_limit_per_account', 100));
        $settings->daily_followup_limit_per_account = max(1, (int)$request->input('daily_followup_limit_per_account', 100));

        $settings->bumpVersion();
        $settings->save();

        flash('success', 'Global schedule, limits, and behavior rules updated successfully!');
        redirect('/settings/automation/global#tab-settings');
    }
}
