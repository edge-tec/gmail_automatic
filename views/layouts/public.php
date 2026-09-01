<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/css/app.css') ?>">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #334155;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
        .public-nav {
            background: #0f172a;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .nav-link-custom {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            color: #ffffff;
        }
        .page-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 70px 0 50px;
            position: relative;
            overflow: hidden;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.15), transparent 60%);
            pointer-events: none;
        }
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            padding: 40px;
            margin-top: -30px;
            margin-bottom: 60px;
            position: relative;
            z-index: 2;
        }
        .footer-public {
            background: #0b1120;
            color: #94a3b8;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .footer-public a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .footer-public a:hover {
            color: #ffffff;
        }
    </style>
    <?= \App\Services\SeoService::renderHeadTags($seoPath ?? null, $seoOverride ?? null) ?>
</head>
<body>
    <!-- Public Navigation Bar -->
    <header>
        <nav class="navbar navbar-expand-lg public-nav py-3" aria-label="Main Navigation">
            <div class="container">
                <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-white" href="<?= url('/') ?>">
                    <div class="bg-primary text-white rounded-3 p-1 px-2 shadow-sm">
                        <i class="fa-solid fa-bolt-lightning text-warning"></i>
                    </div>
                    <span><?= e(\App\Services\SeoService::getSiteName()) ?></span>
                </a>
                <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavDropdown" aria-controls="publicNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="collapse navbar-collapse" id="publicNavDropdown">
                    <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/') ?>">Home</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/features') ?>">Features</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/pricing') ?>">Pricing</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/how-it-works') ?>">How It Works</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/faq') ?>">FAQ</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/blog') ?>">Blog</a></li>
                        <li class="nav-item"><a class="nav-link nav-link-custom" href="<?= url('/contact') ?>">Contact</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (\App\Core\Auth::check()): ?>
                        <a href="<?= url('/dashboard') ?>" class="btn btn-sm btn-primary fw-bold px-3 py-2">
                            <i class="fa-solid fa-gauge me-1"></i> Dashboard
                        </a>
                        <?php else: ?>
                        <a href="<?= url('/login') ?>" class="btn btn-sm btn-outline-light px-3 py-2 fw-semibold">Sign In</a>
                        <a href="<?= url('/register') ?>" class="btn btn-sm btn-primary px-3 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-sparkles me-1"></i> Start Free Trial
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main>
        <?php if (isset($pageHeroTitle)): ?>
        <section class="page-hero text-center">
            <div class="container">
                <nav aria-label="breadcrumb" class="mb-3 d-flex justify-content-center">
                    <ol class="breadcrumb mb-0 small text-white-50">
                        <li class="breadcrumb-item"><a href="<?= url('/') ?>" class="text-white-50 text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?= e($breadcrumbCurrent ?? $pageHeroTitle) ?></li>
                    </ol>
                </nav>
                <h1 class="fw-extrabold display-6 mb-2"><?= e($pageHeroTitle) ?></h1>
                <?php if (isset($pageHeroSubtitle)): ?>
                <p class="text-white-50 fs-6 max-w-600 mx-auto mb-0"><?= e($pageHeroSubtitle) ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (isset($rawLayout) && $rawLayout): ?>
            <?= $content ?>
        <?php else: ?>
            <div class="container my-5">
                <?= flash_messages() ?>
                <?= $content ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer Section -->
    <footer class="footer-public py-5 mt-auto">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-center gap-2 text-white mb-3">
                        <div class="bg-primary text-white rounded-3 p-1 px-2">
                            <i class="fa-solid fa-bolt-lightning text-warning"></i>
                        </div>
                        <span class="fw-bold fs-5"><?= e(\App\Services\SeoService::getSiteName()) ?></span>
                    </div>
                    <p class="small text-secondary mb-3 leading-relaxed">
                        Official Google API-backed cloud automation software. Scale your response speed with 24/7 automated replies, multi-step sequential follow-ups, and duplicate traffic protection.
                    </p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-secondary bg-opacity-25 text-light px-3 py-2"><i class="fa-solid fa-shield-halved text-success me-1"></i> Google Verified API</span>
                        <span class="badge bg-secondary bg-opacity-25 text-light px-3 py-2"><i class="fa-solid fa-lock text-info me-1"></i> Stripe 256-bit</span>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="text-white fw-bold mb-3 small text-uppercase tracking-wider">Product</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="<?= url('/features') ?>">Features</a></li>
                        <li><a href="<?= url('/pricing') ?>">Pricing Plans</a></li>
                        <li><a href="<?= url('/how-it-works') ?>">How It Works</a></li>
                        <li><a href="<?= url('/faq') ?>">FAQ</a></li>
                        <li><a href="<?= url('/register') ?>">7-Day Free Trial</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="text-white fw-bold mb-3 small text-uppercase tracking-wider">Resources</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="<?= url('/blog') ?>">Automation Blog</a></li>
                        <li><a href="<?= url('/contact') ?>">Contact Support</a></li>
                        <li><a href="<?= url('/sitemap.xml') ?>">XML Sitemap</a></li>
                        <li><a href="<?= url('/robots.txt') ?>">Robots.txt</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="text-white fw-bold mb-3 small text-uppercase tracking-wider">Legal &amp; Safety</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="<?= url('/privacy') ?>">Privacy Policy</a></li>
                        <li><a href="<?= url('/terms') ?>">Terms of Service</a></li>
                        <li><a href="<?= url('/google-api-disclosure') ?>">Google API Disclosure</a></li>
                        <li><a href="<?= url('/zero-fallback-policy') ?>">Zero-Fallback Policy</a></li>
                        <li><a href="<?= url('/data-security') ?>">Data Security</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="text-white fw-bold mb-3 small text-uppercase tracking-wider">Support</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><i class="fa-solid fa-envelope me-1 text-primary"></i> <a href="mailto:<?= e(\App\Models\SeoSetting::get('support_email', 'support@2xbets.net')) ?>"><?= e(\App\Models\SeoSetting::get('support_email', 'support@2xbets.net')) ?></a></li>
                        <li><i class="fa-solid fa-clock me-1 text-success"></i> 24/7 Background Engine</li>
                    </ul>
                </div>
            </div>
            <div class="pt-4 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-3 small text-secondary">
                <div>&copy; <?= date('Y') ?> <?= e(\App\Services\SeoService::getSiteName()) ?> (2xbets.net). All rights reserved.</div>
                <div>All rights &amp; design by <strong>Mizanur Rahman</strong> | <a href="tel:+8801611195794" class="text-secondary fw-semibold">+8801611195794</a></div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
