<?php
/**
 * ajax/view.php — GET ?id=123 → full FAQ record. Used to populate both
 * the read-only View modal and to pre-fill the Edit form.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/faq_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid FAQ ID.', 400);
}

$faq = get_faq($id);
if (!$faq) {
    json_error('FAQ not found.', 404);
}

json_response(['success' => true, 'data' => $faq]);
