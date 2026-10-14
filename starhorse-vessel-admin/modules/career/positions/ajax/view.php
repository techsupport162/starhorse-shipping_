<?php
/**
 * ajax/view.php — GET ?id=123 → full position record. Used to pre-fill
 * the Edit form.
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid position ID.', 400);
}

$position = get_position($id);
if (!$position) {
    json_error('Position not found.', 404);
}

json_response([
    'success' => true,
    'data' => [
        'id'        => (int) $position['id'],
        'name'      => $position['name'],
        'is_active' => (bool) $position['is_active'],
    ],
]);