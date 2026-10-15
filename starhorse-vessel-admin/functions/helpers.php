<?php
/**
 * functions/helpers.php
 * -----------------------------------------------------------------------
 * Small, dependency-free utility functions shared across the admin CMS:
 * CSRF tokens, flash messages, input sanitization, and image upload
 * validation. Keeping these in one place avoids duplicated code across
 * modules.
 * -----------------------------------------------------------------------
 */

/** Generate (or reuse) the CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echo a hidden CSRF input field for use inside admin forms/modals. */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate a submitted CSRF token. On AJAX endpoints, call this first and
 * bail out with a 403 JSON response if it fails.
 */
function csrf_verify(?string $submittedToken): bool
{
    return !empty($submittedToken)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/** Send a standard JSON response and stop execution. */
function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/** Shortcut for a failed JSON response with a message. */
function json_error(string $message, int $statusCode = 400, array $extra = []): void
{
    json_response(array_merge(['success' => false, 'message' => $message], $extra), $statusCode);
}

/**
 * Send field-level validation errors, e.g.
 *   json_field_errors(['email' => 'That email is already in use.', 'username' => 'Too short.']);
 * The frontend's ShValidator.mapBackendErrors() reads the `errors` object
 * and shows each message directly under its matching form field (by
 * `name`), instead of one generic alert. Any key that doesn't match a
 * field on the form falls back to the modal's top alert box, so
 * business-rule errors that aren't about one specific field (e.g. "at
 * least one administrator must remain") still surface clearly.
 *
 * This is additive — json_error()/json_response() are unchanged and still
 * used as before for non-field errors (CSRF, not-found, server errors).
 *
 * @param array  $fields  ['fieldName' => 'Error message', ...] — at least one entry
 * @param string $message top-level fallback summary shown if nothing maps
 */
function json_field_errors(array $fields, int $statusCode = 422, string $message = 'Please correct the highlighted fields.'): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors'  => $fields,
    ], $statusCode);
}

/** Store a one-time flash message shown after a redirect (e.g. after login). */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Retrieve and clear the flash message, if any. */
function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Basic RFC-compliant email format check (uniqueness is checked separately at the DB layer). */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Password policy: min 8 characters, at least 1 uppercase, 1 lowercase, and 1 symbol. Mirrors the client-side checklist in assets/js/form-validator.js. */
function is_valid_password(string $password): bool
{
    return mb_strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

/** Trim a plain text field submitted from a form. */
function clean_str(?string $value): string
{
    return trim($value ?? '');
}

/**
 * Parse a numeric spec field (length, breadth, depth, gross tonnage).
 * Returns null for blank input so optional fields stay NULL in the DB,
 * or a float rounded to 2 decimals.
 */
function clean_decimal(?string $value): ?float
{
    $value = trim($value ?? '');
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value) || (float) $value < 0) {
        return null;
    }
    return round((float) $value, 2);
}

/**
 * Companion to clean_decimal() for CRUD endpoints that want a field-level
 * error message (via json_field_errors()) instead of silently coercing an
 * invalid value to null. A blank value is valid — these spec fields are
 * optional — only a non-numeric or negative value is flagged.
 */
function validate_decimal_field(?string $value, string $label): ?string
{
    $value = trim($value ?? '');
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return $label . ' must be a valid number.';
    }
    if ((float) $value < 0) {
        return $label . ' cannot be negative.';
    }
    return null;
}

/**
 * Turn a PHP upload error code into a message a non-technical admin can
 * act on. UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE specifically mean
 * the file was rejected by the server's own upload_max_filesize before
 * our application-level size check ever ran — a distinct situation from
 * "you picked too large a file for what we display here," so it gets
 * its own message.
 */
function image_upload_error_message(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That file is larger than the server currently accepts. Please contact the site administrator if this keeps happening.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was interrupted partway through. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'The server could not process this upload. Please try again or contact the site administrator.';
        default:
            return 'Upload failed. Please try again.';
    }
}

/**
 * Parse a php.ini size shorthand ("10M", "1G", "512K") into bytes.
 */
function ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    switch ($unit) {
        case 'g':
            return (int) ($number * 1024 * 1024 * 1024);
        case 'm':
            return (int) ($number * 1024 * 1024);
        case 'k':
            return (int) ($number * 1024);
        default:
            return (int) $number;
    }
}

