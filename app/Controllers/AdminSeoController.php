<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Auth;
use App\Models\SeoSetting;
use App\Models\SeoPage;
use App\Models\SeoRedirect;
use App\Models\SeoFaq;
use App\Models\BlogPost;
use App\Services\SeoService;
use App\Services\SeoAuditService;

class AdminSeoController {
    /**
     * SEO Dashboard & Real-Time Audit
     */
    public function index(): string {
        $audit = SeoAuditService::runAudit();
        $pages = SeoPage::all();
        $blogs = BlogPost::all();
        $faqs = SeoFaq::all();
        $redirects = SeoRedirect::all();

        return View::render('admin/seo/index', [
            'audit' => $audit,
            'pagesCount' => count($pages),
            'indexableCount' => count(array_filter($pages, fn($p) => $p->is_indexable)),
            'blogsCount' => count($blogs),
            'faqsCount' => count($faqs),
            'redirectsCount' => count($redirects),
        ], 'layouts/main');
    }

    /**
     * Global SEO & Entity Configuration
     */
    public function globalSettings(): string {
        $settings = SeoSetting::all();
        return View::render('admin/seo/global', ['settings' => $settings], 'layouts/main');
    }

    public function updateGlobalSettings(Request $request): void {
        $fields = [
            'site_name',
            'site_url',
            'default_title',
            'default_description',
            'default_keywords',
            'default_canonical_url',
            'default_og_image',
            'default_og_title',
            'default_og_description',
            'default_twitter_card',
            'organization_name',
            'organization_description',
            'organization_address',
            'support_email',
            'support_phone',
            'gsc_verification_code',
            'google_analytics_id',
        ];

        foreach ($fields as $f) {
            SeoSetting::set($f, trim((string)$request->input($f, '')));
        }

        logger("Admin updated Global SEO Settings", 'info', Auth::id());
        flash('success', 'Global SEO & Organization Entity settings updated successfully.');
        redirect('/admin/seo/global');
    }

    /**
     * Page-Level SEO Manager
     */
    public function pages(): string {
        $pages = SeoPage::all();
        return View::render('admin/seo/pages', ['pages' => $pages], 'layouts/main');
    }

    public function editPage(Request $request, int $id): string {
        $page = SeoPage::find($id);
        if (!$page) {
            flash('error', 'Page not found.');
            redirect('/admin/seo/pages');
        }
        return View::render('admin/seo/page_edit', ['page' => $page], 'layouts/main');
    }

    public function updatePage(Request $request, int $id): void {
        $page = SeoPage::find($id);
        if (!$page) {
            flash('error', 'Page not found.');
            redirect('/admin/seo/pages');
            return;
        }

        $page->update([
            'seo_title' => trim((string)$request->input('seo_title', '')),
            'meta_description' => trim((string)$request->input('meta_description', '')),
            'focus_keyword' => trim((string)$request->input('focus_keyword', '')),
            'secondary_keywords' => trim((string)$request->input('secondary_keywords', '')),
            'canonical_url' => trim((string)$request->input('canonical_url', '')),
            'is_indexable' => $request->input('is_indexable') ? 1 : 0,
            'is_followable' => $request->input('is_followable') ? 1 : 0,
            'og_title' => trim((string)$request->input('og_title', '')),
            'og_description' => trim((string)$request->input('og_description', '')),
            'og_image' => trim((string)$request->input('og_image', '')),
            'twitter_card' => trim((string)$request->input('twitter_card', 'summary_large_image')),
            'schema_type' => trim((string)$request->input('schema_type', 'WebPage')),
            'custom_schema_json' => trim((string)$request->input('custom_schema_json', '')),
        ]);

        logger("Admin updated SEO configuration for page: {$page->page_name}", 'info', Auth::id());
        flash('success', "SEO settings for '{$page->page_name}' saved successfully.");
        redirect('/admin/seo/pages');
    }

    /**
     * 301/302 Redirect Manager
     */
    public function redirects(): string {
        $redirects = SeoRedirect::all();
        return View::render('admin/seo/redirects', ['redirects' => $redirects], 'layouts/main');
    }

