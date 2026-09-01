<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-magnifying-glass-chart text-primary me-2"></i> SEO Management &amp; Audit</h1>
        <p class="text-muted small mb-0">Google SEO, AI Search Optimization, Structured Data, and Health Audits</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/sitemap.xml') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-sitemap me-1"></i> View Sitemap
        </a>
        <a href="<?= url('/robots.txt') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-robot me-1"></i> View Robots.txt
        </a>
        <a href="<?= url('/admin/seo/global') ?>" class="btn btn-primary btn-sm fw-bold">
            <i class="fa-solid fa-gear me-1"></i> Global Settings
        </a>
    </div>
</div>

<!-- SEO Navigation Tabs -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill flex-column flex-sm-row gap-1">
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo') ?>"><i class="fa-solid fa-gauge-high me-1"></i> Overview &amp; Audit</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/global') ?>"><i class="fa-solid fa-earth-americas me-1"></i> Global SEO</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/pages') ?>"><i class="fa-solid fa-file-lines me-1"></i> Pages (<?= $pagesCount ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/faqs') ?>"><i class="fa-solid fa-circle-question me-1"></i> FAQs (<?= $faqsCount ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog (<?= $blogsCount ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects (<?= $redirectsCount ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<!-- Top Stats Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 text-center">
            <div class="text-muted small text-uppercase fw-bold">Overall SEO Score</div>
            <div class="display-6 fw-bold <?= $audit['score'] >= 80 ? 'text-success' : ($audit['score'] >= 60 ? 'text-warning' : 'text-danger') ?>">
                <?= $audit['score'] ?>%
            </div>
            <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar <?= $audit['score'] >= 80 ? 'bg-success' : ($audit['score'] >= 60 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= $audit['score'] ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 text-center">
            <div class="text-muted small text-uppercase fw-bold">Indexable Pages</div>
            <div class="display-6 fw-bold text-primary"><?= $indexableCount ?></div>
            <div class="small text-muted">of <?= $pagesCount ?> total routes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 text-center">
            <div class="text-muted small text-uppercase fw-bold">Critical Issues</div>
            <div class="display-6 fw-bold <?= $audit['critical_count'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= $audit['critical_count'] ?>
            </div>
            <div class="small text-muted"><?= $audit['critical_count'] > 0 ? 'Needs Attention' : 'All Clear' ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 text-center">
            <div class="text-muted small text-uppercase fw-bold">Active FAQs &amp; Schema</div>
            <div class="display-6 fw-bold text-info"><?= $faqsCount ?></div>
            <div class="small text-muted">Rich FAQPage schema</div>
        </div>
    </div>
</div>

<!-- SEO Health Audit Report -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-stethoscope text-primary me-2"></i> Automated SEO Health Audit</h2>
        <span class="badge bg-light text-secondary border">Audited: <?= date('M d, Y H:i', strtotime($audit['audited_at'])) ?></span>
    </div>
    <div class="card-body p-4">
        <!-- Critical Issues Section -->
        <?php if (!empty($audit['critical'])): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-circle-exclamation me-1"></i> Critical Issues (<?= count($audit['critical']) ?>)</h6>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($audit['critical'] as $c): ?>
                <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-danger me-2"><?= e($c['type']) ?></span>
                        <strong class="text-dark"><?= e($c['item']) ?></strong>
                        <div class="small text-danger mt-1">Recommended: <?= e($c['solution']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warnings Section -->
        <?php if (!empty($audit['warnings'])): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-warning mb-3"><i class="fa-solid fa-triangle-exclamation me-1"></i> Opportunities &amp; Warnings (<?= count($audit['warnings']) ?>)</h6>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($audit['warnings'] as $w): ?>
                <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-warning text-dark me-2"><?= e($w['type']) ?></span>
                        <span class="text-dark fw-semibold"><?= e($w['item']) ?></span>
                        <?php if (isset($w['solution'])): ?>
                        <div class="small text-muted mt-1">Suggestion: <?= e($w['solution']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Passed Checks Section -->
        <div>
            <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-circle-check me-1"></i> Passed SEO Checks (<?= count($audit['passed']) ?>)</h6>
            <div class="row g-2">
                <?php foreach ($audit['passed'] as $p): ?>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-check text-success"></i>
                        <span class="text-dark small"><strong><?= e($p['type']) ?>:</strong> <?= e($p['item']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
