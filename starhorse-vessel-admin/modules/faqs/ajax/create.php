<?php
/**
 * ajax/create.php — POST → insert a new FAQ. Called from the Add FAQ
 * modal. No file uploads involved, but sent the same way (POST + CSRF
 * field) as the rest of the admin CRUD modules for consistency.
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

$question = clean_str($_POST['question'] ?? '');
$answer = clean_str($_POST['answer'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation ------------------------------------------
// Collected into one object and returned together via json_field_errors()
// so the modal can show every invalid field inline at once, instead of
// stopping at the first problem.
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
    // Silently defaulted to 'published' above, but a status was submitted
    // and it wasn't one of the two valid options — surface that instead
    // of pretending it was fine.
    $fieldErrors['status'] = 'Invalid status selected.';
}

if (empty($fieldErrors['question']) && faq_question_exists($question)) {
    $fieldErrors['question'] = 'This question already exists. Please edit the existing FAQ instead.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $faqId = create_faq([
        'question' => $question,
        'answer'   => $answer,
        'status'   => $status,
    ]);

    json_response([
        'success' => true,
        'message' => 'FAQ added successfully.',
        'id' => $faqId,
    ]);
} catch (Throwable $e) {
    error_log('[FAQs create] ' . $e->getMessage());
    json_error('Something went wrong while saving the FAQ. Please try again.', 500);
}
