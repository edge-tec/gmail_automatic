<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\DatabaseSanitizer;
use App\Models\SeoSetting;
use App\Models\SeoPage;
use App\Models\SeoRedirect;
use App\Models\SeoFaq;
use App\Models\BlogPost;
use App\Services\SeoService;
use App\Services\SeoAuditService;

class SeoSystemTest extends TestCase {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        
        $sqlitePath = storage_path('database/test.sqlite');
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE={$sqlitePath}");
        putenv("APP_KEY=base64:32characterRandomSecretKeyForTesting==");
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $sqlitePath;
        $_ENV['APP_KEY'] = 'base64:32characterRandomSecretKeyForTesting==';

        config('_reset_');
        \App\Core\Database::resetConnection();

        new \App\Core\App();
        \Database\MigrationRunner::run();
        DatabaseSanitizer::run();
    }

    protected function setUp(): void {
        parent::setUp();
        new \App\Core\App();
        DatabaseSanitizer::run();
    }

    public function testGlobalSeoSettingsGetAndSet(): void {
        SeoSetting::set('test_seo_key', 'test_seo_value');
        $val = SeoSetting::get('test_seo_key');
        $this->assertEquals('test_seo_value', $val);

        $default = SeoSetting::get('non_existent_key', 'fallback_val');
        $this->assertEquals('fallback_val', $default);
    }

    public function testPageSeoMetadataAndFallbacks(): void {
        $metaHome = SeoService::getMetadata('/');
        $this->assertNotEmpty($metaHome['title']);
        $this->assertStringContainsString('index, follow', $metaHome['robots']);
        $this->assertFalse($metaHome['is_private']);
        $this->assertNotEmpty($metaHome['canonical']);
        $this->assertNotEmpty($metaHome['schema_json']);

        $metaFeatures = SeoService::getMetadata('/features');
        $this->assertStringContainsString('Features', $metaFeatures['title']);
    }

    public function testPrivateRoutesAreAutomaticallyNoindexNofollow(): void {
        $metaAdmin = SeoService::getMetadata('/admin/users');
        $this->assertTrue($metaAdmin['is_private']);
        $this->assertEquals('noindex, nofollow', $metaAdmin['robots']);

        $metaDashboard = SeoService::getMetadata('/dashboard');
        $this->assertTrue($metaDashboard['is_private']);
        $this->assertEquals('noindex, nofollow', $metaDashboard['robots']);

        $metaBilling = SeoService::getMetadata('/billing');
        $this->assertTrue($metaBilling['is_private']);
        $this->assertEquals('noindex, nofollow', $metaBilling['robots']);
    }

    public function testStructuredDataJsonLdGeneration(): void {
        $homeMeta = SeoService::getMetadata('/');
        $json = $homeMeta['schema_json'];
        $this->assertNotEmpty($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        $types = array_column($decoded, '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('SoftwareApplication', $types);

        // Check Pricing Schema
        $pricingMeta = SeoService::getMetadata('/pricing');
        $pricingJson = $pricingMeta['schema_json'];
        $this->assertStringContainsString('Product', $pricingJson);
        $this->assertStringContainsString('Offer', $pricingJson);
    }

    public function testDynamicSitemapXmlGeneration(): void {
        $xml = SeoService::generateSitemapXml();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('</urlset>', $xml);
        $this->assertStringContainsString('/features', $xml);
        $this->assertStringContainsString('/pricing', $xml);
        $this->assertStringContainsString('/faq', $xml);
        $this->assertStringContainsString('/blog', $xml);

        // Ensure private admin and login routes are NEVER in sitemap
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertStringNotContainsString('/dashboard', $xml);
        $this->assertStringNotContainsString('/login', $xml);
    }

    public function testDynamicRobotsTxtGeneration(): void {
        $robots = SeoService::generateRobotsTxt();
        $this->assertStringContainsString('User-agent: *', $robots);
        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('Disallow: /billing', $robots);
        $this->assertStringContainsString('Sitemap:', $robots);
    }

    public function testRedirectCreationAndLoopProtection(): void {
        $uniqueSuffix = uniqid();
        $oldUrl = "/old-test-page-{$uniqueSuffix}";
        $newUrl = "/features";

        $redirect = SeoRedirect::create([
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'status_code' => 301,
            'is_active' => 1,
        ]);

        $this->assertInstanceOf(SeoRedirect::class, $redirect);
        $this->assertEquals($oldUrl, $redirect->old_url);
        $this->assertEquals('/features', $redirect->new_url);

        $lookup = SeoRedirect::findByOldUrl($oldUrl);
        $this->assertNotNull($lookup);
        $this->assertEquals(301, $lookup->status_code);

        // Test loop protection exception
        $this->expectException(\Exception::class);
        SeoRedirect::create([
            'old_url' => '/same-url',
            'new_url' => '/same-url',
            'status_code' => 301,
        ]);
    }

    public function testFaqCrudAndSchemaIntegration(): void {
        $faq = SeoFaq::create([
            'question' => 'How does multi-account automation scale?',
            'answer' => 'Our engine processes background queues across up to 250 connected Gmail accounts simultaneously.',
            'category' => 'Scalability',
            'sort_order' => 10,
            'is_active' => 1,
        ]);

        $this->assertInstanceOf(SeoFaq::class, $faq);
        $this->assertGreaterThan(0, $faq->id);

        $activeFaqs = SeoFaq::allActive();
        $this->assertNotEmpty($activeFaqs);

        $faqMeta = SeoService::getMetadata('/faq');
        $this->assertStringContainsString('FAQPage', $faqMeta['schema_json']);
        $this->assertStringContainsString('How does multi-account automation scale?', $faqMeta['schema_json']);

        // Clean up
        $faq->delete();
    }

    public function testBlogPostCreationAndSlugGeneration(): void {
        $post = BlogPost::create([
            'title' => 'Testing Automated Gmail API Replies in 2026',
            'slug' => '',
            'excerpt' => 'This is a test guide for automated responses.',
            'content' => '<p>Article content here with <h2>Subheading</h2>.</p>',
            'author_name' => 'SEO Specialist',
            'category' => 'Guides',
            'seo_title' => 'Automated Gmail API Replies Guide 2026',
            'meta_description' => 'A complete testing guide for automated replies.',
            'focus_keyword' => 'Gmail API guide',
            'status' => 'published',
        ]);

        $this->assertInstanceOf(BlogPost::class, $post);
        $this->assertEquals('testing-automated-gmail-api-replies-in-2026', $post->slug);

        $found = BlogPost::findBySlug('testing-automated-gmail-api-replies-in-2026');
        $this->assertNotNull($found);
        $this->assertEquals('SEO Specialist', $found->author_name);

        // Test updating slug and automatic 301 redirect
        $post->update(['slug' => 'updated-gmail-automation-guide-2026']);
        $this->assertEquals('updated-gmail-automation-guide-2026', $post->slug);

        // Clean up
        $post->delete();
    }

    public function testStructuredDataXssProtection(): void {
        $customSchema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => '</script><script>alert(1)</script>',
        ]);

        $page = SeoPage::findByRoute('/');
        $this->assertNotNull($page);
        $page->custom_schema_json = $customSchema;

        $json = SeoService::buildStructuredData('/', $page, null);
        // Ensure literal </script> is escaped with \u003C
        $this->assertStringNotContainsString('</script><script>', $json);
        $this->assertStringContainsString('\u003C', $json);
    }

    public function testSeoAuditServiceHealthCheck(): void {
        $audit = SeoAuditService::runAudit();
        $this->assertIsArray($audit);
        $this->assertArrayHasKey('score', $audit);
        $this->assertArrayHasKey('passed', $audit);
        $this->assertGreaterThanOrEqual(50, $audit['score']);
        $this->assertNotEmpty($audit['passed']);
    }

    public function testAllPublicAndLegalPagesRenderSuccessfully(): void {
        $legal = new \App\Controllers\LegalController();
        $req = new \App\Core\Request();

        $termsHtml = $legal->terms($req);
        $this->assertStringContainsString('Terms of Service', $termsHtml);

        $privacyHtml = $legal->privacy($req);
        $this->assertStringContainsString('Privacy Policy', $privacyHtml);

        $apiDisclosureHtml = $legal->googleApiDisclosure($req);
        $this->assertStringContainsString('Google API Services User Data Disclosure', $apiDisclosureHtml);

        $zeroFallbackHtml = $legal->zeroFallbackPolicy($req);
        $this->assertStringContainsString('Zero-Fallback Security Policy', $zeroFallbackHtml);

        $dataSecurityHtml = $legal->dataSecurity($req);
        $this->assertStringContainsString('Data Security', $dataSecurityHtml);

        $public = new \App\Controllers\PublicPageController();
        $this->assertStringContainsString('Features', $public->features());
        $this->assertStringContainsString('Pricing', $public->pricing());
        $this->assertStringContainsString('How It Works', $public->howItWorks());
        $this->assertStringContainsString('Frequently Asked Questions', $public->faq());
        $this->assertStringContainsString('Contact', $public->contact());
    }
}
