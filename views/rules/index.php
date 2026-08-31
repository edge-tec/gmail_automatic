<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Filter &amp; Routing Rules</h4>
        <p class="text-muted small mb-0">Create conditional rules for incoming emails. If an email matches the condition, you can skip auto-reply or send a targeted custom response.</p>
    </div>
    
    <!-- Account Selector -->
    <div class="dropdown">
        <button class="btn btn-outline-dark dropdown-toggle d-flex align-items-center gap-2 shadow-sm px-3 py-2" type="button" data-bs-toggle="dropdown">
            <i class="fa-solid fa-envelope text-primary"></i>
            <span class="fw-semibold"><?= e($selectedAccount->gmail_email) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <?php foreach ($accounts as $acc): ?>
            <li>
                <a class="dropdown-item d-flex align-items-center justify-content-between py-2 <?= $acc->id === $selectedAccount->id ? 'active' : '' ?>" href="<?= url('/rules/' . $acc->id) ?>">
                    <span><?= e($acc->gmail_email) ?></span>
                    <?php if ($acc->id === $selectedAccount->id): ?>
                    <i class="fa-solid fa-check small ms-2"></i>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Add New Rule Builder -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 sticky-top" style="top: 1rem; z-index: 10;">
            <div class="card-header bg-transparent py-3 d-flex align-items-center gap-2">
                <i class="fa-solid fa-plus-circle text-primary fs-5"></i>
                <h6 class="fw-bold mb-0">Create New Filter Rule</h6>
            </div>
            <div class="card-body">
                <form action="<?= url('/rules/' . $selectedAccount->id . '/create') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">1. Condition (IF)</label>
                        <select name="rule_type" id="rule_type" class="form-select">
                            <option value="sender_contains">Sender Email contains</option>
                            <option value="sender_domain">Sender Domain matches (e.g. company.com)</option>
                            <option value="domain_extension">Domain Extension / TLD matches (e.g. .net, .bi, .xyz)</option>
                            <option value="subject_contains">Email Subject contains</option>
                            <option value="body_contains">Email Body / Content contains</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">2. Value / Keyword to Match</label>
                        <input type="text" name="rule_value" class="form-control" placeholder="e.g. .net, .bi, .xyz, support@, partner.com, pricing" required>
                        <div class="form-text small">Case-insensitive matching will be performed.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">3. Action (THEN)</label>
                        <select name="action" id="rule_action" class="form-select" onchange="toggleActionFields(this.value)">
                            <option value="skip">Skip Auto-Reply &amp; Follow-up (Do Not Reply) 🚫</option>
                            <option value="custom_reply">Send a Specific Custom Reply Message 💬</option>
                        </select>
                    </div>

                    <div id="custom_message_box" class="mb-3" style="display: none;">
                        <label class="form-label small fw-bold">Custom Reply Message</label>
                        <textarea name="custom_message" rows="4" class="form-control" placeholder="Hi {{first_name}},\n\nThanks for inquiring about our pricing..."></textarea>
                        <div class="form-text small">Variables: <code>{{first_name}}</code>, <code>{{name}}</code>, <code>{{subject}}</code></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Filter Rule</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. Active Rules List -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-filter text-primary fs-5"></i>
                    <h6 class="fw-bold mb-0">Active Filter Rules (<?= count($rules) ?>)</h6>
                </div>
                <span class="badge bg-light text-muted border font-monospace"><?= e($selectedAccount->gmail_email) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rules)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-filter-circle-xmark fs-1 text-secondary mb-3 opacity-50"></i>
                    <p class="mb-1 fw-semibold">No filter rules configured yet.</p>
                    <p class="small text-muted mb-0">Use the form on the left to set custom conditions (e.g. skip specific domains or send targeted custom replies).</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th class="ps-3" style="width: 40px;">#</th>
                                <th>Condition</th>
                                <th>Match Pattern</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $idx => $r): ?>
                            <tr>
                                <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                <td>
                                    <?php if ($r->rule_type === 'sender_contains'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-at text-danger me-1"></i> Sender Contains</span>
                                    <?php elseif ($r->rule_type === 'sender_domain'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-globe text-primary me-1"></i> Domain Matches</span>
                                    <?php elseif ($r->rule_type === 'domain_extension'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-network-wired text-success me-1"></i> Extension (.xyz)</span>
                                    <?php elseif ($r->rule_type === 'subject_contains'): ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-heading text-warning me-1"></i> Subject Contains</span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-file-lines text-info me-1"></i> Body Contains</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="text-primary fw-semibold"><?= e($r->rule_value) ?></code>
                                </td>
                                <td>
                                    <?php if ($r->action === 'skip'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-ban me-1"></i> Skip
                                    </span>
                                    <?php elseif ($r->action === 'custom_reply'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle" title="Sends custom response">
                                        <i class="fa-solid fa-comment-dots me-1"></i> Custom Reply
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-reply me-1"></i> Normal Reply
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="<?= url('/rules/' . $r->id . '/toggle') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm p-0 border-0" title="Click to toggle active/inactive">
                                            <?php if ($r->status === 'active'): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fa-solid fa-circle-xmark me-1"></i> Inactive</span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end pe-3">
                                    <form action="<?= url('/rules/' . $r->id . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this filter rule?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Rule">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleActionFields(action) {
    const box = document.getElementById('custom_message_box');
    if (action === 'custom_reply') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}
</script>
