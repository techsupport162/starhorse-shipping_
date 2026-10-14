<?php
/**
 * ajax/career_apply.php
 * -----------------------------------------------------------------------
 * Public-facing endpoint that receives a submission from the Career
 * section's Apply form (built in Phase 6) and stores it as a
 * career_applicants row. Returns JSON, same shape/conventions as the
 * admin AJAX endpoints (json_response / json_field_errors), so the
 * public form can reuse the same ShValidator + inline-error UI pattern
 * instead of a separate system.
 *
 * Only accepts applications against a currently PUBLISHED posting — a
 * Draft or missing posting is rejected outright, same as the public
 * listing/detail endpoints never expose Draft postings.
 * -----------------------------------------------------------------------
 */

// Same hardened session the Career page issued the CSRF token in.
require_once __DIR__ . '/../includes/security.php';
sh_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/helpers.php';
require_once __DIR__ . '/../starhorse-vessel-admin/functions/career_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (request_body_exceeded_post_max_size()) {
    json_error('That submission was too large (likely the resume file). Please try a smaller file.', 413);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

// Per-session throttle against repeated/scripted submissions (each one
// can carry a 5MB file). This only slows a single browser session down —
// real abuse protection needs IP-level rate limiting at the server/CDN.
$careerApplyWindow = 3600;
$careerApplyMax = 10;
$recentApplications = array_values(array_filter(
    (array) ($_SESSION['career_apply_times'] ?? []),
    fn($t) => is_int($t) && $t > time() - $careerApplyWindow
));
if (count($recentApplications) >= $careerApplyMax) {
    json_error('You have submitted several applications recently. Please try again later.', 429);
}

$postingId = filter_var($_POST['posting_id'] ?? '', FILTER_VALIDATE_INT);
$fullName = clean_str($_POST['full_name'] ?? '');
$email = clean_str($_POST['email'] ?? '');
$contactNumber = clean_str($_POST['contact_number'] ?? '');
$address = clean_str($_POST['address'] ?? '');
$message = clean_str($_POST['message'] ?? '');

// ---- Confirm the posting is real and currently published first — a
// missing/invalid/draft posting is a general error, not a field error,
// since posting_id is a hidden field the visitor never edits directly.
if (!$postingId) {
    json_error('No job posting was selected. Please go back and choose a position to apply for.', 400);
}
$posting = get_published_career_posting($postingId);
if (!$posting) {
    json_error('This job posting is no longer available for applications. Please check the Career page for current openings.', 404);
}

// ---- Field-level validation --------------------------------------------
$fieldErrors = [];

if ($fullName === '') {
    $fieldErrors['full_name'] = 'Full Name is required.';
} elseif (mb_strlen($fullName) > 150) {
    $fieldErrors['full_name'] = 'Full Name is too long (150 characters max).';
}

if ($email === '') {
    $fieldErrors['email'] = 'Email is required.';
} elseif (!is_valid_email($email)) {
    $fieldErrors['email'] = 'Please enter a valid email address.';
} elseif (mb_strlen($email) > 150) {
    $fieldErrors['email'] = 'Email is too long (150 characters max).';
}

if ($contactNumber === '') {
    $fieldErrors['contact_number'] = 'Contact Number is required.';
} elseif (mb_strlen($contactNumber) > 30) {
    $fieldErrors['contact_number'] = 'Contact Number is too long (30 characters max).';
}

if (mb_strlen($address) > 255) {
    $fieldErrors['address'] = 'Address is too long (255 characters max).';
}

if (mb_strlen($message) > 5000) {
    $fieldErrors['message'] = 'Message is too long (5000 characters max).';
}

// ---- Resume/CV (optional) ------------------------------------------
$resumeMaxBytes = 5 * 1024 * 1024;
$resumeCheck = null;

if (!empty($_FILES['resume']) && ($_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $check = validate_resume_upload($_FILES['resume'], $resumeMaxBytes);
    if (!$check['ok']) {
        $fieldErrors['resume'] = $check['error'];
    } else {
        $resumeCheck = $check;
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $resumeFilename = null;
    if ($resumeCheck !== null) {
        $resumeFilename = store_uploaded_document($_FILES['resume'], $resumeCheck['ext'], 'career_resumes');
    }

    create_career_applicant([
        'posting_id'             => $posting['id'],
        // Snapshotted from the posting at the moment of application —
        // stays accurate even if the posting is edited or later deleted.
        'applied_position_title' => $posting['position_name'] . ' — ' . $posting['title'],
        'full_name'               => $fullName,
        'email'                   => $email,
        'contact_number'          => $contactNumber,
        'address'                 => $address !== '' ? $address : null,
        'message'                 => $message !== '' ? $message : null,
        'resume_file'             => $resumeFilename,
    ]);

    $recentApplications[] = time();
    $_SESSION['career_apply_times'] = $recentApplications;

    json_response([
        'success' => true,
        'message' => 'Your application has been submitted successfully. Thank you for your interest!',
    ]);
} catch (Throwable $e) {
    error_log('[Career Apply] ' . $e->getMessage());
    json_error('Something went wrong while submitting your application. Please try again.', 500);
}