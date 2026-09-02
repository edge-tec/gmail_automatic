<!-- Quill.js Rich Text Editor CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
.followup-page-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.ql-toolbar.ql-snow {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: #f8fafc;
    border-color: #dee2e6;
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    padding: 8px;
}

.ql-container.ql-snow {
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    background: #ffffff;
    border-color: #dee2e6;
    font-size: 0.95rem;
    min-height: 160px;
    max-height: 420px;
    overflow-y: auto;
}

.variable-badge {
    cursor: pointer;
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.15s ease;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    touch-action: manipulation;
}

.variable-badge:hover {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    transform: translateY(-1px);
}

.variable-badge:active {
    transform: translateY(0);
}

/* Responsive message preview */
.followup-message-preview {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.9rem 1.1rem;
    font-size: 0.92rem;
    line-height: 1.65;
    color: #1e293b;
    word-break: break-word;
    overflow-wrap: anywhere;
    max-height: 500px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.followup-message-preview p:last-child {
    margin-bottom: 0;
}

.followup-message-preview img,
.ql-editor img,
.quill-edit-box img {
    max-width: 100% !important;
    height: auto !important;
    max-height: 380px !important;
    object-fit: contain !important;
    border-radius: 8px;
    display: inline-block;
    margin: 8px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
}

.step-card {
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.step-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06) !important;
}

.editor-invalid .ql-toolbar,
.editor-invalid .ql-container {
    border-color: #ef4444 !important;
}

@media (max-width: 575.98px) {
    .followup-page-header {
        padding: 1rem;
    }
    .account-select-wrapper {
        width: 100%;
    }
    .account-select-wrapper select {
        width: 100% !important;
        min-width: 0 !important;
    }
    .step-header-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
        margin-top: 0.25rem;
    }
    .ql-toolbar.ql-snow {
        padding: 4px;
    }
    .ql-snow .ql-picker-label {
        padding-left: 4px;
        padding-right: 4px;
    }
}
</style>

