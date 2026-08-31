<!-- Quill.js Rich Text Editor CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
.ql-toolbar.ql-snow {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: #f8fafc;
    border-color: #dee2e6;
}
.ql-container.ql-snow {
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    background: #ffffff;
    border-color: #dee2e6;
    font-size: 0.92rem;
    min-height: 140px;
}
.variable-badge {
    cursor: pointer;
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.15s ease;
    user-select: none;
}
.variable-badge:hover {
    background: #4f46e5;
    color: #ffffff;
    transform: translateY(-1px);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Follow-up Sequence Automation</h4>
        <p class="text-muted small mb-0">Create up to 5+ multi-step follow-up emails with rich formatting, links, images, and custom delay times.</p>
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
    <!-- Follow-up Steps Timeline (Left Side) -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold">
                    <i class="fa-solid fa-arrows-split-up-and-left me-2 text-primary"></i> Configured Follow-up Sequence
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    <?= count($steps) ?> / 5+ Steps Configured
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($steps)): ?>
                <div class="text-center p-5">
                    <i class="fa-solid fa-diagram-project fs-1 text-muted opacity-50 mb-3"></i>
                    <h5 class="fw-bold">No Follow-up Steps Configured</h5>
                    <p class="text-muted small mb-0">Add up to 5 follow-up steps using the form on the right. If a lead doesn't reply, each step will trigger automatically.</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($steps as $step): ?>
                    <div class="timeline-step mb-3">
                        <div class="card shadow-sm border">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary text-white font-monospace">Step #<?= $step->step_number ?></span>
                                    <span class="fw-bold text-dark"><?= e($step->name) ?></span>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="fa-regular fa-clock me-1"></i> Delay: <?= $step->delay_value ?> <?= ucfirst(e($step->delay_unit)) ?>
                                    </span>
                                </div>
                                <div class="d-inline-flex gap-1">
                                    <!-- Edit Step Button -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editStepModal<?= $step->id ?>" title="Edit Step">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete Step Form -->
                                    <form action="<?= url("/settings/followups/step/{$step->id}/delete") ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete follow-up Step #<?= $step->step_number ?>?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Step">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body py-3">
                                <div class="bg-white p-3 rounded-2 border font-monospace small text-pre-wrap" style="white-space: pre-wrap; font-size: 0.9rem;"><?= $step->message ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal for Step -->
                    <div class="modal fade" id="editStepModal<?= $step->id ?>" tabindex="-1" aria-labelledby="editStepModalLabel<?= $step->id ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <form action="<?= url("/settings/followups/step/{$step->id}/update") ?>" method="POST" onsubmit="syncEditQuill(<?= $step->id ?>)">
                                    <?= csrf_field() ?>
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold" id="editStepModalLabel<?= $step->id ?>">
                                            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Follow-up Step #<?= $step->step_number ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Step Name</label>
                                            <input type="text" name="name" class="form-control" value="<?= e($step->name) ?>" required>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <label class="form-label small fw-semibold">Delay Value</label>
                                                <input type="number" name="delay_value" class="form-control" min="1" max="999" value="<?= $step->delay_value ?>" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small fw-semibold">Delay Unit</label>
                                                <select name="delay_unit" class="form-select">
                                                    <option value="seconds" <?= $step->delay_unit === 'seconds' ? 'selected' : '' ?>>Seconds</option>
                                                    <option value="minutes" <?= $step->delay_unit === 'minutes' ? 'selected' : '' ?>>Minutes</option>
                                                    <option value="hours" <?= $step->delay_unit === 'hours' ? 'selected' : '' ?>>Hours</option>
                                                    <option value="days" <?= $step->delay_unit === 'days' ? 'selected' : '' ?>>Days</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Follow-up Message (Supports Links & Images)</label>
                                            <div id="quill_edit_<?= $step->id ?>" class="quill-edit-box">
                                                <?= $step->message ?>
                                            </div>
                                            <input type="hidden" name="message" id="hidden_edit_msg_<?= $step->id ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <i class="fa-solid fa-plus me-2 text-primary"></i> <strong>Add Follow-up Step #<?= count($steps) + 1 ?></strong>
            </div>
            <div class="card-body">
                <form id="createStepForm" action="<?= url("/settings/followups/{$selectedAccount->id}/create") ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Step Name</label>
                        <input type="text" name="name" class="form-control" value="Follow-up #<?= count($steps) + 1 ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Delay Value</label>
                            <input type="number" name="delay_value" class="form-control" min="1" max="999" value="2" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Delay Unit</label>
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
                            <label class="form-label small fw-semibold m-0">Follow-up Message Template</label>
                            <span class="small text-muted" style="font-size: 0.75rem;">(Link 🔗 & Image 🖼️ Supported)</span>
                        </div>
                        
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="variable-badge fu-var" data-variable="{{first_name}}"><i class="fa-solid fa-plus me-1"></i>{{first_name}}</span>
                            <span class="variable-badge fu-var" data-variable="{{subject}}"><i class="fa-solid fa-plus me-1"></i>{{subject}}</span>
                            <span class="variable-badge fu-var" data-variable="{{date}}"><i class="fa-solid fa-plus me-1"></i>{{date}}</span>
                        </div>
                        
                        <!-- Quill Create Box -->
                        <div id="quill_create_step" style="min-height: 150px;"></div>
                        <input type="hidden" name="message" id="hidden_create_msg">
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>Smart Stop:</strong> If the recipient replies at any time, all pending follow-up steps are cancelled immediately!
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
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
    }

    // Initialize edit modals Quill
    document.querySelectorAll('.quill-edit-box').forEach(el => {
        const id = el.id.replace('quill_edit_', '');
        editQuills[id] = new Quill(el, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });
    });

    // Variable click insertion for create editor
    document.querySelectorAll('.fu-var').forEach(badge => {
        badge.addEventListener('click', function() {
            const variable = this.getAttribute('data-variable');
            if (createQuill) {
                const range = createQuill.getSelection(true);
                const pos = range ? range.index : createQuill.getLength();
                createQuill.insertText(pos, variable);
                createQuill.setSelection(pos + variable.length);
            }
        });
    });

    // Form submit sync for create step
    const createForm = document.getElementById('createStepForm');
    if (createForm) {
        createForm.addEventListener('submit', function() {
            const hiddenMsg = document.getElementById('hidden_create_msg');
            if (createQuill && hiddenMsg) {
                hiddenMsg.value = createQuill.root.innerHTML;
            }
        });
    }
});

function syncEditQuill(stepId) {
    const quill = editQuills[stepId];
    const hidden = document.getElementById('hidden_edit_msg_' + stepId);
    if (quill && hidden) {
        hidden.value = quill.root.innerHTML;
    }
}
</script>
