<?php
$isInstant = ($settings->reply_time_type === 'instant' || ($settings->working_start === '00:00' && $settings->working_end === '23:59' && count(explode(',', $settings->working_days)) >= 7));
$workingDaysArray = !empty($settings->working_days) ? explode(',', $settings->working_days) : [1,2,3,4,5,6,7];
$connectedAccountsCount = count($accounts);
?>
<!-- Quill.js Rich Text Editor CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
.global-page-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.nav-pills-custom .nav-link {
    color: #64748b;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.nav-pills-custom .nav-link.active {
    background-color: #4f46e5;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.nav-pills-custom .nav-link:hover:not(.active) {
    background-color: #f1f5f9;
    color: #334155;
}
.step-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: box-shadow 0.2s ease;
}
.step-card:hover {
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}
.variation-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    position: relative;
}
.variation-badge {
    background: #e0e7ff;
    color: #4338ca;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.ql-toolbar.ql-snow {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: #f8fafc;
    border-color: #cbd5e1;
    padding: 6px;
}
.ql-container.ql-snow {
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    background: #ffffff;
    border-color: #cbd5e1;
    font-size: 0.95rem;
    min-height: 120px;
    max-height: 380px;
    overflow-y: auto;
}
.ql-editor img {
    max-width: 100% !important;
    height: auto !important;
    border-radius: 6px;
}
.var-pill {
    cursor: pointer;
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s ease;
    user-select: none;
    display: inline-flex;
    align-items: center;
}
.var-pill:hover {
    background: #4f46e5;
    color: #ffffff;
}
</style>

