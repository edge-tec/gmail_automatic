<section class="py-5">
    <div class="text-center max-w-700 mx-auto mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold text-uppercase tracking-wider mb-2">Knowledge Base &amp; Guides</span>
        <h1 class="fw-extrabold display-5 text-dark">Gmail Automation &amp; Outreach Blog</h1>
        <p class="text-muted fs-5">Actionable insights, email deliverability best practices, template guides, and workflow tutorials.</p>
    </div>

    <!-- Blog Posts Grid -->
    <div class="row g-4 mb-5">
        <?php if (empty($posts)): ?>
        <div class="col-12 text-center py-5">
            <i class="fa-solid fa-newspaper text-muted display-4 mb-3"></i>
            <p class="text-muted">No published articles yet. Check back soon!</p>
        </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
            <div class="col-12 col-md-6 col-lg-6">
                <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-shadow transition">
                    <div class="card-body p-4 p-lg-5 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= e($post->category) ?></span>
                                <span class="text-muted small">&bull;</span>
                                <span class="text-muted small"><i class="fa-solid fa-clock me-1"></i> <?= date('M d, Y', strtotime($post->published_at ?: $post->created_at)) ?></span>
                            </div>
                            <h2 class="h4 fw-bold text-dark mb-3">
                                <a href="<?= url('/blog/' . $post->slug) ?>" class="text-dark text-decoration-none hover-primary">
                                    <?= e($post->title) ?>
                                </a>
                            </h2>
                            <p class="text-secondary small leading-relaxed mb-4">
                                <?= e($post->excerpt ?: substr(strip_tags($post->content), 0, 150) . '...') ?>
                            </p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-secondary bg-opacity-10 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-user-pen text-secondary small"></i>
                                </div>
                                <span class="small fw-semibold text-dark"><?= e($post->author_name) ?></span>
                            </div>
                            <a href="<?= url('/blog/' . $post->slug) ?>" class="btn btn-sm btn-link text-primary fw-bold text-decoration-none">
                                Read Guide <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
