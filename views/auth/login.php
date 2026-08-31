<h4 class="fw-bold mb-3">Sign In</h4>
<p class="text-muted small mb-4">Enter your credentials to access your automation portal.</p>

<form action="<?= url('/login') ?>" method="POST">
    <?= csrf_field() ?>
    
    <div class="mb-3">
        <label class="form-label small fw-semibold">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required autofocus>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label small fw-semibold">Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
        Sign In <i class="fa-solid fa-arrow-right ms-1"></i>
    </button>

    <div class="text-center small text-muted">
        Don't have an account? <a href="<?= url('/register') ?>" class="text-primary fw-semibold text-decoration-none">Create Account</a>
    </div>
</form>
