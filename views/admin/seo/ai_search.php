<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-brain text-primary me-2"></i> AI Search Optimization (LLM &amp; Answer Engine SEO)</h1>
        <p class="text-muted small mb-0">Structure entity definitions, product facts, and problem-solution maps so ChatGPT, Perplexity, Gemini, and Google AI Overview cite your SaaS accurately</p>
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
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<form action="<?= url('/admin/seo/ai-search') ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-diagram-project text-primary me-2"></i> Machine-Readable Entity Information</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Factual Product Summary (What is the product?)</label>
                        <textarea name="ai_product_summary" class="form-control" rows="3"><?= e($aiSettings['ai_product_summary'] ?? '') ?></textarea>
                        <div class="form-text small">High-density factual summary describing the exact software capability.</div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Target Audience &amp; Personas (Who is it for?)</label>
                        <textarea name="ai_target_audience" class="form-control" rows="3"><?= e($aiSettings['ai_target_audience'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Key Problems Solved (How does it help?)</label>
                        <textarea name="ai_key_problems_solved" class="form-control" rows="4"><?= e($aiSettings['ai_key_problems_solved'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Live Pricing &amp; Tier Summary</label>
                        <textarea name="ai_pricing_summary" class="form-control" rows="3"><?= e($aiSettings['ai_pricing_summary'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save AI Search Configurations
            </button>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 fw-bold text-dark mb-0"><i class="fa-solid fa-circle-info text-info me-2"></i> AI Search Best Practices</h2>
                </div>
                <div class="card-body p-4 small text-secondary">
                    <p class="mb-3">AI answer engines like <strong>Perplexity</strong>, <strong>ChatGPT Search</strong>, and <strong>Google AI Overviews</strong> prioritize structured, clear, and factual statements over promotional hype.</p>
                    <ul class="d-flex flex-column gap-2 ps-3 mb-0">
                        <li>Maintain clear subject-predicate entity relations.</li>
                        <li>Match features directly to real product capabilities.</li>
                        <li>Update pricing summaries whenever plan costs change.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