<!-- Top Navigation & Account Selector Bar -->
<div class="followup-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="flex-grow-1">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2 flex-wrap">
                <i class="fa-solid fa-arrows-split-up-and-left text-primary"></i>
                <span>Follow-up Sequence Automation</span>
            </h4>
            <p class="text-muted small mb-0">Create up to 5+ multi-step sequential follow-up emails with rich formatting, links, images, and custom delay times.</p>
        </div>
        <div class="d-flex align-items-center gap-2 account-select-wrapper">
            <span class="small text-muted fw-semibold text-nowrap"><i class="fa-solid fa-user-circle me-1"></i>Account:</span>
            <select class="form-select form-select-sm" style="min-width: 220px;" onchange="location.href = '<?= url('/settings/followups') ?>/' + this.value">
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc->id ?>" <?= $acc->id === $selectedAccount->id ? 'selected' : '' ?>>
                        <?= e($acc->gmail_email) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Follow-up Steps Timeline (Left Side) -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i>Configured Follow-up Sequence
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                        <?= count($steps) ?> / 5+ Steps
                    </span>
                </div>
                <?php if (!empty($steps)): ?>
                <form action="<?= url("/settings/followups/{$selectedAccount->id}/delete-all") ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete ALL follow-up steps for this account? This will cancel all pending follow-up emails.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 py-1 px-2" title="Delete and clear all follow-up steps">
                        <i class="fa-solid fa-trash-can"></i>
                        <span class="small">Delete All Steps</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body p-3 p-sm-4">
                <?php if (empty($steps)): ?>
                <div class="text-center py-5 px-3">
                    <div class="mb-3">
                        <i class="fa-solid fa-diagram-project text-muted opacity-50" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No Follow-up Steps Configured Yet</h5>
                    <p class="text-muted small mx-auto mb-3" style="max-width: 440px;">
                        Add your follow-up sequence using the form on the right. If a lead doesn't reply to your first email, these steps will trigger automatically after each delay.
                    </p>
                    <span class="badge bg-light text-secondary border px-3 py-2">
                        <i class="fa-regular fa-lightbulb text-warning me-1"></i> Pro Tip: Add photos, links, or personalized variables like <code>{{first_name}}</code>
                    </span>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($steps as $step): ?>
                    <div class="timeline-step mb-3">
                        <div class="card shadow-sm border step-card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3 flex-wrap gap-2">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <span class="badge bg-primary text-white font-monospace">Step #<?= $step->step_number ?></span>
                                    <span class="fw-bold text-dark"><?= e($step->name) ?></span>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="fa-regular fa-clock me-1"></i> Delay: <?= $step->delay_value ?> <?= ucfirst(e($step->delay_unit)) ?>
                                    </span>
                                </div>
                                <div class="step-header-actions d-inline-flex gap-1">
                                    <!-- Edit Step Button -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editStepModal<?= $step->id ?>" title="Edit Step #<?= $step->step_number ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span class="d-none d-sm-inline small">Edit</span>
                                    </button>

                                    <!-- Delete Step Form -->
                                    <form action="<?= url("/settings/followups/step/{$step->id}/delete") ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete follow-up Step #<?= $step->step_number ?> (<?= e($step->name) ?>)?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" title="Delete Step #<?= $step->step_number ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                            <span class="d-none d-sm-inline small">Delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div class="followup-message-preview">
                                    <?= $step->message ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal for Step -->
                    <div class="modal fade" id="editStepModal<?= $step->id ?>" tabindex="-1" aria-labelledby="editStepModalLabel<?= $step->id ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                            <div class="modal-content border-0 shadow">
                                <form id="editStepForm<?= $step->id ?>" action="<?= url("/settings/followups/step/{$step->id}/update") ?>" method="POST" onsubmit="return handleEditSubmit(event, <?= $step->id ?>)">
                                    <?= csrf_field() ?>
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title fw-bold text-dark" id="editStepModalLabel<?= $step->id ?>">
                                            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Follow-up Step #<?= $step->step_number ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-3 p-sm-4">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark">Step Name</label>
                                            <input type="text" name="name" class="form-control" value="<?= e($step->name) ?>" required>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small fw-bold text-dark">Delay Value</label>
                                                <input type="number" name="delay_value" class="form-control" min="1" max="999" value="<?= $step->delay_value ?>" required>
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small fw-bold text-dark">Delay Unit</label>
                                                <select name="delay_unit" class="form-select">
                                                    <option value="seconds" <?= $step->delay_unit === 'seconds' ? 'selected' : '' ?>>Seconds</option>
                                                    <option value="minutes" <?= $step->delay_unit === 'minutes' ? 'selected' : '' ?>>Minutes</option>
                                                    <option value="hours" <?= $step->delay_unit === 'hours' ? 'selected' : '' ?>>Hours</option>
                                                    <option value="days" <?= $step->delay_unit === 'days' ? 'selected' : '' ?>>Days</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                                                <label class="form-label small fw-bold text-dark m-0">Follow-up Message Template</label>
                                                <span class="small text-muted" style="font-size: 0.75rem;">(Links 🔗 & Images 🖼️ Supported)</span>
                                            </div>

                                            <!-- Variable Badges for Edit Modal -->
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <span class="variable-badge modal-var" data-step-id="<?= $step->id ?>" data-variable="{{first_name}}"><i class="fa-solid fa-plus"></i>{{first_name}}</span>
                                                <span class="variable-badge modal-var" data-step-id="<?= $step->id ?>" data-variable="{{last_name}}"><i class="fa-solid fa-plus"></i>{{last_name}}</span>
                                                <span class="variable-badge modal-var" data-step-id="<?= $step->id ?>" data-variable="{{subject}}"><i class="fa-solid fa-plus"></i>{{subject}}</span>
                                                <span class="variable-badge modal-var" data-step-id="<?= $step->id ?>" data-variable="{{date}}"><i class="fa-solid fa-plus"></i>{{date}}</span>
                                            </div>

                                            <div id="quill_edit_wrap_<?= $step->id ?>">
                                                <div id="quill_edit_<?= $step->id ?>" class="quill-edit-box">
                                                    <?= $step->message ?>
                                                </div>
                                            </div>
                                            <input type="hidden" name="message" id="hidden_edit_msg_<?= $step->id ?>">
                                            <div id="edit_error_<?= $step->id ?>" class="text-danger small mt-1 d-none">
                                                <i class="fa-solid fa-circle-exclamation me-1"></i> Please enter a message or insert an image.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                            <span>Save Changes</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add New Step Form (Right Side) -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 sticky-lg-top" style="top: 1.5rem; z-index: 10;">
            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-plus-circle me-2 text-primary"></i>Add Follow-up Step #<?= count($steps) + 1 ?>
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                    Next Step #<?= count($steps) + 1 ?>
                </span>
            </div>
            <div class="card-body p-3 p-sm-4">
                <form id="createStepForm" action="<?= url("/settings/followups/{$selectedAccount->id}/create") ?>" method="POST" onsubmit="return handleCreateSubmit(event)">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Step Name</label>
                        <input type="text" name="name" class="form-control" value="Follow-up #<?= count($steps) + 1 ?>" required placeholder="e.g. Friendly Reminder #<?= count($steps) + 1 ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-dark">Delay Value</label>
                            <input type="number" name="delay_value" class="form-control" min="1" max="999" value="2" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-dark">Delay Unit</label>
                            <select name="delay_unit" class="form-select">
                                <option value="seconds">Seconds</option>
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                            <label class="form-label small fw-bold text-dark m-0">Follow-up Message Template</label>
                            <span class="small text-muted" style="font-size: 0.75rem;">(Link 🔗 & Image 🖼️ Supported)</span>
                        </div>
                        
                        <!-- Variable helper buttons -->
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="variable-badge fu-var" data-variable="{{first_name}}"><i class="fa-solid fa-plus"></i>{{first_name}}</span>
                            <span class="variable-badge fu-var" data-variable="{{last_name}}"><i class="fa-solid fa-plus"></i>{{last_name}}</span>
                            <span class="variable-badge fu-var" data-variable="{{subject}}"><i class="fa-solid fa-plus"></i>{{subject}}</span>
                            <span class="variable-badge fu-var" data-variable="{{date}}"><i class="fa-solid fa-plus"></i>{{date}}</span>
                        </div>
                        
                        <!-- Quill Create Box -->
                        <div id="quill_create_wrap">
                            <div id="quill_create_step" style="min-height: 160px;"></div>
                        </div>
                        <input type="hidden" name="message" id="hidden_create_msg">
                        <div id="create_error_msg" class="text-danger small mt-1 d-none">
                            <i class="fa-solid fa-circle-exclamation me-1"></i> Follow-up message cannot be empty. Please enter your text or insert an image.
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-3 border-0 bg-info-subtle text-info-emphasis rounded-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Smart Stop:</strong> If the recipient replies at any time, all pending follow-up steps for that conversation are cancelled immediately!
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="createSubmitBtn" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Step to Sequence</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Quill.js Rich Text Editor Script -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
let createQuill = null;
const editQuills = {};

