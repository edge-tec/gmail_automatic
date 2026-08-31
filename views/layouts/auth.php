<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app.name', 'Gmail Automation Engine')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/css/app.css') ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="w-100" style="max-width: 440px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-3 p-3 mb-2 shadow-sm">
                <i class="fa-solid fa-bolt-lightning fs-3 text-warning"></i>
            </div>
            <h4 class="fw-bold mb-1">Gmail Auto Reply & Follow-up</h4>
            <p class="text-muted small">Official Gmail API Automation Engine</p>
        </div>

        <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-lg p-4">
            <?= $content ?>
        </div>

        <div class="text-center mt-4 text-muted small">
            <div class="d-flex justify-content-center gap-3 mb-1">
                <a href="<?= url('/privacy') ?>" class="text-decoration-none text-muted">Privacy Policy</a>
                <span>&bull;</span>
                <a href="<?= url('/terms') ?>" class="text-decoration-none text-muted">Terms of Service</a>
            </div>
            <div>&copy; <?= date('Y') ?> Gmail Automation System. All rights reserved.</div>
            <div class="mt-1 text-secondary">All rights &amp; design by <strong>Mizanur Rahman</strong> | <a href="tel:+8801611195794" class="text-decoration-none text-secondary fw-semibold">+8801611195794</a></div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
