<div class="card p-5 text-center my-5 mx-auto" style="max-width: 500px;">
    <div class="display-1 fw-bold text-primary mb-2">404</div>
    <h4 class="fw-bold mb-2">Page Not Found</h4>
    <p class="text-muted small mb-4"><?= e($message ?? 'The page you requested could not be found.') ?></p>
    <div>
        <a href="<?= url('/dashboard') ?>" class="btn btn-primary px-4">
            <i class="fa-solid fa-house me-1"></i> Go to Dashboard
        </a>
    </div>
</div>
