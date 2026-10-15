<?php
// sections/masthead.php - Hero section

require_once __DIR__ . '/../config/database.php';

// Cloudinary background video configuration (read from .env / process environment)
$masthead_video_url = 'assets/vid/0508.mp4';
$cld_cloud_name = getenv('CLOUDINARY_CLOUD_NAME') ?: '';
$cld_url = getenv('CLOUDINARY_URL') ?: '';
if (!$cld_cloud_name && $cld_url) {
    $cld_cloud_name = parse_url($cld_url, PHP_URL_HOST) ?: '';
}
$cld_cloud_name = $cld_cloud_name ?: 'trda3kbd';
$cld_public_id  = getenv('CLOUDINARY_VIDEO_PUBLIC_ID') ?: '0508';

if (!empty(getenv('CLOUDINARY_VIDEO_URL'))) {
    $masthead_video_url = getenv('CLOUDINARY_VIDEO_URL');
} elseif ($cld_cloud_name) {
    $masthead_video_url = 'https://res.cloudinary.com/' . rawurlencode($cld_cloud_name) . '/video/upload/' . rawurlencode($cld_public_id) . '.mp4';
}
?>
<header class="masthead">
    <!-- Decorative background video -->
    <video class="video-background" autoplay loop muted playsinline aria-hidden="true">
        <source src="<?= htmlspecialchars($masthead_video_url, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
    </video>

    <div class="container px-4 px-lg-5 h-100">
        <div class="row gx-4 gx-lg-5 h-100 align-items-center justify-content-center text-center">
            <div class="col-lg-8 align-self-end">
                <h1 class="text-white">
                    Setting the Standards of Excellence and Good Service.
                </h1>
                <hr class="divider" />
                <a class="masthead-book-link d-xl-none" href="https://ferryeasy.ph/" target="_blank"
                    rel="noopener noreferrer">
                    <i class="bi bi-calendar-check-fill" aria-hidden="true"></i>
                    Book Now
                    <span class="visually-hidden">(opens in a new tab)</span>
                </a>
            </div>
        </div>
    </div>
</header>
