<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app.name', 'Gmail Automation Engine')) ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= url('/css/app.css') ?>">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside class="sidebar d-flex flex-column p-3">
            <a href="<?= url('/dashboard') ?>" class="d-flex align-items-center mb-4 text-white text-decoration-none px-2 gap-2">
                <i class="fa-solid fa-bolt-lightning text-warning fs-4"></i>
                <span class="fs-5 fw-bold tracking-tight">GmailAuto</span>
            </a>
            
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= url('/dashboard') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/accounts') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/accounts') ? 'active' : '' ?>">
                        <i class="fa-brands fa-google"></i>
                        <span>Gmail Accounts</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/settings/automation') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/settings/automation') ? 'active' : '' ?>">
                        <i class="fa-solid fa-robot"></i>
                        <span>Auto Reply</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/settings/followups') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/settings/followups') ? 'active' : '' ?>">
                        <i class="fa-solid fa-arrows-split-up-and-left"></i>
                        <span>Follow-up Sequence</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/rules') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/rules') ? 'active' : '' ?>">
                        <i class="fa-solid fa-filter text-info"></i>
                        <span>Filter Rules</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/threads') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/threads') ? 'active' : '' ?>">
                        <i class="fa-solid fa-comments"></i>
                        <span>Conversations</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/billing') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/billing') ? 'active' : '' ?>">
                        <i class="fa-solid fa-credit-card text-success"></i>
                        <span>Billing &amp; Plan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/logs') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/logs') ? 'active' : '' ?>">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>

                <?php if (auth_user() && auth_user()->role === 'admin'): ?>
                <hr class="my-3">
                <div class="sidebar-heading px-2 mb-2">Admin Area</div>
                <li>
                    <a href="<?= url('/admin') ?>" class="nav-link <?= $_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Admin Overview</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/users') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/users') ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i>
                        <span>Users &amp; Plans</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/plans') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/plans') ? 'active' : '' ?>">
                        <i class="fa-solid fa-tags text-primary"></i>
                        <span>Subscription Plans</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/trial') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/trial') ? 'active' : '' ?>">
                        <i class="fa-solid fa-gift text-warning"></i>
                        <span>Trial Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/smtp') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/smtp') ? 'active' : '' ?>">
                        <i class="fa-solid fa-server text-info"></i>
                        <span>SMTP Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/email-templates') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/email-templates') ? 'active' : '' ?>">
                        <i class="fa-solid fa-envelope-open-text text-purple" style="color:#a855f7;"></i>
                        <span>Email Templates</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/gateways') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/gateways') ? 'active' : '' ?>">
                        <i class="fa-solid fa-credit-card text-success"></i>
                        <span>Payment Gateways</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/payments') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/payments') ? 'active' : '' ?>">
                        <i class="fa-solid fa-receipt text-primary"></i>
                        <span>Payments &amp; Invoices</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/filters') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/filters') ? 'active' : '' ?>">
                        <i class="fa-solid fa-ban text-danger"></i>
                        <span>Blacklist Filters</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/settings') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/settings') ? 'active' : '' ?>">
                        <i class="fa-solid fa-sliders"></i>
                        <span>API &amp; Stripe Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('/admin/logs') ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/logs') ? 'active' : '' ?>">
                        <i class="fa-solid fa-list-check"></i>
                        <span>System Logs</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="mt-auto pt-3 border-top border-secondary">
                <div class="d-flex align-items-center justify-content-between px-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                            <?= strtoupper(substr(auth_user()->name ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="text-truncate" style="max-width: 130px;">
                            <div class="text-white small fw-semibold text-truncate"><?= e(auth_user()->name ?? 'User') ?></div>
                            <div class="user-role"><?= ucfirst(e(auth_user()->role ?? 'user')) ?></div>
                        </div>
                    </div>
                    <a href="<?= url('/logout') ?>" class="sidebar-logout-btn" title="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0 fw-bold"><?= \App\Core\View::yield('title', 'Gmail Automation') ?></h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-clock me-1 text-primary"></i> <?= date('d M Y, H:i') ?></span>
                    <a href="<?= url('/accounts/connect') ?>" class="btn btn-sm btn-primary">
                        <i class="fa-brands fa-google me-1"></i> Connect Gmail
                    </a>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="p-4">
                <!-- Flash Messages -->
                <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="fa-solid fa-circle-check fs-5"></i>
                    <div><?= e($msg) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                    <div><?= e($msg) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($msg = flash('warning')): ?>
                <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                    <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                    <div><?= e($msg) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- View Output -->
                <?= $content ?>
            </main>

            <!-- Footer -->
            <footer class="mt-auto py-3 px-4 border-top bg-white text-muted small d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    &copy; <?= date('Y') ?> <strong><?= e(config('app.name', 'Gmail Automation System')) ?></strong>. All rights reserved.
                </div>
                <div class="text-secondary">
                    All rights &amp; design by <strong>Mizanur Rahman</strong> | <i class="fa-solid fa-phone text-primary me-1"></i><a href="tel:+8801611195794" class="text-decoration-none text-secondary fw-bold">+8801611195794</a>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= url('/js/app.js') ?>"></script>
</body>
</html>
