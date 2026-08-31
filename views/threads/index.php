<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Conversation Threads</h4>
        <p class="text-muted small mb-0">Track all incoming conversations, reply counts, follow-up progression, and thread statuses.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="btn-group btn-group-sm">
            <a href="<?= url('/threads') ?>" class="btn <?= empty($selectedStatus) ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
            <a href="<?= url('/threads?status=active') ?>" class="btn <?= $selectedStatus === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>">Active</a>
            <a href="<?= url('/threads?status=replied') ?>" class="btn <?= $selectedStatus === 'replied' ? 'btn-primary' : 'btn-outline-secondary' ?>">Replied</a>
            <a href="<?= url('/threads?status=stopped') ?>" class="btn <?= $selectedStatus === 'stopped' ? 'btn-primary' : 'btn-outline-secondary' ?>">Stopped</a>
        </div>

        <?php if (!empty($threads)): ?>
        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#clearAllModal">
            <i class="fa-solid fa-trash-can"></i>
            <span>Clear Email List</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0">
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
                        <th class="text-end" style="width: 170px;">Actions</th>
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
                            <div class="d-inline-flex gap-1">
                                <a href="<?= url("/threads/{$t['id']}") ?>" class="btn btn-sm btn-primary" title="View Thread">
                                    <i class="fa-solid fa-eye me-1"></i> View
                                </a>

                                <form action="<?= url("/threads/{$t['id']}/delete") ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Conversation" onclick="return confirm('Delete conversation with <?= e($t['sender_email']) ?>?')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Clear Email List Modal -->
<div class="modal fade" id="clearAllModal" tabindex="-1" aria-labelledby="clearAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/threads/clear-all') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger" id="clearAllModalLabel">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Clear Email List
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Are you sure you want to clear conversation threads? This will remove thread records, message history, and cancel any pending automated replies.</p>

                    <?php if (!empty($accounts) && count($accounts) > 1): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Account to Clear</label>
                        <select name="account_id" class="form-select">
                            <option value="">All Connected Accounts (Everything)</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc->id ?>"><?= e($acc->gmail_email) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-warning small mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i> Note: This only clears local application database records. Your original emails in Gmail will not be touched.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger d-flex align-items-center gap-1">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Confirm & Clear</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
