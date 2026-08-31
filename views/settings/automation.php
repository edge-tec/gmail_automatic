<?php
$isInstant = ($settings->working_start === '00:00' && $settings->working_end === '23:59' && (int)$settings->reply_delay === 0 && count(explode(',', $settings->working_days)) >= 7);
$replyMessages = $settings->getReplyMessages();
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-1">Auto Reply & Sequential Response Settings</h4>
        <p class="text-muted small mb-0">Configure 1st, 2nd, 3rd, and 4th auto-replies when traffic/leads reply back repeatedly in the same thread.</p>
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

<form action="<?= url("/settings/automation/{$selectedAccount->id}") ?>" method="POST">
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
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="auto_reply_enabled" value="1" id="auto_reply_enabled" <?= $settings->auto_reply_enabled ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold ms-1" for="auto_reply_enabled">Enable Automation</label>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Variable Helper Tags -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <label class="form-label small fw-bold text-muted mb-2 text-uppercase">Insert Variables:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="variable-badge" data-variable="{{first_name}}"><i class="fa-solid fa-plus me-1"></i>{{first_name}}</span>
                            <span class="variable-badge" data-variable="{{last_name}}"><i class="fa-solid fa-plus me-1"></i>{{last_name}}</span>
                            <span class="variable-badge" data-variable="{{sender_email}}"><i class="fa-solid fa-plus me-1"></i>{{sender_email}}</span>
                            <span class="variable-badge" data-variable="{{subject}}"><i class="fa-solid fa-plus me-1"></i>{{subject}}</span>
                            <span class="variable-badge" data-variable="{{date}}"><i class="fa-solid fa-plus me-1"></i>{{date}}</span>
                        </div>
                        <div class="small text-muted mt-2" style="font-size: 0.8rem;">
                            Click any variable to copy or insert into active message body.
                        </div>
                    </div>

                    <!-- Sequential Reply Steps (1st, 2nd, 3rd, 4th...) -->
                    <div id="replyStepsContainer">
                        <?php 
                        $stepTitles = [
                            1 => ['title' => '1st Auto Reply', 'badge' => 'Initial Contact', 'desc' => 'Sent immediately when a contact/traffic sends their first email.'],
                            2 => ['title' => '2nd Auto Reply', 'badge' => 'When Lead Replies 1st Time', 'desc' => 'Sent automatically when the lead/traffic replies back to your 1st email.'],
                            3 => ['title' => '3rd Auto Reply', 'badge' => 'When Lead Replies 2nd Time', 'desc' => 'Sent automatically when the lead/traffic replies back again.'],
                            4 => ['title' => '4th Auto Reply', 'badge' => 'When Lead Replies 3rd Time', 'desc' => 'Sent automatically when the lead/traffic replies back again.'],
                        ];

                        $maxStepsToRender = max(4, count($replyMessages));
                        for ($step = 1; $step <= $maxStepsToRender; $step++):
                            $msgContent = $replyMessages[$step] ?? ($step === 1 ? $settings->reply_message : '');
                            $meta = $stepTitles[$step] ?? [
                                'title' => "{$step}th Auto Reply",
                                'badge' => "When Lead Replies " . ($step - 1) . " Times",
                                'desc' => "Sent automatically when the lead replies back {$step} times."
                            ];
                        ?>
                        <div class="card mb-3 border bg-white step-card" id="step_card_<?= $step ?>">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <span class="fw-bold text-dark me-2">
                                        <i class="fa-solid fa-reply text-primary me-1"></i> <?= $meta['title'] ?>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 11px;">
                                        <?= $meta['badge'] ?>
                                    </span>
                                </div>
                                <span class="text-muted small">Step #<?= $step ?></span>
                            </div>
                            <div class="card-body p-3">
                                <div class="small text-muted mb-2"><?= $meta['desc'] ?></div>
                                <textarea name="reply_messages[<?= $step ?>]" id="reply_message_<?= $step ?>" rows="4" class="form-control font-monospace reply-textarea" style="font-size: 0.9rem;" placeholder="Enter message for Step #<?= $step ?>..."><?= e($msgContent) ?></textarea>
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
                                            <div class="small text-muted mt-1">Send 1st, 2nd, 3rd, 4th replies instantly 24/7 anytime an email is received without delays or working hour limits.</div>
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
        </div>

        <!-- Limits & Delays Sidebar -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-transparent py-3">
                    <i class="fa-solid fa-shield-halved me-2 text-primary"></i> <strong>Limits & Delays</strong>
                </div>
                <div class="card-body">
                    <div class="mb-3" id="replyDelayGroup">
                        <label class="form-label small fw-semibold">Reply Delay (in seconds)</label>
                        <input type="number" name="reply_delay" id="reply_delay" class="form-control" min="0" max="86400" value="<?= $settings->reply_delay ?>">
                        <div class="form-text small">Set <strong>0</strong> for instant sending without delay.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Per-Thread Reply Limit</label>
                        <input type="number" name="max_reply_per_thread" class="form-control" min="1" max="50" value="<?= $settings->max_reply_per_thread ?>">
                        <div class="form-text small">Maximum automated replies allowed per conversation (e.g. 4 for 4-step sequence).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Reply Limit</label>
                        <input type="number" name="daily_reply_limit" class="form-control" min="1" max="1000" value="<?= $settings->daily_reply_limit ?>">
                        <div class="form-text small">Maximum automated replies per day from this account.</div>
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

<script>
let lastFocusedTextarea = document.getElementById('reply_message_1');

document.querySelectorAll('.reply-textarea').forEach(el => {
    el.addEventListener('focus', function() {
        lastFocusedTextarea = this;
    });
});

document.querySelectorAll('.variable-badge').forEach(badge => {
    badge.addEventListener('click', function() {
        const variable = this.getAttribute('data-variable');
        if (lastFocusedTextarea) {
            const start = lastFocusedTextarea.selectionStart || 0;
            const end = lastFocusedTextarea.selectionEnd || 0;
            const text = lastFocusedTextarea.value;
            lastFocusedTextarea.value = text.substring(0, start) + variable + text.substring(end);
            lastFocusedTextarea.focus();
            lastFocusedTextarea.selectionStart = lastFocusedTextarea.selectionEnd = start + variable.length;
        }
    });
});

function toggleScheduleMode(mode) {
    const customSection = document.getElementById('customScheduleSection');
    const delayInput = document.getElementById('reply_delay');
    const cardInstant = document.getElementById('cardModeInstant');
    const cardCustom = document.getElementById('cardModeCustom');

    if (mode === 'instant') {
        customSection.classList.add('d-none');
        if (delayInput) delayInput.value = '0';
        cardInstant.classList.add('border-primary', 'bg-primary-subtle');
        cardCustom.classList.remove('border-primary', 'bg-primary-subtle');
    } else {
        customSection.classList.remove('d-none');
        cardCustom.classList.add('border-primary', 'bg-primary-subtle');
        cardInstant.classList.remove('border-primary', 'bg-primary-subtle');
    }
}
</script>
