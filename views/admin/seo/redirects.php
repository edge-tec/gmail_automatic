<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i> SEO 301 / 302 Redirect Manager</h1>
        <p class="text-muted small mb-0">Create permanent (301) and temporary (302) URL redirects with automatic loop protection and hit tracking</p>
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
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects (<?= count($redirects) ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <!-- Add New Redirect -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Add URL Redirect</h2>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/seo/redirects') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label fw-bold small text-secondary">Old / Source URL <span class="text-danger">*</span></label>
                            <input type="text" name="old_url" class="form-control font-monospace" placeholder="/old-features-page" required>
                            <div class="form-text small">Path being redirected from.</div>
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-secondary">New / Target Destination <span class="text-danger">*</span></label>
                            <input type="text" name="new_url" class="form-control font-monospace" placeholder="/features" required>
                            <div class="form-text small">Target internal path or absolute URL.</div>
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-secondary">HTTP Status Code</label>
                            <select name="status_code" class="form-select">
                                <option value="301" selected>301 Moved Permanently (SEO Recommended)</option>
                                <option value="302">302 Found / Temporary Redirect</option>
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="swActive" checked>
                            <label class="form-check-label fw-bold text-dark small" for="swActive">Active immediately</label>
                        </div>
                        <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-plus me-1"></i> Create Redirect
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Redirects Table -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold text-dark mb-0">Active URL Rules (<?= count($redirects) ?>)</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Source URL</th>
                            <th>Target URL</th>
                            <th>Status</th>
                            <th>Hits</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($redirects)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No URL redirects configured yet.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($redirects as $r): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-danger small fw-bold"><?= e($r->old_url) ?></td>
                                <td class="font-monospace text-success small fw-bold"><?= e($r->new_url) ?></td>
                                <td>
                                    <span class="badge <?= $r->status_code === 301 ? 'bg-primary' : 'bg-warning text-dark' ?>">
                                        <?= $r->status_code ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= number_format($r->hits) ?> hits</span>
                                </td>
                                <td class="text-end pe-4">
                                    <form action="<?= url('/admin/seo/redirects/' . $r->id . '/delete') ?>" method="POST" onsubmit="return confirm('Delete this redirect rule?');" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
