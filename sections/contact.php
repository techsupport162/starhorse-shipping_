<?php
// sections/contact.php - Contact section
// All content here is pulled from the database and managed via
// starhorse-vessel-admin/ (Contact Management module). No contact
// details should be hard-coded — same convention as sections/faqs.php
// and sections/vessels.php. Only published records are shown; drafts
// stay admin-only.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/contact_functions.php';

$officeSettings = get_published_contact_settings();
$officePhones   = $officeSettings ? get_published_contact_phones() : [];
$officeEmails   = $officeSettings ? get_published_contact_emails() : [];
$ports          = get_published_ports();

$hasOffice = !empty($officeSettings);

// Admin-entered URLs are re-checked at render time: the admin form's
// FILTER_VALIDATE_URL accepts "javascript:" URLs, and its Google Maps
// check is a substring match. The map host list must match frame-src in
// the page CSP (includes/security.php).
$mapEmbedUrl = $hasOffice
    ? sh_safe_url($officeSettings['map_embed_url'] ?? '', ['www.google.com', 'google.com', 'maps.google.com'], true)
    : '';
$facebookUrl = $hasOffice ? sh_safe_url($officeSettings['facebook_url'] ?? '') : '';

$hasMap    = $hasOffice && ($officeSettings['map_status'] ?? 'draft') === 'published' && $mapEmbedUrl !== '';

// Directions link is derived from the address so it always matches what's
// displayed, independent of whatever the admin has set as the map embed URL.
$directionsUrl = $hasOffice
    ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($officeSettings['address'])
    : '';
?>
<section class="page-section" id="contact">
    <div class="container px-4 px-lg-5">
        <div class="row gx-4 gx-lg-5 justify-content-center">
            <div class="col-lg-8 col-xl-6 text-center">
                <h2 class="mt-0">Get In Touch</h2>
                <hr class="divider" />
                <p class="text-muted mb-5">
                    Reach our Head Office directly, or find the contact number for the port nearest you.
                </p>
            </div>
        </div>

        <?php if (!$hasOffice): ?>
        <div class="sh-empty-state" data-animate-in>
            <i class="bi bi-headset" aria-hidden="true"></i>
            <p class="mb-0">Contact details are currently being updated. Please check back soon.</p>
        </div>
        <?php else: ?>
        <div class="sh-contact-grid mb-5">
            <!-- ============================== Head Office ============================== -->
            <div class="sh-contact-office" data-animate-in>
                <div class="sh-contact-card">
                    <div class="sh-contact-card__badge"><i class="bi bi-building" aria-hidden="true"></i></div>
                    <h3 class="sh-contact-card__title">
                        <?= htmlspecialchars($officeSettings['office_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="sh-contact-address">
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($officeSettings['address'], ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                    <a class="btn btn-sh-directions" href="<?= htmlspecialchars($directionsUrl, ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-signpost-2 me-2" aria-hidden="true"></i>Get Directions
                    </a>
                </div>
            </div>

            <!-- ============================== Map ============================== -->
            <div class="sh-contact-map" data-animate-in>
                <?php if ($hasMap): ?>
                <div class="sh-contact-map__frame">
                    <iframe src="<?= htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                        title="Map to <?= htmlspecialchars($officeSettings['office_name'], ENT_QUOTES, 'UTF-8') ?>"></iframe>
                </div>
                <?php else: ?>
                <div class="sh-contact-map__empty">
                    <i class="bi bi-map" aria-hidden="true"></i>
                    <span>Map preview unavailable</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============================== Contact Details ============================== -->
            <div class="sh-contact-details" data-animate-in>
                <div class="sh-contact-card">
                    <h3 class="sh-contact-card__subtitle">Contact Details</h3>

                    <?php if (!empty($officeSettings['tel_number'])): ?>
                    <a class="sh-contact-row"
                        href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $officeSettings['tel_number']), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="sh-contact-row__icon"><i class="bi bi-telephone-fill" aria-hidden="true"></i></span>
                        <span class="sh-contact-row__text">
                            <span class="sh-contact-row__label">Telephone</span>
                            <span><?= htmlspecialchars($officeSettings['tel_number'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </a>
                    <?php endif; ?>

                    <?php foreach ($officePhones as $i => $phone): ?>
                    <a class="sh-contact-row"
                        href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone['phone_number']), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="sh-contact-row__icon"><i class="bi bi-phone-fill" aria-hidden="true"></i></span>
                        <span class="sh-contact-row__text">
                            <?php if ($i === 0): ?><span class="sh-contact-row__label">Mobile</span><?php endif; ?>
                            <span><?= htmlspecialchars($phone['phone_number'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </a>
                    <?php endforeach; ?>

                    <?php foreach ($officeEmails as $i => $email): ?>
                    <a class="sh-contact-row"
                        href="mailto:<?= htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="sh-contact-row__icon"><i class="bi bi-envelope-fill" aria-hidden="true"></i></span>
                        <span class="sh-contact-row__text">
                            <?php if ($i === 0): ?><span class="sh-contact-row__label">Email</span><?php endif; ?>
                            <span
                                class="text-break"><?= str_replace('@', '<wbr>@', htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8')) ?></span>
                        </span>
                    </a>
                    <?php endforeach; ?>

                    <?php if ($facebookUrl !== ''): ?>
                    <a class="sh-contact-row"
                        href="<?= htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank" rel="noopener noreferrer">
                        <span class="sh-contact-row__icon"><i class="bi bi-facebook" aria-hidden="true"></i></span>
                        <span class="sh-contact-row__text">
                            <span class="sh-contact-row__label">Facebook</span>
                            <span><?= htmlspecialchars($officeSettings['facebook_name'] ?: $facebookUrl, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================== Ports ============================== -->
            <div class="sh-contact-ports" data-animate-in>
                <h3 class="sh-contact-ports__title h4"><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Our Ports</h3>

                <?php if (empty($ports)): ?>
                <div class="sh-empty-state sh-contact-empty--inline">
                    <i class="bi bi-anchor" aria-hidden="true"></i>
                    <p class="mb-0">Port contact information is currently being updated.</p>
                </div>
                <?php else: ?>
                <div class="sh-port-grid">
                    <?php foreach ($ports as $port): ?>
                    <a class="sh-port-card"
                        href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $port['phone_number']), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="sh-port-card__icon"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i></span>
                        <span class="sh-port-card__body">
                            <span
                                class="sh-port-card__name"><?= htmlspecialchars($port['port_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="sh-port-card__phone"><i class="bi bi-telephone" aria-hidden="true"></i>
                                <?= htmlspecialchars($port['phone_number'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
