<?php
/**
 * modules/career/applicants/index.php
 * -----------------------------------------------------------------------
 * Career Applicant Management screen. Applicants are only ever created
 * from the public Career application form (ajax/career_apply.php) —
 * there is no "Add Applicant" here. This screen covers viewing,
 * filtering, downloading a submitted resume, and moving an applicant
 * through its own status workflow (New -> Reviewed -> Shortlisted ->
 * Interview -> Accepted/Rejected), which is completely independent of
 * any career posting's Published/Draft status.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';

$pageTitle = 'Career Applicants';
$activeNav = 'career-applicants';
$extraScripts = [admin_base_url() . '/modules/career/applicants/assets/applicants.js'];

require __DIR__ . '/../../../includes/header.php';
?>

<!-- No CRUD form on this page (applicants only ever arrive via the
     public site), so the CSRF token needed by fetch() calls below is
     carried on this standalone hidden input instead of a form field. -->
<input type="hidden" id="applicantsCsrfToken" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Career Applicants</h2>
        <div class="d-flex align-items-center gap-2">
            <label for="applicantStatusFilter" class="small text-muted mb-0 flex-shrink-0">Status:</label>
            <select class="form-select form-select-sm" id="applicantStatusFilter" style="width:auto;">
                <option value="">All</option>
                <option value="new">New</option>
                <option value="reviewed">Reviewed</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="interview">Interview</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
    <div class="sh-card__body">
        <table id="applicantsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Position Applied For</th>
                    <th>Applied Date</th>
                    <th>Resume</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- populated by DataTables via ajax/list.php -->
            </tbody>
        </table>
    </div>
</div>

<!-- =====================================================================
     View Applicant Modal (read-only details + status control + resume)
     ===================================================================== -->
<div class="modal fade" id="applicantViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Applicant Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="applicantViewBody">
                <div class="text-center text-muted py-4">Loading…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     Delete Confirmation Modal
     ===================================================================== -->
<div class="modal fade" id="applicantDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Applicant
                    Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete the application from
                    <strong id="deleteApplicantName"></strong>? Their uploaded resume, if any, will also be removed.
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteApplicantBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deleteApplicantSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>