/**
 * Detect the one upload failure mode with no per-file error to read at
 * all: when the *entire* POST body exceeds post_max_size, PHP discards
 * it before populating either $_POST or $_FILES, and the request just
 * looks like an empty submission. Left unhandled, that surfaces as a
 * misleading "please choose a file" or "session expired" (since the
 * CSRF token field is empty too) instead of the real cause. Callers
 * should check this before anything that depends on $_POST/$_FILES.
 */
function request_body_exceeded_post_max_size(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    if (!empty($_POST) || !empty($_FILES)) {
        return false; // Body was parsed; this isn't that case.
    }
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return false;
    }
    return $contentLength > ini_size_to_bytes((string) ini_get('post_max_size'));
}

/**
 * Validate a single uploaded image ($_FILES entry). Checks the upload
 * error code, size, and format.
 *
 * Raster formats (JPG/PNG/WEBP/GIF) are verified via finfo's real MIME
 * sniff (not the client-supplied one) plus getimagesize(). SVG is XML
 * text, not a raster image, so neither of those checks applies to it —
 * when the allow-list includes 'image/svg+xml', a .svg-extensioned
 * upload is routed to validate_and_sanitize_svg_upload() instead, which
 * parses and sanitizes the markup (see that function for details).
 *
 * $allowedMimes lets each call site define its own accepted formats
 * (e.g. the banner allows a larger size and no GIF; destinations keep
 * the existing GIF support) without changing the shared default used by
 * unrelated modules (vessels, user profile photos, etc.).
 *
 * @return array{ok: bool, error?: string, ext?: string, svg_sanitized?: string}
 */
function validate_image_upload(array $file, int $maxBytes = 5 * 1024 * 1024, ?array $allowedMimes = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => image_upload_error_message($file['error'])];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $allowed = $allowedMimes ?? [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $formatLabel = image_allowed_format_label($allowed);

    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'Image exceeds the ' . image_max_size_label($maxBytes) . ' limit.'];
    }

    $submittedExt = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    // ---- SVG: dedicated validation path -------------------------------
    if ($submittedExt === 'svg') {
        if (!isset($allowed['image/svg+xml'])) {
            return ['ok' => false, 'error' => "Only {$formatLabel} images are allowed."];
        }
        return validate_and_sanitize_svg_upload($file);
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // A file with an SVG (or other XML/text) MIME type but a raster
    // extension — e.g. renamed to dodge the branch above — is rejected
    // here rather than silently falling through to getimagesize(),
    // which would simply fail on it anyway.
    if (in_array($mimeType, ['image/svg+xml', 'text/xml', 'application/xml'], true)) {
        return ['ok' => false, 'error' => 'SVG files must be uploaded with a .svg extension.'];
    }

    if (!isset($allowed[$mimeType])) {
        return ['ok' => false, 'error' => "Only {$formatLabel} images are allowed."];
    }

    // getimagesize() also confirms the file is genuinely a valid raster
    // image, guarding against disguised/renamed non-image files.
    if (@getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    return ['ok' => true, 'ext' => $allowed[$mimeType]];
}

/**
 * Build a human-readable format list for validation messages from an
 * $allowedMimes map, e.g. ['image/jpeg' => 'jpg', 'image/svg+xml' =>
 * 'svg'] -> "JPG, PNG, WEBP, or SVG". Keeps error copy in sync with
 * whatever formats a given call site actually allows.
 */
function image_allowed_format_label(array $allowedMimes): string
{
    $exts = array_values(array_unique(array_map('strtoupper', $allowedMimes)));
    if (count($exts) === 1) {
        return $exts[0];
    }
    $last = array_pop($exts);
    return implode(', ', $exts) . ', or ' . $last;
}

/** Format a byte limit as a short "10MB" / "5MB" style label for messages. */
function image_max_size_label(int $maxBytes): string
{
    $mb = $maxBytes / (1024 * 1024);
    $formatted = rtrim(rtrim(number_format($mb, 1), '0'), '.');
    return $formatted . 'MB';
}

/**
 * Validate and sanitize an uploaded SVG file. SVG is XML, not a raster
 * image, so this is a completely separate path from validate_image_upload()'s
 * finfo/getimagesize() checks:
 *
 *   1. Reject a DOCTYPE/ENTITY declaration outright (the standard
 *      XXE / entity-expansion attack vector) — SVG never legitimately
 *      needs one for this project's use case.
 *   2. Parse as XML and require a well-formed <svg> root element,
 *      rejecting anything malformed or that isn't actually an SVG.
 *   3. Strip active/executable content from the markup before it is
 *      ever written to disk: <script> elements, on*="" event handler
 *      attributes, javascript: URIs, and embedding elements
 *      (<foreignObject>, <iframe>, <embed>, <object>) that could smuggle
 *      in further HTML/script content.
 *
 * This is a denylist filter, not a full sanitizer library. Combined
 * with the .htaccess Content-Type lock in uploads/routes/ and
 * uploads/routes_banner/ (which stops a served .svg from ever being
 * sniffed/executed as HTML), it's defense in depth rather than the only
 * control — appropriate for a small, admin-only upload surface rather
 * than a general-purpose public SVG host.
 *
 * @return array{ok: bool, error?: string, ext?: string, svg_sanitized?: string}
 */
function validate_and_sanitize_svg_upload(array $file): array
{
    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false || trim($contents) === '') {
        return ['ok' => false, 'error' => 'Could not read the uploaded SVG file.'];
    }

    if (preg_match('/<!DOCTYPE/i', $contents) === 1 || preg_match('/<!ENTITY/i', $contents) === 1) {
        return ['ok' => false, 'error' => 'SVG files with a DOCTYPE or ENTITY declaration are not allowed.'];
    }

    $previousSetting = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    $parseErrors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);

    if ($xml === false || !empty($parseErrors)) {
        return ['ok' => false, 'error' => 'File is not a valid SVG (malformed XML).'];
    }

    if (strtolower($xml->getName()) !== 'svg') {
        return ['ok' => false, 'error' => 'File is not a valid SVG (unexpected root element).'];
    }

    return ['ok' => true, 'ext' => 'svg', 'svg_sanitized' => sanitize_svg_markup($contents)];
}

