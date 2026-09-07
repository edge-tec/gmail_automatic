<div class="mb-4">
    <a href="<?= url('/campaigns') ?>" class="text-decoration-none text-muted small">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Campaigns
    </a>
    <h4 class="fw-bold mt-2 mb-1"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Create Bulk Email Campaign</h4>
    <p class="text-muted small mb-0">Configure your campaign parameters, upload recipients list, and set up dynamic message variations.</p>
</div>

<form action="<?= url('/campaigns/create') ?>" method="POST" enctype="multipart/form-data" id="campaignForm">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column: Basic Info & File Upload -->
        <div class="col-12 col-lg-7">
            <!-- 1. Campaign Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>1. Campaign Details</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. September Product Outreach" required>
                    </div>

                    <!-- Multiple File Upload & Drag-and-Drop Zone -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">
                            Recipient Lead Files (.txt, .csv, .xlsx) <span class="text-danger">*</span>
                        </label>

                        <!-- Drag and Drop Box -->
                        <div class="lead-dropzone-box p-4 border border-2 border-dashed rounded-3 text-center position-relative" id="leadDropzone" style="border-color: #cbd5e1 !important; background-color: #f8fafc; cursor: pointer; transition: all 0.2s ease-in-out;">
                            <input type="file" name="recipient_files[]" id="recipientFileInput" class="d-none" accept=".txt,.csv,.xlsx" multiple>
                            <div class="dropzone-content py-2" style="pointer-events: none;">
                                <div class="mb-2">
                                    <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-10 text-primary mb-1">
                                        <i class="fa-solid fa-cloud-arrow-up fs-2"></i>
                                    </div>
                                </div>
                                <h6 class="fw-bold mb-1 text-dark">Drag &amp; drop multiple lead files here</h6>
                                <p class="text-muted small mb-2">or <span class="text-primary fw-semibold text-decoration-underline" style="cursor: pointer;">browse files</span> from your device</p>
                                <div class="d-flex justify-content-center gap-1 flex-wrap">
                                    <span class="badge bg-white text-dark border"><i class="fa-solid fa-file-csv text-success me-1"></i> .csv</span>
                                    <span class="badge bg-white text-dark border"><i class="fa-solid fa-file-excel text-success me-1"></i> .xlsx</span>
                                    <span class="badge bg-white text-dark border"><i class="fa-solid fa-file-lines text-secondary me-1"></i> .txt</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-layer-group me-1"></i> Multiple files supported</span>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Files Preview List -->
                        <div id="fileListContainer" class="d-none mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-bold text-dark" id="fileCountSummary">0 files selected</span>
                                <button type="button" class="btn btn-link text-danger btn-xs p-0 text-decoration-none small" id="clearFilesBtn">
                                    <i class="fa-solid fa-trash-can me-1"></i> Clear all
                                </button>
                            </div>
                            <div class="list-group list-group-flush border rounded-3 bg-white shadow-sm overflow-auto" id="selectedFilesList" style="max-height: 220px;">
                            </div>
                        </div>

                        <div class="form-text small mt-2">
                            <span class="text-muted">
                                <strong>Supported columns:</strong> <code>email</code>, <code>first_name</code>, <code>last_name</code>, <code>company</code>, <code>custom_field_1</code>, <code>custom_field_2</code>. Duplicate emails across files are automatically filtered.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Message Variations Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-envelope-open-text text-warning me-2"></i>2. Message Variations</h6>
                        <small class="text-muted">A message will be randomly selected for each recipient from active variations.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addVariationBtn">
                        <i class="fa-solid fa-plus me-1"></i> Add Variation
                    </button>
                </div>
                <div class="card-body pt-0">
                    <!-- Variable Tags Helper -->
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="small fw-semibold mb-2 text-dark"><i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Available Personalization Variables:</div>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{first_name}}')">{{first_name}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{last_name}}')">{{last_name}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{company}}')">{{company}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{email}}')">{{email}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{custom_field_1}}')">{{custom_field_1}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{custom_field_2}}')">{{custom_field_2}}</span>
                            <span class="badge bg-white text-dark border tag-badge" style="cursor: pointer;" onclick="insertTag('{{date}}')">{{date}}</span>
                        </div>
                        <div class="form-text small mt-1 text-muted" style="font-size: 0.75rem;">
                            Click a variable tag to copy or insert. Missing recipient values are safely replaced with an empty string.
                        </div>
                    </div>

                    <!-- Global Default Message -->
                    <div class="mb-4 pb-3 border-bottom">
                        <label class="form-label small fw-semibold">Global Subject Line <span class="text-danger">*</span></label>
                        <input type="text" name="global_subject" class="form-control mb-3" placeholder="e.g. Quick question regarding {{company}}" required>

                        <label class="form-label small fw-semibold">Global Message Body <span class="text-danger">*</span></label>
                        <textarea name="global_message" id="activeEditor" class="form-control" rows="5" placeholder="Hello {{first_name}},&#10;&#10;We would like to introduce our service to {{company}}.&#10;&#10;Best regards," required></textarea>
                    </div>

                    <!-- Variations Container -->
                    <div id="variationsContainer">
                        <!-- Dynamic variation cards will be inserted here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Sending Parameters, Limits & Schedule -->
        <div class="col-12 col-lg-5">
            <!-- 3. Sending Parameters & Limits -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-gauge text-info me-2"></i>3. Rate Limits &amp; Throttle</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Daily Campaign Limit (Total across all Gmail accounts)</label>
                        <div class="input-group">
                            <input type="number" name="daily_campaign_limit" class="form-control" value="300" min="1" max="10000" required>
                            <span class="input-group-text small text-muted">emails/day</span>
                        </div>
                        <div class="form-text small">Combined with your Per-Gmail daily limits to enforce safe sending capacity.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sending Interval (Delay between sends)</label>
                        <div class="input-group">
                            <input type="number" name="sending_interval" class="form-control" value="60" min="5" max="3600" required>
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
                            Emails will cycle through connected accounts via <strong>True Round-Robin</strong> (1 email per Gmail turn).
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
                    <!-- Sending Mode Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sending Mode</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-2 rounded-3 border bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="schedule_mode" id="modeInstant" value="instant" checked onchange="toggleScheduleMode()">
                                <label class="form-check-label small fw-bold text-dark cursor-pointer" for="modeInstant">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i> Instant Send (No Schedule / 24/7)
                                </label>
                                <div class="small text-muted ms-4" style="font-size: 0.78rem;">
                                    Starts immediately after creation. Runs 24 hours non-stop without hour restrictions.
                                </div>
                            </div>
                            <div class="form-check p-2 rounded-3 border bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="schedule_mode" id="modeCustom" value="custom" onchange="toggleScheduleMode()">
                                <label class="form-check-label small fw-bold text-dark cursor-pointer" for="modeCustom">
                                    <i class="fa-regular fa-clock text-primary me-1"></i> Custom Schedule Window
                                </label>
                                <div class="small text-muted ms-4" style="font-size: 0.78rem;">
                                    Only send emails during specific hours of the day (e.g. 9 AM to 6 PM).
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Schedule Pickers (Hidden in Instant Mode) -->
                    <div id="customScheduleSection" style="display: none;">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Start Time</label>
                                <input type="time" name="start_time" id="startTimeInput" class="form-control" value="00:00" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">End Time</label>
                                <input type="time" name="end_time" id="endTimeInput" class="form-control" value="23:59" required>
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
                                <option value="<?= e($tz) ?>" <?= $tz === 'Asia/Dhaka' ? 'selected' : '' ?>><?= e($tz) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Daily quota counter resets at midnight in this timezone.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active (Start sending immediately)</option>
                            <option value="draft">Draft (Save now, launch manually later)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Anti-Abuse & Zero Fallback Warning -->
            <div class="card border-0 bg-primary bg-opacity-10 text-primary p-3 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <i class="fa-solid fa-shield-halved fs-5 mt-1"></i>
                    <div class="small">
                        <strong>Anti-Abuse &amp; Zero Fallback Policy:</strong> The engine will strictly send only the user-configured message variations. If messages are deleted or empty, no emails will ever be dispatched. All sends include one-click unsubscribe headers.
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm py-3 fw-bold">
                <i class="fa-solid fa-rocket me-2"></i> Launch Bulk Campaign
            </button>
        </div>
    </div>