<div class="global-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary px-2 py-1"><i class="fa-solid fa-globe me-1"></i> Global System</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="fa-solid fa-bolt me-1"></i> Random Message Variations
                </span>
            </div>
            <h4 class="fw-bold mb-1">Global Auto-Reply &amp; Follow-up Configuration</h4>
            <p class="text-muted small mb-0">
                Configure once. Every eligible Gmail account (<strong><?= $connectedAccountsCount ?> connected</strong>) will automatically inherit these multi-step responses and independently pick random variations per send.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('/accounts') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-brands fa-google me-1"></i> View Gmail Accounts
            </a>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills nav-pills-custom mb-4 gap-2" id="globalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active d-flex align-items-center gap-2" id="autoreply-tab" data-bs-toggle="pill" data-bs-target="#tab-autoreply" type="button" role="tab">
            <i class="fa-solid fa-robot"></i>
            <span>Global Auto-Reply</span>
            <span class="badge bg-white text-primary rounded-pill small ms-1"><?= count($autoRepliesByStep) ?> Steps</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link d-flex align-items-center gap-2" id="followup-tab" data-bs-toggle="pill" data-bs-target="#tab-followup" type="button" role="tab">
            <i class="fa-solid fa-arrows-split-up-and-left"></i>
            <span>Global Follow-up</span>
            <span class="badge bg-white text-primary rounded-pill small ms-1"><?= count($followupSequences) ?> Steps</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link d-flex align-items-center gap-2" id="settings-tab" data-bs-toggle="pill" data-bs-target="#tab-settings" type="button" role="tab">
            <i class="fa-solid fa-clock"></i>
            <span>Schedule &amp; Limits</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="globalTabsContent">

    <!-- TAB 1: GLOBAL AUTO-REPLY -->
    <div class="tab-pane fade show active" id="tab-autoreply" role="tabpanel">
        <div class="alert alert-info border-0 bg-info bg-opacity-10 d-flex align-items-center justify-content-between p-3 mb-4 rounded-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-shuffle text-info fs-4"></i>
                <div>
                    <strong class="d-block text-dark">Per-Send Random Variation &amp; Multi-Step Logic</strong>
                    <span class="small text-muted">
                        Add variations for each step. When a new incoming email qualifies for Step #1, the system picks ONE active variation at random. If recipient replies later, Step #2 is triggered and picks a random variation from Step #2.
                    </span>
                </div>
            </div>
            <span class="badge bg-warning text-dark border border-warning px-2 py-1 text-nowrap">
                <i class="fa-solid fa-shield-halved me-1"></i> Zero Fallback Active
            </span>
        </div>

        <form id="globalAutoReplyForm" action="<?= url('/settings/automation/global/reply') ?>" method="POST">
            <?= csrf_field() ?>

            <div id="autoReplyStepsContainer" class="d-flex flex-column gap-4">
                <?php
                $stepList = $autoRepliesByStep;
                if (empty($stepList)) {
                    // Default step 1 with 1 variation
                    $stepList = [
                        1 => [
                            (object)[
                                'id' => '',
                                'step_number' => 1,
                                'delay_minutes' => 0,
                                'variation_name' => 'Variation A',
                                'body_html' => '<p>Hi {{first_name}},</p><p>Thank you for reaching out! We received your message and will get back to you shortly.</p>',
                                'is_active' => 1,
                            ]
                        ]
                    ];
                }
                foreach ($stepList as $stepNum => $vars):
                    $stepDelay = !empty($vars[0]->delay_minutes) ? $vars[0]->delay_minutes : 0;
                ?>
                <div class="step-card p-4 auto-reply-step-block" data-step="<?= $stepNum ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                                <i class="fa-solid fa-reply me-1"></i> Reply Step #<?= $stepNum ?>
                            </span>
                            <span class="text-muted small">
                                <?= $stepNum === 1 ? '(Sent on first incoming email from lead)' : "(Triggered when recipient replies back to Reply #" . ($stepNum - 1) . ")" ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted fw-semibold text-nowrap">Delay (Mins):</label>
                                <input type="number" name="steps[<?= $stepNum ?>][delay_minutes]" value="<?= (int)$stepDelay ?>" min="0" max="1440" class="form-control form-control-sm" style="width: 85px;" title="0 = Instant send">
                            </div>
                            <input type="hidden" name="steps[<?= $stepNum ?>][step_number]" value="<?= $stepNum ?>">

                            <?php if ($stepNum > 1 || count($stepList) > 1): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteStep(<?= $stepNum ?>, 'reply')">
                                <i class="fa-solid fa-trash me-1"></i> Delete Step
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Variations Container for this step -->
                    <div class="variations-container d-flex flex-column gap-3" data-step="<?= $stepNum ?>">
                        <?php foreach ($vars as $vIndex => $v): ?>
                        <div class="variation-box" data-var-index="<?= $vIndex ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="variation-badge">VARIATION</span>
                                    <input type="text" name="steps[<?= $stepNum ?>][variations][<?= $vIndex ?>][variation_name]" value="<?= e($v->variation_name ?: 'Variation ' . chr(65 + $vIndex)) ?>" class="form-control form-control-sm fw-semibold" style="width: 180px;" placeholder="e.g. Variation A">
                                    <input type="hidden" name="steps[<?= $stepNum ?>][variations][<?= $vIndex ?>][id]" value="<?= $v->id ?? '' ?>">
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="steps[<?= $stepNum ?>][variations][<?= $vIndex ?>][is_active]" value="1" <?= (!isset($v->is_active) || $v->is_active) ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-semibold text-muted">Active</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                                        <i class="fa-solid fa-circle-xmark fs-5"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Variable Chips -->
                            <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
                                <span class="small text-muted me-1 fw-semibold">Insert:</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
                            </div>

                            <!-- Quill Editor -->
                            <div class="quill-editor-wrapper">
                                <div class="quill-editor" style="min-height: 120px;"><?= $v->body_html ?></div>
                                <textarea name="steps[<?= $stepNum ?>][variations][<?= $vIndex ?>][body_html]" class="d-none quill-textarea"><?= e($v->body_html) ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReplyVariation(<?= $stepNum ?>)">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Variation to Step #<?= $stepNum ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="addNewReplyStep()">
                    <i class="fa-solid fa-layer-group me-1"></i> Add Sequential Reply Step
                </button>
                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Global Auto-Reply Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 2: GLOBAL FOLLOW-UP SEQUENCES -->
    <div class="tab-pane fade" id="tab-followup" role="tabpanel">
        <div class="alert alert-info border-0 bg-info bg-opacity-10 d-flex align-items-center justify-content-between p-3 mb-4 rounded-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-arrows-split-up-and-left text-info fs-4"></i>
                <div>
                    <strong class="d-block text-dark">Global Follow-up Sequence Engine</strong>
                    <span class="small text-muted">
                        Follow-ups are automatically scheduled after the initial Auto-Reply. If the recipient replies at any time, pending follow-ups are automatically stopped. Each step independently selects a random active variation.
                    </span>
                </div>
            </div>
            <span class="badge bg-warning text-dark border border-warning px-2 py-1 text-nowrap">
                <i class="fa-solid fa-shield-halved me-1"></i> Zero Fallback Active
            </span>
        </div>

        <form id="globalFollowupForm" action="<?= url('/settings/automation/global/followups') ?>" method="POST">
            <?= csrf_field() ?>

            <div id="followupStepsContainer" class="d-flex flex-column gap-4">
                <?php
                $fSteps = $followupSequences;
                if (empty($fSteps)) {
                    // Default step 1
                    $fSteps = [
                        (object)[
                            'step_number' => 1,
                            'delay_value' => 2,
                            'delay_unit' => 'days',
                            'is_active' => 1,
                        ]
                    ];
                }

                foreach ($fSteps as $fSeq):
                    $fStepNum = (int)$fSeq->step_number;
                    $fVars = $followupVariationsByStep[$fStepNum] ?? [
                        (object)[
                            'id' => '',
                            'step_number' => $fStepNum,
                            'variation_name' => 'Variation A',
                            'subject' => 'Quick follow up regarding your message',
                            'body_html' => '<p>Hi {{first_name}},</p><p>Just checking in to see if you had any questions regarding my previous email.</p>',
                            'is_active' => 1,
                        ]
                    ];
                ?>
                <div class="step-card p-4 followup-step-block" data-step="<?= $fStepNum ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark fs-6 px-3 py-2 rounded-pill">
                                <i class="fa-solid fa-stopwatch me-1"></i> Follow-up Step #<?= $fStepNum ?>
                            </span>
                            <span class="text-muted small">
                                Scheduled after <?= $fStepNum === 1 ? 'initial Auto-Reply' : "Follow-up #" . ($fStepNum - 1) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted fw-semibold text-nowrap">Send After:</label>
                                <input type="number" name="followup_steps[<?= $fStepNum ?>][delay_value]" value="<?= (int)($fSeq->delay_value ?? 1) ?>" min="1" max="365" class="form-control form-control-sm" style="width: 75px;">
                                <select name="followup_steps[<?= $fStepNum ?>][delay_unit]" class="form-select form-select-sm" style="width: 100px;">
                                    <option value="minutes" <?= ($fSeq->delay_unit ?? '') === 'minutes' ? 'selected' : '' ?>>Minutes</option>
                                    <option value="hours" <?= ($fSeq->delay_unit ?? '') === 'hours' ? 'selected' : '' ?>>Hours</option>
                                    <option value="days" <?= ($fSeq->delay_unit ?? 'days') === 'days' ? 'selected' : '' ?>>Days</option>
                                </select>
                            </div>

                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="followup_steps[<?= $fStepNum ?>][is_active]" value="1" <?= (!isset($fSeq->is_active) || $fSeq->is_active) ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-semibold text-muted">Step Active</label>
                            </div>

                            <input type="hidden" name="followup_steps[<?= $fStepNum ?>][step_number]" value="<?= $fStepNum ?>">

                            <?php if ($fStepNum > 1 || count($fSteps) > 1): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteStep(<?= $fStepNum ?>, 'followup')">
                                <i class="fa-solid fa-trash me-1"></i> Delete Step
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Follow-up Variations Container -->
                    <div class="followup-vars-container d-flex flex-column gap-3" data-step="<?= $fStepNum ?>">
                        <?php foreach ($fVars as $fvIndex => $fv): ?>
                        <div class="variation-box" data-var-index="<?= $fvIndex ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="variation-badge">VARIATION</span>
                                    <input type="text" name="followup_steps[<?= $fStepNum ?>][variations][<?= $fvIndex ?>][variation_name]" value="<?= e($fv->variation_name ?: 'Variation ' . chr(65 + $fvIndex)) ?>" class="form-control form-control-sm fw-semibold" style="width: 180px;" placeholder="e.g. Variation A">
                                    <input type="hidden" name="followup_steps[<?= $fStepNum ?>][variations][<?= $fvIndex ?>][id]" value="<?= $fv->id ?? '' ?>">
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="followup_steps[<?= $fStepNum ?>][variations][<?= $fvIndex ?>][is_active]" value="1" <?= (!isset($fv->is_active) || $fv->is_active) ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-semibold text-muted">Active</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                                        <i class="fa-solid fa-circle-xmark fs-5"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small text-muted mb-1 fw-semibold">Subject (Leave empty to keep original thread subject):</label>
                                <input type="text" name="followup_steps[<?= $fStepNum ?>][variations][<?= $fvIndex ?>][subject]" value="<?= e($fv->subject ?? '') ?>" class="form-control form-control-sm" placeholder="e.g. Re: {{subject}}">
                            </div>

                            <!-- Variable Chips -->
                            <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
                                <span class="small text-muted me-1 fw-semibold">Insert:</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
                                <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
                            </div>

                            <!-- Quill Editor -->
                            <div class="quill-editor-wrapper">
                                <div class="quill-editor" style="min-height: 120px;"><?= $fv->body_html ?></div>
                                <textarea name="followup_steps[<?= $fStepNum ?>][variations][<?= $fvIndex ?>][body_html]" class="d-none quill-textarea"><?= e($fv->body_html) ?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="addFollowupVariation(<?= $fStepNum ?>)">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Variation to Follow-up Step #<?= $fStepNum ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="addNewFollowupStep()">
                    <i class="fa-solid fa-plus me-1"></i> Add Next Follow-up Step
                </button>
                <button type="submit" class="btn btn-info px-4 py-2 fw-semibold text-dark">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Global Follow-up Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 3: GLOBAL SCHEDULE, LIMITS & BEHAVIOR RULES -->
    <div class="tab-pane fade" id="tab-settings" role="tabpanel">
        <form action="<?= url('/settings/automation/global/settings') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="row g-4">
                <!-- Master Switches & Behavior Rules -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-transparent py-3 fw-bold fs-6">
                            <i class="fa-solid fa-toggle-on text-primary me-2"></i> Master Toggles &amp; Behavior Rules
                        </div>
                        <div class="card-body p-4 d-flex flex-column gap-3">
                            <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block text-dark">Global Auto-Reply Engine</strong>
                                    <span class="small text-muted">Enable automatic replies across all connected Gmail accounts.</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input fs-5" type="checkbox" role="switch" name="auto_reply_enabled" value="1" <?= $settings->auto_reply_enabled ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block text-dark">Global Follow-up Automation</strong>
                                    <span class="small text-muted">Enable sequential follow-ups across all connected Gmail accounts.</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input fs-5" type="checkbox" role="switch" name="followup_enabled" value="1" <?= $settings->followup_enabled ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block text-dark"><i class="fa-solid fa-shield-halved text-success me-1"></i> Only Reply When Recipient Replies</strong>
                                    <span class="small text-muted">Strictly prevent sending sequential reply steps (Reply #2, #3) unless the recipient has replied back.</span>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input fs-5" type="checkbox" role="switch" name="require_recipient_reply_before_next_reply" value="1" <?= $settings->require_recipient_reply_before_next_reply ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Daily Reply Limit / Account</label>
                                    <input type="number" name="daily_reply_limit_per_account" value="<?= (int)$settings->daily_reply_limit_per_account ?>" min="1" max="1500" class="form-control">
                                    <span class="text-muted small">Max auto-replies per Gmail/day</span>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Daily Follow-up Limit / Account</label>
                                    <input type="number" name="daily_followup_limit_per_account" value="<?= (int)$settings->daily_followup_limit_per_account ?>" min="1" max="1500" class="form-control">
                                    <span class="text-muted small">Max follow-ups per Gmail/day</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule & Working Hours -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-transparent py-3 fw-bold fs-6">
                            <i class="fa-solid fa-clock text-info me-2"></i> Working Hours &amp; Timezone
                        </div>
                        <div class="card-body p-4 d-flex flex-column gap-3">
                            <div>
                                <label class="form-label small fw-semibold">Delivery Timing Mode</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="reply_time_type" id="mode_instant" value="instant" <?= $isInstant ? 'checked' : '' ?> onchange="toggleScheduleFields(false)">
                                        <label class="form-check-label fw-semibold" for="mode_instant">24/7 Instant Sending</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="reply_time_type" id="mode_working" value="working_hours" <?= !$isInstant ? 'checked' : '' ?> onchange="toggleScheduleFields(true)">
                                        <label class="form-check-label fw-semibold" for="mode_working">Working Hours Only</label>
                                    </div>
                                </div>
                            </div>

                            <div id="scheduleFields" style="<?= $isInstant ? 'display: none;' : '' ?>">
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Start Time</label>
                                        <input type="time" name="working_start" value="<?= e($settings->working_start) ?>" class="form-control">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">End Time</label>
                                        <input type="time" name="working_end" value="<?= e($settings->working_end) ?>" class="form-control">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold mb-2">Active Working Days</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php
                                        $daysMap = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                                        foreach ($daysMap as $dayVal => $dayLabel):
                                            $checked = in_array((string)$dayVal, $workingDaysArray, true) || in_array($dayVal, $workingDaysArray, true);
                                        ?>
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="checkbox" name="working_days[]" value="<?= $dayVal ?>" id="gday_<?= $dayVal ?>" <?= $checked ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="gday_<?= $dayVal ?>"><?= $dayLabel ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label small fw-semibold">System Timezone</label>
                                <select name="timezone" class="form-select">
                                    <?php foreach ($timezones as $tzKey => $tzLabel): ?>
                                    <option value="<?= $tzKey ?>" <?= $settings->timezone === $tzKey ? 'selected' : '' ?>>
                                        <?= e($tzLabel) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Global Settings &amp; Schedule
                </button>
            </div>
        </form>
    </div>

</div>

<!-- Hidden Delete Step Form -->
<form id="deleteStepForm" method="POST" action="" class="d-none">
    <?= csrf_field() ?>
</form>

<!-- Quill.js JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
let lastFocusedQuill = null;
const quillInstances = new Map();

function initQuillForWrapper(wrapper) {
    const editorDiv = wrapper.querySelector('.quill-editor');
    const textarea = wrapper.querySelector('.quill-textarea');
    if (!editorDiv || quillInstances.has(editorDiv)) return;

    const quill = new Quill(editorDiv, {
        theme: 'snow',
        placeholder: 'Compose message with rich formatting, links, and images...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    quill.on('text-change', () => {
        textarea.value = quill.root.innerHTML;
    });

    quill.root.addEventListener('focus', () => {
        lastFocusedQuill = quill;
    });

    quillInstances.set(editorDiv, quill);
}

// Initialize all existing quill editors
document.querySelectorAll('.quill-editor-wrapper').forEach(wrapper => {
    initQuillForWrapper(wrapper);
});

// Sync before any form submit
['globalAutoReplyForm', 'globalFollowupForm'].forEach(formId => {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', () => {
            quillInstances.forEach((quill, div) => {
                const wrapper = div.closest('.quill-editor-wrapper');
                if (wrapper) {
                    const textarea = wrapper.querySelector('.quill-textarea');
                    if (textarea) {
                        textarea.value = quill.root.innerHTML;
                    }
                }
            });
        });
    }
});

