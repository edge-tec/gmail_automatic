<section class="py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <article class="card border-0 shadow-sm rounded-4 p-4 p-lg-5 mb-5">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb small">
                        <li class="breadcrumb-item"><a href="<?= url('/') ?>" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item"><a href="<?= url('/blog') ?>" class="text-decoration-none">Blog</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($post->category) ?></li>
                    </ol>
                </nav>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2"><?= e($post->category) ?></span>
                    <span class="text-muted small">&bull;</span>
                    <span class="text-muted small"><i class="fa-solid fa-calendar-day me-1"></i> Published <?= date('F d, Y', strtotime($post->published_at ?: $post->created_at)) ?></span>
                    <span class="text-muted small">&bull;</span>
                    <span class="text-muted small"><i class="fa-solid fa-eye me-1"></i> <?= number_format($post->views) ?> views</span>
                </div>

                <h1 class="fw-extrabold display-6 text-dark mb-4"><?= e($post->title) ?></h1>

                <div class="d-flex align-items-center gap-3 pb-4 mb-4 border-bottom">
                    <div class="bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?= e($post->author_name) ?></div>
                        <div class="text-muted small">Email Automation Specialist</div>
                    </div>
                </div>

                <?php if ($post->featured_image): ?>
                <div class="mb-4 rounded-3 overflow-hidden shadow-sm">
                    <img src="<?= e($post->featured_image) ?>" alt="<?= e($post->title) ?>" class="img-fluid w-100" style="max-height: 400px; object-fit: cover;">
                </div>
                <?php endif; ?>

                <!-- Article Content Body -->
                <div class="article-content leading-relaxed text-secondary fs-6 mb-5" style="line-height: 1.85;">
                    <?= $post->content ?>
                </div>

                <?php if (!empty($post->tags)): ?>
                <div class="pt-4 border-top d-flex align-items-center gap-2 flex-wrap mb-4">
                    <span class="small fw-bold text-muted"><i class="fa-solid fa-tags me-1"></i> Tags:</span>
                    <?php foreach (explode(',', $post->tags) as $tag): ?>
                    <span class="badge bg-light text-secondary border px-3 py-2"><?= e(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Author Bio Box -->
                <div class="bg-light rounded-4 p-4 d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-bolt-lightning text-warning fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?= e($post->author_name) ?></div>
                        <div class="text-muted small">Official Contributor at <?= e(\App\Services\SeoService::getSiteName()) ?>. Helping sales and customer success teams optimize email deliverability and reply automations.</div>
                    </div>
                </div>
            </article>

            <!-- CTA Card -->
            <div class="card border-0 bg-primary text-white rounded-4 p-4 p-lg-5 text-center shadow mb-5">
                <h3 class="fw-bold mb-2">Automate Your Gmail Accounts Today</h3>
                <p class="text-white-50 small max-w-500 mx-auto mb-4">Set up smart auto replies and multi-step follow-up sequences in minutes with our 7-day free trial.</p>
                <div>
                    <a href="<?= url('/register') ?>" class="btn btn-light btn-lg fw-bold px-4 py-2 text-primary">
                        Start 7-Day Free Trial
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar Recent Articles -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h4 class="h6 fw-bold text-dark text-uppercase tracking-wider mb-3">Recent Guides</h4>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($recentPosts as $rp): ?>
                    <div class="pb-3 border-bottom last-border-0">
                        <span class="badge bg-light text-primary border mb-1 small"><?= e($rp->category) ?></span>
                        <h5 class="fs-6 fw-bold mb-1">
                            <a href="<?= url('/blog/' . $rp->slug) ?>" class="text-dark text-decoration-none hover-primary">
                                <?= e($rp->title) ?>
                            </a>
                        </h5>
                        <div class="text-muted small"><?= date('M d, Y', strtotime($rp->published_at ?: $rp->created_at)) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
