<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-circle-question text-primary me-2"></i> FAQ &amp; Structured Schema Manager</h1>
        <p class="text-muted small mb-0">Manage customer FAQs displayed on the website and automatically generate rich FAQPage JSON-LD schema</p>
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
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/faqs') ?>"><i class="fa-solid fa-circle-question me-1"></i> FAQs (<?= count($faqs) ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <!-- Add FAQ -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Add FAQ Question</h2>
            </div>
            <div class="card-body p-4">
                <form action="<?= url('/admin/seo/faqs') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label fw-bold small text-secondary">Question <span class="text-danger">*</span></label>
                            <input type="text" name="question" class="form-control" placeholder="e.g. How does Gmail OAuth work?" required>
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-secondary">Answer <span class="text-danger">*</span></label>
                            <textarea name="answer" class="form-control" rows="4" placeholder="Clear, concise and factual explanation" required></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label fw-bold small text-secondary">Category</label>
                                <input type="text" name="category" class="form-control" value="General" placeholder="General, Pricing, Setup">
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-bold small text-secondary">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="swFaqActive" checked>
                            <label class="form-check-label fw-bold text-dark small" for="swFaqActive">Active (Display &amp; Index)</label>
                        </div>
                        <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-plus me-1"></i> Add FAQ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FAQs List -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h5 fw-bold text-dark mb-0">Active FAQ Items (<?= count($faqs) ?>)</h2>
            </div>
            <div class="card-body p-4 d-flex flex-column gap-3">
                <?php if (empty($faqs)): ?>
                <div class="text-center text-muted py-4">No FAQs added yet.</div>
                <?php else: ?>
                    <?php foreach ($faqs as $faq): ?>
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary me-2"><?= e($faq->category) ?></span>
                                <span class="badge bg-light text-secondary border">Order: <?= $faq->sort_order ?></span>
                                <?php if ($faq->is_active): ?>
                                <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary">Disabled</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <form action="<?= url('/admin/seo/faqs/' . $faq->id . '/delete') ?>" method="POST" onsubmit="return confirm('Delete this FAQ item?');" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= e($faq->question) ?></h6>
                        <p class="text-secondary small mb-0"><?= nl2br(e($faq->answer)) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
