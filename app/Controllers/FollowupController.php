<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\FollowupTemplate;

class FollowupController {
    public function show(Request $request, ?int $accountId = null): string {
        $user = Auth::user();
        $accounts = GmailAccount::findByUserId($user->id);

        if (empty($accounts)) {
            flash('warning', 'Please connect a Gmail account first to configure follow-ups.');
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

        $steps = FollowupTemplate::findByAccountId($selectedAccount->id);
        $settings = $selectedAccount->getSettings();

        return View::render('settings/followups', [
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'steps' => $steps,
            'settings' => $settings,
        ]);
    }

    public function create(Request $request, int $accountId): void {
        $user = Auth::user();
        $account = GmailAccount::find($accountId);

        if (!$account || $account->user_id !== $user->id) {
            flash('error', 'Account not found.');
            redirect('/settings/followups');
            return;
        }

        $existingSteps = FollowupTemplate::findByAccountId($account->id);
        $nextStepNum = count($existingSteps) + 1;

        $name = trim($request->input('name', "Follow-up #{$nextStepNum}"));
        $rawMessage = trim($request->input('message', ''));
        if (strlen($rawMessage) > 10 * 1024 * 1024) {
            flash('error', 'Follow-up message exceeds the maximum allowed size (10 MB).');
            redirect("/settings/followups/{$account->id}");
            return;
        }

        $message = \App\Controllers\AutomationSettingsController::sanitizeRichText($rawMessage);
        $delayValue = max(1, (int)$request->input('delay_value', 2));
        $delayUnit = $request->input('delay_unit', 'days');

        $isMeaningful = !empty(trim(strip_tags($message))) || !empty(trim(strip_tags($message, '<img><picture><figure><svg><video><audio><object><embed><canvas><hr><input>')));
        $isPlaceholder = in_array($message, ['', '<p><br></p>', '<p></p>', '<br>', '<div><br></div>']);

        if (!$isMeaningful || $isPlaceholder) {
            flash('error', 'Follow-up message cannot be empty.');
            redirect("/settings/followups/{$account->id}");
            return;
        }

        FollowupTemplate::create([
            'user_id' => $user->id,
            'gmail_account_id' => $account->id,
            'step_number' => $nextStepNum,
            'name' => $name,
            'message' => $message,
            'delay_value' => $delayValue,
            'delay_unit' => $delayUnit,
            'status' => 'active',
        ]);

        flash('success', "Follow-up step #{$nextStepNum} added successfully!");
        redirect("/settings/followups/{$account->id}");
    }

    public function update(Request $request, int $id): void {
        $step = FollowupTemplate::find($id);
        if (!$step || $step->user_id !== Auth::id()) {
            flash('error', 'Follow-up step not found.');
            redirect('/settings/followups');
            return;
        }

        $name = trim($request->input('name', $step->name));
        $rawMessage = trim($request->input('message', $step->message));
        if (strlen($rawMessage) > 10 * 1024 * 1024) {
            flash('error', 'Follow-up message exceeds the maximum allowed size (10 MB).');
            redirect("/settings/followups/{$step->gmail_account_id}");
            return;
        }

        $message = \App\Controllers\AutomationSettingsController::sanitizeRichText($rawMessage);
        $delayValue = max(1, (int)$request->input('delay_value', $step->delay_value));
        $delayUnit = $request->input('delay_unit', $step->delay_unit);
        $status = $request->input('status', 'active');

        $step->update([
            'name' => $name,
            'message' => $message,
            'delay_value' => $delayValue,
            'delay_unit' => $delayUnit,
            'status' => $status,
        ]);

        flash('success', "Follow-up step updated successfully!");
        redirect("/settings/followups/{$step->gmail_account_id}");
    }

    public function delete(Request $request, int $id): void {
        $step = FollowupTemplate::find($id);
        if (!$step || $step->user_id !== Auth::id()) {
            flash('error', 'Follow-up step not found.');
            redirect('/settings/followups');
            return;
        }

        $accountId = $step->gmail_account_id;
        $stepId = $step->id;
        $stepNum = $step->step_number;
        $step->delete();

        // Immediately cancel any pending scheduled jobs for this deleted follow-up step
        \App\Core\Database::execute(
            "UPDATE scheduled_jobs 
             SET status = 'cancelled', last_error = 'Follow-up step was deleted by user' 
             WHERE gmail_account_id = :acc 
               AND job_type = 'follow_up' 
               AND status = 'pending' 
               AND (payload LIKE :tplId OR payload LIKE :stepNum)",
            [
                'acc' => $accountId,
                'tplId' => '%"template_id":' . $stepId . '%',
                'stepNum' => '%"step_number":' . $stepNum . '%',
            ]
        );

        // Re-index remaining steps
        $remaining = FollowupTemplate::findByAccountId($accountId);
        $idx = 1;
        foreach ($remaining as $r) {
            $r->update(['step_number' => $idx++]);
        }

        flash('success', 'Follow-up step deleted successfully and pending jobs cancelled.');
        redirect("/settings/followups/{$accountId}");
    }

    public function deleteAll(Request $request, int $accountId): void {
        $user = Auth::user();
        $account = GmailAccount::find($accountId);
        if (!$account || $account->user_id !== $user->id) {
            flash('error', 'Account not found.');
            redirect('/settings/followups');
            return;
        }

        \App\Core\Database::execute("DELETE FROM followup_templates WHERE gmail_account_id = :acc", ['acc' => $account->id]);
        \App\Core\Database::execute("UPDATE scheduled_jobs SET status = 'cancelled', last_error = 'All follow-up steps deleted by user' WHERE gmail_account_id = :acc AND job_type = 'follow_up' AND status = 'pending'", ['acc' => $account->id]);

        flash('success', 'All follow-up steps deleted successfully and pending jobs cancelled.');
        redirect("/settings/followups/{$accountId}");
    }
}
