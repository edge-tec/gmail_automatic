<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-earth-americas text-primary me-2"></i> Global SEO &amp; Entity Configuration</h1>
        <p class="text-muted small mb-0">Define site-wide metadata fallbacks, Organization Schema, Google Search Console, and Analytics</p>
    </div>
    <div>
        <a href="<?= url('/admin/seo') ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to SEO Dashboard</a>
    </div>
</div>

<!-- SEO Navigation Tabs -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill flex-column flex-sm-row gap-1">
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo') ?>"><i class="fa-solid fa-gauge-high me-1"></i> Overview &amp; Audit</a></li>
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/global') ?>"><i class="fa-solid fa-earth-americas me-1"></i> Global SEO</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/pages') ?>"><i class="fa-solid fa-file-lines me-1"></i> Pages</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/faqs') ?>"><i class="fa-solid fa-circle-question me-1"></i> FAQs</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<form action="<?= url('/admin/seo/global') ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Global Defaults -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i> Global Metadata Defaults</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Website Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? 'Gmail Auto Reply & Follow-up') ?>" required>
                        <div class="form-text small">Used in title suffixes, Organization Schema, and OpenGraph.</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Canonical Base URL</label>
                        <input type="url" name="site_url" class="form-control" value="<?= e($settings['site_url'] ?? url('/')) ?>" placeholder="https://yourdomain.com">
                        <div class="form-text small">Production base domain for absolute canonicals and sitemaps.</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Default SEO Title</label>
                        <input type="text" name="default_title" class="form-control" value="<?= e($settings['default_title'] ?? 'Gmail Auto Reply & Follow-up Automation Software') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Default Meta Description</label>
                        <textarea name="default_description" class="form-control" rows="3"><?= e($settings['default_description'] ?? 'Scale your outreach with official Google API auto reply, multi-step sequential follow-ups, and duplicate traffic protection.') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Default Focus Keywords</label>
                        <input type="text" name="default_keywords" class="form-control" value="<?= e($settings['default_keywords'] ?? 'Gmail auto reply, Gmail automation, email follow up, Gmail API software') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Media & Entity Information -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-building text-success me-2"></i> Organization Entity Details (Schema.org)</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Organization / Brand Name</label>
                        <input type="text" name="organization_name" class="form-control" value="<?= e($settings['organization_name'] ?? 'Gmail Automation Platform') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Customer Support Email</label>
                        <input type="email" name="support_email" class="form-control" value="<?= e($settings['support_email'] ?? 'support@2xbets.net') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Customer Support Phone / WhatsApp</label>
                        <input type="text" name="support_phone" class="form-control" value="<?= e($settings['support_phone'] ?? '+8801611195794') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Headquarters Address</label>
                        <input type="text" name="organization_address" class="form-control" value="<?= e($settings['organization_address'] ?? 'Dhaka, Bangladesh') ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-brands fa-google text-danger me-2"></i> Google Search Console &amp; Analytics</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Google Search Console Verification Code</label>
                        <input type="text" name="gsc_verification_code" class="form-control font-monospace" placeholder="e.g. google1234567890abcdef" value="<?= e($settings['gsc_verification_code'] ?? '') ?>">
                        <div class="form-text small">Meta tag verification string for Search Console ownership verification.</div>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Google Analytics Measurement ID</label>
                        <input type="text" name="google_analytics_id" class="form-control font-monospace" placeholder="G-XXXXXXXXXX" value="<?= e($settings['google_analytics_id'] ?? '') ?>">
                        <div class="form-text small">Only loaded on public pages. Automatically omitted on private dashboard routes.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save Global SEO Settings
            </button>
        </div>
    </div>
</form>
