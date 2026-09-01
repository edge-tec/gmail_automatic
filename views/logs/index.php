<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Reply & Follow-up Logs</h4>
        <p class="text-muted small mb-0">Monitor real-time execution logs for automated replies, sequence follow-ups, and Gmail syncs.</p>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (!empty($logs)): ?>
        <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
            <i class="fa-solid fa-trash-can"></i>
            <span>Clear Logs</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="btn-group btn-group-sm flex-wrap">
                <a href="<?= url('/logs' . ($selectedAccountId ? "?account_id={$selectedAccountId}" : '')) ?>" class="btn <?= empty($selectedType) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    All Logs
                </a>
                <a href="<?= url('/logs?type=reply' . ($selectedAccountId ? "&account_id={$selectedAccountId}" : '')) ?>" class="btn <?= $selectedType === 'reply' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="fa-solid fa-reply me-1"></i> Auto Replies
                </a>
                <a href="<?= url('/logs?type=followup' . ($selectedAccountId ? "&account_id={$selectedAccountId}" : '')) ?>" class="btn <?= $selectedType === 'followup' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="fa-solid fa-share me-1"></i> Follow-ups
                </a>
                <a href="<?= url('/logs?type=error' . ($selectedAccountId ? "&account_id={$selectedAccountId}" : '')) ?>" class="btn <?= $selectedType === 'error' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Errors
                </a>
                <a href="<?= url('/logs?type=success' . ($selectedAccountId ? "&account_id={$selectedAccountId}" : '')) ?>" class="btn <?= $selectedType === 'success' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="fa-solid fa-circle-check me-1"></i> Success
                </a>
            </div>

            <?php if (!empty($accounts)): ?>
            <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-md-auto">
                <span class="small text-muted fw-semibold">Filter Account:</span>
                <select class="form-select form-select-sm" style="min-width: 180px; flex: 1;" onchange="location.href = '<?= url('/logs') ?>?<?= $selectedType ? "type={$selectedType}&" : '' ?>account_id=' + this.value">
                    <option value="">All Accounts</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc->id ?>" <?= $selectedAccountId == $acc->id ? 'selected' : '' ?>>
                            <?= e($acc->gmail_email) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Logs Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center p-5 text-muted">
            <i class="fa-solid fa-list-check fs-1 opacity-50 mb-3"></i>
            <h5 class="fw-bold">No Activity Logs Found</h5>
            <p class="small text-muted mb-0">Logs will appear here automatically when emails are received and replies/follow-ups are processed.</p>
        </div>
        <?php else: ?>
        <!-- Mobile Card List View (< 768px) -->
        <div class="d-md-none divide-y">
            <?php foreach ($logs as $log): 
                $msgLower = strtolower($log['message']);
                $isReply = str_contains($msgLower, 'reply') || str_contains($msgLower, 'auto-reply');
                $isFollowup = str_contains($msgLower, 'follow-up') || str_contains($msgLower, 'followup');
                $rawMessage = $log['message'];
                if (str_starts_with($rawMessage, 'Saved reply_steps')) {
                    $displayMsg = 'Updated auto-reply message sequence settings';
                } else {
                    $displayMsg = $rawMessage;
                }
            ?>
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <?php if ($log['log_type'] === 'error'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i> Error</span>
                        <?php elseif ($isFollowup): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-share me-1"></i> Follow-up</span>
                        <?php elseif ($isReply): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-reply me-1"></i> Auto Reply</span>
                        <?php elseif ($log['log_type'] === 'success'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Success</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-circle-info me-1"></i> Info</span>
                        <?php endif; ?>
                    </div>
                    <span class="font-monospace text-muted small" style="font-size: 0.75rem;">
                        <i class="fa-regular fa-clock me-1"></i><?= date('M d, H:i', strtotime($log['created_at'])) ?>
                    </span>
                </div>
                <div class="fw-semibold text-dark mb-2" style="font-size: 0.88rem; line-height: 1.5;">
                    <?= e($displayMsg) ?>
                </div>
                <?php if (!empty($log['gmail_email'])): ?>
                <div class="d-flex align-items-center gap-2 small text-muted">
                    <span class="badge bg-light text-dark border font-monospace"><i class="fa-brands fa-google text-danger me-1"></i> <?= e($log['gmail_email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($log['context_json'])): ?>
                    <div class="text-muted font-monospace small mt-2 p-2 bg-light rounded" style="font-size: 0.72rem; word-break: break-all;">
                        <?= e($log['context_json']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Desktop Table View (>= 768px) -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem; min-width: 600px;">
                <thead class="table-light text-muted small">
                    <tr>
                        <th style="width: 120px;">Event Type</th>
                        <th style="min-width: 250px;">Activity Details</th>
                        <th style="width: 180px;">Gmail Account</th>
                        <th class="text-end" style="width: 180px;">Time (Exact)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $msgLower = strtolower($log['message']);
                        $isReply = str_contains($msgLower, 'reply') || str_contains($msgLower, 'auto-reply');
                        $isFollowup = str_contains($msgLower, 'follow-up') || str_contains($msgLower, 'followup');
                    ?>
                    <tr>
                        <td>
                            <?php if ($log['log_type'] === 'error'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i> Error</span>
                            <?php elseif ($isFollowup): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-share me-1"></i> Follow-up</span>
                            <?php elseif ($isReply): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-reply me-1"></i> Auto Reply</span>
                            <?php elseif ($log['log_type'] === 'success'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i> Success</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-circle-info me-1"></i> Info</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                $rawMessage = $log['message'];
                                if (str_starts_with($rawMessage, 'Saved reply_steps')) {
                                    $displayMsg = 'Updated auto-reply message sequence settings';
                                } else {
                                    $displayMsg = $rawMessage;
                                }
                                $isLong = mb_strlen($displayMsg) > 160;
                            ?>
                            <div class="fw-semibold text-dark text-break" style="line-height: 1.5;">
                                <?php if ($isLong): ?>
                                    <span id="log_short_<?= $log['id'] ?>"><?= e(mb_substr($displayMsg, 0, 160)) ?>...</span>
                                    <span id="log_full_<?= $log['id'] ?>" class="d-none"><?= e($displayMsg) ?></span>
                                    <a href="javascript:void(0)" class="small text-primary text-decoration-none ms-1" onclick="document.getElementById('log_short_<?= $log['id'] ?>').classList.toggle('d-none'); document.getElementById('log_full_<?= $log['id'] ?>').classList.toggle('d-none'); this.textContent = this.textContent === 'Show more' ? 'Show less' : 'Show more';">Show more</a>
                                <?php else: ?>
                                    <?= e($displayMsg) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($log['context_json'])): ?>
                                <div class="text-muted font-monospace small mt-1" style="font-size: 0.75rem; word-break: break-all;"><?= e($log['context_json']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($log['gmail_email'])): ?>
                                <span class="badge bg-light text-dark border font-monospace"><i class="fa-brands fa-google text-danger me-1"></i> <?= e($log['gmail_email']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end font-monospace text-muted small">
                            <i class="fa-regular fa-clock me-1 text-secondary"></i>
                            <?= date('M d, Y - h:i A', strtotime($log['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= url('/logs/clear') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger" id="clearLogsModalLabel">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Clear Activity Logs
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Are you sure you want to clear your activity and automation execution logs?</p>

                    <?php if (!empty($accounts) && count($accounts) > 1): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Account to Clear</label>
                        <select name="account_id" class="form-select">
                            <option value="">All Accounts (Clear Everything)</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc->id ?>"><?= e($acc->gmail_email) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger d-flex align-items-center gap-1">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Confirm & Clear Logs</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