/**
 * Strip active/executable content from SVG markup. See
 * validate_and_sanitize_svg_upload() for what this is (and isn't)
 * intended to guard against.
 */
function sanitize_svg_markup(string $contents): string
{
    // <script>...</script> blocks and self-closing <script/> tags.
    $contents = (string) preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $contents);
    $contents = (string) preg_replace('/<script\b[^>]*\/\s*>/is', '', $contents);

    // Embedding elements that could smuggle in further HTML/script.
    foreach (['foreignObject', 'iframe', 'embed', 'object'] as $tag) {
        $contents = (string) preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '\s*>/is', '', $contents);
        $contents = (string) preg_replace('/<' . $tag . '\b[^>]*\/\s*>/is', '', $contents);
    }

    // on*="..." / on*='...' event handler attributes (onload, onclick, ...).
    $contents = (string) preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $contents);
    $contents = (string) preg_replace("/\s+on[a-z]+\s*=\s*'[^']*'/i", '', $contents);

    // javascript: URIs in href / xlink:href / src attributes.
    $contents = (string) preg_replace('/((?:xlink:)?href|src)(\s*=\s*)"\s*javascript:[^"]*"/i', '$1$2"#"', $contents);
    $contents = (string) preg_replace("/((?:xlink:)?href|src)(\s*=\s*)'\s*javascript:[^']*'/i", "$1$2'#'", $contents);

    return $contents;
}

/** Return a Cloudinary API client configured from the server-side CLOUDINARY_URL. */
function cloudinary_upload_api(): \Cloudinary\Api\Upload\UploadApi
{
    static $api = null;
    if ($api instanceof \Cloudinary\Api\Upload\UploadApi) {
        return $api;
    }

    $cloudinaryUrl = getenv('CLOUDINARY_URL');
    if (!$cloudinaryUrl) {
        throw new RuntimeException('Cloudinary is not configured on the server.');
    }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Cloudinary dependencies are not installed.');
    }
    require_once $autoload;

    \Cloudinary\Configuration\Configuration::instance($cloudinaryUrl);
    $api = new \Cloudinary\Api\Upload\UploadApi();
    return $api;
}

/** Upload a local temporary image to a unique Cloudinary public ID. */
function upload_cloudinary_image(string $path, string $subdir): string
{
    if (!is_file($path) || !preg_match('/^[a-z0-9_-]+$/i', $subdir)) {
        throw new RuntimeException('Invalid image upload source.');
    }

    $result = cloudinary_upload_api()->upload($path, [
        'public_id' => 'starhorse/' . strtolower($subdir) . '/' . bin2hex(random_bytes(16)),
        'resource_type' => 'image',
        'overwrite' => false,
    ]);
    $url = (string) ($result['secure_url'] ?? '');
    if (!str_starts_with($url, 'https://res.cloudinary.com/')) {
        throw new RuntimeException('Cloudinary did not return a secure image URL.');
    }

    return $url;
}

