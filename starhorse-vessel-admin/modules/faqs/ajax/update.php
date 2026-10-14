<?php
/**
 * ajax/update.php — POST → update an existing FAQ's fields.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/faq_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid FAQ ID.', 400);
}

$existing = get_faq($id);
if (!$existing) {
    json_error('FAQ not found.', 404);
}

$question = clean_str($_POST['question'] ?? '');
$answer = clean_str($_POST['answer'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if ($question === '') {
    $fieldErrors['question'] = 'Question is required.';
} elseif (mb_strlen($question) > 255) {
    $fieldErrors['question'] = 'Question is too long (255 characters max).';
}

if ($answer === '') {
    $fieldErrors['answer'] = 'Answer is required.';
} elseif (mb_strlen($answer) > 5000) {
    $fieldErrors['answer'] = 'Answer is too long (5000 characters max).';
}

if (!in_array($_POST['status'] ?? '', ['published', 'draft'], true)) {
    $fieldErrors['status'] = 'Invalid status selected.';
}

if (empty($fieldErrors['question']) && faq_question_exists($question, $id)) {
    $fieldErrors['question'] = 'Another FAQ already uses this question.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_faq($id, [
        'question' => $question,
        'answer'   => $answer,
        'status'   => $status,
    ]);

    json_response([
        'success' => true,
        'message' => 'FAQ updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[FAQs update] ' . $e->getMessage());
    json_error('Something went wrong while updating the FAQ. Please try again.', 500);
}