</form>

<script>
let variationCount = 0;
let lastFocusedTextarea = document.getElementById('activeEditor');

document.querySelectorAll('textarea').forEach(el => {
    el.addEventListener('focus', function() {
        lastFocusedTextarea = this;
    });
});

function insertTag(tag) {
    if (!lastFocusedTextarea) {
        lastFocusedTextarea = document.getElementById('activeEditor');
    }
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
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold small text-dark"><i class="fa-solid fa-shuffle text-warning me-1"></i> Variation #${variationCount + 1}</span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="document.getElementById('variation_${variationCount}').remove()">
                <i class="fa-solid fa-trash-can me-1"></i> Remove
            </button>
        </div>
        <div class="mb-2">
            <input type="text" name="subjects[]" class="form-control form-control-sm mb-2" placeholder="Subject for this variation (optional, defaults to global)">
            <textarea name="bodies[]" class="form-control form-control-sm" rows="4" placeholder="Variation message body..."></textarea>
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

// ----------------------------------------------------
// Drag & Drop Multiple File Uploader
// ----------------------------------------------------
const dropzone = document.getElementById('leadDropzone');
const fileInput = document.getElementById('recipientFileInput');
const fileListContainer = document.getElementById('fileListContainer');
const selectedFilesList = document.getElementById('selectedFilesList');
const fileCountSummary = document.getElementById('fileCountSummary');
const clearFilesBtn = document.getElementById('clearFilesBtn');

let dt = new DataTransfer();

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateFileListUI() {
    selectedFilesList.innerHTML = '';
    const files = dt.files;

    if (files.length === 0) {
        fileListContainer.classList.add('d-none');
        fileInput.files = dt.files;
        return;
    }

    fileListContainer.classList.remove('d-none');
    let totalSize = 0;
    for (let i = 0; i < files.length; i++) {
        totalSize += files[i].size;
    }
    fileCountSummary.textContent = `${files.length} file${files.length > 1 ? 's' : ''} selected (${formatBytes(totalSize)})`;

    Array.from(files).forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3';

        let iconClass = 'fa-file-lines text-secondary';
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'csv') iconClass = 'fa-file-csv text-success';
        else if (ext === 'xlsx') iconClass = 'fa-file-excel text-success';

        item.innerHTML = `
            <div class="d-flex align-items-center gap-2 text-truncate me-2">
                <i class="fa-solid ${iconClass} fs-5"></i>
                <div class="text-truncate">
                    <span class="fw-semibold text-dark small d-block text-truncate" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                    <span class="text-muted" style="font-size: 0.75rem;">${formatBytes(file.size)}</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" title="Remove file">
                <i class="fa-solid fa-xmark fs-5"></i>
            </button>
        `;

        item.querySelector('button').addEventListener('click', function(e) {
            e.stopPropagation();
            removeFile(index);
        });

        selectedFilesList.appendChild(item);
    });

    fileInput.files = dt.files;
}

