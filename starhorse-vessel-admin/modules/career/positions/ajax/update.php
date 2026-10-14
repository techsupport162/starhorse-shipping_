<?php
/**
 * ajax/update.php — POST → update an existing career position's name
 * and/or active flag. (For a quick active/inactive-only toggle from the
 * table, the UI calls ajax/toggle.php instead — this endpoint is for the
 * full Edit form.)
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid position ID.', 400);
}

$existing = get_position($id);
if (!$existing) {
    json_error('Position not found.', 404);
}

$name = clean_str($_POST['name'] ?? '');
$isActive = ($_POST['is_active'] ?? '1') !== '0';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if ($name === '') {
    $fieldErrors['name'] = 'Position Name is required.';
} elseif (mb_strlen($name) > 100) {
    $fieldErrors['name'] = 'Position Name is too long (100 characters max).';
}

if (empty($fieldErrors['name']) && career_position_name_exists($name, $id)) {
    $fieldErrors['name'] = 'Another position already uses this name.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_career_position($id, [
        'name'      => $name,
        'is_active' => $isActive,
    ]);

    json_response([
        'success' => true,
        'message' => 'Position updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[Career Positions update] ' . $e->getMessage());
    json_error('Something went wrong while updating the position. Please try again.', 500);
}