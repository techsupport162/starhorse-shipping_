<?php
/**
 * ajax/update.php — POST multipart/form-data → update an existing career
 * posting's fields, and independently keep / replace / remove its
 * recruitment image (remove_recruitment_image=1 clears it; a new
 * recruitment_image file replaces it; neither leaves it untouched).
 * Mirrors modules/fares/ajax/update.php's shape.
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
    json_error('Invalid job posting ID.', 400);
}

$existing = get_career_posting($id);
if (!$existing) {
    json_error('Job posting not found.', 404);
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
$removeImage = ($_POST['remove_recruitment_image'] ?? '0') === '1';

// ---- Field-level validation (see create.php for the same pattern) ----
$fieldErrors = [];

if (!$positionId) {
    $fieldErrors['position_id'] = 'Position is required.';
} else {
    $positionError = validate_position_for_posting($positionId, false, (int) $existing['position_id']);
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

// ---- Recruitment image (optional — only validated if a new file was
// actually uploaded; keep/remove are handled separately below) --------
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
    update_career_posting($id, [
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
        // Replace: store the new file, then remove the old one on disk.
        $filename = store_uploaded_image($_FILES['recruitment_image'], $imageCheck['ext'], 'career');
        update_career_posting_image($id, $filename);
        if (!empty($existing['recruitment_image'])) {
            delete_uploaded_image_file($existing['recruitment_image'], 'career');
        }
    } elseif ($removeImage && !empty($existing['recruitment_image'])) {
        update_career_posting_image($id, null);
        delete_uploaded_image_file($existing['recruitment_image'], 'career');
    }

    $response = [
        'success' => true,
        'message' => 'Job posting updated successfully.',
    ];
    if ($dimensionWarning !== null) {
        $response['image_dimension_warning'] = $dimensionWarning;
    }

    json_response($response);
} catch (Throwable $e) {
    error_log('[Career Postings update] ' . $e->getMessage());
    json_error('Something went wrong while updating the job posting. Please try again.', 500);
}