<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fa-solid fa-feather text-primary me-2"></i> <?= $post ? 'Edit Article: ' . e($post->title) : 'Create SEO-Optimized Article' ?>
        </h1>
        <p class="text-muted small mb-0">Write rich articles with meta tags, target keywords, and structured BlogPosting schema</p>
    </div>
    <div>
        <a href="<?= url('/admin/seo/blog') ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Blog List</a>
    </div>
</div>

<form action="<?= $post ? url('/admin/seo/blog/' . $post->id . '/edit') : url('/admin/seo/blog/create') ?>" method="POST">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Content Column -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Article Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="blogTitle" class="form-control form-control-lg fw-bold" placeholder="e.g. 5 Best Practices for Managing Multi-Account Gmail Automation" value="<?= e($post->title ?? '') ?>" required>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">URL Slug</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted font-monospace small">/blog/</span>
                            <input type="text" name="slug" id="blogSlug" class="form-control font-monospace" placeholder="auto-generated-from-title" value="<?= e($post->slug ?? '') ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Short Excerpt / Summary</label>
                        <textarea name="excerpt" class="form-control" rows="2" placeholder="Brief 1-2 sentence overview for cards and meta descriptions"><?= e($post->excerpt ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-secondary">Article Content (HTML supported) <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control font-monospace" rows="12" placeholder="Write full article using semantic HTML: <h2>, <h3>, <p>, <ul>, <li>..." required><?= e($post->content ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- SEO Controls for Blog -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h5 fw-bold text-dark mb-0"><i class="fa-solid fa-magnifying-glass text-primary me-2"></i> Article SEO &amp; Meta Tags</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Custom SEO Title</label>
                        <input type="text" name="seo_title" class="form-control" placeholder="Leave blank to use Article Title" value="<?= e($post->seo_title ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Custom Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="Leave blank to use Excerpt"><?= e($post->meta_description ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-secondary">Primary Focus Keyword</label>
                            <input type="text" name="focus_keyword" class="form-control" placeholder="e.g. Gmail automation tips" value="<?= e($post->focus_keyword ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-secondary">Tags (comma-separated)</label>
                            <input type="text" name="tags" class="form-control" placeholder="Gmail, Outreach, Deliverability" value="<?= e($post->tags ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Metadata -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 class="h6 fw-bold text-dark mb-0">Publishing Options</h2>
                </div>
                <div class="card-body p-4 d-flex flex-column gap-3">
                    <div>
                        <label class="form-label fw-bold small text-secondary">Publishing Status</label>
                        <select name="status" class="form-select">
                            <option value="published" <?= ($post && $post->status === 'published') || !$post ? 'selected' : '' ?>>Published (Live &amp; Indexed)</option>
                            <option value="draft" <?= $post && $post->status === 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Category</label>
                        <input type="text" name="category" class="form-control" value="<?= e($post->category ?? 'Guides') ?>" placeholder="Guides, News, Deliverability">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Author Name</label>
                        <input type="text" name="author_name" class="form-control" value="<?= e($post->author_name ?? 'Team') ?>">
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-secondary">Featured Image URL</label>
                        <input type="url" name="featured_image" class="form-control" placeholder="https://domain.com/img/guide.jpg" value="<?= e($post->featured_image ?? '') ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 py-3 shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> <?= $post ? 'Update Article' : 'Publish Article' ?>
            </button>
        </div>
    </div>
</form>
