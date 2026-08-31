<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Auto Reply Settings</h4>
        <p class="text-muted small mb-0">Configure instant reply messages, delay intervals, daily caps, and schedule constraints.</p>
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
        <!-- Main Message Card -->
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-envelope-open-text text-primary"></i>
                        <span>Default Auto Reply Message</span>
                    </span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="auto_reply_enabled" value="1" id="auto_reply_enabled" <?= $settings->auto_reply_enabled ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold ms-1" for="auto_reply_enabled">Enable Auto Reply</label>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Variable Helper Tags -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Click variables to insert into your template:</label>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="variable-badge" data-variable="{{first_name}}" data-target="reply_message"><i class="fa-solid fa-plus me-1"></i>{{first_name}}</span>
                            <span class="variable-badge" data-variable="{{last_name}}" data-target="reply_message"><i class="fa-solid fa-plus me-1"></i>{{last_name}}</span>
                            <span class="variable-badge" data-variable="{{sender_email}}" data-target="reply_message"><i class="fa-solid fa-plus me-1"></i>{{sender_email}}</span>
                            <span class="variable-badge" data-variable="{{subject}}" data-target="reply_message"><i class="fa-solid fa-plus me-1"></i>{{subject}}</span>
                            <span class="variable-badge" data-variable="{{date}}" data-target="reply_message"><i class="fa-solid fa-plus me-1"></i>{{date}}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reply Message Body</label>
                        <textarea name="reply_message" id="reply_message" rows="8" class="form-control font-monospace" style="font-size: 0.9rem;" placeholder="Type your automated reply message here..."><?= e($settings->reply_message) ?></textarea>
                        <div class="form-text small text-muted">
                            Replies will automatically preserve the Gmail thread context and recipient headers.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Working Hours & Schedule -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-business-time me-2 text-primary"></i> Working Schedule & Timezone
                </div>
                <div class="card-body">
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
                            <input type="time" name="working_start" class="form-control" value="<?= e($settings->working_start) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">End Time</label>
                            <input type="time" name="working_end" class="form-control" value="<?= e($settings->working_end) ?>">
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
                                <input class="form-check-input" type="checkbox" name="working_days[]" value="<?= $day ?>" id="day_<?= $day ?>" <?= in_array($day, $activeDays) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="day_<?= $day ?>"><?= $day ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limits & Delays Sidebar -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fa-solid fa-shield-halved me-2 text-primary"></i> Limits & Safe Sending
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Per-Thread Reply Limit</label>
                        <input type="number" name="max_reply_per_thread" class="form-control" min="1" max="50" value="<?= $settings->max_reply_per_thread ?>">
                        <div class="form-text small">Maximum automated replies sent in the same conversation thread.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Reply Limit</label>
                        <input type="number" name="daily_reply_limit" class="form-control" min="1" max="1000" value="<?= $settings->daily_reply_limit ?>">
                        <div class="form-text small">Maximum automated replies per day from this account.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reply Delay (in seconds)</label>
                        <input type="number" name="reply_delay" class="form-control" min="0" max="86400" value="<?= $settings->reply_delay ?>">
                        <div class="form-text small">Set 0 for instant reply, or e.g. 120 for 2 minutes delay.</div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-semibold m-0">Follow-up Automation</label>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="followup_enabled" value="1" id="followup_enabled" <?= $settings->followup_enabled ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="form-text small">Enable multi-step follow-up sequences for unanswered threads.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Follow-up Limit</label>
                        <input type="number" name="daily_followup_limit" class="form-control" min="1" max="1000" value="<?= $settings->daily_followup_limit ?>">
                    </div>
                </div>
            </div>

            <div class="card p-3 bg-light">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Settings
                </button>
            </div>
        </div>
    </div>
</form>