/** Resolve Cloudinary URLs directly and legacy local filenames against their previous upload path. */
function image_asset_url(?string $storedValue, string $legacyBaseUrl): ?string
{
    if ($storedValue === null || $storedValue === '') {
        return null;
    }

    $parts = parse_url($storedValue);
    if (($parts['scheme'] ?? '') === 'https'
        && strtolower((string) ($parts['host'] ?? '')) === 'res.cloudinary.com'
        && str_contains((string) ($parts['path'] ?? ''), '/image/upload/')) {
        return $storedValue;
    }

    return $legacyBaseUrl . rawurlencode($storedValue);
}

/** Write sanitized SVG markup to a temporary file and upload it to Cloudinary. */
function store_uploaded_svg(string $sanitizedMarkup, string $subdir): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'starhorse-image-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Could not prepare the SVG upload.');
    }
    $svgPath = $temporaryPath . '.svg';

    try {
        if (!rename($temporaryPath, $svgPath) || file_put_contents($svgPath, $sanitizedMarkup) === false) {
            throw new RuntimeException('Could not prepare the SVG upload.');
        }
        return upload_cloudinary_image($svgPath, $subdir);
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
        if (is_file($svgPath)) {
            @unlink($svgPath);
        }
    }
}

/** Upload a validated image and return its Cloudinary secure URL for database storage. */
function store_uploaded_image(array $file, string $ext, string $subdir): string
{
    return upload_cloudinary_image($file['tmp_name'], $subdir);
}

/** Delete a Cloudinary image URL or a legacy local image filename. */
function delete_uploaded_image_file(string $storedValue, string $subdir): void
{
    if ($storedValue === '') {
        return;
    }

    $parts = parse_url($storedValue);
    if (($parts['scheme'] ?? '') === 'https'
        && strtolower((string) ($parts['host'] ?? '')) === 'res.cloudinary.com') {
        $publicId = cloudinary_public_id_from_url($storedValue);
        if ($publicId === null) {
            throw new RuntimeException('Cannot identify the Cloudinary image to delete.');
        }
        $result = cloudinary_upload_api()->destroy($publicId, [
            'resource_type' => 'image',
            'invalidate' => true,
        ]);
        if (!in_array($result['result'] ?? '', ['ok', 'not found'], true)) {
            throw new RuntimeException('Cloudinary could not delete the image.');
        }
        return;
    }

    $path = __DIR__ . '/../uploads/' . $subdir . '/' . basename($storedValue);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Extract a public ID only from this configured cloud's original image delivery URLs. */
function cloudinary_public_id_from_url(string $url): ?string
{
    $cloudinaryUrl = getenv('CLOUDINARY_URL') ?: '';
    $cloudName = parse_url($cloudinaryUrl, PHP_URL_HOST);
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== 'res.cloudinary.com'
        || !$cloudName) {
        return null;
    }

    $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));
    $uploadIndex = array_search('upload', $segments, true);
    if (($segments[0] ?? '') !== $cloudName || $uploadIndex === false
        || ($segments[$uploadIndex - 1] ?? '') !== 'image') {
        return null;
    }

    $publicIdParts = array_slice($segments, $uploadIndex + 1);
    if (isset($publicIdParts[0]) && preg_match('/^v[0-9]+$/', $publicIdParts[0])) {
        array_shift($publicIdParts);
    }
    if (!$publicIdParts) {
        return null;
    }

    $lastIndex = count($publicIdParts) - 1;
    $publicIdParts[$lastIndex] = pathinfo(rawurldecode($publicIdParts[$lastIndex]), PATHINFO_FILENAME);
    return implode('/', array_map('rawurldecode', $publicIdParts)) ?: null;
}

/**
 * Upload a validated vessel image to Cloudinary (kept for compatibility
 * with the existing Vessel module — delegates to store_uploaded_image()).
 */
function store_vessel_image(array $file, string $ext): string
{
    return store_uploaded_image($file, $ext, 'vessels');
}

/**
 * Process a validated profile-photo upload into a square "2x2" ID-photo
 * style thumbnail: center-crops to a square (using the shorter side),
 * resizes down to $size x $size, flattens any transparency onto white,
 * and always encodes as a JPG before upload for a small, consistent file
 * size. Used by User Management so profile images are uniform regardless
 * of the uploaded aspect ratio or resolution.
 *
 * @param array  $file validated $_FILES entry
 * @param string $ext  'jpg' | 'jpeg' | 'png' | 'webp' (from validate_image_upload())
 * @param int    $size output width/height in pixels (400 = a crisp square thumbnail)
 * @return string Cloudinary HTTPS delivery URL
 */
