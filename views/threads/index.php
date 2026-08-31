<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Conversation Threads</h4>
        <p class="text-muted small mb-0">Track all incoming conversations, reply counts, follow-up progression, and thread statuses.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/threads') ?>" class="btn btn-sm <?= empty($selectedStatus) ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <a href="<?= url('/threads?status=active') ?>" class="btn btn-sm <?= $selectedStatus === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>">Active</a>
        <a href="<?= url('/threads?status=replied') ?>" class="btn btn-sm <?= $selectedStatus === 'replied' ? 'btn-primary' : 'btn-outline-secondary' ?>">Replied</a>
        <a href="<?= url('/threads?status=stopped') ?>" class="btn btn-sm <?= $selectedStatus === 'stopped' ? 'btn-primary' : 'btn-outline-secondary' ?>">Stopped</a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($threads)): ?>
        <div class="text-center p-5">
            <i class="fa-solid fa-comments fs-1 text-muted opacity-50 mb-3"></i>
            <h5 class="fw-bold">No Conversation Threads Found</h5>
            <p class="text-muted small">New emails received by your connected Gmail accounts will appear here automatically.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th>Contact / Sender</th>
                        <th>Subject</th>
                        <th>Gmail Account</th>
                        <th>Replies Sent</th>
                        <th>Follow-ups</th>
                        <th>Next Follow-up</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $t): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-truncate" style="max-width: 180px;"><?= e($t['sender_name'] ?: $t['sender_email']) ?></div>
                            <div class="text-muted small text-truncate" style="max-width: 180px;"><?= e($t['sender_email']) ?></div>
                        </td>
                        <td>
                            <div class="fw-medium text-truncate" style="max-width: 260px;"><?= e($t['subject']) ?></div>
                            <div class="text-muted font-monospace" style="font-size: 0.75rem;">Thread ID: <?= e(substr($t['gmail_thread_id'], 0, 16)) ?>...</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= e($t['gmail_email']) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $t['reply_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $t['followup_count'] ?></span>
                        </td>
                        <td>
                            <?php if ($t['next_followup_at']): ?>
                                <span class="small text-muted font-monospace"><i class="fa-regular fa-clock me-1 text-info"></i><?= date('M d, H:i', strtotime($t['next_followup_at'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['automation_status'] === 'active'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php elseif ($t['automation_status'] === 'replied'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-reply me-1"></i>Replied</span>
                            <?php elseif ($t['automation_status'] === 'completed'): ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Completed</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Stopped</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url("/threads/{$t['id']}") ?>" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-eye me-1"></i> View Thread
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
