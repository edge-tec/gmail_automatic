<div class="card p-5 text-center my-5 mx-auto" style="max-width: 500px;">
    <div class="display-1 fw-bold text-danger mb-2">500</div>
    <h4 class="fw-bold mb-2">Internal Server Error</h4>
    <p class="text-muted small mb-4"><?= e($message ?? 'Something went wrong while processing your request. Please check the logs or contact the administrator.') ?></p>
    <div>
        <a href="<?= url('/dashboard') ?>" class="btn btn-primary px-4">
            <i class="fa-solid fa-house me-1"></i> Return to Dashboard
        </a>
    </div>
</div>