function process_and_store_profile_image(array $file, string $ext, int $size = 400): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('The PHP GD extension is required to process images but is not enabled.');
    }

    $tmpPath = $file['tmp_name'];
    $ext = strtolower($ext) === 'jpeg' ? 'jpg' : strtolower($ext);

    switch ($ext) {
        case 'jpg':
            $source = @imagecreatefromjpeg($tmpPath);
            $source = $source ? fix_jpeg_orientation($source, $tmpPath) : $source;
            break;
        case 'png':
            $source = @imagecreatefrompng($tmpPath);
            break;
        case 'webp':
            if (!function_exists('imagecreatefromwebp')) {
                throw new RuntimeException('WEBP images are not supported by this server. Please upload a JPG or PNG instead.');
            }
            $source = @imagecreatefromwebp($tmpPath);
            break;
        default:
            throw new RuntimeException('Unsupported image type.');
    }

    if (!$source) {
        throw new RuntimeException('Could not read the uploaded image.');
    }

    $srcWidth  = imagesx($source);
    $srcHeight = imagesy($source);
    $cropSize  = min($srcWidth, $srcHeight);
    $cropX     = intdiv($srcWidth - $cropSize, 2);
    $cropY     = intdiv($srcHeight - $cropSize, 2);

    // Flatten onto a white canvas first (handles PNG/WEBP transparency
    // consistently instead of leaving black or undefined backgrounds).
    $square = imagecreatetruecolor($size, $size);
    $white  = imagecolorallocate($square, 255, 255, 255);
    imagefill($square, 0, 0, $white);
    imagecopyresampled($square, $source, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize);
    imagedestroy($source);

    $temporaryPath = tempnam(sys_get_temp_dir(), 'starhorse-profile-');
    if ($temporaryPath === false) {
        imagedestroy($square);
        throw new RuntimeException('Could not prepare the profile photo.');
    }
    $jpgPath = $temporaryPath . '.jpg';
    if (!rename($temporaryPath, $jpgPath)) {
        @unlink($temporaryPath);
        imagedestroy($square);
        throw new RuntimeException('Could not prepare the profile photo.');
    }

    $saved = imagejpeg($square, $jpgPath, 88);
    imagedestroy($square);

    if (!$saved) {
        @unlink($jpgPath);
        throw new RuntimeException('Could not save the processed profile photo.');
    }

    try {
        return upload_cloudinary_image($jpgPath, 'users');
    } finally {
        if (is_file($jpgPath)) {
            @unlink($jpgPath);
        }
    }
}

/**
 * Reads EXIF orientation from a JPEG and rotates the in-memory image
 * upright before cropping — without this, portrait phone-camera photos
 * often get saved sideways because JPEG stores rotation as metadata
 * rather than actually rotating the pixel data.
 *
 * @param \GdImage|resource $image
 * @param string $path original file path (to re-read EXIF data from)
 * @return \GdImage|resource
 */
function fix_jpeg_orientation($image, string $path)
{
    if (!function_exists('exif_read_data')) {
        return $image; // exif extension not enabled — skip silently, non-critical
    }

    $exif = @exif_read_data($path);
    if (empty($exif['Orientation'])) {
        return $image;
    }

    switch ($exif['Orientation']) {
        case 3:
            return imagerotate($image, 180, 0);
        case 6:
            return imagerotate($image, -90, 0);
        case 8:
            return imagerotate($image, 90, 0);
        default:
            return $image;
    }
}
/**
 * Soft dimension check for the Career recruitment image (target
 * 1350x1080, 5:4). Deliberately advisory rather than a hard gate for
 * anything but genuinely unusable images: a hiring poster designed at a
 * slightly different resolution/ratio is still perfectly fine to post,
 * so a mismatch is surfaced to the admin as a warning (not blocked) —
 * only an image too small to read clearly as a poster is rejected
 * outright. Call this AFTER validate_image_upload() has already
 * confirmed the file is a genuine, readable raster image.
 *
 * @param string $tmpPath   the validated upload's tmp_name
 * @param int    $targetW   recommended width  (default 1350)
 * @param int    $targetH   recommended height (default 1080)
 * @return array{ok: bool, error?: string, warning?: string, width?: int, height?: int}
 */
