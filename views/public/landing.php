<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail Automation - Automate Your Gmail Replies & Follow-ups</title>
    <meta name="description" content="Connect multiple Gmail accounts, automatically send custom replies, schedule smart multi-step follow-ups, and manage full email automation 24/7.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --hero-bg: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.12) 0%, rgba(248, 250, 252, 0) 70%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            --card-hover-shadow: 0 20px 30px -10px rgba(79, 70, 229, 0.12), 0 10px 15px -5px rgba(0, 0, 0, 0.04);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            overflow-x: hidden;
        }
        .navbar-custom {
            backdrop-filter: blur(12px);
            background-color: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
        }
        .hero-section {
            background: var(--hero-bg);
            padding: 120px 0 80px 0;
            position: relative;
        }
        .badge-trial {
            background: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
            border: 1px solid rgba(79, 70, 229, 0.2);
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 50px;
        }
        .btn-gradient {
            background: var(--primary-gradient);
            color: #ffffff;
            border: none;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 8px;
            box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.35);
            transition: all 0.25s ease;
        }
        .btn-gradient:hover {
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(79, 70, 229, 0.45);
        }
        .feature-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
            border-color: #e0e7ff;
        }
        .feature-icon-wrapper {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        .dashboard-mockup {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .pricing-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .pricing-card.popular {
            border: 2px solid #4f46e5;
            box-shadow: 0 20px 35px -5px rgba(79, 70, 229, 0.18);
        }
        .popular-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-gradient);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 4px 16px;
            border-radius: 20px;
        }
        .step-number {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            color: #4f46e5;
            font-weight: 800;
            font-size: 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }
        .cta-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #ffffff;
            border-radius: 24px;
            padding: 70px 40px;
            position: relative;
            overflow: hidden;
        }
        .faq-accordion .accordion-button {
            font-weight: 600;
            font-size: 1.05rem;
            padding: 20px;
            border-radius: 12px !important;
            background: #f8fafc;
        }
        .faq-accordion .accordion-button:not(.collapsed) {
            background: #eef2ff;
            color: #4f46e5;
        }
        .faq-accordion .accordion-item {
            border: none;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>

    <!-- 1. NAVIGATION BAR -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url('/') ?>">
                <div class="bg-primary text-white rounded-3 p-2 shadow-sm">
                    <i class="fa-solid fa-bolt-lightning text-warning fs-5"></i>
                </div>
                <span class="fs-4 text-dark">Gmail Automation</span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <i class="fa-solid fa-bars-staggered fs-4"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold text-secondary gap-lg-3">
                    <li class="nav-item"><a class="nav-link text-dark" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#pricing">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link text-dark" href="#faq">FAQ</a></li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <?php if (\App\Core\Auth::check()): ?>
                        <a href="<?= url('/dashboard') ?>" class="btn btn-outline-primary fw-semibold px-3 py-2">
                            <i class="fa-solid fa-gauge me-1"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= url('/login') ?>" class="btn btn-link text-decoration-none text-dark fw-semibold px-2">Login</a>
                        <a href="<?= url('/register') ?>" class="btn btn-gradient">
                            <?= $trialEnabled ? "Start Free Trial" : "Get Started" ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 2. HERO SECTION -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-12 col-lg-6 text-center text-lg-start">
                    <?php if ($trialEnabled): ?>
                    <div class="d-inline-flex align-items-center gap-2 badge-trial mb-3">
                        <i class="fa-solid fa-gift"></i>
                        <span>Try Free for <?= $trialDays ?> Days &bull; Connect Up To <?= $trialGmailLimit ?> Accounts</span>
                    </div>
                    <?php endif; ?>

                    <h1 class="display-5 fw-extrabold text-dark tracking-tight mb-3">
                        Automate Your Gmail Replies &amp; Follow-ups
                    </h1>
                    <p class="lead text-secondary mb-4 fs-5" style="line-height: 1.7;">
                        Connect your Gmail accounts, automatically reply to incoming emails, schedule personalized follow-ups, and manage your email automation from one powerful platform.
                    </p>

                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-4">
                        <a href="<?= url('/register') ?>" class="btn btn-gradient btn-lg px-4 py-3">
                            <i class="fa-solid fa-play me-2"></i> <?= $trialEnabled ? "Start {$trialDays}-Day Free Trial" : "Start Now" ?>
                        </a>
                        <a href="#pricing" class="btn btn-outline-dark btn-lg px-4 py-3 fw-semibold">
                            <i class="fa-solid fa-tags me-2"></i> View Pricing
                        </a>
                    </div>

                    <!-- Trust Points -->
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start text-muted small fw-semibold mb-2">
                        <span><i class="fa-solid fa-shield-check text-success me-1"></i> Secure Gmail OAuth</span>
                        <span>&bull;</span>
                        <span><i class="fa-solid fa-bolt text-primary me-1"></i> Automated Replies</span>
                        <span>&bull;</span>
                        <span><i class="fa-solid fa-arrows-split-up-and-left text-info me-1"></i> Smart Follow-ups</span>
                        <span>&bull;</span>
                        <span><i class="fa-solid fa-server text-warning me-1"></i> Server-Side Automation</span>
                    </div>

                    <div class="text-center text-lg-start text-muted small">
                        <a href="https://2xbets.net/privacy" class="text-secondary text-decoration-none fw-semibold">Privacy Policy</a>
                        <span class="mx-1">&bull;</span>
                        <a href="https://2xbets.net/terms" class="text-secondary text-decoration-none fw-semibold">Terms of Service</a>
                    </div>
                </div>

                <!-- Product Mockup Preview -->
                <div class="col-12 col-lg-6">
                    <div class="dashboard-mockup p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="fa-solid fa-circle text-success small me-1"></i> Automation Active</span>
                                <span class="text-muted small fw-semibold">24/7 Engine</span>
                            </div>
                            <span class="badge bg-light text-dark border">Server Queue: Active</span>
                        </div>

                        <!-- Mini Stats Row -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-sm-3">
                                <div class="bg-light p-2 rounded-3 text-center">
                                    <div class="text-muted" style="font-size: 0.75rem;">Connected Gmail</div>
                                    <div class="fw-bold fs-6 text-dark mt-1">85 / 100</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="bg-light p-2 rounded-3 text-center">
                                    <div class="text-muted" style="font-size: 0.75rem;">Replies Today</div>
                                    <div class="fw-bold fs-6 text-success mt-1">47</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="bg-light p-2 rounded-3 text-center">
                                    <div class="text-muted" style="font-size: 0.75rem;">Follow-ups Today</div>
                                    <div class="fw-bold fs-6 text-info mt-1">22</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="bg-light p-2 rounded-3 text-center">
                                    <div class="text-muted" style="font-size: 0.75rem;">Success Rate</div>
                                    <div class="fw-bold fs-6 text-primary mt-1">99.8%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Mock Thread Item -->
                        <div class="border rounded-3 p-3 bg-white mb-2 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-dark"><i class="fa-solid fa-envelope text-primary me-1"></i> Sarah Jenkins &lt;sarah@company.com&gt;</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary">Step #1 Replied</span>
                            </div>
                            <p class="text-muted small mb-1">"Hi, I am interested in your software services..."</p>
                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
                                <span><i class="fa-solid fa-check-double text-success me-1"></i> Custom User Message Sent</span>
                                <span>Scheduled Follow-up in 2 days</span>
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 bg-light bg-opacity-50">
                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                <span><i class="fa-solid fa-shield-halved text-success me-1"></i> 100% User-Configured Message Guarantee</span>
                                <span class="fw-semibold text-primary">Zero Fallback Policy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. PROBLEM / SOLUTION SECTION -->
    <section class="py-5 bg-light">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">The Challenge</span>
                <h2 class="fw-bold text-dark mt-1">Stop Managing Every Email Manually</h2>
                <p class="text-muted">Handling hundreds of incoming leads and remembering timely follow-ups manually is time-consuming and causes lost deals.</p>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border h-100 shadow-sm">
                        <div class="text-danger fs-3 mb-3"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <h5 class="fw-bold">Manual Replies</h5>
                        <p class="text-muted mb-0">Responding to repetitive inquiries by hand takes hours every day and leads to slow response times.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border h-100 shadow-sm">
                        <div class="text-warning fs-3 mb-3"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <h5 class="fw-bold">Missed Follow-ups</h5>
                        <p class="text-muted mb-0">Crucial conversations get buried in inboxes, and leads go cold without consistent, timely follow-up sequences.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border h-100 shadow-sm">
                        <div class="text-primary fs-3 mb-3"><i class="fa-brands fa-google"></i></div>
                        <h5 class="fw-bold">Multiple Gmail Accounts</h5>
                        <p class="text-muted mb-0">Switching back and forth between 50 to 250 different Gmail accounts individually is inefficient and prone to mistakes.</p>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-4 text-center bg-white border border-primary border-opacity-25 shadow-sm">
                <h5 class="fw-bold text-primary mb-1"><i class="fa-solid fa-sparkles me-2"></i> The Solution</h5>
                <p class="text-secondary mb-0 fs-6 fw-medium">
                    Automate repetitive email communication while keeping complete control over your messages, schedules, and accounts from a single centralized dashboard.
                </p>
            </div>
        </div>
    </section>

    <!-- 4. FEATURES SECTION (12 Rich Cards) -->
    <section id="features" class="py-5">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Everything You Need</span>
                <h2 class="fw-bold text-dark mt-1">Enterprise-Grade Email Automation Features</h2>
                <p class="text-muted">Built for speed, accuracy, and strict security to supercharge your email campaigns and communication.</p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-primary bg-opacity-10 text-primary">
                            <i class="fa-brands fa-google"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Multiple Gmail Accounts</h5>
                        <p class="text-muted small mb-0">Connect and manage 100 to 250+ Gmail accounts from one centralized dashboard based on your active plan.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-reply-all"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Automatic Replies</h5>
                        <p class="text-muted small mb-0">Instantly or with custom delays respond to new eligible incoming emails with precision server processing.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-indigo bg-opacity-10 text-indigo" style="background:#eef2ff; color:#4f46e5;">
                            <i class="fa-solid fa-pen-nib"></i>
                        </div>
                        <h5 class="fw-bold mb-2">100% Custom Messages</h5>
                        <p class="text-muted small mb-0">The system sends strictly your configured user message. Zero default, fallback, or hardcoded emails are ever dispatched.</p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-arrows-split-up-and-left"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Sequential Follow-ups</h5>
                        <p class="text-muted small mb-0">Build automated multi-step follow-up sequences with dedicated delays (e.g. Step 1 in 2 days, Step 2 in 4 days).</p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-ban"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Smart Cancellation</h5>
                        <p class="text-muted small mb-0">When a prospect replies to your email, all remaining pending follow-up jobs for that conversation stop automatically.</p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Custom Sending Limits</h5>
                        <p class="text-muted small mb-0">Set daily reply limits, daily follow-up limits, and max reply per thread to protect your sender reputation.</p>
                    </div>
                </div>

                <!-- Feature 7 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-dark bg-opacity-10 text-dark">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Smart Scheduler &amp; Hours</h5>
                        <p class="text-muted small mb-0">24/7 server-side queue processing. Set working days and business hours in any global timezone.</p>
                    </div>
                </div>

                <!-- Feature 8 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-teal bg-opacity-10 text-teal" style="background:#ccfbf1; color:#0d9488;">
                            <i class="fa-solid fa-shield-virus"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Queue &amp; Deletion Guard</h5>
                        <p class="text-muted small mb-0">Queued jobs are re-validated right before sending. If you edit or delete a message, old messages are never sent.</p>
                    </div>
                </div>

                <!-- Feature 9 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Gmail Thread Preservation</h5>
                        <p class="text-muted small mb-0">Keeps all automated replies and follow-ups within the same Gmail thread with accurate In-Reply-To and References.</p>
                    </div>
                </div>

                <!-- Feature 10 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-purple bg-opacity-10 text-purple" style="background:#f3e8ff; color:#9333ea;">
                            <i class="fa-solid fa-code"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Dynamic Template Variables</h5>
                        <p class="text-muted small mb-0">Personalize emails with variables like <code>{{sender_email}}</code>, <code>{{sender_name}}</code>, <code>{{subject}}</code>, and <code>{{date}}</code>.</p>
                    </div>
                </div>

                <!-- Feature 11 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-secondary bg-opacity-10 text-secondary">
                            <i class="fa-solid fa-filter"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Automation &amp; Filter Rules</h5>
                        <p class="text-muted small mb-0">Trigger targeted responses or skip emails based on sender, domain, subject keywords, or spam patterns.</p>
                    </div>
                </div>

                <!-- Feature 12 -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Real-Time Analytics Dashboard</h5>
                        <p class="text-muted small mb-0">Track incoming messages, replies sent, pending queue jobs, account limits, and trial status live.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. HOW IT WORKS -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Simple Setup</span>
                <h2 class="fw-bold text-dark mt-1">Get Started in 3 Easy Steps</h2>
                <p class="text-muted">No complex setup. Connect your Gmail, write your message, and let the server handle the rest.</p>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border text-center h-100 shadow-sm">
                        <div class="step-number mx-auto">01</div>
                        <h5 class="fw-bold mb-2">Connect Gmail</h5>
                        <p class="text-muted small mb-0">Securely authenticate your Gmail accounts with one click using official Google OAuth 2.0.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border text-center h-100 shadow-sm">
                        <div class="step-number mx-auto">02</div>
                        <h5 class="fw-bold mb-2">Configure Automation</h5>
                        <p class="text-muted small mb-0">Set your custom rich-text reply messages, step delays, follow-up sequences, and working hours.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bg-white p-4 rounded-4 border text-center h-100 shadow-sm">
                        <div class="step-number mx-auto">03</div>
                        <h5 class="fw-bold mb-2">Let Automation Work</h5>
                        <p class="text-muted small mb-0">The 24/7 background queue engine monitors inboxes and dispatches scheduled replies automatically.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. SECURITY SECTION -->
    <section class="py-5">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-12 col-lg-6">
                    <span class="text-primary fw-bold text-uppercase small tracking-wide">Enterprise Protection</span>
                    <h2 class="fw-bold text-dark mt-1 mb-3">Built With Security &amp; Privacy in Mind</h2>
                    <p class="text-muted mb-4">
                        Your account credentials and sensitive data are strictly guarded. We never store Gmail passwords or raw credit card details on our servers.
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check text-success fs-5 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Google OAuth 2.0</h6>
                                    <p class="text-muted small mb-0">Official tokenized authentication.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check text-success fs-5 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Stripe Checkout</h6>
                                    <p class="text-muted small mb-0">PCI-compliant secure billing.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check text-success fs-5 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">CSRF &amp; XSS Protection</h6>
                                    <p class="text-muted small mb-0">Protected web request pipeline.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check text-success fs-5 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Server-Side Queue</h6>
                                    <p class="text-muted small mb-0">Isolated execution environment.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="bg-light p-4 p-md-5 rounded-4 border text-center">
                        <div class="d-inline-flex p-3 rounded-circle bg-white shadow-sm text-primary fs-2 mb-3">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Zero Fallback Security Policy</h4>
                        <p class="text-muted small mb-0">
                            Our system enforces an absolute zero-fallback policy. If you delete a message or turn off automation, no placeholder or boilerplate text will ever be sent from your Gmail account.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. PRICING SECTION (Dynamic Database-Driven) -->
    <section id="pricing" class="py-5 bg-light">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Simple, Transparent Pricing</span>
                <h2 class="fw-bold text-dark mt-1">Choose the Right Plan for Your Business</h2>
                <p class="text-muted">Flexible subscription tiers with high Gmail account limits, smart queue workers, and continuous updates.</p>
                
                <?php if ($trialEnabled): ?>
                <div class="alert alert-info d-inline-block py-2 px-4 rounded-pill mt-2 shadow-sm">
                    <i class="fa-solid fa-gift me-2 text-primary"></i> <strong>Try Free for <?= $trialDays ?> Days</strong> &bull; No card required for trial
                </div>
                <?php endif; ?>
            </div>

            <div class="row justify-content-center g-4 mb-5">
                <?php foreach ($plans as $plan): ?>
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="pricing-card p-4 p-md-5 <?= $plan->is_popular ? 'popular' : '' ?>">
                        <?php if ($plan->is_popular): ?>
                        <div class="popular-badge">Most Popular</div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h4 class="fw-bold text-dark mb-1"><?= e($plan->name) ?></h4>
                            <p class="text-muted small mb-3">Perfect for high-volume email campaigns &amp; agencies.</p>
                            <div class="d-flex align-items-baseline gap-1">
                                <span class="fs-1 fw-extrabold text-dark">$<?= number_format($plan->price, 0) ?></span>
                                <span class="text-muted fw-semibold">/ <?= e($plan->billing_period) ?></span>
                            </div>
                            <div class="badge bg-primary bg-opacity-10 text-primary fw-bold mt-2 px-3 py-2">
                                <i class="fa-brands fa-google me-1"></i> Up to <?= $plan->gmail_limit ?> Gmail Accounts
                            </div>
                        </div>

                        <ul class="list-unstyled mb-5 d-flex flex-column gap-3 small text-secondary flex-grow-1">
                            <?php foreach ($plan->getFeaturesList() as $feat): ?>
                            <li class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check text-success fs-6"></i>
                                <span><?= e($feat) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-auto">
                            <?php if (\App\Core\Auth::check()): ?>
                                <a href="<?= url('/billing/checkout/' . $plan->id) ?>" class="btn <?= $plan->is_popular ? 'btn-gradient' : 'btn-outline-dark' ?> w-100 py-3 fw-bold">
                                    Choose <?= e($plan->name) ?>
                                </a>
                            <?php else: ?>
                                <a href="<?= url('/register') ?>" class="btn <?= $plan->is_popular ? 'btn-gradient' : 'btn-outline-dark' ?> w-100 py-3 fw-bold">
                                    Choose <?= e($plan->name) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 8. COMPARISON TABLE -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mt-5">
                <h4 class="fw-bold text-center mb-4">Plan Feature Comparison</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3">Feature</th>
                                <th class="text-center py-3">Starter</th>
                                <th class="text-center py-3">Professional</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">Connected Gmail Accounts</td>
                                <td class="text-center text-primary fw-bold">100 Accounts</td>
                                <td class="text-center text-primary fw-bold">250 Accounts</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Conversational Auto Reply</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Multi-Step Auto Follow-ups</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Custom Messages &amp; Formatting</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Smart Scheduling &amp; Working Hours</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Daily &amp; Per-Thread Limits</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Template Variables Support</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Automation &amp; Filter Rules</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Gmail Thread Preservation</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Real-Time Analytics Dashboard</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Server-Side Background Queue (24/7)</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">SMTP Email Notifications</td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                                <td class="text-center text-success"><i class="fa-solid fa-check fs-5"></i></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Support</td>
                                <td class="text-center">Standard Support</td>
                                <td class="text-center text-primary fw-semibold">Priority VIP Support</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. FAQ SECTION -->
    <section id="faq" class="py-5">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <span class="text-primary fw-bold text-uppercase small tracking-wide">Got Questions?</span>
                <h2 class="fw-bold text-dark mt-1">Frequently Asked Questions</h2>
                <p class="text-muted">Find answers to common questions regarding Gmail automation, limits, and security.</p>
            </div>

            <div class="accordion faq-accordion max-w-800 mx-auto" id="faqAccordion">
                <!-- FAQ 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            What is Gmail Automation?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Gmail Automation is a cloud SaaS platform that connects directly with your Gmail accounts via official Google OAuth 2.0 to send configured automatic replies, schedule smart follow-ups, and manage high-volume email workflows 24/7.
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            How do I connect my Gmail accounts?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Connecting is instantaneous and safe. You simply click "Connect Gmail", grant Google OAuth permissions, and the account is added. We never see or store your Google password.
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Can I customize my reply messages?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Yes! You have full control over the rich-text reply message, link insertions, and dynamic variables. The system strictly sends ONLY the message you configure.
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Can I customize follow-up sequences?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Yes. You can add multiple sequential follow-up steps, each with its own customized delay (e.g. 2 days, 4 days, 7 days) and distinct message content.
                        </div>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            What happens if I delete a scheduled message?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Our live queue protection checks the database right before sending. If you delete a step or message, the pending job is immediately cancelled, and NO email is sent.
                        </div>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            Can I use multiple Gmail accounts simultaneously?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            Yes. Depending on your active subscription plan, you can connect and automate up to 100 Gmail accounts on Starter and 250 Gmail accounts on Professional.
                        </div>
                    </div>
                </div>

                <!-- FAQ 7 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            Is there a free trial available?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            <?php if ($trialEnabled): ?>
                            Yes! You can start a <?= $trialDays ?>-day free trial immediately upon registration with up to <?= $trialGmailLimit ?> connected Gmail accounts without requiring a credit card.
                            <?php else: ?>
                            Please select one of our subscription plans to begin automating your Gmail accounts immediately.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- FAQ 8 -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            How are payments processed?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary">
                            All payments are securely handled through Stripe with end-to-end encryption. Your subscription activates automatically upon verified payment confirmation.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. FINAL CTA SECTION -->
    <section class="py-5">
        <div class="container">
            <div class="cta-banner text-center">
                <h2 class="display-6 fw-extrabold mb-3">Start Automating Your Gmail Today</h2>
                <p class="lead text-white-50 mb-4 max-w-600 mx-auto">
                    Connect your Gmail accounts, configure your messages, and let your automation run in the background 24/7.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="<?= url('/register') ?>" class="btn btn-primary btn-lg px-4 py-3 fw-bold shadow">
                        <?= $trialEnabled ? "Start Free {$trialDays}-Day Trial" : "Get Started Now" ?>
                    </a>
                    <a href="#pricing" class="btn btn-outline-light btn-lg px-4 py-3 fw-semibold">
                        View Plans &amp; Pricing
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 11. FOOTER -->
    <footer class="bg-dark text-light pt-5 pb-4 border-top border-secondary border-opacity-25">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-primary text-white rounded-3 p-2 shadow-sm">
                            <i class="fa-solid fa-bolt-lightning text-warning"></i>
                        </div>
                        <h5 class="fw-bold mb-0 text-white">Gmail Automation</h5>
                    </div>
                    <p class="text-muted small mb-4">
                        Professional SaaS solution for high-volume conversational auto-replies, smart follow-up sequences, and 24/7 background queue automation.
                    </p>
                    <div class="text-secondary small">
                        All rights &amp; design by <strong>Mizanur Rahman</strong> | <a href="tel:+8801611195794" class="text-decoration-none text-light fw-bold">+8801611195794</a>
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3">Product</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                        <li><a href="#features" class="text-decoration-none text-muted">Features</a></li>
                        <li><a href="#pricing" class="text-decoration-none text-muted">Pricing</a></li>
                        <li><a href="#how-it-works" class="text-decoration-none text-muted">How It Works</a></li>
                        <li><a href="#faq" class="text-decoration-none text-muted">FAQ</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3">Account</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                        <li><a href="<?= url('/login') ?>" class="text-decoration-none text-muted">Login</a></li>
                        <li><a href="<?= url('/register') ?>" class="text-decoration-none text-muted">Register</a></li>
                        <li><a href="<?= url('/dashboard') ?>" class="text-decoration-none text-muted">Dashboard</a></li>
                        <li><a href="<?= url('/billing') ?>" class="text-decoration-none text-muted">Billing</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3">Legal</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                        <li><a href="https://2xbets.net/privacy" class="text-decoration-none text-muted">Privacy Policy</a></li>
                        <li><a href="https://2xbets.net/terms" class="text-decoration-none text-muted">Terms of Service</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="fw-bold text-white mb-3">Support</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                        <li><a href="mailto:support@2xbets.net" class="text-decoration-none text-muted"><i class="fa-solid fa-envelope me-1"></i> Email Support</a></li>
                        <li><a href="tel:+8801611195794" class="text-decoration-none text-muted"><i class="fa-solid fa-phone me-1"></i> +8801611195794</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-top border-secondary border-opacity-25 pt-4 text-center text-muted small d-flex flex-wrap justify-content-center align-items-center gap-2">
                <span>&copy; <?= date('Y') ?> Gmail Automation Platform (2xbets.net). All rights reserved.</span>
                <span>&bull;</span>
                <a href="https://2xbets.net/privacy" class="text-decoration-none text-light fw-semibold">Privacy Policy</a>
                <span>&bull;</span>
                <a href="https://2xbets.net/terms" class="text-decoration-none text-light fw-semibold">Terms of Service</a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
