<?php
/**
 * ajax/update.php — POST multipart/form-data → update a user's profile
 * fields. Password is intentionally NOT accepted here — see
 * change_password.php. Administrator-only.
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

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    json_error('Invalid user ID.', 400);
}

$existing = get_user($id);
if (!$existing) {
    json_error('User not found.', 404);
}

$fullName      = clean_str($_POST['full_name'] ?? '');
$email         = clean_str($_POST['email'] ?? '');
$contactNumber = clean_str($_POST['contact_number'] ?? '') ?: null;
$username      = clean_str($_POST['username'] ?? '');
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
} elseif (email_exists($email, $id)) {
    $fieldErrors['email'] = 'That email address is already in use.';
}

if ($username === '' || mb_strlen($username) < 3) {
    $fieldErrors['username'] = 'Username must be at least 3 characters.';
} elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
    $fieldErrors['username'] = 'Username can only contain letters, numbers, underscores, and periods.';
} elseif (username_exists($username, $id)) {
    $fieldErrors['username'] = 'That username is already taken.';
}

// Safety: don't allow demoting/deactivating the last remaining active
// administrator. This is a combination of two fields (user_access +
// status), not one — so it deliberately does NOT use either field's name
// as the key. The frontend's mapBackendErrors() falls back to the modal's
// top alert box for any key that doesn't match a real input, which is
// exactly right here.
$isDemotingSelfOrLast = ($existing['user_access'] === 'administrator')
    && ($userAccess !== 'administrator' || $status !== 'active')
    && count_active_administrators($id) === 0;

if ($isDemotingSelfOrLast) {
    $fieldErrors['_business_rule'] = 'At least one active administrator account must remain. Promote another user to Administrator before changing this one.';
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    $data = [
        'username'       => $username,
        'full_name'      => $fullName,
        'email'          => $email,
        'contact_number' => $contactNumber,
        'user_access'    => $userAccess,
        'status'         => $status,
    ];

    // Optional replacement profile photo — auto center-cropped to a square
    // and resized, same as on create (see process_and_store_profile_image()).
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
            $newFilename = process_and_store_profile_image($_FILES['profile_image'], $check['ext']);
        } catch (Throwable $e) {
            error_log('[Users update] image processing: ' . $e->getMessage());
            json_error('Profile image: ' . $e->getMessage(), 422);
        }
        if (!empty($existing['profile_image'])) {
            delete_uploaded_image_file($existing['profile_image'], 'users');
        }
        $data['profile_image'] = $newFilename;
    } elseif (!empty($_POST['remove_profile_image']) && $_POST['remove_profile_image'] === '1') {
        // User cleared the photo in the avatar picker without choosing a
        // replacement — delete the file and blank the column.
        if (!empty($existing['profile_image'])) {
            delete_uploaded_image_file($existing['profile_image'], 'users');
        }
        $data['profile_image'] = null;
    }

    update_user($id, $data);

    // If this account updated its own profile, keep the session in sync.
    if ((int) ($_SESSION['admin_id'] ?? 0) === $id) {
        $_SESSION['admin_name'] = $fullName;
        $_SESSION['admin_role'] = $userAccess;
        if (array_key_exists('profile_image', $data)) {
            $_SESSION['admin_avatar'] = $data['profile_image'];
        }
    }

    json_response(['success' => true, 'message' => 'User updated successfully.']);
} catch (Throwable $e) {
    error_log('[Users update] ' . $e->getMessage());
    json_error('Something went wrong while updating the user. Please try again.', 500);
}