function insertVariable(btn, variableText) {
    // Find closest variation box
    const varBox = btn.closest('.variation-box');
    let targetQuill = null;
    if (varBox) {
        const editorDiv = varBox.querySelector('.quill-editor');
        if (editorDiv && quillInstances.has(editorDiv)) {
            targetQuill = quillInstances.get(editorDiv);
        }
    }
    if (!targetQuill) {
        targetQuill = lastFocusedQuill;
    }
    if (targetQuill) {
        const range = targetQuill.getSelection(true);
        const index = range ? range.index : targetQuill.getLength();
        targetQuill.insertText(index, variableText);
        targetQuill.setSelection(index + variableText.length, 0);
    }
}

function removeVariation(btn) {
    const varBox = btn.closest('.variation-box');
    const container = varBox.parentElement;
    if (container.querySelectorAll('.variation-box').length <= 1) {
        alert('Each step must have at least one variation. You can delete the entire step if you do not want to use it.');
        return;
    }
    if (confirm('Are you sure you want to remove this variation?')) {
        const editorDiv = varBox.querySelector('.quill-editor');
        if (editorDiv) quillInstances.delete(editorDiv);
        varBox.remove();
    }
}

function addReplyVariation(stepNum) {
    const container = document.querySelector(`.variations-container[data-step="${stepNum}"]`);
    if (!container) return;

    const count = container.querySelectorAll('.variation-box').length;
    const nextChar = String.fromCharCode(65 + count);
    const vIndex = Date.now();

    const div = document.createElement('div');
    div.className = 'variation-box';
    div.setAttribute('data-var-index', vIndex);
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="variation-badge">VARIATION</span>
                <input type="text" name="steps[${stepNum}][variations][${vIndex}][variation_name]" value="Variation ${nextChar}" class="form-control form-control-sm fw-semibold" style="width: 180px;">
                <input type="hidden" name="steps[${stepNum}][variations][${vIndex}][id]" value="">
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="steps[${stepNum}][variations][${vIndex}][is_active]" value="1" checked>
                    <label class="form-check-label small fw-semibold text-muted">Active</label>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                    <i class="fa-solid fa-circle-xmark fs-5"></i>
                </button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
            <span class="small text-muted me-1 fw-semibold">Insert:</span>
            <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
        </div>
        <div class="quill-editor-wrapper">
            <div class="quill-editor" style="min-height: 120px;"></div>
            <textarea name="steps[${stepNum}][variations][${vIndex}][body_html]" class="d-none quill-textarea"></textarea>
        </div>
    `;

    container.appendChild(div);
    initQuillForWrapper(div.querySelector('.quill-editor-wrapper'));
}

function addNewReplyStep() {
    const container = document.getElementById('autoReplyStepsContainer');
    const existingSteps = container.querySelectorAll('.auto-reply-step-block');
    let maxStep = 0;
    existingSteps.forEach(el => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s > maxStep) maxStep = s;
    });
    const newStep = maxStep + 1;

    const div = document.createElement('div');
    div.className = 'step-card p-4 auto-reply-step-block';
    div.setAttribute('data-step', newStep);
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                    <i class="fa-solid fa-reply me-1"></i> Reply Step #${newStep}
                </span>
                <span class="text-muted small">
                    (Triggered when recipient replies back to Reply #${newStep - 1})
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-semibold text-nowrap">Delay (Mins):</label>
                    <input type="number" name="steps[${newStep}][delay_minutes]" value="0" min="0" max="1440" class="form-control form-control-sm" style="width: 85px;">
                </div>
                <input type="hidden" name="steps[${newStep}][step_number]" value="${newStep}">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.step-card').remove()">
                    <i class="fa-solid fa-trash me-1"></i> Remove Step
                </button>
            </div>
        </div>
        <div class="variations-container d-flex flex-column gap-3" data-step="${newStep}">
            <div class="variation-box" data-var-index="0">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="variation-badge">VARIATION</span>
                        <input type="text" name="steps[${newStep}][variations][0][variation_name]" value="Variation A" class="form-control form-control-sm fw-semibold" style="width: 180px;">
                        <input type="hidden" name="steps[${newStep}][variations][0][id]" value="">
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="steps[${newStep}][variations][0][is_active]" value="1" checked>
                            <label class="form-check-label small fw-semibold text-muted">Active</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                            <i class="fa-solid fa-circle-xmark fs-5"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
                    <span class="small text-muted me-1 fw-semibold">Insert:</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
                </div>
                <div class="quill-editor-wrapper">
                    <div class="quill-editor" style="min-height: 120px;"></div>
                    <textarea name="steps[${newStep}][variations][0][body_html]" class="d-none quill-textarea"></textarea>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReplyVariation(${newStep})">
                <i class="fa-solid fa-plus me-1"></i> Add Another Variation to Step #${newStep}
            </button>
        </div>
    `;

    container.appendChild(div);
    initQuillForWrapper(div.querySelector('.quill-editor-wrapper'));
}

