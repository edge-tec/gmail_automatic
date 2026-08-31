<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Follow-up Sequence Automation</h4>
        <p class="text-muted small mb-0">Create multi-step follow-up emails that trigger automatically until the recipient replies.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="small text-muted fw-semibold">Account:</span>
        <select class="form-select form-select-sm" style="min-width: 220px;" onchange="location.href = '<?= url('/settings/followups/') ?>/' + this.value">
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc->id ?>" <?= $acc->id === $selectedAccount->id ? 'selected' : '' ?>>
                    <?= e($acc->gmail_email) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="row g-4">
    <!-- Follow-up Steps Timeline -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-arrows-split-up-and-left me-2 text-primary"></i> Configured Follow-up Sequence</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($steps) ?> Step(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($steps)): ?>
                <div class="text-center p-4">
                    <i class="fa-solid fa-diagram-project fs-1 text-muted opacity-50 mb-2"></i>
                    <p class="text-muted mb-2">No follow-up steps configured for this account.</p>
                    <p class="text-muted small">Add your first follow-up step using the form on the right.</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($steps as $step): ?>
                    <div class="timeline-step">
                        <div class="timeline-number"><?= $step->step_number ?></div>
                        <div class="card shadow-sm border">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <span class="fw-bold"><?= e($step->name) ?></span>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-2">
                                        Delay: <?= $step->delay_value ?> <?= e($step->delay_unit) ?>
                                    </span>
                                </div>
                                <div>
                                    <form action="<?= url("/settings/followups/step/{$step->id}/delete") ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this follow-up step?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete Step">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body py-3">
                                <div class="bg-light p-3 rounded-2 font-monospace small text-pre-wrap" style="white-space: pre-wrap;"><?= e($step->message) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add New Step Form -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="fa-solid fa-plus me-2 text-primary"></i> Add Follow-up Step #<?= count($steps) + 1 ?>
            </div>
            <div class="card-body">
                <form action="<?= url("/settings/followups/{$selectedAccount->id}/create") ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Step Name</label>
                        <input type="text" name="name" class="form-control" value="Follow-up #<?= count($steps) + 1 ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Delay Value</label>
                            <input type="number" name="delay_value" class="form-control" min="1" max="365" value="2" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Delay Unit</label>
                            <select name="delay_unit" class="form-select">
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Follow-up Message Template</label>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="variable-badge" data-variable="{{first_name}}" data-target="fu_message"><i class="fa-solid fa-plus me-1"></i>{{first_name}}</span>
                            <span class="variable-badge" data-variable="{{subject}}" data-target="fu_message"><i class="fa-solid fa-plus me-1"></i>{{subject}}</span>
                            <span class="variable-badge" data-variable="{{date}}" data-target="fu_message"><i class="fa-solid fa-plus me-1"></i>{{date}}</span>
                        </div>
                        <textarea name="message" id="fu_message" rows="6" class="form-control font-monospace" style="font-size: 0.9rem;" placeholder="Hi {{first_name}}, just following up on my previous email regarding {{subject}}..." required></textarea>
                    </div>

                    <div class="alert alert-info py-2 px-3 small">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>Smart Stop:</strong> If the recipient replies at any time, all pending follow-up steps are cancelled automatically!
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="fa-solid fa-plus me-1"></i> Add Step to Sequence
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
