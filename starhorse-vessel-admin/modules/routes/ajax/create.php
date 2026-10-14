<?php
/**
 * ajax/create.php — POST multipart/form-data → insert a new destination,
 * plus its optional image. Called from the Add Destination modal.
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

$locationName = clean_str($_POST['location_name'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation ------------------------------------------
// Collected into one object and returned together via json_field_errors()
// so the modal can show every invalid field inline at once, instead of
// stopping at the first problem.
$fieldErrors = [];

if ($locationName === '') {
    $fieldErrors['location_name'] = 'Port name is required.';
} elseif (mb_strlen($locationName) > 150) {
    $fieldErrors['location_name'] = 'Port name is too long (150 characters max).';
}

if (empty($fieldErrors['location_name']) && destination_location_exists($locationName)) {
    $fieldErrors['location_name'] = 'A port with this name already exists.';
}

// ---- Image validation (optional on create — a destination can be added
// first and have its photo uploaded later) -----------------------------
$imageCheck = null;
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
    $destinationId = create_destination([
        'location_name' => $locationName,
        'status'        => $status,
    ]);

    if ($imageCheck !== null) {
        $filename = $imageCheck['ext'] === 'svg'
            ? store_uploaded_svg($imageCheck['svg_sanitized'], 'routes')
            : store_uploaded_image($_FILES['image'], $imageCheck['ext'], 'routes');
        update_destination_image($destinationId, $filename);
    }

    json_response([
        'success' => true,
        'message' => 'Port added successfully.',
        'id' => $destinationId,
    ]);
} catch (Throwable $e) {
    error_log('[Routes create] ' . $e->getMessage());
    json_error('Something went wrong while saving the port. Please try again.', 500);
}