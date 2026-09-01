<section class="py-5">
    <div class="text-center max-w-700 mx-auto mb-5">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fw-bold text-uppercase tracking-wider mb-2">Help Center</span>
        <h1 class="fw-extrabold display-5 text-dark">Frequently Asked Questions</h1>
        <p class="text-muted fs-5">Everything you need to know about our Gmail automation platform, security, pricing, and campaign workflows.</p>
    </div>

    <!-- FAQ Accordion -->
    <div class="row justify-content-center mb-5">
        <div class="col-12 col-lg-9">
            <div class="accordion accordion-flush bg-white rounded-4 shadow-sm p-4 border" id="faqPublicAccordion">
                <?php foreach ($faqs as $idx => $faq): ?>
                <div class="accordion-item border-bottom py-2">
                    <h2 class="accordion-header" id="heading-<?= $faq->id ?>">
                        <button class="accordion-button <?= $idx !== 0 ? 'collapsed' : '' ?> fw-bold text-dark fs-6 bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $faq->id ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= $faq->id ?>">
                            <span class="badge bg-primary bg-opacity-10 text-primary me-3"><?= e($faq->category) ?></span>
                            <?= e($faq->question) ?>
                        </button>
                    </h2>
                    <div id="collapse-<?= $faq->id ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $faq->id ?>" data-bs-parent="#faqPublicAccordion">
                        <div class="accordion-body text-secondary small leading-relaxed pt-2">
                            <?= nl2br(e($faq->answer)) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Need More Help Banner -->
    <div class="card border-0 bg-light rounded-4 p-5 text-center max-w-700 mx-auto">
        <h3 class="h5 fw-bold text-dark mb-2">Still Have Questions?</h3>
        <p class="text-muted small mb-4">Our dedicated support team is here to assist you with custom integrations, limits, and setup.</p>
        <div>
            <a href="<?= url('/contact') ?>" class="btn btn-outline-primary fw-bold px-4 py-2 me-2">
                <i class="fa-solid fa-envelope me-1"></i> Contact Support
            </a>
            <a href="<?= url('/register') ?>" class="btn btn-primary fw-bold px-4 py-2">
                <i class="fa-solid fa-sparkles me-1"></i> Start Free Trial
            </a>
        </div>
    </div>
</section>
