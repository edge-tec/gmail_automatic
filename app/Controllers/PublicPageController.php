<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Plan;
use App\Models\SeoFaq;
use App\Models\BlogPost;
use App\Models\SeoSetting;
use App\Services\SeoService;

class PublicPageController {
    public function features(): string {
        return View::render('public/features', [], 'layouts/public');
    }

    public function pricing(): string {
        $plans = Plan::allActive();
        return View::render('public/pricing', ['plans' => $plans], 'layouts/public');
    }

    public function howItWorks(): string {
        return View::render('public/how_it_works', [], 'layouts/public');
    }

    public function faq(): string {
        $faqs = SeoFaq::allActive();
        return View::render('public/faq', ['faqs' => $faqs], 'layouts/public');
    }

    public function contact(): string {
        return View::render('public/contact', [
            'supportEmail' => SeoSetting::get('support_email', 'support@2xbets.net'),
            'phone' => SeoSetting::get('support_phone', '+8801611195794'),
            'address' => SeoSetting::get('organization_address', 'Dhaka, Bangladesh'),
        ], 'layouts/public');
    }

    public function submitContact(Request $request): void {
        $name = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $subject = trim($request->input('subject', 'General Inquiry'));
        $message = trim($request->input('message', ''));

        if (empty($name) || empty($email) || empty($message)) {
            flash('error', 'Please fill in all required fields.');
            redirect('/contact');
            return;
        }

        logger("Public contact inquiry from {$name} ({$email}): {$subject}", 'info');
        flash('success', 'Thank you for reaching out! Our support team will get back to you shortly.');
        redirect('/contact');
    }

    public function blog(): string {
        $posts = BlogPost::allPublished(24);
        return View::render('public/blog', ['posts' => $posts], 'layouts/public');
    }

    public function blogSingle(Request $request, string $slug): string {
        $post = BlogPost::findBySlug($slug);
        if (!$post || $post->status !== 'published') {
            http_response_code(404);
            return View::render('errors/404', ['message' => 'Article not found.'], 'layouts/public');
        }

        $post->incrementViews();
        $recentPosts = BlogPost::allPublished(5);

        return View::render('public/blog_single', [
            'post' => $post,
            'recentPosts' => $recentPosts,
        ], 'layouts/public');
    }

    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        echo SeoService::generateSitemapXml();
        exit;
    }

    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo SeoService::generateRobotsTxt();
        exit;
    }
}