    public function createRedirect(Request $request): void {
        $old = trim((string)$request->input('old_url', ''));
        $new = trim((string)$request->input('new_url', ''));
        $code = (int)$request->input('status_code', 301);

        if (empty($old) || empty($new)) {
            flash('error', 'Both Old URL and New URL are required.');
            redirect('/admin/seo/redirects');
            return;
        }

        try {
            SeoRedirect::create([
                'old_url' => $old,
                'new_url' => $new,
                'status_code' => in_array($code, [301, 302]) ? $code : 301,
                'is_active' => $request->input('is_active') ? 1 : 0,
            ]);
            flash('success', 'URL redirect created successfully.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/admin/seo/redirects');
    }

    public function deleteRedirect(Request $request, int $id): void {
        $redirect = SeoRedirect::find($id);
        if ($redirect) {
            $redirect->delete();
            flash('success', 'Redirect deleted successfully.');
        }
        redirect('/admin/seo/redirects');
    }

    /**
     * FAQ Management
     */
    public function faqs(): string {
        $faqs = SeoFaq::all();
        return View::render('admin/seo/faqs', ['faqs' => $faqs], 'layouts/main');
    }

    public function createFaq(Request $request): void {
        $question = trim((string)$request->input('question', ''));
        $answer = trim((string)$request->input('answer', ''));
        $category = trim((string)$request->input('category', 'General'));
        $sortOrder = (int)$request->input('sort_order', 0);

        if (empty($question) || empty($answer)) {
            flash('error', 'Question and Answer are required.');
            redirect('/admin/seo/faqs');
            return;
        }

        SeoFaq::create([
            'question' => $question,
            'answer' => $answer,
            'category' => $category,
            'sort_order' => $sortOrder,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);

        flash('success', 'FAQ added successfully.');
        redirect('/admin/seo/faqs');
    }

    public function updateFaq(Request $request, int $id): void {
        $faq = SeoFaq::find($id);
        if ($faq) {
            $faq->update([
                'question' => trim((string)$request->input('question', '')),
                'answer' => trim((string)$request->input('answer', '')),
                'category' => trim((string)$request->input('category', 'General')),
                'sort_order' => (int)$request->input('sort_order', 0),
                'is_active' => $request->input('is_active') ? 1 : 0,
            ]);
            flash('success', 'FAQ updated.');
        }
        redirect('/admin/seo/faqs');
    }

    public function deleteFaq(Request $request, int $id): void {
        $faq = SeoFaq::find($id);
        if ($faq) {
            $faq->delete();
            flash('success', 'FAQ deleted.');
        }
        redirect('/admin/seo/faqs');
    }

    /**
     * Blog Articles Manager
     */
    public function blog(): string {
        $posts = BlogPost::all();
        return View::render('admin/seo/blog', ['posts' => $posts], 'layouts/main');
    }

    public function createBlogPost(): string {
        return View::render('admin/seo/blog_edit', ['post' => null], 'layouts/main');
    }

    public function editBlogPost(Request $request, int $id): string {
        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', 'Blog post not found.');
            redirect('/admin/seo/blog');
        }
        return View::render('admin/seo/blog_edit', ['post' => $post], 'layouts/main');
    }

    public function saveBlogPost(Request $request, ?int $id = null): void {
        $title = trim((string)$request->input('title', ''));
        $content = (string)$request->input('content', '');
        $slug = trim((string)$request->input('slug', ''));

        if (empty($title) || empty($content)) {
            flash('error', 'Title and Content are required.');
            redirect($id ? "/admin/seo/blog/{$id}/edit" : '/admin/seo/blog/create');
            return;
        }

        $data = [
            'title' => $title,
            'slug' => $slug ?: $title,
            'excerpt' => trim((string)$request->input('excerpt', '')),
            'content' => $content,
            'featured_image' => trim((string)$request->input('featured_image', '')),
            'author_name' => trim((string)$request->input('author_name', 'Team')),
            'category' => trim((string)$request->input('category', 'Guides')),
            'tags' => trim((string)$request->input('tags', '')),
            'seo_title' => trim((string)$request->input('seo_title', '')),
            'meta_description' => trim((string)$request->input('meta_description', '')),
            'focus_keyword' => trim((string)$request->input('focus_keyword', '')),
            'status' => $request->input('status') === 'draft' ? 'draft' : 'published',
        ];

        if ($id) {
            $post = BlogPost::find($id);
            if ($post) {
                $oldSlug = $post->slug;
                $post->update($data);
                if (!empty($oldSlug) && $post->slug !== $oldSlug) {
                    try {
                        SeoRedirect::create([
                            'old_url' => '/blog/' . $oldSlug,
                            'new_url' => '/blog/' . $post->slug,
                            'status_code' => 301,
                            'is_active' => 1,
                        ]);
                    } catch (\Throwable $t) {
                        // Ignore if redirect already exists
                    }
                }
                flash('success', 'Article updated successfully.');
            }
        } else {
            BlogPost::create($data);
            flash('success', 'New article published successfully.');
        }

        redirect('/admin/seo/blog');
    }

    public function deleteBlogPost(Request $request, int $id): void {
        $post = BlogPost::find($id);
        if ($post) {
            $post->delete();
            flash('success', 'Article deleted.');
        }
        redirect('/admin/seo/blog');
    }

    /**
     * AI Search Optimization
     */
    public function aiSearch(): string {
        $aiSettings = [
            'ai_product_summary' => SeoSetting::get('ai_product_summary', 'Gmail Auto Reply & Follow-up Automation is a cloud SaaS providing official Google API automated replies, 5-step follow-up sequences, and duplicate traffic protection for businesses and agencies.'),
            'ai_target_audience' => SeoSetting::get('ai_target_audience', 'Sales professionals, recruitment agencies, customer support teams, real estate agents, and B2B marketers who need to manage high volume Gmail replies effortlessly.'),
            'ai_key_problems_solved' => SeoSetting::get('ai_key_problems_solved', "1. High lead response latency.\n2. Manual email follow-up fatigue.\n3. Accidental spam replies to duplicate traffic.\n4. Inability to run sequences without keeping computers powered on."),
            'ai_pricing_summary' => SeoSetting::get('ai_pricing_summary', 'Starter Plan ($50/month, up to 100 connected Gmail accounts), Professional Plan ($100/month, up to 250 connected Gmail accounts). Includes 7-day free trial.'),
        ];
        return View::render('admin/seo/ai_search', ['aiSettings' => $aiSettings], 'layouts/main');
    }

    public function updateAiSearch(Request $request): void {
        SeoSetting::set('ai_product_summary', trim((string)$request->input('ai_product_summary', '')));
        SeoSetting::set('ai_target_audience', trim((string)$request->input('ai_target_audience', '')));
        SeoSetting::set('ai_key_problems_solved', trim((string)$request->input('ai_key_problems_solved', '')));
        SeoSetting::set('ai_pricing_summary', trim((string)$request->input('ai_pricing_summary', '')));

        logger("Admin updated AI Search Optimization Settings", 'info', Auth::id());
        flash('success', 'AI Search entity & machine-readable optimization parameters saved.');
        redirect('/admin/seo/ai-search');
    }

    /**
     * Sitemap & Robots.txt Manager
     */
    public function sitemapRobots(): string {
        return View::render('admin/seo/sitemap_robots', [
            'sitemapXml' => SeoService::generateSitemapXml(),
            'robotsTxt' => SeoService::generateRobotsTxt(),
            'customRobots' => SeoSetting::get('custom_robots_txt', ''),
        ], 'layouts/main');
    }

    public function updateRobots(Request $request): void {
        $customRobots = trim((string)$request->input('custom_robots_txt', ''));
        SeoSetting::set('custom_robots_txt', $customRobots);
        flash('success', 'Robots.txt updated successfully.');
        redirect('/admin/seo/sitemap-robots');
    }
}
