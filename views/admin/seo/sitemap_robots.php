<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-network-wired text-primary me-2"></i> XML Sitemap &amp; Robots.txt Manager</h1>
        <p class="text-muted small mb-0">Inspect dynamically generated sitemaps and customize search crawler crawling directives</p>
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
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/global') ?>"><i class="fa-solid fa-earth-americas me-1"></i> Global SEO</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/pages') ?>"><i class="fa-solid fa-file-lines me-1"></i> Pages</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/faqs') ?>"><i class="fa-solid fa-circle-question me-1"></i> FAQs</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <!-- Robots.txt Editor -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-robot text-primary me-2"></i> Robots.txt Configuration</h2>
                <a href="<?= url('/robots.txt') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-external-link me-1"></i> Live File
                </a>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/seo/robots') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Custom Robots.txt (Leave blank to use dynamic default)</label>
                        <textarea name="custom_robots_txt" class="form-control font-monospace small" rows="14" placeholder="<?= e($robotsTxt) ?>"><?= e($customRobots) ?></textarea>
                        <div class="form-text small">Ensure private paths (<code>/admin</code>, <code>/dashboard</code>) remain blocked.</div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Robots.txt
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Sitemap XML Preview -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-sitemap text-success me-2"></i> Live Dynamic XML Sitemap Preview</h2>
                <a href="<?= url('/sitemap.xml') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-external-link me-1"></i> View /sitemap.xml
                </a>
            </div>
            <div class="card-body p-4">
                <p class="small text-secondary mb-2">Generated automatically from all active indexable pages and published blog articles:</p>
                <textarea class="form-control font-monospace small bg-light" rows="15" readonly><?= e($sitemapXml) ?></textarea>
            </div>
        </div>
    </div>
</div>