function addFiles(newFiles) {
    const allowed = ['txt', 'csv', 'xlsx'];
    for (let i = 0; i < newFiles.length; i++) {
        const file = newFiles[i];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) {
            alert(`File "${file.name}" has an unsupported format. Allowed formats: .txt, .csv, .xlsx`);
            continue;
        }

        // Avoid adding duplicate files with same name and size
        let isDup = false;
        for (let j = 0; j < dt.items.length; j++) {
            const existing = dt.items[j].getAsFile();
            if (existing && existing.name === file.name && existing.size === file.size) {
                isDup = true;
                break;
            }
        }

        if (!isDup) {
            dt.items.add(file);
        }
    }
    updateFileListUI();
}

function removeFile(index) {
    const newDt = new DataTransfer();
    for (let i = 0; i < dt.files.length; i++) {
        if (i !== index) {
            newDt.items.add(dt.files[i]);
        }
    }
    dt = newDt;
    updateFileListUI();
}

if (clearFilesBtn) {
    clearFilesBtn.addEventListener('click', function() {
        dt = new DataTransfer();
        updateFileListUI();
    });
}

// Click anywhere on dropzone to browse
dropzone.addEventListener('click', function() {
    fileInput.click();
});

fileInput.addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        addFiles(e.target.files);
    }
});

// Drag & Drop events with animation
['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = '#2563eb';
        dropzone.style.backgroundColor = '#eff6ff';
        dropzone.style.transform = 'scale(1.01)';
    }, false);
});

['dragleave', 'dragend', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.backgroundColor = '#f8fafc';
        dropzone.style.transform = 'scale(1)';
    }, false);
});

dropzone.addEventListener('drop', function(e) {
    const dropped = e.dataTransfer.files;
    if (dropped && dropped.length > 0) {
        addFiles(dropped);
    }
});

// Form submission validation
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    if (!dt || dt.files.length === 0) {
        e.preventDefault();
        alert('Please upload at least one recipient lead file (.txt, .csv, or .xlsx).');
        dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        dropzone.style.borderColor = '#dc2626';
        dropzone.style.backgroundColor = '#fef2f2';
        setTimeout(() => {
            dropzone.style.borderColor = '#cbd5e1';
            dropzone.style.backgroundColor = '#f8fafc';
        }, 1500);
        return false;
    }
});
</script>
