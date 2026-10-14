<?php
// sections/vessels.php - Vessels section
// Vessel data (including images and features) is pulled from the database
// and managed via starhorse-vessel-admin/ (Vessel Management module). No
// vessel info should be hard-coded here.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/vessel_functions.php';

$vessels = get_published_vessels();
$vesselImageBase = 'starhorse-vessel-admin/uploads/vessels/';
$vesselCount = count($vessels);

/** Small helper: build [icon, label, value] rows for whichever specs this vessel actually has. */
function sh_vessel_spec_rows(array $vessel): array
{
    $rows = [];
    if ($vessel['length_m'] !== null) {
        $rows[] = ['bi-rulers', 'Length', number_format((float) $vessel['length_m'], 2) . ' m'];
    }
    if ($vessel['breadth_m'] !== null) {
        $rows[] = ['bi-arrows-expand', 'Breadth', number_format((float) $vessel['breadth_m'], 2) . ' m'];
    }
    if ($vessel['depth_m'] !== null) {
        $rows[] = ['bi-arrow-bar-down', 'Depth', number_format((float) $vessel['depth_m'], 2) . ' m'];
    }
    if ($vessel['gross_tonnage'] !== null) {
        $rows[] = ['bi-speedometer2', 'Gross Tonnage', number_format((float) $vessel['gross_tonnage'], 2)];
    }
    return $rows;
}
?>
<section class="page-section" id="vessels">
    <h2 class="text-center mt-0 mb-4">Our Vessels</h2>
    <hr class="divider" />

    <div class="container-fluid px-4 px-lg-5">
        <?php if (empty($vessels)): ?>
        <p class="text-center text-muted">Vessel information is currently being updated. Please check back soon.</p>
        <?php else: ?>

        <div class="sh-vc" id="vesselCarousel" data-vessel-count="<?= $vesselCount ?>" role="region"
            aria-roledescription="carousel" aria-label="Our Vessels" tabindex="0">

            <div class="sh-vc-viewport">
                <div class="sh-vc-track" id="vesselTrack">

                    <?php foreach ($vessels as $index => $vessel):
                        $images = $vessel['images'] ?? [];
                        $features = $vessel['features'] ?? [];
                        $vesselId = (int) $vessel['id'];
                        $isFirst = $index === 0;
                        $specs = sh_vessel_spec_rows($vessel);
                        $vesselName = htmlspecialchars($vessel['name'], ENT_QUOTES, 'UTF-8');
                        $vesselTagline = htmlspecialchars($vessel['tagline'] ?: 'Our Fleet', ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="sh-vc-slide" data-vessel-index="<?= $index ?>" data-vessel-id="<?= $vesselId ?>"
                        role="group" aria-roledescription="slide"
                        aria-label="Vessel <?= $index + 1 ?> of <?= $vesselCount ?>: <?= $vesselName ?>"
                        <?= $isFirst ? '' : 'aria-hidden="true"' ?>>
                        <div class="sh-vc-slide__grid">

                            <div class="sh-vc-info">
                                <p class="sh-vc-info__eyebrow"><i class="bi bi-water" aria-hidden="true"></i><?= $vesselTagline ?></p>
                                <h3 class="sh-vc-info__name"><?= $vesselName ?></h3>

                                <?php if (!empty($specs)): ?>
                                <ul class="sh-vc-specs">
                                    <?php foreach ($specs as [$icon, $label, $value]): ?>
                                    <li class="sh-vc-specs__item">
                                        <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                                        <span
                                            class="sh-vc-specs__value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="sh-vc-specs__label"><?= $label ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>

                                <?php if (!empty($vessel['description'])): ?>
                                <p class="sh-vc-info__desc">
                                    <?= nl2br(htmlspecialchars($vessel['description'], ENT_QUOTES, 'UTF-8')) ?>
                                </p>
                                <?php endif; ?>

                                <?php if (!empty($features)): ?>
                                <div class="sh-vc-features">
                                    <h4 class="sh-vc-features__title">Vessel Features</h4>
                                    <ul class="sh-vc-features__list">
                                        <?php foreach ($features as $feature): ?>
                                        <li class="sh-vc-feature">
                                            <span class="sh-vc-feature__icon">
                                                <i class="bi <?= htmlspecialchars($feature['icon'], ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-hidden="true"></i>
                                            </span>
                                            <span
                                                class="sh-vc-feature__label"><?= htmlspecialchars($feature['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="sh-vc-gallery">
                                <div class="sh-vc-gallery__main" <?php if (count($images) > 1): ?> tabindex="0"
                                    role="group"
                                    aria-label="<?= $vesselName ?> photo, <?= count($images) ?> total. Use left and right arrow keys or swipe to browse."
                                    <?php endif; ?>>
                                    <?php if (!empty($images)): ?>
                                    <img src="<?= htmlspecialchars($vesselImageBase . $images[0], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= $vesselName ?>" class="sh-vc-gallery__main-img" data-main-image
                                        loading="<?= $isFirst ? 'eager' : 'lazy' ?>" decoding="async"
                                        <?= $isFirst ? 'fetchpriority="high"' : 'fetchpriority="low"' ?>>
                                    <?php else: ?>
                                    <div class="sh-vc-gallery__empty">
                                        <i class="bi bi-image" aria-hidden="true"></i>
                                        <span>No photos yet</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (count($images) > 1): ?>
                                <div class="sh-vc-thumbs-wrap" data-thumbs-wrap>
                                    <div class="sh-vc-thumbs" role="tablist" aria-label="<?= $vesselName ?> photos"
                                        data-thumbs-track>
                                        <?php foreach ($images as $imgIndex => $img):
                                            $imgUrl = htmlspecialchars($vesselImageBase . $img, ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <button type="button"
                                            class="sh-vc-thumb<?= $imgIndex === 0 ? ' is-active' : '' ?>"
                                            data-img-src="<?= $imgUrl ?>" data-img-index="<?= $imgIndex ?>" role="tab"
                                            aria-selected="<?= $imgIndex === 0 ? 'true' : 'false' ?>"
                                            aria-label="Photo <?= $imgIndex + 1 ?> of <?= count($images) ?>">
                                            <img src="<?= $imgUrl ?>" alt="" loading="lazy" decoding="async">
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </article>
                    <?php endforeach; ?>

                </div>
            </div>

            <?php if ($vesselCount > 1): ?>
            <div class="sh-vc-nav">
                <button type="button" class="sh-vc-arrow sh-vc-arrow--prev" id="vesselPrevBtn"
                    aria-label="Previous vessel">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <div class="sh-vc-dots" id="vesselDots" role="tablist" aria-label="Select vessel">
                    <?php for ($i = 0; $i < $vesselCount; $i++): ?>
                    <button type="button" class="sh-vc-dot<?= $i === 0 ? ' is-active' : '' ?>" data-goto="<?= $i ?>"
                        role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-label="Go to vessel <?= $i + 1 ?>"></button>
                    <?php endfor; ?>
                </div>
                <button type="button" class="sh-vc-arrow sh-vc-arrow--next" id="vesselNextBtn" aria-label="Next vessel">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
            <p class="sh-vc-swipe-hint" aria-hidden="true">
                <i class="bi bi-arrow-left-right"></i> Swipe to browse vessels
            </p>
            <?php endif; ?>

            <div class="visually-hidden" aria-live="polite" id="vesselLiveRegion"></div>
        </div>

        <?php endif; ?>
    </div>

</section>
