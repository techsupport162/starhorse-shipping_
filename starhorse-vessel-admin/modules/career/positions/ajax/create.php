<?php
/**
 * ajax/create.php — POST → insert a new career position. Called from the
 * Add Position modal.
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

$name = clean_str($_POST['name'] ?? '');
$isActive = ($_POST['is_active'] ?? '1') !== '0';

// ---- Field-level validation ------------------------------------------
$fieldErrors = [];

if ($name === '') {
    $fieldErrors['name'] = 'Position Name is required.';
} elseif (mb_strlen($name) > 100) {
    $fieldErrors['name'] = 'Position Name is too long (100 characters max).';
}

if (empty($fieldErrors['name']) && career_position_name_exists($name)) {
    $fieldErrors['name'] = 'This position already exists. Please edit the existing one instead.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $positionId = create_career_position([
        'name'      => $name,
        'is_active' => $isActive,
    ]);

    json_response([
        'success' => true,
        'message' => 'Position added successfully.',
        'id' => $positionId,
    ]);
} catch (Throwable $e) {
    error_log('[Career Positions create] ' . $e->getMessage());
    json_error('Something went wrong while saving the position. Please try again.', 500);
}