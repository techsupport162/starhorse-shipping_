<?php
/**
 * ajax/update.php — POST multipart/form-data → update a destination's
 * fields, and independently keep / replace / remove its image. Sent as
 * POST rather than PUT for the same reason as Vessels/Fares: multipart
 * file uploads via PUT aren't reliably supported by PHP's request parsing.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/routes_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

// See functions/helpers.php: request_body_exceeded_post_max_size() — an
// oversized image here empties $_POST (including csrf_token) too, which
// would otherwise show as a misleading "session expired".
if (request_body_exceeded_post_max_size()) {
    json_field_errors([
        'image' => 'That file is larger than the server currently accepts (max ' . ini_get('post_max_size') . '). Please contact the site administrator if this keeps happening.',
    ]);
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid destination ID.', 400);
}

$existing = get_destination($id);
if (!$existing) {
    json_error('Destination not found.', 404);
}

$locationName = clean_str($_POST['location_name'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if ($locationName === '') {
    $fieldErrors['location_name'] = 'Port name is required.';
} elseif (mb_strlen($locationName) > 150) {
    $fieldErrors['location_name'] = 'Port name is too long (150 characters max).';
}

if (empty($fieldErrors['location_name']) && destination_location_exists($locationName, $id)) {
    $fieldErrors['location_name'] = 'Another port already uses this name.';
}

// ---- Image validation for a newly-uploaded replacement file -----------
$imageCheck = null;
$removeImage = !empty($_POST['remove_image']) && $_POST['remove_image'] === '1';

if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    // Destination images keep their existing 5MB limit — only the
    // format list changes here, to add SVG support alongside the
    // existing JPG/PNG/WEBP/GIF.
    $destinationAllowedMimes = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
    ];
    $check = validate_image_upload($_FILES['image'], 5 * 1024 * 1024, $destinationAllowedMimes);
    if (!$check['ok']) {
        $fieldErrors['image'] = $check['error'];
    } else {
        $imageCheck = $check;
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_destination($id, [
        'location_name' => $locationName,
        'status'        => $status,
    ]);

    // The image is independently: replaced (new file uploaded), removed
    // (explicit "remove" flag sent, no new file), or left untouched.
    if ($imageCheck !== null) {
        $newFilename = $imageCheck['ext'] === 'svg'
            ? store_uploaded_svg($imageCheck['svg_sanitized'], 'routes')
            : store_uploaded_image($_FILES['image'], $imageCheck['ext'], 'routes');
        if (!empty($existing['destination_image'])) {
            delete_uploaded_image_file($existing['destination_image'], 'routes');
        }
        update_destination_image($id, $newFilename);
    } elseif ($removeImage) {
        if (!empty($existing['destination_image'])) {
            delete_uploaded_image_file($existing['destination_image'], 'routes');
        }
        update_destination_image($id, null);
    }

    json_response([
        'success' => true,
        'message' => 'Port updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[Routes update] ' . $e->getMessage());
    json_error('Something went wrong while updating the port. Please try again.', 500);
}