function isQuillEmpty(quill) {
    if (!quill) return true;
    const text = quill.getText().trim();
    if (text.length > 0) return false;
    // Check if there are embedded media elements like img, svg, video, etc.
    const media = quill.root.querySelector('img, svg, video, audio, iframe, table');
    return media === null;
}

document.addEventListener('DOMContentLoaded', function() {
    const toolbarOptions = [
        ['bold', 'italic', 'underline'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'header': [1, 2, 3, false] }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        ['link', 'image'],
        ['clean']
    ];

    // Initialize create step Quill
    const createBox = document.getElementById('quill_create_step');
    if (createBox) {
        createQuill = new Quill(createBox, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions },
            placeholder: 'Compose follow-up message with links and images...'
        });

        createQuill.on('text-change', function() {
            const errorEl = document.getElementById('create_error_msg');
            const wrapEl = document.getElementById('quill_create_wrap');
            if (!isQuillEmpty(createQuill)) {
                if (errorEl) errorEl.classList.add('d-none');
                if (wrapEl) wrapEl.classList.remove('editor-invalid');
            }
        });
    }

    // Initialize edit modals Quill
    document.querySelectorAll('.quill-edit-box').forEach(el => {
        const id = el.id.replace('quill_edit_', '');
        const quill = new Quill(el, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions },
            placeholder: 'Edit follow-up message...'
        });
        editQuills[id] = quill;

        quill.on('text-change', function() {
            const errorEl = document.getElementById('edit_error_' + id);
            const wrapEl = document.getElementById('quill_edit_wrap_' + id);
            if (!isQuillEmpty(quill)) {
                if (errorEl) errorEl.classList.add('d-none');
                if (wrapEl) wrapEl.classList.remove('editor-invalid');
            }
        });
    });

    // Variable click insertion for create editor
    document.querySelectorAll('.fu-var').forEach(badge => {
        badge.addEventListener('click', function(e) {
            e.preventDefault();
            const variable = this.getAttribute('data-variable');
            if (createQuill) {
                const range = createQuill.getSelection(true);
                const pos = range ? range.index : createQuill.getLength();
                createQuill.insertText(pos, variable);
                createQuill.setSelection(pos + variable.length);
                createQuill.focus();
            }
        });
    });

    // Variable click insertion for edit modal editors
    document.querySelectorAll('.modal-var').forEach(badge => {
        badge.addEventListener('click', function(e) {
            e.preventDefault();
            const variable = this.getAttribute('data-variable');
            const stepId = this.getAttribute('data-step-id');
            const quill = editQuills[stepId];
            if (quill) {
                const range = quill.getSelection(true);
                const pos = range ? range.index : quill.getLength();
                quill.insertText(pos, variable);
                quill.setSelection(pos + variable.length);
                quill.focus();
            }
        });
    });
});

