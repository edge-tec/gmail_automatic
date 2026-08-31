<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\GmailAccount;
use App\Models\AutomationRule;
use App\Models\ReplyTemplate;

class RuleController {
    public function index(Request $request, ?int $accountId = null): string {
        $userId = Auth::id();
        $accounts = GmailAccount::findByUserId($userId);

        if (empty($accounts)) {
            flash('warning', 'Please connect a Gmail account first to configure custom filter rules.');
            redirect('/accounts');
        }

        $selectedAccount = null;
        if ($accountId) {
            $selectedAccount = GmailAccount::find($accountId);
        }

        if (!$selectedAccount || $selectedAccount->user_id !== $userId) {
            $selectedAccount = $accounts[0];
        }

        $rules = AutomationRule::findByAccountIdAll($selectedAccount->id);
        $templates = ReplyTemplate::findByUserId($userId);

        return View::render('rules/index', [
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'rules' => $rules,
            'templates' => $templates,
        ]);
    }

    public function create(Request $request, int $accountId): void {
        $userId = Auth::id();
        $account = GmailAccount::find($accountId);

        if (!$account || $account->user_id !== $userId) {
            flash('error', 'Account not found.');
            redirect('/rules');
            return;
        }

        $ruleType = trim($request->input('rule_type', 'sender_contains'));
        $ruleValue = trim($request->input('rule_value', ''));
        $action = trim($request->input('action', 'skip'));
        $templateId = (int)$request->input('template_id', 0);
        $customMessage = trim($request->input('custom_message', ''));

        if (empty($ruleValue)) {
            flash('error', 'Please enter a keyword, email, or domain for this filter rule.');
            redirect("/rules/{$account->id}");
            return;
        }

        if (!in_array($ruleType, ['sender_contains', 'sender_domain', 'subject_contains', 'body_contains'])) {
            $ruleType = 'sender_contains';
        }

        if (!in_array($action, ['skip', 'custom_reply', 'reply'])) {
            $action = 'skip';
        }

        // If custom reply action and user entered a new message, create a reply template
        if ($action === 'custom_reply') {
            if ($templateId > 0) {
                $tpl = ReplyTemplate::find($templateId);
                if (!$tpl || $tpl->user_id !== $userId) {
                    $templateId = 0;
                }
            }

            if (!$templateId && !empty($customMessage)) {
                $newTpl = ReplyTemplate::create([
                    'user_id' => $userId,
                    'name' => 'Custom Reply: ' . mb_substr($ruleValue, 0, 30),
                    'message' => $customMessage,
                    'status' => 'active',
                ]);
                $templateId = $newTpl->id;
            }

            if (!$templateId) {
                flash('error', 'Please provide a custom reply message or select a saved template.');
                redirect("/rules/{$account->id}");
                return;
            }
        } else {
            $templateId = null;
        }

        AutomationRule::create([
            'user_id' => $userId,
            'gmail_account_id' => $account->id,
            'rule_type' => $ruleType,
            'rule_value' => $ruleValue,
            'template_id' => $templateId,
            'action' => $action,
            'status' => 'active',
        ]);

        logger("Created automation filter rule [{$ruleType}: {$ruleValue} -> {$action}]", 'info', $userId, $account->id);
        flash('success', "Filter rule added successfully for {$account->gmail_email}!");
        redirect("/rules/{$account->id}");
    }

    public function toggle(Request $request, int $id): void {
        $userId = Auth::id();
        $rule = AutomationRule::find($id);

        if (!$rule || $rule->user_id !== $userId) {
            flash('error', 'Rule not found.');
            redirect('/rules');
            return;
        }

        $newStatus = $rule->status === 'active' ? 'inactive' : 'active';
        $rule->update(['status' => $newStatus]);

        flash('success', "Filter rule status set to {$newStatus}.");
        redirect("/rules/{$rule->gmail_account_id}");
    }

    public function delete(Request $request, int $id): void {
        $userId = Auth::id();
        $rule = AutomationRule::find($id);

        if (!$rule || $rule->user_id !== $userId) {
            flash('error', 'Rule not found.');
            redirect('/rules');
            return;
        }

        $accId = $rule->gmail_account_id;
        $rule->delete();

        flash('success', 'Filter rule deleted successfully.');
        redirect("/rules/{$accId}");
    }
}
