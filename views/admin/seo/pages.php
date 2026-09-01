<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-file-lines text-primary me-2"></i> Page-Level SEO Manager</h1>
        <p class="text-muted small mb-0">Configure targeted titles, meta descriptions, focus keywords, canonicals, and indexability for each route</p>
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
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/pages') ?>"><i class="fa-solid fa-file-lines me-1"></i> Pages (<?= count($pages) ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/faqs') ?>"><i class="fa-solid fa-circle-question me-1"></i> FAQs</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-bottom py-3">
        <h2 class="h5 fw-bold text-dark mb-0">Managed Application Routes</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Page / Route</th>
                    <th>SEO Title</th>
                    <th>Focus Keyword</th>
                    <th>Robots Directive</th>
                    <th>Schema Type</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold text-dark"><?= e($p->page_name) ?></div>
                        <div class="small text-muted font-monospace"><?= e($p->route_path) ?></div>
                    </td>
                    <td>
                        <div class="text-dark small fw-semibold"><?= e($p->seo_title ?: '— Uses Global Default —') ?></div>
                        <div class="text-muted text-truncate" style="max-width: 320px; font-size: 0.8rem;"><?= e($p->meta_description) ?></div>
                    </td>
                    <td>
                        <?php if ($p->focus_keyword): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= e($p->focus_keyword) ?></span>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p->is_indexable): ?>
                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check me-1"></i> Index, Follow</span>
                        <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-ban me-1"></i> Noindex</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-secondary border"><?= e($p->schema_type) ?></span>
                    </td>
                    <td class="text-end pe-4">
                        <a href="<?= url('/admin/seo/pages/' . $p->id) ?>" class="btn btn-sm btn-outline-primary fw-bold">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit SEO
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
