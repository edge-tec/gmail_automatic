<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\GmailAccount;
use App\Models\ScheduledJob;

class ThreadController {
    public function index(Request $request): string {
        $user = Auth::user();
        $status = $request->input('status');
        $threads = EmailThread::findByUserId($user->id, 50, $status);
        $accounts = GmailAccount::findByUserId($user->id);

        return View::render('threads/index', [
            'threads' => $threads,
            'accounts' => $accounts,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, int $id): string {
        $thread = EmailThread::find($id);
        if (!$thread) {
            flash('error', 'Conversation thread not found.');
            redirect('/threads');
        }

        $sourceMailbox = $thread->getSourceMailbox();
        $account = $sourceMailbox ?: GmailAccount::find($thread->gmail_account_id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Unauthorized.');
            redirect('/threads');
        }

        $messages = EmailMessage::findByThreadId($thread->id);
        $pendingJobs = ScheduledJob::findPendingByThreadId($thread->id);

        return View::render('threads/show', [
            'thread' => $thread,
            'account' => $account,
            'sourceMailbox' => $sourceMailbox,
            'messages' => $messages,
            'pendingJobs' => $pendingJobs,
        ]);
    }

    public function reply(Request $request, int $id): void {
        $thread = EmailThread::find($id);
        if (!$thread) {
            flash('error', 'Conversation thread not found.');
            redirect('/threads');
            return;
        }

        $validation = \App\Services\MailboxRoutingService::validateAndResolveForSending($thread->id);
        if (!$validation['allowed']) {
            flash('error', $validation['reason']);
            redirect("/threads/{$thread->id}");
            return;
        }

        /** @var GmailAccount $account */
        $account = $validation['account'];
        if ($account->user_id !== Auth::id()) {
            flash('error', 'Unauthorized.');
            redirect('/threads');
            return;
        }

        $body = trim((string)$request->input('body', ''));
        if (empty($body)) {
            flash('error', 'Reply message cannot be empty.');
            redirect("/threads/{$thread->id}");
            return;
        }

        try {
            $messages = EmailMessage::findByThreadId($thread->id);
            $lastMsg = !empty($messages) ? end($messages) : null;
            $inReplyTo = $lastMsg ? $lastMsg->gmail_message_id : null;

            $gmailService = new \App\Services\GmailService($account);
            $sent = $gmailService->sendThreadReply(
                $thread->sender_email,
                $thread->subject,
                $body,
                $thread->gmail_thread_id,
                $inReplyTo,
                $inReplyTo
            );

            $sentMessageId = $sent['id'];
            $sentAt = date('Y-m-d H:i:s');

            EmailMessage::create([
                'thread_id' => $thread->id,
                'gmail_account_id' => $account->id,
                'source_mailbox_id' => $account->id,
                'gmail_message_id' => $sentMessageId,
                'direction' => 'outgoing',
                'sender' => $account->gmail_email,
                'recipient' => $thread->sender_email,
                'subject' => 'Re: ' . preg_replace('/^Re:\s*/i', '', $thread->subject),
                'snippet' => substr(strip_tags($body), 0, 150),
                'message_body' => $body,
                'sent_at' => $sentAt,
                'status' => 'sent',
            ]);

            \App\Services\MailboxRoutingService::logOutgoingAttempt([
                'thread_id' => $thread->id,
                'job_id' => null,
                'attempted_mailbox_id' => $account->id,
                'source_mailbox_id' => $thread->source_mailbox_id,
                'recipient_email' => $thread->sender_email,
                'sender_email' => $account->gmail_email,
                'status' => 'sent',
                'reason' => "Manual reply sent via {$account->gmail_email}",
            ]);

            $thread->update([
                'reply_count' => $thread->reply_count + 1,
                'last_outgoing_at' => $sentAt,
            ]);

            flash('success', "Manual reply sent successfully from {$account->gmail_email}.");
        } catch (\Throwable $e) {
            flash('error', 'Failed to send reply: ' . $e->getMessage());
        }

        redirect("/threads/{$thread->id}");
    }

    public function toggleAutomation(Request $request, int $id): void {
        $thread = EmailThread::find($id);
        if (!$thread) {
            flash('error', 'Thread not found.');
            redirect('/threads');
            return;
        }

        $account = GmailAccount::find($thread->gmail_account_id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Unauthorized.');
            redirect('/threads');
            return;
        }

        if ($thread->automation_status === 'stopped') {
            $thread->update(['automation_status' => 'active']);
            flash('success', 'Automation resumed for this conversation.');
        } else {
            $thread->update(['automation_status' => 'stopped', 'next_followup_at' => null]);
            ScheduledJob::cancelPendingJobsForThread($thread->id, 'Manually stopped by user');
            flash('success', 'Automation stopped for this conversation and pending jobs cancelled.');
        }

        redirect("/threads/{$thread->id}");
    }

    public function delete(Request $request, int $id): void {
        $thread = EmailThread::find($id);
        if (!$thread) {
            flash('error', 'Thread not found.');
            redirect('/threads');
            return;
        }

        $account = GmailAccount::find($thread->gmail_account_id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Unauthorized.');
            redirect('/threads');
            return;
        }

        $subject = $thread->subject;
        $thread->delete();

        flash('success', "Conversation [{$subject}] and its messages deleted successfully.");
        redirect('/threads');
    }

    public function clearAll(Request $request): void {
        $user = Auth::user();
        $accountId = $request->input('account_id') ? (int)$request->input('account_id') : null;

        if ($accountId) {
            $account = GmailAccount::find($accountId);
            if (!$account || $account->user_id !== $user->id) {
                flash('error', 'Invalid account selected.');
                redirect('/threads');
                return;
            }
        }

        $count = EmailThread::deleteAllByUserId($user->id, $accountId);

        if ($count > 0) {
            logger("User cleared {$count} conversation thread(s)", 'info', $user->id, $accountId);
            flash('success', "Successfully cleared {$count} email conversation thread(s), messages, and scheduled tasks.");
        } else {
            flash('info', 'No conversation threads found to clear.');
        }

        redirect('/threads');
    }
}
