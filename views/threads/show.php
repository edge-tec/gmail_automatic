<div class="mb-4">
    <a href="<?= url('/threads') ?>" class="text-decoration-none text-muted small">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Conversation Threads
    </a>
</div>

<!-- Thread Overview Card -->
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1"><?= e($thread->subject) ?></h4>
                <div class="d-flex align-items-center gap-3 text-muted small mt-2">
                    <span><i class="fa-solid fa-user me-1 text-primary"></i> <strong><?= e($thread->sender_name ?: $thread->sender_email) ?></strong> &lt;<?= e($thread->sender_email) ?>&gt;</span>
                    <span><i class="fa-brands fa-google me-1 text-danger"></i> <?= e($account->gmail_email) ?></span>
                    <span class="font-monospace"><i class="fa-solid fa-hashtag me-1"></i> <?= e($thread->gmail_thread_id) ?></span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Status Badge -->
                <?php if ($thread->automation_status === 'active'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="fa-solid fa-bolt me-1"></i> Active Automation
                    </span>
                <?php elseif ($thread->automation_status === 'replied'): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="fa-solid fa-reply me-1"></i> Recipient Replied
                    </span>
                <?php elseif ($thread->automation_status === 'completed'): ?>
                    <span class="badge bg-secondary-subtle text-secondary border px-3 py-2">
                        <i class="fa-solid fa-check-double me-1"></i> Sequence Completed
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                        <i class="fa-solid fa-pause me-1"></i> Automation Stopped
                    </span>
                <?php endif; ?>

                <!-- Manual Stop / Resume Button -->
                <form action="<?= url("/threads/{$thread->id}/toggle-automation") ?>" method="POST">
                    <?= csrf_field() ?>
                    <?php if ($thread->automation_status === 'stopped'): ?>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fa-solid fa-play me-1"></i> Resume Automation
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i class="fa-solid fa-hand me-1"></i> Stop Automation
                        </button>
                    <?php endif; ?>
                </form>

                <!-- Delete Conversation Button -->
                <form action="<?= url("/threads/{$thread->id}/delete") ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this conversation and its history?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Conversation">
                        <i class="fa-solid fa-trash-can me-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-2 mt-3 pt-3 border-top text-center">
            <div class="col-3">
                <div class="small text-muted">Replies Sent</div>
                <div class="fs-5 fw-bold text-primary"><?= $thread->reply_count ?></div>
            </div>
            <div class="col-3">
                <div class="small text-muted">Follow-ups Sent</div>
                <div class="fs-5 fw-bold text-info"><?= $thread->followup_count ?></div>
            </div>
            <div class="col-3">
                <div class="small text-muted">Last Incoming</div>
                <div class="small fw-semibold mt-1"><?= $thread->last_incoming_at ? date('M d, H:i', strtotime($thread->last_incoming_at)) : '—' ?></div>
            </div>
            <div class="col-3">
                <div class="small text-muted">Next Scheduled Action</div>
                <div class="small fw-semibold mt-1 text-warning"><?= $thread->next_followup_at ? date('M d, H:i', strtotime($thread->next_followup_at)) : 'None' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Messages History -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-envelope-open me-2 text-primary"></i> Message History (<?= count($messages) ?>)</span>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                <div class="text-center p-4 text-muted">No messages loaded for this thread.</div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message-bubble <?= $msg->direction === 'incoming' ? 'message-incoming' : 'message-outgoing' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="fw-bold <?= $msg->direction === 'incoming' ? 'text-dark' : 'text-primary' ?>">
                                    <?= $msg->direction === 'incoming' ? '<i class="fa-solid fa-arrow-down-left text-success me-1"></i> From: ' . e($msg->sender) : '<i class="fa-solid fa-robot text-primary me-1"></i> Sent (Automated): ' . e($msg->sender) ?>
                                </span>
                            </div>
                            <div class="small text-muted font-monospace">
                                <?= date('M d, Y H:i', strtotime($msg->received_at ?? $msg->sent_at ?? $msg->created_at)) ?>
                            </div>
                        </div>

                        <div class="small text-pre-wrap" style="white-space: pre-wrap;"><?= e($msg->message_body ?: $msg->snippet) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scheduled Jobs for this thread -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Scheduled Queue Jobs
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingJobs)): ?>
                <div class="text-center p-4 text-muted small">
                    No pending scheduled jobs for this thread.
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingJobs as $job): 
                        $payload = $job->getPayloadArray();
                    ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace text-uppercase">
                                <?= e($job->job_type) ?>
                            </span>
                            <span class="small text-muted font-monospace">
                                <?= date('M d, H:i', strtotime($job->scheduled_at)) ?>
                            </span>
                        </div>
                        <div class="small text-muted mt-2">
                            Target: <?= e($payload['recipient_email'] ?? $thread->sender_email) ?>
                        </div>
                        <?php if (isset($payload['step_number'])): ?>
                        <div class="small text-info fw-semibold">
                            Step #<?= $payload['step_number'] ?> (<?= e($payload['template_name'] ?? 'Follow-up') ?>)
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
