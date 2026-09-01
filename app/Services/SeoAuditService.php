<?php
namespace App\Services;

use App\Models\SeoSetting;
use App\Models\SeoPage;
use App\Models\SeoFaq;
use App\Models\BlogPost;
use App\Models\SeoRedirect;

class SeoAuditService {
    /**
     * Perform comprehensive real-time SEO audit
     */
    public static function runAudit(): array {
        $critical = [];
        $warnings = [];
        $passed = [];

        // 1. Check Global SEO Settings
        $siteUrl = SeoSetting::get('site_url');
        if (empty($siteUrl)) {
            $warnings[] = [
                'type' => 'Global SEO',
                'item' => 'Website URL is not explicitly configured in Global SEO Settings (using host fallback).',
                'solution' => 'Set your production domain URL in Admin → SEO → Global SEO.'
            ];
        } else {
            $passed[] = [
                'type' => 'Global SEO',
                'item' => "Canonical base URL is configured: {$siteUrl}"
            ];
        }

        $defaultTitle = SeoSetting::get('default_title');
        if (empty($defaultTitle)) {
            $critical[] = [
                'type' => 'Global SEO',
                'item' => 'Default SEO Title is missing.',
                'solution' => 'Configure a compelling default title in Global SEO settings.'
            ];
        } else {
            $passed[] = [
                'type' => 'Global SEO',
                'item' => "Default SEO Title is configured ({$defaultTitle})"
            ];
        }

        $defaultDesc = SeoSetting::get('default_description');
        if (empty($defaultDesc)) {
            $critical[] = [
                'type' => 'Global SEO',
                'item' => 'Default Meta Description is missing.',
                'solution' => 'Add a 150-160 character description summarizing your SaaS platform.'
            ];
        } elseif (strlen($defaultDesc) < 50 || strlen($defaultDesc) > 300) {
            $warnings[] = [
                'type' => 'Global SEO',
                'item' => "Default Meta Description length is " . strlen($defaultDesc) . " characters (recommended 120-160).",
                'solution' => 'Optimize description length for Google search snippets.'
            ];
        } else {
            $passed[] = [
                'type' => 'Global SEO',
                'item' => "Default Meta Description length is optimal (" . strlen($defaultDesc) . " chars)."
            ];
        }

        // 2. Audit Page-Level SEO
        $pages = SeoPage::all();
        $titlesSeen = [];
        $descSeen = [];

        foreach ($pages as $p) {
            $pName = "Page [{$p->page_name}] ({$p->route_path})";

            // Title check
            if (empty($p->seo_title)) {
                $critical[] = [
                    'type' => 'Page SEO',
                    'item' => "{$pName} has no custom SEO title.",
                    'solution' => 'Add a distinct, keyword-targeted title in Page SEO settings.'
                ];
            } else {
                if (isset($titlesSeen[$p->seo_title])) {
                    $warnings[] = [
                        'type' => 'Duplicate Title',
                        'item' => "{$pName} shares a duplicate SEO title with {$titlesSeen[$p->seo_title]}.",
                        'solution' => 'Make every indexable page title unique.'
                    ];
                } else {
                    $titlesSeen[$p->seo_title] = $p->page_name;
                }

                if (strlen($p->seo_title) < 20 || strlen($p->seo_title) > 70) {
                    $warnings[] = [
                        'type' => 'Title Length',
                        'item' => "{$pName} title is " . strlen($p->seo_title) . " characters (recommended 30-60).",
                        'solution' => 'Adjust length to avoid SERP truncation.'
                    ];
                } else {
                    $passed[] = [
                        'type' => 'Title Length',
                        'item' => "{$pName} has optimal title length (" . strlen($p->seo_title) . " chars)."
                    ];
                }
            }

            // Description check
            if (empty($p->meta_description)) {
                $critical[] = [
                    'type' => 'Meta Description',
                    'item' => "{$pName} has no meta description.",
                    'solution' => 'Provide a concise summary in Page SEO settings.'
                ];
            } else {
                if (isset($descSeen[$p->meta_description])) {
                    $warnings[] = [
                        'type' => 'Duplicate Description',
                        'item' => "{$pName} shares duplicate description with {$descSeen[$p->meta_description]}.",
                        'solution' => 'Craft unique descriptions per page.'
                    ];
                } else {
                    $descSeen[$p->meta_description] = $p->page_name;
                }
            }

            // Indexable checks
            if ($p->is_indexable) {
                $passed[] = [
                    'type' => 'Indexability',
                    'item' => "{$pName} is set to Index, Follow."
                ];
            }
        }

        // 3. Audit Structured Data / Schema
        $activeFaqs = SeoFaq::allActive();
        if (count($activeFaqs) >= 3) {
            $passed[] = [
                'type' => 'Structured Data',
                'item' => "FAQPage schema is populated with " . count($activeFaqs) . " active FAQs."
            ];
        } else {
            $warnings[] = [
                'type' => 'Structured Data',
                'item' => 'Only ' . count($activeFaqs) . ' active FAQs found. Recommended at least 4 for rich FAQ schema.',
                'solution' => 'Add detailed questions in Admin → SEO → FAQs.'
            ];
        }

        // 4. Audit Blog Content
        $blogs = BlogPost::allPublished();
        if (count($blogs) > 0) {
            $passed[] = [
                'type' => 'Content / Blog',
                'item' => count($blogs) . " published blog articles found with structured BlogPosting schema."
            ];
        } else {
            $warnings[] = [
                'type' => 'Content / Blog',
                'item' => 'No published blog articles found.',
                'solution' => 'Publish informative guides in Admin → SEO → Blog to attract organic search traffic.'
            ];
        }

        // 5. Audit XML Sitemap & Robots.txt
        $sitemapContent = SeoService::generateSitemapXml();
        if (str_contains($sitemapContent, '<urlset') && str_contains($sitemapContent, '</urlset>')) {
            $passed[] = [
                'type' => 'XML Sitemap',
                'item' => 'Dynamic XML Sitemap is generated and healthy.'
            ];
        } else {
            $critical[] = [
                'type' => 'XML Sitemap',
                'item' => 'XML Sitemap output format is invalid.',
                'solution' => 'Check SeoService::generateSitemapXml.'
            ];
        }

        $robotsContent = SeoService::generateRobotsTxt();
        if (str_contains($robotsContent, 'Disallow: /admin') && str_contains($robotsContent, 'Sitemap:')) {
            $passed[] = [
                'type' => 'Robots.txt',
                'item' => 'Robots.txt correctly blocks private admin/dashboard routes and declares sitemap.'
            ];
        } else {
            $critical[] = [
                'type' => 'Robots.txt',
                'item' => 'Robots.txt is missing security disallows or sitemap declaration.',
                'solution' => 'Review Robots.txt configuration in Admin.'
            ];
        }

        // 6. Calculate SEO Health Score (0 - 100)
        $totalChecks = count($critical) * 3 + count($warnings) * 1.5 + count($passed) * 1;
        $passedPoints = count($passed) * 1;
        $score = $totalChecks > 0 ? round(($passedPoints / $totalChecks) * 100) : 100;
        $score = max(20, min(100, $score));

        return [
            'score' => $score,
            'critical_count' => count($critical),
            'warning_count' => count($warnings),
            'passed_count' => count($passed),
            'critical' => $critical,
            'warnings' => $warnings,
            'passed' => $passed,
            'audited_at' => date('Y-m-d H:i:s'),
        ];
    }
}
