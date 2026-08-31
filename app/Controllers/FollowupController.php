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
            if (!$selectedAccount || $selectedAccount->user_id !== $user->id) {
                $selectedAccount = null;
            }
        }

        if (!$selectedAccount) {
            $selectedAccount = $accounts[0];
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
        $message = trim($request->input('message', ''));
        $delayValue = max(1, (int)$request->input('delay_value', 2));
        $delayUnit = $request->input('delay_unit', 'days');

        if (empty($message)) {
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
        $message = trim($request->input('message', $step->message));
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
        $step->delete();

        // Re-index remaining steps
        $remaining = FollowupTemplate::findByAccountId($accountId);
        $idx = 1;
        foreach ($remaining as $r) {
            $r->update(['step_number' => $idx++]);
        }

        flash('success', 'Follow-up step deleted successfully.');
        redirect("/settings/followups/{$accountId}");
    }
}
