<h4 class="fw-bold mb-3">Create Account</h4>
<p class="text-muted small mb-4">Start automating your Gmail auto replies and follow-ups.</p>

<form action="<?= url('/register') ?>" method="POST">
    <?= csrf_field() ?>
    
    <div class="mb-3">
        <label class="form-label small fw-semibold">Full Name</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
            <input type="text" name="name" class="form-control" placeholder="John Doe" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
            <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label small fw-semibold">Confirm Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-shield-halved text-muted"></i></span>
            <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
        Register Account <i class="fa-solid fa-arrow-right ms-1"></i>
    </button>

    <div class="text-center small text-muted">
        Already have an account? <a href="<?= url('/login') ?>" class="text-primary fw-semibold text-decoration-none">Sign In</a>
    </div>
</form>
