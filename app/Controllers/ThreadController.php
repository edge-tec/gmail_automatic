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

        $account = GmailAccount::find($thread->gmail_account_id);
        if (!$account || $account->user_id !== Auth::id()) {
            flash('error', 'Unauthorized.');
            redirect('/threads');
        }

        $messages = EmailMessage::findByThreadId($thread->id);
        $pendingJobs = ScheduledJob::findPendingByThreadId($thread->id);

        return View::render('threads/show', [
            'thread' => $thread,
            'account' => $account,
            'messages' => $messages,
            'pendingJobs' => $pendingJobs,
        ]);
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
