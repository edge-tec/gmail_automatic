<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Legal Documents') ?> - <?= e(config('app.name', 'Gmail Automation')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/css/app.css') ?>">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #334155;
        }
        .legal-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 45px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .legal-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            padding: 40px;
            margin-top: -30px;
            margin-bottom: 50px;
        }
        .legal-card h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .legal-card p, .legal-card li {
            font-size: 0.95rem;
            line-height: 1.75;
            color: #475569;
        }
        .legal-card ul {
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url('/') ?>">
                <div class="bg-primary text-white rounded p-1 px-2">
                    <i class="fa-solid fa-bolt-lightning text-warning"></i>
                </div>
                <span>Gmail Automation</span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="<?= url('/login') ?>" class="btn btn-sm btn-outline-light">Sign In</a>
                <a href="<?= url('/register') ?>" class="btn btn-sm btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="legal-header">
        <div class="container text-center">
            <h1 class="fw-bold mb-2"><?= e($pageTitle ?? 'Legal Information') ?></h1>
            <p class="text-white-50 mb-0">Last updated: <?= date('F d, Y') ?></p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <div class="legal-card">
                    <?= $content ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-top py-4 text-center text-muted small">
        <div class="container">
            <div class="d-flex justify-content-center gap-3 mb-2">
                <a href="<?= url('/privacy') ?>" class="text-decoration-none text-muted">Privacy Policy</a>
                <span>&bull;</span>
                <a href="<?= url('/terms') ?>" class="text-decoration-none text-muted">Terms of Service</a>
                <span>&bull;</span>
                <a href="<?= url('/login') ?>" class="text-decoration-none text-muted">Dashboard</a>
            </div>
            <div>&copy; <?= date('Y') ?> Gmail Automation Platform (2xbets.net). All rights reserved.</div>
        </div>
    </footer>
</body>
</html>
