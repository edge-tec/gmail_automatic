<section class="py-5">
    <div class="text-center max-w-700 mx-auto mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold text-uppercase tracking-wider mb-2">Get in Touch</span>
        <h1 class="fw-extrabold display-5 text-dark">Contact Our Support &amp; Sales Team</h1>
        <p class="text-muted fs-5">Have a question about high-volume agency limits, custom enterprise plans, or setup? We are here to help.</p>
    </div>

    <div class="row g-4 justify-content-center mb-5">
        <!-- Contact Information Column -->
        <div class="col-12 col-lg-5">
            <div class="card h-100 border-0 bg-dark text-white rounded-4 p-4 p-lg-5 shadow">
                <h2 class="h4 fw-bold mb-4">Direct Contact Channels</h2>
                
                <div class="d-flex flex-column gap-4 mb-5">
                    <div class="d-flex align-items-start gap-3">
                        <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <div class="text-white-50 small">Support Email</div>
                            <a href="mailto:<?= e($supportEmail) ?>" class="text-white fw-bold text-decoration-none"><?= e($supportEmail) ?></a>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="bg-success text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <div>
                            <div class="text-white-50 small">Telephone / WhatsApp</div>
                            <a href="tel:<?= e($phone) ?>" class="text-white fw-bold text-decoration-none"><?= e($phone) ?></a>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="bg-info text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <div class="text-white-50 small">Office Headquarters</div>
                            <div class="text-white fw-bold"><?= e($address) ?></div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-top border-secondary border-opacity-25 mt-auto">
                    <div class="small text-white-50">Operational Response Time:</div>
                    <div class="text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Average Response under 1 Hour</div>
                </div>
            </div>
        </div>

        <!-- Contact Form Column -->
        <div class="col-12 col-lg-7">
            <div class="card h-100 border-0 bg-white rounded-4 p-4 p-lg-5 shadow-sm border">
                <h2 class="h4 fw-bold text-dark mb-3">Send Us a Message</h2>
                <form action="<?= url('/contact') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control py-2" placeholder="John Doe" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control py-2" placeholder="name@company.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Subject / Inquiry Type <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control py-2" placeholder="Custom Agency Limit Request" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Your Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Tell us how we can help your team..." required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 py-3 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i> Send Inquiry
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
