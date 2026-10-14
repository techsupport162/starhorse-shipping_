<?php
/**
 * ajax/settings_update.php — POST (JSON body) → save the Head Office /
 * map settings row plus the full phone and email lists in one request.
 *
 * Sent as JSON (not multipart form data) because the phone/email
 * repeater fields are nested arrays — mirrors the JSON-body approach
 * already used by modules/faqs/ajax/reorder.php for structured payloads.
 * All three writes happen together so the settings form always saves as
 * one atomic "Save Changes" action from the admin's point of view, even
 * though contact_settings/contact_phones/contact_emails are separate
 * tables under the hood.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/contact_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_error('Invalid request body.', 400);
}

if (!csrf_verify($input['csrf_token'] ?? '')) {
    json_error('Your session expired. Please refresh the page and try again.', 403);
}

$officeName   = clean_str($input['office_name'] ?? 'Head Office');
$address      = clean_str($input['address'] ?? '');
$telNumber    = clean_str($input['tel_number'] ?? '');
$facebookName = clean_str($input['facebook_name'] ?? '');
$facebookUrl  = clean_str($input['facebook_url'] ?? '');
$mapEmbedUrl  = trim((string) ($input['map_embed_url'] ?? ''));
$mapStatus    = ($input['map_status'] ?? 'published') === 'draft' ? 'draft' : 'published';
$status       = ($input['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

$phonesInput = is_array($input['phones'] ?? null) ? $input['phones'] : [];
$emailsInput = is_array($input['emails'] ?? null) ? $input['emails'] : [];

// ---- Field-level validation --------------------------------------------
$fieldErrors = [];

if ($officeName === '') {
    $fieldErrors['office_name'] = 'Office name is required.';
} elseif (mb_strlen($officeName) > 150) {
    $fieldErrors['office_name'] = 'Office name is too long (150 characters max).';
}

if ($address === '') {
    $fieldErrors['address'] = 'Address is required.';
} elseif (mb_strlen($address) > 255) {
    $fieldErrors['address'] = 'Address is too long (255 characters max).';
}

if ($telNumber !== '' && mb_strlen($telNumber) > 50) {
    $fieldErrors['tel_number'] = 'Telephone number is too long (50 characters max).';
}

if ($facebookUrl !== '' && !filter_var($facebookUrl, FILTER_VALIDATE_URL)) {
    $fieldErrors['facebook_url'] = 'Enter a valid Facebook URL (e.g. https://www.facebook.com/yourpage).';
}

if ($mapEmbedUrl !== '' && !filter_var($mapEmbedUrl, FILTER_VALIDATE_URL)) {
    $fieldErrors['map_embed_url'] = 'Enter a valid Google Maps embed URL.';
} elseif ($mapEmbedUrl !== '' && stripos($mapEmbedUrl, 'google.com/maps') === false && stripos($mapEmbedUrl, 'maps.google') === false) {
    $fieldErrors['map_embed_url'] = 'This doesn\'t look like a Google Maps URL. Use the "Embed a map" link from Google Maps\' Share menu.';
}

// Phones: clean + validate each row; reject the whole request if any row
// is malformed so the admin sees exactly which one to fix rather than
// having it silently dropped.
$cleanPhones = [];
foreach ($phonesInput as $i => $row) {
    $number = clean_str($row['phone_number'] ?? '');
    if ($number === '') {
        continue; // blank repeater rows are simply skipped, not an error
    }
    if (mb_strlen($number) > 30) {
        $fieldErrors['phones'] = 'One of the phone numbers is too long (30 characters max).';
        break;
    }
    if (!preg_match('/^[0-9+()\-.\s]{5,30}$/', $number)) {
        $fieldErrors['phones'] = 'One of the phone numbers contains invalid characters.';
        break;
    }
    $cleanPhones[] = [
        'id'           => !empty($row['id']) ? (int) $row['id'] : null,
        'phone_number' => $number,
        'label'        => clean_str($row['label'] ?? ''),
        'status'       => ($row['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
    ];
}

// Emails: same approach.
$cleanEmails = [];
foreach ($emailsInput as $i => $row) {
    $email = clean_str($row['email'] ?? '');
    if ($email === '') {
        continue;
    }
    if (!is_valid_email($email)) {
        $fieldErrors['emails'] = 'One of the email addresses is not valid: ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        break;
    }
    $cleanEmails[] = [
        'id'     => !empty($row['id']) ? (int) $row['id'] : null,
        'email'  => $email,
        'label'  => clean_str($row['label'] ?? ''),
        'status' => ($row['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
    ];
}

if ($fieldErrors) {
    json_field_errors($fieldErrors);
}

try {
    update_contact_settings([
        'office_name'   => $officeName,
        'address'       => $address,
        'tel_number'    => $telNumber,
        'facebook_name' => $facebookName,
        'facebook_url'  => $facebookUrl,
        'map_embed_url' => $mapEmbedUrl,
        'map_status'    => $mapStatus,
        'status'        => $status,
    ]);

    sync_contact_phones($cleanPhones);
    sync_contact_emails($cleanEmails);

    json_response([
        'success' => true,
        'message' => 'Head Office details saved successfully.',
    ]);
} catch (Throwable $e) {
    error_log('[Contact settings update] ' . $e->getMessage());
    json_error('Something went wrong while saving. Please try again.', 500);
}