function handleCreateSubmit(e) {
    const errorEl = document.getElementById('create_error_msg');
    const wrapEl = document.getElementById('quill_create_wrap');
    const hiddenMsg = document.getElementById('hidden_create_msg');

    if (!createQuill || isQuillEmpty(createQuill)) {
        e.preventDefault();
        if (errorEl) errorEl.classList.remove('d-none');
        if (wrapEl) wrapEl.classList.add('editor-invalid');
        if (createQuill) createQuill.focus();
        return false;
    }

    if (errorEl) errorEl.classList.add('d-none');
    if (wrapEl) wrapEl.classList.remove('editor-invalid');
    if (hiddenMsg) {
        hiddenMsg.value = createQuill.root.innerHTML;
    }

    const btn = document.getElementById('createSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Adding Step...';
    }
    return true;
}

function handleEditSubmit(e, stepId) {
    const quill = editQuills[stepId];
    const hidden = document.getElementById('hidden_edit_msg_' + stepId);
    const errorEl = document.getElementById('edit_error_' + stepId);
    const wrapEl = document.getElementById('quill_edit_wrap_' + stepId);

    if (!quill || isQuillEmpty(quill)) {
        e.preventDefault();
        if (errorEl) errorEl.classList.remove('d-none');
        if (wrapEl) wrapEl.classList.add('editor-invalid');
        if (quill) quill.focus();
        return false;
    }

    if (errorEl) errorEl.classList.add('d-none');
    if (wrapEl) wrapEl.classList.remove('editor-invalid');
    if (hidden) {
        hidden.value = quill.root.innerHTML;
    }
    return true;
}
</script>
