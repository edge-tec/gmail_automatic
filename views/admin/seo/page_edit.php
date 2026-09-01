<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit SEO: <?= e($page->page_name) ?></h1>
        <p class="text-muted small mb-0">Route: <code class="text-primary"><?= e($page->route_path) ?></code></p>
    </div>
    <div>
        <a href="<?= url('/admin/seo/pages') ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Pages</a>
    </div>
</div>

<form action="<?= url('/admin/seo/pages/' . $page->id) ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Main Form Fields -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0">On-Page Meta Tags</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-secondary mb-0">SEO Title Tag</label>
                            <span id="titleCount" class="small text-muted">0 / 60 chars</span>
                        </div>
                        <input type="text" name="seo_title" id="inputTitle" class="form-control" value="<?= e($page->seo_title) ?>" placeholder="Title for Search Engines">
                        <div class="form-text small">Recommended: 30–60 characters. Target your primary focus keyword.</div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-secondary mb-0">Meta Description</label>
                            <span id="descCount" class="small text-muted">0 / 160 chars</span>
                        </div>
                        <textarea name="meta_description" id="inputDesc" class="form-control" rows="3" placeholder="Compelling summary for SERP snippets"><?= e($page->meta_description) ?></textarea>
                        <div class="form-text small">Recommended: 120–160 characters. Clear value proposition to maximize click-through rate (CTR).</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-secondary">Primary Focus Keyword</label>
                            <input type="text" name="focus_keyword" class="form-control" value="<?= e($page->focus_keyword) ?>" placeholder="e.g. Gmail auto reply">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-secondary">Secondary Keywords</label>
                            <input type="text" name="secondary_keywords" class="form-control" value="<?= e($page->secondary_keywords) ?>" placeholder="comma-separated">
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Custom Canonical URL (Optional)</label>
                        <input type="url" name="canonical_url" class="form-control" value="<?= e($page->canonical_url) ?>" placeholder="Leave blank to use default route URL">
                    </div>

                    <div class="p-3 bg-light rounded-3 d-flex gap-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_indexable" value="1" id="switchIndex" <?= $page->is_indexable ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-dark small" for="switchIndex">Allow Search Indexing (Index)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_followable" value="1" id="switchFollow" <?= $page->is_followable ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-dark small" for="switchFollow">Allow Link Following (Follow)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media / OpenGraph -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0">Open Graph &amp; Twitter Card (Social Sharing)</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">OG / Social Title</label>
                        <input type="text" name="og_title" class="form-control" value="<?= e($page->og_title) ?>" placeholder="Leave blank to inherit SEO Title">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">OG / Social Description</label>
                        <textarea name="og_description" class="form-control" rows="2" placeholder="Leave blank to inherit Meta Description"><?= e($page->og_description) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Social Share Image URL</label>
                        <input type="url" name="og_image" class="form-control" value="<?= e($page->og_image) ?>" placeholder="https://domain.com/img/share-preview.jpg">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: SERP Preview & Schema -->
        <div class="col-12 col-lg-5">
            <!-- Google SERP Live Snippet Preview -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 fw-bold text-dark mb-0"><i class="fa-brands fa-google text-primary me-2"></i> Google Search SERP Preview</h2>
                </div>
                <div class="card-body p-4 bg-white">
                    <div class="p-3 border rounded-3 bg-white" style="font-family: Arial, sans-serif;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="bg-light rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                                <i class="fa-solid fa-globe text-muted" style="font-size: 11px;"></i>
                            </div>
                            <div class="text-truncate" style="font-size: 12px; color: #202124;">
                                <?= e(\App\Services\SeoService::getSiteUrl()) ?><span class="text-muted"><?= e($page->route_path) ?></span>
                            </div>
                        </div>
                        <div id="serpPreviewTitle" class="fw-medium text-truncate mb-1" style="font-size: 18px; color: #1a0dab; line-height: 1.3;">
                            <?= e($page->seo_title ?: 'Page Title Example') ?>
                        </div>
                        <div id="serpPreviewDesc" class="text-secondary" style="font-size: 13px; line-height: 1.4; color: #4d5156;">
                            <?= e($page->meta_description ?: 'Provide a descriptive meta description to see how it renders on Google Search.') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Structured Data Schema Config -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 fw-bold text-dark mb-0"><i class="fa-solid fa-code text-info me-2"></i> Schema.org Structured Data</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Primary Schema Type</label>
                        <select name="schema_type" class="form-select">
                            <option value="WebPage" <?= $page->schema_type === 'WebPage' ? 'selected' : '' ?>>WebPage (Standard)</option>
                            <option value="SoftwareApplication" <?= $page->schema_type === 'SoftwareApplication' ? 'selected' : '' ?>>SoftwareApplication (SaaS Product)</option>
                            <option value="Product" <?= $page->schema_type === 'Product' ? 'selected' : '' ?>>Product / Pricing Plans</option>
                            <option value="FAQPage" <?= $page->schema_type === 'FAQPage' ? 'selected' : '' ?>>FAQPage (Rich Questions)</option>
                            <option value="ContactPage" <?= $page->schema_type === 'ContactPage' ? 'selected' : '' ?>>ContactPage</option>
                            <option value="CollectionPage" <?= $page->schema_type === 'CollectionPage' ? 'selected' : '' ?>>CollectionPage (Blog Index)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Custom JSON-LD Override (Optional)</label>
                        <textarea name="custom_schema_json" class="form-control font-monospace small" rows="5" placeholder='{"@context":"https://schema.org", "@type":"..."}'><?= e($page->custom_schema_json) ?></textarea>
                        <div class="form-text small">Must be valid JSON if provided.</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 py-3 shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save Page SEO Settings
            </button>
        </div>
    </div>
</form>

<script>
    const inputTitle = document.getElementById('inputTitle');
    const inputDesc = document.getElementById('inputDesc');
    const titleCount = document.getElementById('titleCount');
    const descCount = document.getElementById('descCount');
    const serpTitle = document.getElementById('serpPreviewTitle');
    const serpDesc = document.getElementById('serpPreviewDesc');

    function updateCounters() {
        const tLen = inputTitle.value.length;
        const dLen = inputDesc.value.length;
        titleCount.textContent = tLen + ' / 60 chars';
        descCount.textContent = dLen + ' / 160 chars';

        titleCount.className = tLen > 60 ? 'small text-danger fw-bold' : 'small text-muted';
        descCount.className = dLen > 160 ? 'small text-danger fw-bold' : 'small text-muted';

        serpTitle.textContent = inputTitle.value.trim() || 'Page Title Example';
        serpDesc.textContent = inputDesc.value.trim() || 'Provide a descriptive meta description to see how it renders on Google Search.';
    }

    inputTitle.addEventListener('input', updateCounters);
    inputDesc.addEventListener('input', updateCounters);
    updateCounters();
</script>
