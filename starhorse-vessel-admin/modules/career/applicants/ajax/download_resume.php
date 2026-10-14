<?php
/**
 * ajax/download_resume.php — GET ?id=123 → streams the applicant's
 * uploaded resume/CV file. Admin-only (require_login(), same as every
 * other page in this panel) — this is the ONLY way a resume is ever
 * served; uploads/career_resumes/ itself is locked down with a
 * deny-all .htaccess so the file can never be fetched directly by URL,
 * satisfying "do not expose applicant information publicly".
 *
 * Always resolves the file from the database-stored filename (never
 * from user input) and re-applies basename() defensively before
 * touching the filesystem, so this can't be tricked into path traversal
 * even if that were somehow attempted via a crafted id/response.
 */
require_once __DIR__ . '/../../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../../functions/helpers.php';
require_once __DIR__ . '/../../../../functions/career_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid applicant ID.');
}

$applicant = get_career_applicant($id);
if (!$applicant || empty($applicant['resume_file'])) {
    http_response_code(404);
    exit('No resume on file for this applicant.');
}

$storedFilename = basename($applicant['resume_file']);
$path = __DIR__ . '/../../../../uploads/career_resumes/' . $storedFilename;

if (!is_file($path)) {
    http_response_code(404);
    exit('Resume file could not be found on the server.');
}

$ext = strtolower((string) pathinfo($storedFilename, PATHINFO_EXTENSION));
$mimeByExt = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$contentType = $mimeByExt[$ext] ?? 'application/octet-stream';

// Friendly, safe download filename built from the applicant's name —
// the actual file on disk keeps its random, collision-safe name.
$safeName = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $applicant['full_name']);
$safeName = trim((string) $safeName, '-');
$downloadName = ($safeName !== '' ? $safeName : 'applicant') . '-resume.' . $ext;

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;