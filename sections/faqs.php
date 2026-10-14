<?php
// sections/faqs.php - FAQS section
// FAQ data is pulled from the database and managed via
// starhorse-vessel-admin/ (FAQ Management module). No FAQ content
// should be hard-coded here — same convention as sections/vessels.php
// and sections/rates.php. Only status = 'published' rows are returned
// by get_published_faqs(); drafts stay admin-only.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/faq_functions.php';

$faqs = get_published_faqs();
?>
<section class="page-section" id="faqs">
    <div class="container px-4 px-lg-5">
        <h2 class="text-center mt-0">Frequently Asked Questions</h2>
        <hr class="divider" />

        <?php if (empty($faqs)): ?>
        <p class="text-center text-muted">FAQ content is currently being updated. Please check back soon.</p>
        <?php else: ?>
        <div class="row gx-4 gx-lg-5 justify-content-center">
            <div class="col-lg-8">
                <div class="accordion sh-faq-accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq):
                        $itemId = 'faq' . (int) $faq['id'];
                        $isFirst = $index === 0;
                    ?>
                    <div class="accordion-item sh-faq-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button<?= $isFirst ? '' : ' collapsed' ?>" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= htmlspecialchars($itemId, ENT_QUOTES, 'UTF-8') ?>"
                                aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
                                aria-controls="<?= htmlspecialchars($itemId, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </h3>
                        <div id="<?= htmlspecialchars($itemId, ENT_QUOTES, 'UTF-8') ?>"
                            class="accordion-collapse collapse<?= $isFirst ? ' show' : '' ?>"
                            data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?= nl2br(htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
