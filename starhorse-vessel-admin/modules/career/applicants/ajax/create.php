<?php
/**
 * ajax/create.php — POST multipart/form-data → insert a new career
 * posting, plus its optional recruitment image. Called from the Add Job
 * Posting modal. Mirrors modules/fares/ajax/create.php's shape.
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

$positionId = filter_var($_POST['position_id'] ?? '', FILTER_VALIDATE_INT);
$title = clean_str($_POST['title'] ?? '');
$shortDescription = clean_str($_POST['short_description'] ?? '');
$jobDescription = clean_str($_POST['job_description'] ?? '');
$qualifications = clean_str($_POST['qualifications'] ?? '');
$responsibilities = clean_str($_POST['responsibilities'] ?? '');
$applicationInstructions = clean_str($_POST['application_instructions'] ?? '');
$contactEmail = clean_str($_POST['contact_email'] ?? '');
$status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

// ---- Field-level validation ------------------------------------------
$fieldErrors = [];

if (!$positionId) {
    $fieldErrors['position_id'] = 'Position is required.';
} else {
    $positionError = validate_position_for_posting($positionId, true);
    if ($positionError !== null) {
        $fieldErrors['position_id'] = $positionError;
    }
}

if ($title === '') {
    $fieldErrors['title'] = 'Job Title is required.';
} elseif (mb_strlen($title) > 150) {
    $fieldErrors['title'] = 'Job Title is too long (150 characters max).';
}

if (mb_strlen($shortDescription) > 255) {
    $fieldErrors['short_description'] = 'Short Description is too long (255 characters max).';
}

if ($contactEmail !== '' && !is_valid_email($contactEmail)) {
    $fieldErrors['contact_email'] = 'Please enter a valid email address.';
}

// ---- Recruitment image (optional on create — a posting can be added
// first and its image uploaded/replaced later, same as Fares) ---------
$careerImageMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$imageMaxBytes = 6 * 1024 * 1024;
$imageCheck = null;
$dimensionWarning = null;

if (!empty($_FILES['recruitment_image']) && ($_FILES['recruitment_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $check = validate_image_upload($_FILES['recruitment_image'], $imageMaxBytes, $careerImageMimes);
    if (!$check['ok']) {
        $fieldErrors['recruitment_image'] = $check['error'];
    } else {
        $dimensionCheck = evaluate_career_image_dimensions($_FILES['recruitment_image']['tmp_name']);
        if (!$dimensionCheck['ok']) {
            // Only genuinely unusable (too-small) images are blocked here.
            $fieldErrors['recruitment_image'] = $dimensionCheck['error'];
        } else {
            $imageCheck = $check;
            $dimensionWarning = $dimensionCheck['warning'] ?? null;
        }
    }
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $postingId = create_career_posting([
        'position_id'               => $positionId,
        'title'                     => $title,
        'short_description'         => $shortDescription !== '' ? $shortDescription : null,
        'job_description'           => $jobDescription !== '' ? $jobDescription : null,
        'qualifications'            => $qualifications !== '' ? $qualifications : null,
        'responsibilities'          => $responsibilities !== '' ? $responsibilities : null,
        'application_instructions' => $applicationInstructions !== '' ? $applicationInstructions : null,
        'contact_email'             => $contactEmail !== '' ? $contactEmail : null,
        'status'                    => $status,
    ]);

    if ($imageCheck !== null) {
        $filename = store_uploaded_image($_FILES['recruitment_image'], $imageCheck['ext'], 'career');
        update_career_posting_image($postingId, $filename);
    }

    $response = [
        'success' => true,
        'message' => 'Job posting added successfully.',
        'id' => $postingId,
    ];
    if ($dimensionWarning !== null) {
        $response['image_dimension_warning'] = $dimensionWarning;
    }

    json_response($response);
} catch (Throwable $e) {
    error_log('[Career Postings create] ' . $e->getMessage());
    json_error('Something went wrong while saving the job posting. Please try again.', 500);
}