function evaluate_career_image_dimensions(string $tmpPath, int $targetW = 1350, int $targetH = 1080): array
{
    $size = @getimagesize($tmpPath);
    if ($size === false) {
        // validate_image_upload() should already have caught this, but
        // fail safe rather than divide by zero below if it's ever called
        // on its own.
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    [$width, $height] = $size;

    // Obviously unsuitable: too small to read clearly as a recruitment
    // poster once displayed. This is the one case we prevent outright —
    // everything else (a different-but-reasonable resolution or ratio)
    // is a warning only, per the "don't blindly reject" requirement.
    $minWidth = 400;
    $minHeight = 320;
    if ($width < $minWidth || $height < $minHeight) {
        return [
            'ok' => false,
            'error' => "Image resolution is too low to use as a recruitment poster " .
                "({$width}×{$height}px). Please upload an image close to " .
                "{$targetW}×{$targetH}px (minimum {$minWidth}×{$minHeight}px).",
        ];
    }

    $targetRatio = $targetW / $targetH;
    $actualRatio = $width / $height;
    $ratioDiff = abs($actualRatio - $targetRatio) / $targetRatio;

    $warning = null;
    if ($width !== $targetW || $height !== $targetH) {
        if ($ratioDiff > 0.08) {
            // Aspect ratio itself is noticeably off (not just a resize of
            // the same 5:4 shape) — most likely to visibly crop/pillarbox
            // on the public site, so call that out specifically.
            $warning = "This image is {$width}×{$height}px, which is a different shape than the " .
                "recommended {$targetW}×{$targetH}px (5:4). It will still be accepted and displayed in " .
                "full (never cropped), but may show extra space around it on some layouts.";
        } else {
            $warning = "This image is {$width}×{$height}px. Recommended is {$targetW}×{$targetH}px " .
                "(5:4) for the sharpest, most consistent result — but this will work fine too.";
        }
    }

    return ['ok' => true, 'warning' => $warning, 'width' => $width, 'height' => $height];
}

/**
 * Validate a single uploaded resume/CV document ($_FILES entry) for the
 * public Career application form. Mirrors validate_image_upload()'s
 * shape and rigor (real MIME sniff via finfo, not the client-supplied
 * one) but for office documents instead of raster images, plus an
 * explicit denylist of executable/script extensions as a second,
 * independent gate — belt-and-suspenders against a disguised upload
 * (e.g. a double extension trick) making it onto disk.
 *
 * @return array{ok: bool, error?: string, ext?: string}
 */
function validate_resume_upload(array $file, int $maxBytes = 5 * 1024 * 1024, ?array $allowedMimes = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => image_upload_error_message($file['error'])];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $allowed = $allowedMimes ?? [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    $formatLabel = image_allowed_format_label($allowed);

    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File exceeds the ' . image_max_size_label($maxBytes) . ' limit.'];
    }

    $submittedExt = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    // Explicit denylist of executable/script extensions, checked before
    // (and independently of) the allow-list below — never rely on a
    // single layer to keep an executable file off the server.
    $dangerousExts = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cmd',
        'js', 'mjs', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'rb', 'html', 'htm', 'svg', 'dll', 'com', 'msi',
    ];
    if (in_array($submittedExt, $dangerousExts, true)) {
        return ['ok' => false, 'error' => 'This file type is not allowed for security reasons.'];
    }

    if (!in_array($submittedExt, $allowed, true)) {
        return ['ok' => false, 'error' => "Only {$formatLabel} files are allowed."];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mimeType])) {
        return ['ok' => false, 'error' => "Only {$formatLabel} files are allowed."];
    }

    if ($allowed[$mimeType] !== $submittedExt) {
        return ['ok' => false, 'error' => "The file's extension doesn't match its actual content type."];
    }

    return ['ok' => true, 'ext' => $allowed[$mimeType]];
}

/**
 * Keep validated non-image uploads (e.g. a resume/CV) on local storage;
 * these are private documents and must not be sent to Cloudinary.
 */
function store_uploaded_document(array $file, string $ext, string $subdir): string
{
    $filename = $subdir . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/' . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('Could not save uploaded document.');
    }
    return $filename;
}

/**
 * Remove an uploaded document file from uploads/<subdir>/ if it exists.
 * Alias of delete_uploaded_image_file() (already subdir/filename-generic
 * internally) for readability at document call sites.
 */
function delete_uploaded_document_file(string $filename, string $subdir): void
{
    delete_uploaded_image_file($filename, $subdir);
}