<?php
/**
 * ajax/create.php — POST multipart/form-data → insert a new user
 * account. Administrator-only. Called from the Add User modal.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/user_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$fullName      = clean_str($_POST['full_name'] ?? '');
$email         = clean_str($_POST['email'] ?? '');
$contactNumber = clean_str($_POST['contact_number'] ?? '') ?: null;
$username      = clean_str($_POST['username'] ?? '');
$password      = (string) ($_POST['password'] ?? '');
$confirmPass   = (string) ($_POST['confirm_password'] ?? '');
$userAccess    = ($_POST['user_access'] ?? 'user') === 'administrator' ? 'administrator' : 'user';
$status        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

// ---- Field-level validation ------------------------------------------
$fieldErrors = [];

if ($fullName === '') {
    $fieldErrors['full_name'] = 'Full name is required.';
} elseif (mb_strlen($fullName) > 100) {
    $fieldErrors['full_name'] = 'Full name is too long (100 characters max).';
}

if ($email === '' || !is_valid_email($email)) {
    $fieldErrors['email'] = 'A valid email address is required.';
} elseif (email_exists($email)) {
    $fieldErrors['email'] = 'That email address is already in use.';
}

if ($username === '' || mb_strlen($username) < 3) {
    $fieldErrors['username'] = 'Username must be at least 3 characters.';
} elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
    $fieldErrors['username'] = 'Username can only contain letters, numbers, underscores, and periods.';
} elseif (username_exists($username)) {
    $fieldErrors['username'] = 'That username is already taken.';
}

if (!is_valid_password($password)) {
    $fieldErrors['password'] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a symbol.';
} elseif ($password !== $confirmPass) {
    $fieldErrors['confirm_password'] = 'Password and confirmation do not match.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $data = [
        'username'       => $username,
        'password'       => password_hash($password, PASSWORD_DEFAULT),
        'full_name'      => $fullName,
        'email'          => $email,
        'contact_number' => $contactNumber,
        'user_access'    => $userAccess,
        'status'         => $status,
    ];

    // Optional profile photo (single file — only JPG/PNG/WEBP per spec, no GIF).
    // Every upload is auto center-cropped to a square and resized (see
    // process_and_store_profile_image()) so table/table icons stay uniform.
    if (!empty($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $check = validate_image_upload($_FILES['profile_image'], 5 * 1024 * 1024, [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ]);
        if (!$check['ok']) {
            json_error('Profile image: ' . $check['error'], 422);
        }
        try {
            $data['profile_image'] = process_and_store_profile_image($_FILES['profile_image'], $check['ext']);
        } catch (Throwable $e) {
            error_log('[Users create] image processing: ' . $e->getMessage());
            json_error('Profile image: ' . $e->getMessage(), 422);
        }
    }

    $userId = create_user($data);

    json_response(['success' => true, 'message' => 'User added successfully.', 'id' => $userId]);
} catch (Throwable $e) {
    error_log('[Users create] ' . $e->getMessage());
    json_error('Something went wrong while saving the user. Please try again.', 500);
}
