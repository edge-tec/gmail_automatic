<?php
$isInstant = ($settings->working_start === '00:00' && $settings->working_end === '23:59' && count(explode(',', $settings->working_days)) >= 7);
$replySteps = $settings->getReplyStepsData();
?>
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
    font-size: 0.95rem;
    min-height: 140px;
}
.variable-badge {
    cursor: pointer;
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    padding: 4px 10px;
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
        <h4 class="fw-bold mb-1">Auto Reply & Sequential Response Settings</h4>
        <p class="text-muted small mb-0">Configure rich-text responses with links and images, per-step delay times, and 24/7 automation.</p>
    </div>
    <!-- Account Selector Dropdown -->
    <div class="d-flex align-items-center gap-2">
        <span class="small text-muted fw-semibold">Account:</span>
        <select class="form-select form-select-sm" style="min-width: 220px;" onchange="location.href = '<?= url('/settings/automation/') ?>/' + this.value">
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc->id ?>" <?= $acc->id === $selectedAccount->id ? 'selected' : '' ?>>
                    <?= e($acc->gmail_email) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<form id="autoReplyForm" action="<?= url("/settings/automation/{$selectedAccount->id}") ?>" method="POST">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Main Messages Sequence Card -->
        <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent py-3">
                    <span class="d-flex align-items-center gap-2 fw-semibold fs-5">
                        <i class="fa-solid fa-comments text-primary"></i>
                        <span>Conversational Auto-Reply Sequence</span>
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check form-switch m-0 fs-5">
                            <input class="form-check-input" type="checkbox" name="auto_reply_enabled" value="1" id="auto_reply_enabled" role="switch" style="cursor: pointer;" <?= $settings->auto_reply_enabled ? 'checked' : '' ?>>
                        </div>
                        <label class="form-check-label small fw-bold text-dark m-0" for="auto_reply_enabled" style="cursor: pointer;">
                            <?= $settings->auto_reply_enabled ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Automation Enabled</span>' : '<span class="text-secondary"><i class="fa-solid fa-circle-pause me-1"></i>Automation Disabled</span>' ?>
                        </label>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Variable Helper Tags -->
                    <div class="mb-4 p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <label class="form-label small fw-bold text-muted m-0 text-uppercase">
                                <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Click to Insert Variables:
                            </label>
                            <span class="small text-muted" style="font-size: 0.75rem;">(Inserts into active editor)</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="variable-badge" data-variable="{{first_name}}"><i class="fa-solid fa-plus me-1"></i>{{first_name}}</span>
                            <span class="variable-badge" data-variable="{{last_name}}"><i class="fa-solid fa-plus me-1"></i>{{last_name}}</span>
                            <span class="variable-badge" data-variable="{{sender_email}}"><i class="fa-solid fa-plus me-1"></i>{{sender_email}}</span>
                            <span class="variable-badge" data-variable="{{subject}}"><i class="fa-solid fa-plus me-1"></i>{{subject}}</span>
                            <span class="variable-badge" data-variable="{{date}}"><i class="fa-solid fa-plus me-1"></i>{{date}}</span>
                        </div>
                    </div>

                    <!-- Sequential Reply Steps (1st, 2nd, 3rd, 4th...) -->
                    <div id="replyStepsContainer">
                        <?php 
                        $stepTitles = [
                            1 => ['title' => '1st Auto Reply', 'badge' => 'Initial Email', 'desc' => 'Sent when a contact/traffic sends their first email.'],
                            2 => ['title' => '2nd Auto Reply', 'badge' => 'When Lead Replies 1st Time', 'desc' => 'Sent automatically when the lead/traffic replies back to your 1st email.'],
                            3 => ['title' => '3rd Auto Reply', 'badge' => 'When Lead Replies 2nd Time', 'desc' => 'Sent automatically when the lead/traffic replies back again.'],
                            4 => ['title' => '4th Auto Reply', 'badge' => 'When Lead Replies 3rd Time', 'desc' => 'Sent automatically when the lead/traffic replies back again.'],
                        ];

                        $maxStepsToRender = max(4, count($replySteps));
                        for ($step = 1; $step <= $maxStepsToRender; $step++):
                            $stepData = $replySteps[$step] ?? [
                                'message' => ($step === 1 ? ($settings->reply_message ?: '') : ''),
                                'delay_value' => ($step === 1 ? (int)$settings->reply_delay : 0),
                                'delay_unit' => 'seconds'
                            ];
                            $meta = $stepTitles[$step] ?? [
                                'title' => "{$step}th Auto Reply",
                                'badge' => "When Lead Replies " . ($step - 1) . " Times",
                                'desc' => "Sent automatically when the lead replies back {$step} times."
                            ];
                        ?>
                        <div class="card mb-4 border shadow-sm step-card" id="step_card_<?= $step ?>">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                                <div>
                                    <span class="fw-bold text-dark me-2">
                                        <i class="fa-solid fa-reply text-primary me-1"></i> <?= $meta['title'] ?>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 11px;">
                                        <?= $meta['badge'] ?>
                                    </span>
                                </div>
                                
                                <!-- Step Delay Control -->
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-semibold text-muted"><i class="fa-regular fa-clock me-1 text-primary"></i> Delay:</span>
                                    <input type="number" name="reply_steps[<?= $step ?>][delay_value]" class="form-control form-control-sm text-center" style="width: 75px;" min="0" max="999" value="<?= (int)($stepData['delay_value'] ?? 0) ?>">
                                    <select name="reply_steps[<?= $step ?>][delay_unit]" class="form-select form-select-sm" style="width: 100px;">
                                        <option value="seconds" <?= ($stepData['delay_unit'] ?? 'seconds') === 'seconds' ? 'selected' : '' ?>>Seconds</option>
                                        <option value="minutes" <?= ($stepData['delay_unit'] ?? '') === 'minutes' ? 'selected' : '' ?>>Minutes</option>
                                        <option value="hours" <?= ($stepData['delay_unit'] ?? '') === 'hours' ? 'selected' : '' ?>>Hours</option>
                                        <option value="days" <?= ($stepData['delay_unit'] ?? '') === 'days' ? 'selected' : '' ?>>Days</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div class="small text-muted mb-2"><?= $meta['desc'] ?> <span class="text-secondary">(Supports Links & Images via Toolbar)</span></div>
                                
                                <!-- Quill Rich Text Editor Container -->
                                <div id="quill_editor_<?= $step ?>" class="quill-editor-box">
                                    <?= $stepData['message'] ?>
                                </div>
                                <input type="hidden" name="reply_steps[<?= $step ?>][message]" id="hidden_message_<?= $step ?>">
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Working Hours & Schedule -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <i class="fa-solid fa-business-time me-2 text-primary"></i> <strong>Sending Schedule & Mode</strong>
                </div>
                <div class="card-body">
                    <!-- Schedule Mode Radio Cards -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-2 text-uppercase">Choose Schedule Mode:</label>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="card p-3 h-100 border <?= $isInstant ? 'border-primary bg-primary-subtle' : 'border' ?>" id="cardModeInstant" style="cursor: pointer;">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1" type="radio" name="schedule_mode" id="mode_instant" value="instant" <?= $isInstant ? 'checked' : '' ?> onchange="toggleScheduleMode(this.value)">
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-1"></i> Instant 24/7 (Anytime)</div>
                                            <div class="small text-muted mt-1">Send auto-replies 24/7 anytime an email is received according to each step's delay without working hour limits.</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="card p-3 h-100 border <?= !$isInstant ? 'border-primary bg-primary-subtle' : 'border' ?>" id="cardModeCustom" style="cursor: pointer;">
                                    <div class="d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1" type="radio" name="schedule_mode" id="mode_custom" value="custom" <?= !$isInstant ? 'checked' : '' ?> onchange="toggleScheduleMode(this.value)">
                                        <div>
                                            <div class="fw-bold text-dark"><i class="fa-solid fa-clock text-primary me-1"></i> Custom Business Hours</div>
                                            <div class="small text-muted mt-1">Only send auto-replies during specific working hours and selected active days.</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Hours Form (Collapsible/Hideable) -->
                    <div id="customScheduleSection" class="<?= $isInstant ? 'd-none' : '' ?>">
                        <hr class="my-3">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Account Timezone</label>
                                <select name="timezone" class="form-select">
                                    <?php foreach ($timezones as $tzKey => $tzLabel): ?>
                                        <option value="<?= $tzKey ?>" <?= $settings->timezone === $tzKey ? 'selected' : '' ?>>
                                            <?= $tzLabel ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">Start Time</label>
                                <input type="time" name="working_start" id="working_start" class="form-control" value="<?= e($settings->working_start) ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">End Time</label>
                                <input type="time" name="working_end" id="working_end" class="form-control" value="<?= e($settings->working_end) ?>">
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold mb-2">Active Working Days</label>
                            <?php 
                            $activeDays = array_map('trim', explode(',', $settings->working_days));
                            $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            ?>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($allDays as $day): ?>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="working_days[]" value="<?= $day ?>" id="day_<?= $day ?>" <?= in_array($day, $activeDays) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="day_<?= $day ?>"><?= $day ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Blacklist & Skip Filter Card -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <span class="d-flex align-items-center gap-2 fw-semibold text-danger">
                        <i class="fa-solid fa-ban"></i>
                        <span class="text-dark">Account Blacklist &amp; Skip Filters</span>
                    </span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle small font-monospace">Auto-Skip Rules</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Emails matching any of these criteria will be skipped automatically without sending auto-replies or follow-ups.</p>
                    
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-dark"><i class="fa-solid fa-at text-danger me-1"></i> Blacklisted Emails</label>
                            <textarea name="blacklist_emails" rows="4" class="form-control font-monospace" style="font-size: 0.82rem;" placeholder="spam@example.com&#10;noreply@google.com"><?= e($settings->getBlacklistEmails()) ?></textarea>
                            <div class="form-text small" style="font-size: 0.72rem;">One email per line</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-dark"><i class="fa-solid fa-globe text-primary me-1"></i> Blacklisted Domains</label>
                            <textarea name="blacklist_domains" rows="4" class="form-control font-monospace" style="font-size: 0.82rem;" placeholder="spamdomain.com&#10;mailtrack.io"><?= e($settings->getBlacklistDomains()) ?></textarea>
                            <div class="form-text small" style="font-size: 0.72rem;">Without '@' (one per line)</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-dark"><i class="fa-solid fa-file-lines text-warning me-1"></i> Blacklisted Keywords</label>
                            <textarea name="blacklist_keywords" rows="4" class="form-control font-monospace" style="font-size: 0.82rem;" placeholder="unsubscribe&#10;out of office&#10;delivery failure"><?= e($settings->getBlacklistKeywords()) ?></textarea>
                            <div class="form-text small" style="font-size: 0.72rem;">Subject/body words (one per line)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limits & Delays Sidebar -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <i class="fa-solid fa-shield-halved me-2 text-primary"></i> <strong>Limits & Settings</strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Per-Thread Reply Limit</label>
                        <input type="number" name="max_reply_per_thread" class="form-control" min="1" max="50" value="<?= $settings->max_reply_per_thread ?>">
                        <div class="form-text small">Maximum automated replies allowed per conversation (e.g. 4 for 4-step sequence).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Reply Limit (Unique Leads / Traffic)</label>
                        <input type="number" name="daily_reply_limit" class="form-control" min="1" max="1000" value="<?= $settings->daily_reply_limit ?>">
                        <div class="form-text small">Maximum unique leads/traffic to reply to per day. Multiple replies in the same conversation with the same lead count as <strong>1</strong>.</div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-semibold m-0">Follow-up Automation</label>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="followup_enabled" value="1" id="followup_enabled" <?= $settings->followup_enabled ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="form-text small">Send timed follow-ups if the contact never replies back.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Follow-up Limit</label>
                        <input type="number" name="daily_followup_limit" class="form-control" min="1" max="1000" value="<?= $settings->daily_followup_limit ?>">
                    </div>
                </div>
            </div>

            <div class="card p-3 bg-light border-0 shadow-sm">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save All Settings</span>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Quill.js Rich Text Editor Script -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
const quillInstances = {};
let activeQuill = null;

document.addEventListener('DOMContentLoaded', function() {
    const toolbarOptions = [
        ['bold', 'italic', 'underline'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'header': [1, 2, 3, false] }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        ['link', 'image'],
        ['clean']
    ];

    const stepElements = document.querySelectorAll('.quill-editor-box');
    stepElements.forEach((el, index) => {
        const step = index + 1;
        const quill = new Quill(el, {
            theme: 'snow',
            modules: {
                toolbar: toolbarOptions
            },
            placeholder: `Write auto-reply message for Step #${step}...`
        });

        quillInstances[step] = quill;

        // Track last focused editor
        quill.on('selection-change', function(range) {
            if (range) {
                activeQuill = quill;
            }
        });

        if (step === 1) {
            activeQuill = quill;
        }
    });

    // Variable badges insertion
    document.querySelectorAll('.variable-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            const variable = this.getAttribute('data-variable');
            const targetQuill = activeQuill || quillInstances[1];
            if (targetQuill) {
                const range = targetQuill.getSelection(true);
                const position = range ? range.index : targetQuill.getLength();
                targetQuill.insertText(position, variable);
                targetQuill.setSelection(position + variable.length);
            }
        });
    });

    // Form submit: sync Quill HTML content to hidden inputs
    document.getElementById('autoReplyForm').addEventListener('submit', function() {
        for (const step in quillInstances) {
            const quill = quillInstances[step];
            const hiddenInput = document.getElementById(`hidden_message_${step}`);
            if (hiddenInput) {
                // If text is not empty, get HTML
                const text = quill.getText().trim();
                hiddenInput.value = text.length > 0 ? quill.root.innerHTML : '';
            }
        }
    });
});

function toggleScheduleMode(mode) {
    const customSection = document.getElementById('customScheduleSection');
    const cardInstant = document.getElementById('cardModeInstant');
    const cardCustom = document.getElementById('cardModeCustom');

    if (mode === 'instant') {
        customSection.classList.add('d-none');
        cardInstant.classList.add('border-primary', 'bg-primary-subtle');
        cardCustom.classList.remove('border-primary', 'bg-primary-subtle');
    } else {
        customSection.classList.remove('d-none');
        cardCustom.classList.add('border-primary', 'bg-primary-subtle');
        cardInstant.classList.remove('border-primary', 'bg-primary-subtle');
    }
}
</script>
