<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1"><i class="fa-solid fa-newspaper text-primary me-2"></i> Blog &amp; Content SEO Manager</h1>
        <p class="text-muted small mb-0">Publish SEO-optimized articles, tutorials, and guides with automated BlogPosting JSON-LD schema</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/admin/seo') ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> SEO Dashboard</a>
        <a href="<?= url('/admin/seo/blog/create') ?>" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-plus me-1"></i> New Article</a>
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
            <li class="nav-item"><a class="nav-link active fw-bold" href="<?= url('/admin/seo/blog') ?>"><i class="fa-solid fa-newspaper me-1"></i> Blog (<?= count($posts) ?>)</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/redirects') ?>"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Redirects</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/ai-search') ?>"><i class="fa-solid fa-brain me-1"></i> AI Search</a></li>
            <li class="nav-item"><a class="nav-link fw-bold text-dark" href="<?= url('/admin/seo/sitemap-robots') ?>"><i class="fa-solid fa-network-wired me-1"></i> Sitemap &amp; Robots</a></li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-bottom py-3">
        <h2 class="h5 fw-bold text-dark mb-0">Published Articles (<?= count($posts) ?>)</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Title / Article Slug</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Focus Keyword</th>
                    <th>Views</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No blog articles yet. Click "New Article" to create one.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= e($post->title) ?></div>
                            <div class="small text-muted font-monospace">/blog/<?= e($post->slug) ?></div>
                        </td>
                        <td><span class="badge bg-light text-primary border"><?= e($post->category) ?></span></td>
                        <td class="small fw-semibold"><?= e($post->author_name) ?></td>
                        <td>
                            <?php if ($post->focus_keyword): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary"><?= e($post->focus_keyword) ?></span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= number_format($post->views) ?></td>
                        <td>
                            <span class="badge <?= $post->status === 'published' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ucfirst($post->status) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="<?= url('/blog/' . $post->slug) ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1" title="View Public Article">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                            <a href="<?= url('/admin/seo/blog/' . $post->id . '/edit') ?>" class="btn btn-sm btn-outline-primary me-1">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <form action="<?= url('/admin/seo/blog/' . $post->id . '/delete') ?>" method="POST" onsubmit="return confirm('Delete this article?');" class="d-inline">
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
