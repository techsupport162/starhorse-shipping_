<?php
/**
 * ajax/banner_update.php — POST multipart/form-data → upload/replace the
 * single Routes Hero banner image. route_banner is a singleton row
 * (id = 1, see database/migration_003_routes.sql), so this always updates
 * that one row rather than inserting a new one.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/routes_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

// Checked before csrf_verify(): if the whole POST body (file + other
// fields, including the CSRF token itself) exceeded post_max_size, PHP
// discards it entirely and every field — including csrf_token — reads
// as empty. Without this check that would surface as a misleading
// "session expired" instead of the real cause.
if (request_body_exceeded_post_max_size()) {
    json_field_errors([
        'banner_image' => 'That file is larger than the server currently accepts (max ' . ini_get('post_max_size') . '). Please contact the site administrator if this keeps happening.',
    ]);
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

if (empty($_FILES['banner_image']) || ($_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_field_errors(['banner_image' => 'Please choose a banner image to upload.']);
}

// The banner gets its own, larger limit and its own format list (adds
// SVG, keeps GIF out — the banner has never supported animated GIFs and
// this isn't the place to add that) rather than using
// validate_image_upload()'s 5MB raster-only default, which is still
// used unchanged by every other module.
$bannerMaxBytes = 10 * 1024 * 1024; // 10MB
$bannerAllowedMimes = [
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/webp'    => 'webp',
    'image/svg+xml' => 'svg',
];

$check = validate_image_upload($_FILES['banner_image'], $bannerMaxBytes, $bannerAllowedMimes);
if (!$check['ok']) {
    json_field_errors(['banner_image' => $check['error']]);
}

try {
    $existing = get_route_banner_row();

    $filename = $check['ext'] === 'svg'
        ? store_uploaded_svg($check['svg_sanitized'], 'routes_banner')
        : store_uploaded_image($_FILES['banner_image'], $check['ext'], 'routes_banner');
    update_route_banner($filename);

    if (!empty($existing['banner_image'])) {
        delete_uploaded_image_file($existing['banner_image'], 'routes_banner');
    }

    json_response([
        'success' => true,
        'message' => 'Banner image updated successfully.',
        'image_url' => image_asset_url($filename, admin_base_url() . '/uploads/routes_banner/'),
    ]);
} catch (Throwable $e) {
    error_log('[Routes banner update] ' . $e->getMessage());
    json_error('Something went wrong while saving the banner image. Please try again.', 500);
}