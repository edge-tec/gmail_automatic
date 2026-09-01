<?php
namespace App\Services;

use App\Models\SeoSetting;
use App\Models\SeoPage;
use App\Models\SeoFaq;
use App\Models\BlogPost;
use App\Models\Plan;

class SeoService {
    /**
     * Get Base Site URL normalized
     */
    public static function getSiteUrl(): string {
        $customUrl = SeoSetting::get('site_url');
        if (!empty($customUrl)) {
            return rtrim($customUrl, '/');
        }
        return rtrim(url('/'), '/');
    }

    /**
     * Get Site Name
     */
    public static function getSiteName(): string {
        return SeoSetting::get('site_name') ?: config('app.name', 'Gmail Auto Reply & Follow-up');
    }

    /**
     * Check if current URI is a private / authenticated route that must NEVER be indexed
     */
    public static function isPrivateRoute(string $path): bool {
        $clean = '/' . trim($path, '/');
        $privatePrefixes = [
            '/admin',
            '/dashboard',
            '/accounts',
            '/settings',
            '/billing',
            '/rules',
            '/threads',
            '/logs',
            '/webhook',
            '/auth/google',
        ];

        foreach ($privatePrefixes as $prefix) {
            if ($clean === $prefix || str_starts_with($clean, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve SEO Metadata for the current or given path
     */
    public static function getMetadata(?string $path = null, ?array $customOverride = null): array {
        $rawPath = $path ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $parsedPath = parse_url($rawPath, PHP_URL_PATH) ?: '/';
        $cleanPath = '/' . trim($parsedPath, '/');
        if ($cleanPath !== '/' && str_ends_with($cleanPath, '/')) {
            $cleanPath = rtrim($cleanPath, '/');
        }

        $siteName = self::getSiteName();
        $siteUrl = self::getSiteUrl();

        // 1. Private Route Protection
        if (self::isPrivateRoute($cleanPath)) {
            return [
                'title' => ($customOverride['title'] ?? 'Dashboard') . ' - ' . $siteName,
                'description' => '',
                'keywords' => '',
                'canonical' => $siteUrl . $cleanPath,
                'robots' => 'noindex, nofollow',
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'og_url' => $siteUrl . $cleanPath,
                'og_type' => 'website',
                'twitter_card' => 'summary',
                'schema_json' => '',
                'is_private' => true,
            ];
        }

        // 2. Lookup Page-Level SEO from Database
        $page = SeoPage::findByRoute($cleanPath);

        // Global SEO Fallbacks
        $defaultTitle = SeoSetting::get('default_title', 'Gmail Auto Reply & Follow-up Automation Software');
        $defaultDesc = SeoSetting::get('default_description', 'Scale your business response speed with official Gmail API auto reply, multi-step sequential follow-ups, and duplicate traffic protection.');
        $defaultKeywords = SeoSetting::get('default_keywords', 'Gmail auto reply, Gmail automation, email follow up, Gmail API software');
        $defaultOgImage = SeoSetting::get('default_og_image', url('/img/og-preview.png'));
        $defaultTwitterCard = SeoSetting::get('default_twitter_card', 'summary_large_image');

        $title = $customOverride['title'] ?? ($page?->seo_title ?: $defaultTitle);
        $description = $customOverride['description'] ?? ($page?->meta_description ?: $defaultDesc);
        $keywords = $customOverride['keywords'] ?? ($page?->focus_keyword ? ($page->focus_keyword . ', ' . $page->secondary_keywords) : $defaultKeywords);
        
        $canonical = $customOverride['canonical'] ?? ($page?->canonical_url ?: ($siteUrl . ($cleanPath === '/' ? '' : $cleanPath)));
        $isIndexable = isset($customOverride['is_indexable']) ? (bool)$customOverride['is_indexable'] : ($page ? $page->is_indexable : true);
        $isFollowable = isset($customOverride['is_followable']) ? (bool)$customOverride['is_followable'] : ($page ? $page->is_followable : true);

        $robots = ($isIndexable ? 'index' : 'noindex') . ', ' . ($isFollowable ? 'follow' : 'nofollow');

        $ogTitle = $customOverride['og_title'] ?? ($page?->og_title ?: $title);
        $ogDesc = $customOverride['og_description'] ?? ($page?->og_description ?: $description);
        $ogImage = $customOverride['og_image'] ?? ($page?->og_image ?: $defaultOgImage);
        $ogType = $customOverride['og_type'] ?? ($cleanPath === '/' ? 'website' : 'article');
        $twitterCard = $customOverride['twitter_card'] ?? ($page?->twitter_card ?: $defaultTwitterCard);

        // Schema JSON-LD Generation
        $schemaJson = self::buildStructuredData($cleanPath, $page, $customOverride);

        return [
            'title' => $title . ($cleanPath !== '/' && !str_contains($title, $siteName) ? ' | ' . $siteName : ''),
            'raw_title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
            'og_image' => $ogImage,
            'og_url' => $canonical,
            'og_type' => $ogType,
            'twitter_card' => $twitterCard,
            'schema_json' => $schemaJson,
            'is_private' => false,
        ];
    }

    /**
     * Render full HTML <head> meta tags and schema markup
     */
    public static function renderHeadTags(?string $path = null, ?array $override = null): string {
        $meta = self::getMetadata($path, $override);
        $siteName = self::getSiteName();

        $html = "<!-- Search Engine Optimization (Google SEO + AI Search SEO) -->\n";
        $html .= "<title>" . htmlspecialchars($meta['title']) . "</title>\n";
        
        if (!empty($meta['description'])) {
            $html .= "<meta name=\"description\" content=\"" . htmlspecialchars($meta['description']) . "\">\n";
        }
        if (!empty($meta['keywords'])) {
            $html .= "<meta name=\"keywords\" content=\"" . htmlspecialchars($meta['keywords']) . "\">\n";
        }

        $html .= "<meta name=\"robots\" content=\"" . htmlspecialchars($meta['robots']) . "\">\n";
        $html .= "<link rel=\"canonical\" href=\"" . htmlspecialchars($meta['canonical']) . "\">\n";

        // Open Graph Meta Tags
        $html .= "<!-- Open Graph / Social Media Meta Tags -->\n";
        $html .= "<meta property=\"og:site_name\" content=\"" . htmlspecialchars($siteName) . "\">\n";
        $html .= "<meta property=\"og:type\" content=\"" . htmlspecialchars($meta['og_type']) . "\">\n";
        $html .= "<meta property=\"og:title\" content=\"" . htmlspecialchars($meta['og_title']) . "\">\n";
        if (!empty($meta['og_description'])) {
            $html .= "<meta property=\"og:description\" content=\"" . htmlspecialchars($meta['og_description']) . "\">\n";
        }
        $html .= "<meta property=\"og:url\" content=\"" . htmlspecialchars($meta['og_url']) . "\">\n";
        if (!empty($meta['og_image'])) {
            $html .= "<meta property=\"og:image\" content=\"" . htmlspecialchars($meta['og_image']) . "\">\n";
        }

        // Twitter / X Card Meta Tags
        $html .= "<!-- Twitter / X Card Meta Tags -->\n";
        $html .= "<meta name=\"twitter:card\" content=\"" . htmlspecialchars($meta['twitter_card']) . "\">\n";
        $html .= "<meta name=\"twitter:title\" content=\"" . htmlspecialchars($meta['og_title']) . "\">\n";
        if (!empty($meta['og_description'])) {
            $html .= "<meta name=\"twitter:description\" content=\"" . htmlspecialchars($meta['og_description']) . "\">\n";
        }
        if (!empty($meta['og_image'])) {
            $html .= "<meta name=\"twitter:image\" content=\"" . htmlspecialchars($meta['og_image']) . "\">\n";
        }

        // Google Search Console Verification
        $gscCode = SeoSetting::get('gsc_verification_code');
        if (!empty($gscCode)) {
            $html .= "<meta name=\"google-site-verification\" content=\"" . htmlspecialchars($gscCode) . "\">\n";
        }

        // Structured Data JSON-LD
        if (!empty($meta['schema_json'])) {
            $html .= "<!-- Structured Data (JSON-LD) -->\n";
            $html .= "<script type=\"application/ld+json\">\n" . $meta['schema_json'] . "\n</script>\n";
        }

        // Google Analytics
        $gaId = SeoSetting::get('google_analytics_id');
        if (!empty($gaId) && !self::isPrivateRoute($path ?? ($_SERVER['REQUEST_URI'] ?? '/'))) {
            $html .= "<!-- Google Analytics -->\n";
            $html .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($gaId) . "\"></script>\n";
            $html .= "<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', '" . htmlspecialchars($gaId) . "');\n</script>\n";
        }

        return $html;
    }

    /**
     * Build comprehensive JSON-LD Structured Data Schema
     */
    public static function buildStructuredData(string $cleanPath, ?SeoPage $page, ?array $customOverride): string {
        $siteName = self::getSiteName();
        $siteUrl = self::getSiteUrl();
        $orgName = SeoSetting::get('organization_name', $siteName);
        $supportEmail = SeoSetting::get('support_email', 'support@2xbets.net');

        $schemas = [];

        // 1. Organization & WebSite Schemas (Always present on public pages)
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $orgName,
            'url' => $siteUrl,
            'logo' => $siteUrl . '/img/logo.png',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'email' => $supportEmail,
                'contactType' => 'customer support',
            ]
        ];
        $schemas[] = $orgSchema;

        $webSiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
        ];
        $schemas[] = $webSiteSchema;

        // 2. Homepage or SoftwareApplication Schema
        if ($cleanPath === '/' || ($page && $page->schema_type === 'SoftwareApplication')) {
            $starterPlan = Plan::findBySlug('starter');
            $proPlan = Plan::findBySlug('professional');

            $offers = [];
            if ($starterPlan) {
                $offers[] = [
                    '@type' => 'Offer',
                    'name' => $starterPlan->name . ' Plan',
                    'price' => (string)$starterPlan->price,
                    'priceCurrency' => 'USD',
                    'description' => "Up to {$starterPlan->gmail_limit} Gmail Accounts, 24/7 cloud automation.",
                    'url' => $siteUrl . '/pricing',
                ];
            }
            if ($proPlan) {
                $offers[] = [
                    '@type' => 'Offer',
                    'name' => $proPlan->name . ' Plan',
                    'price' => (string)$proPlan->price,
                    'priceCurrency' => 'USD',
                    'description' => "Up to {$proPlan->gmail_limit} Gmail Accounts, priority processing.",
                    'url' => $siteUrl . '/pricing',
                ];
            }

            $softwareSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => $siteName,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Cloud, Web',
                'url' => $siteUrl,
                'description' => 'Official Google API-backed cloud automation software for Gmail auto-replies, multi-step sequential follow-ups, and duplicate traffic protection.',
                'offers' => $offers,
            ];
            $schemas[] = $softwareSchema;
        }

        // 3. Pricing Page (Product / Offers Schema)
        if ($cleanPath === '/pricing' || ($page && $page->schema_type === 'Product')) {
            $plans = Plan::allActive();
            foreach ($plans as $pl) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $pl->name . ' Plan - ' . $siteName,
                    'description' => "Cloud Gmail automation supporting up to {$pl->gmail_limit} connected accounts with sequential follow-ups.",
                    'brand' => [
                        '@type' => 'Brand',
                        'name' => $siteName
                    ],
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => (string)$pl->price,
                        'priceCurrency' => 'USD',
                        'url' => $siteUrl . '/pricing',
                        'availability' => 'https://schema.org/InStock',
                    ]
                ];
            }
        }

        // 4. FAQPage Schema (Rendered on /faq and / if FAQs are present)
        if ($cleanPath === '/faq' || $cleanPath === '/' || ($page && $page->schema_type === 'FAQPage')) {
            $faqs = SeoFaq::allActive();
            if (!empty($faqs)) {
                $faqElements = [];
                foreach ($faqs as $faq) {
                    $faqElements[] = [
                        '@type' => 'Question',
                        'name' => $faq->question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => strip_tags($faq->answer),
                        ]
                    ];
                }
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqElements,
                ];
            }
        }

        // 5. Blog Article Schema (BlogPosting)
        if (isset($customOverride['blog_post'])) {
            /** @var BlogPost $post */
            $post = $customOverride['blog_post'];
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $post->title,
                'description' => $post->excerpt ?: substr(strip_tags($post->content), 0, 160),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->author_name,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $orgName,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $siteUrl . '/img/logo.png',
                    ]
                ],
                'datePublished' => date('c', strtotime($post->published_at ?: $post->created_at)),
                'dateModified' => date('c', strtotime($post->updated_at ?: $post->created_at)),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $siteUrl . '/blog/' . $post->slug,
                ],
                'image' => $post->featured_image ?: ($siteUrl . '/img/og-preview.png'),
            ];
        }

        // 6. Custom JSON-LD injected by Admin
        if ($page && !empty($page->custom_schema_json)) {
            $decoded = json_decode($page->custom_schema_json, true);
            if ($decoded) {
                $schemas[] = $decoded;
            }
        }

        return json_encode($schemas, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate Dynamic XML Sitemap (/sitemap.xml)
     */
    public static function generateSitemapXml(): string {
        $siteUrl = self::getSiteUrl();
        $now = date('c');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 1. Static Public Indexable Pages from database
        $pages = SeoPage::getIndexablePages();
        foreach ($pages as $p) {
            $loc = $siteUrl . ($p->route_path === '/' ? '' : $p->route_path);
            $priority = $p->route_path === '/' ? '1.0' : ($p->route_path === '/pricing' || $p->route_path === '/features' ? '0.9' : '0.8');
            $changefreq = $p->route_path === '/' ? 'daily' : 'weekly';
            $lastmod = !empty($p->updated_at) ? date('c', strtotime($p->updated_at)) : $now;

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        // 2. Published Blog Articles
        $posts = BlogPost::allPublished(100);
        foreach ($posts as $post) {
            $loc = $siteUrl . '/blog/' . $post->slug;
            $lastmod = !empty($post->updated_at) ? date('c', strtotime($post->updated_at)) : date('c', strtotime($post->published_at));

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Generate Dynamic Robots.txt (/robots.txt)
     */
    public static function generateRobotsTxt(): string {
        $customRobots = SeoSetting::get('custom_robots_txt');
        if (!empty($customRobots)) {
            return $customRobots;
        }

        $siteUrl = self::getSiteUrl();

        $txt = "User-agent: *\n";
        $txt .= "Allow: /\n";
        $txt .= "Allow: /features\n";
        $txt .= "Allow: /pricing\n";
        $txt .= "Allow: /how-it-works\n";
        $txt .= "Allow: /faq\n";
        $txt .= "Allow: /contact\n";
        $txt .= "Allow: /blog\n";
        $txt .= "Allow: /blog/*\n";
        $txt .= "Allow: /privacy\n";
        $txt .= "Allow: /terms\n\n";

        $txt .= "# Protect Private & Authenticated Routes\n";
        $txt .= "Disallow: /admin\n";
        $txt .= "Disallow: /admin/*\n";
        $txt .= "Disallow: /dashboard\n";
        $txt .= "Disallow: /dashboard/*\n";
        $txt .= "Disallow: /accounts\n";
        $txt .= "Disallow: /accounts/*\n";
        $txt .= "Disallow: /settings\n";
        $txt .= "Disallow: /settings/*\n";
        $txt .= "Disallow: /billing\n";
        $txt .= "Disallow: /billing/*\n";
        $txt .= "Disallow: /rules\n";
        $txt .= "Disallow: /rules/*\n";
        $txt .= "Disallow: /threads\n";
        $txt .= "Disallow: /threads/*\n";
        $txt .= "Disallow: /logs\n";
        $txt .= "Disallow: /logs/*\n";
        $txt .= "Disallow: /webhook\n";
        $txt .= "Disallow: /webhook/*\n\n";

        $txt .= "Sitemap: {$siteUrl}/sitemap.xml\n";
        return $txt;
    }
}
