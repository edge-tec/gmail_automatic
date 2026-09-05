<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= url('/campaigns/' . $campaign->id) ?>" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Campaign
        </a>
        <span class="text-muted small">/</span>
        <span class="text-muted small">Edit #<?= $campaign->id ?></span>
    </div>
    <h4 class="fw-bold mt-2 mb-1"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Campaign: <?= e($campaign->name) ?></h4>
    <p class="text-muted small mb-0">Update sending parameters, schedule window, delay throttle, or message variations.</p>
</div>

<form action="<?= url('/campaigns/' . $campaign->id . '/edit') ?>" method="POST" enctype="multipart/form-data" id="campaignEditForm">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column: Details & Messages -->
        <div class="col-12 col-lg-7">
            <!-- 1. Campaign Details -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>1. Campaign Details</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($campaign->name) ?>" required>
                    </div>

                    <!-- Append More Recipients (Optional) -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Append More Recipients (Optional)</label>
                        <input type="file" name="recipient_file" class="form-control" accept=".txt,.csv,.xlsx">
                        <div class="form-text small mt-1">
                            Leave empty if you don't need to add new leads. Uploading a file will append new valid recipients without removing existing ones.
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Message Variations -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-envelope-open-text text-warning me-2"></i>2. Message Variations</h6>
                        <small class="text-muted">Active variations will be randomly cycled for each recipient.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addVariationBtn">
                        <i class="fa-solid fa-plus me-1"></i> Add Variation
                    </button>
                </div>
                <div class="card-body pt-0">
                    <!-- Personalization Tag Chips -->
                    <div class="mb-3 p-2 bg-light rounded-3 border">
                        <span class="small fw-bold text-muted d-block mb-1">Click to insert personalization tag:</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{first_name}}')">{{first_name}}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{last_name}}')">{{last_name}}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{company}}')">{{company}}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{email}}')">{{email}}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{custom_field_1}}')">{{custom_field_1}}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" onclick="insertTag('{{custom_field_2}}')">{{custom_field_2}}</button>
                        </div>
                    </div>

                    <div id="variationsContainer">
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $idx => $msg): ?>
                                <div class="card border p-3 mb-3 bg-light variation-card" id="variation_<?= $idx ?>">
                                    <input type="hidden" name="message_ids[]" value="<?= $msg->id ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small text-dark"><i class="fa-solid fa-shuffle text-warning me-1"></i> Variation #<?= $idx + 1 ?></span>
                                        <span class="badge bg-secondary-subtle text-dark border"><?= $msg->sends_count ?> sent</span>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="subjects[]" class="form-control form-control-sm mb-2" value="<?= e($msg->subject) ?>" placeholder="Subject line" required>
                                        <textarea name="bodies[]" class="form-control form-control-sm active-editor" rows="4" placeholder="Message body..." required><?= e($msg->body) ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="card border p-3 mb-3 bg-light variation-card" id="variation_0">
                                <input type="hidden" name="message_ids[]" value="0">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small text-dark"><i class="fa-solid fa-shuffle text-warning me-1"></i> Variation #1</span>
                                </div>
                                <div class="mb-2">
                                    <input type="text" name="subjects[]" class="form-control form-control-sm mb-2" placeholder="Subject line" required>
                                    <textarea name="bodies[]" class="form-control form-control-sm active-editor" rows="4" placeholder="Message body..." required></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Rate Limits & Schedule -->
        <div class="col-12 col-lg-5">
            <!-- 3. Rate Limits & Throttle -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-gauge-high text-info me-2"></i>3. Rate Limits &amp; Throttle</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Campaign Limit (Total across all Gmail accounts)</label>
                        <div class="input-group">
                            <input type="number" name="daily_campaign_limit" class="form-control" value="<?= (int)$campaign->daily_campaign_limit ?>" min="1" max="10000" required>
                            <span class="input-group-text small text-muted">emails/day</span>
                        </div>
                        <div class="form-text small">Combined with your Per-Gmail daily limits to enforce safe sending capacity.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sending Interval (Delay between sends)</label>
                        <div class="input-group">
                            <input type="number" name="sending_interval" class="form-control" value="<?= (int)$campaign->sending_interval ?>" min="5" max="3600" required>
                            <span class="input-group-text small text-muted">seconds</span>
                        </div>
                        <div class="d-flex gap-1 mt-2">
                            <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" onclick="document.querySelector('input[name=sending_interval]').value=5">5s (Fast)</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="document.querySelector('input[name=sending_interval]').value=10">10s</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="document.querySelector('input[name=sending_interval]').value=30">30s</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="document.querySelector('input[name=sending_interval]').value=60">1m</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="document.querySelector('input[name=sending_interval]').value=300">5m</button>
                        </div>
                    </div>

                    <!-- Connected Accounts Overview -->
                    <div class="p-3 bg-light rounded-3 border mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-semibold text-dark"><i class="fa-brands fa-google text-danger me-1"></i> Connected Gmail Accounts</span>
                            <a href="<?= url('/campaigns/accounts') ?>" target="_blank" class="small text-decoration-none">Configure Limits <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </div>
                        <?php foreach ($accounts as $acc): ?>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                <span class="small text-truncate" style="max-width: 180px;"><?= e($acc->gmail_email) ?></span>
                                <span class="badge bg-secondary-subtle text-dark border"><?= $acc->bulk_daily_limit ?>/day</span>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-text small mt-2" style="font-size: 0.75rem;">
                            Emails will cycle through connected accounts via <strong>True Round-Robin</strong>.
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Schedule & Timezone -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="fa-regular fa-clock text-success me-2"></i>4. Schedule &amp; Timezone</h6>
                </div>
                <div class="card-body pt-0">
                    <?php 
                        $isInstant = ($campaign->start_time === '00:00' && $campaign->end_time === '23:59') || empty($campaign->start_time);
                    ?>
                    <!-- Sending Mode Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sending Mode</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-2 rounded-3 border bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="schedule_mode" id="modeInstant" value="instant" <?= $isInstant ? 'checked' : '' ?> onchange="toggleScheduleMode()">
                                <label class="form-check-label small fw-bold text-dark cursor-pointer" for="modeInstant">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i> Instant Send (No Schedule / 24/7)
                                </label>
                                <div class="small text-muted ms-4" style="font-size: 0.78rem;">
                                    Runs 24 hours non-stop without hour restrictions.
                                </div>
                            </div>
                            <div class="form-check p-2 rounded-3 border bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="schedule_mode" id="modeCustom" value="custom" <?= !$isInstant ? 'checked' : '' ?> onchange="toggleScheduleMode()">
                                <label class="form-check-label small fw-bold text-dark cursor-pointer" for="modeCustom">
                                    <i class="fa-regular fa-clock text-primary me-1"></i> Custom Schedule Window
                                </label>
                                <div class="small text-muted ms-4" style="font-size: 0.78rem;">
                                    Only send emails during specific hours of the day.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Schedule Pickers -->
                    <div id="customScheduleSection" style="<?= $isInstant ? 'display: none;' : '' ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Start Time</label>
                                <input type="time" name="start_time" id="startTimeInput" class="form-control" value="<?= e($campaign->start_time ?: '00:00') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">End Time</label>
                                <input type="time" name="end_time" id="endTimeInput" class="form-control" value="<?= e($campaign->end_time ?: '23:59') ?>" required>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.78rem;" onclick="setSchedule('00:00', '23:59')">
                                <i class="fa-solid fa-infinity me-1"></i> 24 Hours (All Day)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.78rem;" onclick="setSchedule('09:00', '18:00')">
                                <i class="fa-solid fa-briefcase me-1"></i> 9 AM – 6 PM
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php foreach ($timezones as $tz): ?>
                                <option value="<?= e($tz) ?>" <?= ($campaign->timezone ?: 'Asia/Dhaka') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Daily quota counter resets at midnight in this timezone.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Campaign Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $campaign->status === 'active' ? 'selected' : '' ?>>Active (Send immediately)</option>
                            <option value="paused" <?= $campaign->status === 'paused' ? 'selected' : '' ?>>Paused (Hold sending)</option>
                            <option value="draft" <?= $campaign->status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm py-3 fw-bold">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save Campaign Changes
            </button>
        </div>
    </div>
