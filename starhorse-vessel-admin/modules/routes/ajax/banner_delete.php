<?php
/**
 * ajax/banner_delete.php — POST → remove the current Routes Hero banner
 * image (file + column). The route_banner row itself is never deleted;
 * it's a singleton that simply goes back to banner_image = NULL.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/routes_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

try {
    $existing = get_route_banner_row();

    if (empty($existing['banner_image'])) {
        json_error('There is no banner image to remove.', 400);
    }

    clear_route_banner();
    delete_uploaded_image_file($existing['banner_image'], 'routes_banner');

    json_response(['success' => true, 'message' => 'Banner image removed.']);
} catch (Throwable $e) {
    error_log('[Routes banner delete] ' . $e->getMessage());
    json_error('Something went wrong while removing the banner image. Please try again.', 500);
}