function addFollowupVariation(stepNum) {
    const container = document.querySelector(`.followup-vars-container[data-step="${stepNum}"]`);
    if (!container) return;

    const count = container.querySelectorAll('.variation-box').length;
    const nextChar = String.fromCharCode(65 + count);
    const vIndex = Date.now();

    const div = document.createElement('div');
    div.className = 'variation-box';
    div.setAttribute('data-var-index', vIndex);
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="variation-badge">VARIATION</span>
                <input type="text" name="followup_steps[${stepNum}][variations][${vIndex}][variation_name]" value="Variation ${nextChar}" class="form-control form-control-sm fw-semibold" style="width: 180px;">
                <input type="hidden" name="followup_steps[${stepNum}][variations][${vIndex}][id]" value="">
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="followup_steps[${stepNum}][variations][${vIndex}][is_active]" value="1" checked>
                    <label class="form-check-label small fw-semibold text-muted">Active</label>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                    <i class="fa-solid fa-circle-xmark fs-5"></i>
                </button>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label small text-muted mb-1 fw-semibold">Subject (Leave empty to keep thread subject):</label>
            <input type="text" name="followup_steps[${stepNum}][variations][${vIndex}][subject]" value="" class="form-control form-control-sm" placeholder="e.g. Re: {{subject}}">
        </div>
        <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
            <span class="small text-muted me-1 fw-semibold">Insert:</span>
            <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
            <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
        </div>
        <div class="quill-editor-wrapper">
            <div class="quill-editor" style="min-height: 120px;"></div>
            <textarea name="followup_steps[${stepNum}][variations][${vIndex}][body_html]" class="d-none quill-textarea"></textarea>
        </div>
    `;

    container.appendChild(div);
    initQuillForWrapper(div.querySelector('.quill-editor-wrapper'));
}

function addNewFollowupStep() {
    const container = document.getElementById('followupStepsContainer');
    const existingSteps = container.querySelectorAll('.followup-step-block');
    let maxStep = 0;
    existingSteps.forEach(el => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s > maxStep) maxStep = s;
    });
    const newStep = maxStep + 1;

    const div = document.createElement('div');
    div.className = 'step-card p-4 followup-step-block';
    div.setAttribute('data-step', newStep);
    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info text-dark fs-6 px-3 py-2 rounded-pill">
                    <i class="fa-solid fa-stopwatch me-1"></i> Follow-up Step #${newStep}
                </span>
                <span class="text-muted small">
                    Scheduled after Follow-up #${newStep - 1}
                </span>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-semibold text-nowrap">Send After:</label>
                    <input type="number" name="followup_steps[${newStep}][delay_value]" value="3" min="1" max="365" class="form-control form-control-sm" style="width: 75px;">
                    <select name="followup_steps[${newStep}][delay_unit]" class="form-select form-select-sm" style="width: 100px;">
                        <option value="minutes">Minutes</option>
                        <option value="hours">Hours</option>
                        <option value="days" selected>Days</option>
                    </select>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="followup_steps[${newStep}][is_active]" value="1" checked>
                    <label class="form-check-label small fw-semibold text-muted">Step Active</label>
                </div>
                <input type="hidden" name="followup_steps[${newStep}][step_number]" value="${newStep}">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.step-card').remove()">
                    <i class="fa-solid fa-trash me-1"></i> Remove Step
                </button>
            </div>
        </div>
        <div class="followup-vars-container d-flex flex-column gap-3" data-step="${newStep}">
            <div class="variation-box" data-var-index="0">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="variation-badge">VARIATION</span>
                        <input type="text" name="followup_steps[${newStep}][variations][0][variation_name]" value="Variation A" class="form-control form-control-sm fw-semibold" style="width: 180px;">
                        <input type="hidden" name="followup_steps[${newStep}][variations][0][id]" value="">
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="followup_steps[${newStep}][variations][0][is_active]" value="1" checked>
                            <label class="form-check-label small fw-semibold text-muted">Active</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 delete-var-btn" title="Remove this variation" onclick="removeVariation(this)">
                            <i class="fa-solid fa-circle-xmark fs-5"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Subject:</label>
                    <input type="text" name="followup_steps[${newStep}][variations][0][subject]" value="" class="form-control form-control-sm" placeholder="e.g. Re: {{subject}}">
                </div>
                <div class="d-flex align-items-center gap-1 mb-2 flex-wrap">
                    <span class="small text-muted me-1 fw-semibold">Insert:</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{first_name}}')">{{first_name}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{sender_name}}')">{{sender_name}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{email}}')">{{email}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{company}}')">{{company}}</span>
                    <span class="var-pill" onclick="insertVariable(this, '{{subject}}')">{{subject}}</span>
                </div>
                <div class="quill-editor-wrapper">
                    <div class="quill-editor" style="min-height: 120px;"></div>
                    <textarea name="followup_steps[${newStep}][variations][0][body_html]" class="d-none quill-textarea"></textarea>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-info" onclick="addFollowupVariation(${newStep})">
                <i class="fa-solid fa-plus me-1"></i> Add Another Variation to Follow-up Step #${newStep}
            </button>
        </div>
    `;

    container.appendChild(div);
    initQuillForWrapper(div.querySelector('.quill-editor-wrapper'));
}

function confirmDeleteStep(stepNum, type) {
    if (confirm(`Are you sure you want to permanently delete Step #${stepNum} and all its message variations?`)) {
        const form = document.getElementById('deleteStepForm');
        if (type === 'reply') {
            form.action = '<?= url('/settings/automation/global/reply/step') ?>/' + stepNum + '/delete';
        } else {
            form.action = '<?= url('/settings/automation/global/followup/step') ?>/' + stepNum + '/delete';
        }
        form.submit();
    }
}

function toggleScheduleFields(show) {
    document.getElementById('scheduleFields').style.display = show ? 'block' : 'none';
}

// Handle tab activation via URL hash
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash === '#tab-followup') {
        const tabBtn = document.getElementById('followup-tab');
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    } else if (hash === '#tab-settings') {
        const tabBtn = document.getElementById('settings-tab');
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    }
});
</script>