</form>

<script>
let variationCount = <?= count($messages) ?>;
let lastFocusedTextarea = document.querySelector('textarea.active-editor');

document.querySelectorAll('textarea').forEach(el => {
    el.addEventListener('focus', function() {
        lastFocusedTextarea = this;
    });
});

function insertTag(tag) {
    if (!lastFocusedTextarea) {
        lastFocusedTextarea = document.querySelector('textarea.active-editor') || document.querySelector('textarea');
    }
    if (!lastFocusedTextarea) return;
    const start = lastFocusedTextarea.selectionStart;
    const end = lastFocusedTextarea.selectionEnd;
    const text = lastFocusedTextarea.value;
    lastFocusedTextarea.value = text.substring(0, start) + tag + text.substring(end);
    lastFocusedTextarea.focus();
    lastFocusedTextarea.selectionStart = lastFocusedTextarea.selectionEnd = start + tag.length;
}

document.getElementById('addVariationBtn').addEventListener('click', function() {
    variationCount++;
    const container = document.getElementById('variationsContainer');
    const div = document.createElement('div');
    div.className = 'card border p-3 mb-3 bg-light variation-card';
    div.id = 'variation_' + variationCount;
    div.innerHTML = `
        <input type="hidden" name="message_ids[]" value="0">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold small text-dark"><i class="fa-solid fa-shuffle text-warning me-1"></i> Variation #${variationCount} (New)</span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="document.getElementById('variation_${variationCount}').remove()">
                <i class="fa-solid fa-trash-can me-1"></i> Remove
            </button>
        </div>
        <div class="mb-2">
            <input type="text" name="subjects[]" class="form-control form-control-sm mb-2" placeholder="Subject for this variation" required>
            <textarea name="bodies[]" class="form-control form-control-sm active-editor" rows="4" placeholder="Variation message body..." required></textarea>
        </div>
    `;
    container.appendChild(div);

    div.querySelector('textarea').addEventListener('focus', function() {
        lastFocusedTextarea = this;
    });
});

function setSchedule(start, end) {
    document.getElementById('startTimeInput').value = start;
    document.getElementById('endTimeInput').value = end;
}

function toggleScheduleMode() {
    const isInstant = document.getElementById('modeInstant').checked;
    const section = document.getElementById('customScheduleSection');
    if (isInstant) {
        section.style.display = 'none';
        document.getElementById('startTimeInput').value = '00:00';
        document.getElementById('endTimeInput').value = '23:59';
    } else {
        section.style.display = 'block';
        if (document.getElementById('startTimeInput').value === '00:00' && document.getElementById('endTimeInput').value === '23:59') {
            document.getElementById('startTimeInput').value = '09:00';
            document.getElementById('endTimeInput').value = '18:00';
        }
    }
}
</script>
