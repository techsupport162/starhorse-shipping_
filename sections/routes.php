<?php
// sections/routes.php - Routes section
// The hero banner and the port logos (tourism logo + port name, stored as
// route destinations) are pulled from the database and managed via
// starhorse-vessel-admin/ (Routes Gallery Management).
// No port or banner should be hard-coded here.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/routes_functions.php';

if (!function_exists('sh_route_banner_natural_dimensions')) {
    /**
     * Read a banner image's real natural dimensions so the <img> can
     * carry accurate width/height attributes (used only for the browser's
     * pre-load aspect-ratio placeholder, to avoid layout shift — the
     * actual displayed size is controlled entirely by CSS: width:100%;
     * height:auto). Whatever the admin uploads (any raster size, or an
     * SVG with an arbitrary viewBox) is measured here rather than
     * assuming a fixed ratio, so this stays correct as the banner is
     * replaced over time.
     *
     * @return array{0:int,1:int}|null [width, height], or null if it
     *   can't be determined (e.g. a malformed file) — the banner still
     *   displays fine without these attributes, just without the CLS
     *   placeholder.
     */
    function sh_route_banner_natural_dimensions(string $absolutePath): ?array
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'svg') {
            $contents = @file_get_contents($absolutePath);
            if ($contents === false) {
                return null;
            }
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($contents);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($xml === false) {
                return null;
            }

            $attrs  = $xml->attributes();
            $width  = isset($attrs['width']) ? (float) $attrs['width'] : 0.0;
            $height = isset($attrs['height']) ? (float) $attrs['height'] : 0.0;

            // Width/height attributes on <svg> are optional and are
            // sometimes given in non-pixel units (e.g. "100%"); fall back
            // to the viewBox, which always carries a real aspect ratio.
            if (($width <= 0 || $height <= 0) && isset($attrs['viewBox'])) {
                $box = preg_split('/[\s,]+/', trim((string) $attrs['viewBox']));
                if (count($box) === 4) {
                    $width  = (float) $box[2];
                    $height = (float) $box[3];
                }
            }

            return ($width > 0 && $height > 0) ? [(int) round($width), (int) round($height)] : null;
        }

        $size = @getimagesize($absolutePath);
        return $size ? [(int) $size[0], (int) $size[1]] : null;
    }
}

$destinations = get_published_destinations();
$destinationImageBase = 'starhorse-vessel-admin/uploads/routes/';
$destinationCount = count($destinations);

$bannerFilename = get_route_banner();
$bannerUrl = $bannerFilename ? 'starhorse-vessel-admin/uploads/routes_banner/' . $bannerFilename : null;
$bannerDimensions = $bannerFilename
    ? sh_route_banner_natural_dimensions(__DIR__ . '/../starhorse-vessel-admin/uploads/routes_banner/' . $bannerFilename)
    : null;

// Port logo marquee: each published destination is one port (tourism logo +
// port name). A "group" repeats the ports until it holds at least
// $marqueeMinItems logos, so a short list still spans wide screens; the group
// is then rendered twice and the track slides by exactly one group (-50%),
// which makes the loop seamless.
$marqueeMinItems = 12;
$marqueeRepeats = $destinationCount > 0 ? (int) ceil($marqueeMinItems / $destinationCount) : 0;
?>

<section class="page-section" id="routes">
    <div class="container px-4 px-lg-5">
        <h2 class="text-center mt-0 mb-4">Our Shipping Routes</h2>
        <hr class="divider" />

        <?php if ($bannerUrl): ?>
        <div class="routes-banner mb-5">
            <img src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Starhorse Shipping routes"
                class="routes-banner-img" <?php if ($bannerDimensions): ?> width="<?= (int) $bannerDimensions[0] ?>"
                height="<?= (int) $bannerDimensions[1] ?>" <?php endif; ?> loading="lazy" decoding="async">
            <div class="routes-banner-overlay">
                <span class="routes-banner-eyebrow">Maritime Routes</span>
                <?php if ($destinationCount > 0): ?>
                <p class="routes-banner-caption">
                    Serving <?= (int) $destinationCount ?> destination<?= $destinationCount === 1 ? '' : 's' ?>
                    across the Philippines
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="port-marquee__header">
            <span class="port-marquee__eyebrow">Ports We Serve</span>
            <h3 class="port-marquee__title">Explore the islands we connect</h3>
        </div>
    </div>

    <?php if (empty($destinations)): ?>
    <div class="container px-4 px-lg-5">
        <p class="text-center text-muted">Our port list is currently being updated. Please check back soon.</p>
    </div>
    <?php else: ?>
    <div class="port-marquee" id="portMarquee">
        <!-- Screen readers get one plain list; the animated strip below is
             decorative (it repeats every port several times). -->
        <ul class="visually-hidden">
            <?php foreach ($destinations as $destination): ?>
            <li><?= htmlspecialchars($destination['location_name'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="port-marquee__viewport" aria-hidden="true">
            <div class="port-marquee__track">
                <?php for ($copy = 0; $copy < 2; $copy++): ?>
                <ul class="port-marquee__group">
                    <?php for ($repeat = 0; $repeat < $marqueeRepeats; $repeat++): ?>
                    <?php foreach ($destinations as $destination):
                        $logoUrl = $destinationImageBase . $destination['destination_image'];
                    ?>
                    <li class="port-logo<?= $repeat > 0 ? ' is-repeat' : '' ?>">
                        <span class="port-logo__mark">
                            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" decoding="async">
                        </span>
                        <span class="port-logo__name"><?= htmlspecialchars($destination['location_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php endfor; ?>
                </ul>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="container px-4 px-lg-5 text-center">
        <a href="#rates" class="port-marquee__cta">
            Check Fares &amp; Schedules
            <svg viewBox="0 0 24 24" class="port-marquee__cta-icon" aria-hidden="true">
                <path d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z" />
            </svg>
        </a>
    </div>
    <?php endif; ?>
</section>
