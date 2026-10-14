<?php
/**
 * modules/career/postings/index.php
 * -----------------------------------------------------------------------
 * Career Job Postings screen — the admin CRUD for recruitment
 * announcements shown on the public Career section. The table itself is
 * populated client-side by DataTables from ajax/list.php. Add / Edit /
 * View / Delete all happen inside Bootstrap modals via AJAX — this page
 * never redirects. Structurally mirrors modules/fares/index.php (single
 * image upload, Published/Draft status), with a Position dropdown
 * sourced from Career Position Settings.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';
require_once __DIR__ . '/../../../functions/career_functions.php';

$pageTitle = 'Career Job Postings';
$activeNav = 'career-postings';
$extraScripts = [admin_base_url() . '/modules/career/postings/assets/postings.js'];

// Only active positions are offered when creating/editing a posting. If
// an existing posting's own position was since disabled, positions.js
// adds it back into the dropdown on load (see openEditModal) so the
// record still displays and saves correctly without corrupting it.
$activePositions = get_active_positions();

require __DIR__ . '/../../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Career Job Postings</h2>
        <?php if (empty($activePositions)): ?>
        <a href="<?= htmlspecialchars(admin_base_url() . '/modules/career/positions/index.php', ENT_QUOTES, 'UTF-8') ?>"
            class="btn btn-sh-primary">
            <i class="bi bi-person-badge me-1"></i> Add a Position First
        </a>
        <?php else: ?>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#postingFormModal"
            id="btnAddPosting">
            <i class="bi bi-plus-lg me-1"></i> Add Job Posting
        </button>
        <?php endif; ?>
    </div>
    <div class="sh-card__body">
        <?php if (empty($activePositions)): ?>
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No active positions yet. Add at least one in <strong>Career Management &rsaquo; Position
                Settings</strong> before creating a job posting.
        </div>
        <?php endif; ?>
        <table id="postingsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Recruitment Image</th>
                    <th>Title</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Updated</th>
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
     Add / Edit Job Posting Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() as multipart/form-data
     (needed for the file upload) to ajax/create.php or ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="postingFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="postingForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="postingFormTitle">Add Job Posting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="postingFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="postingId" value="">

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Job Title *</label>
                            <input type="text" class="form-control" name="title" id="postingTitle" maxlength="150"
                                placeholder="e.g. Able Seaman &ndash; Inter-island Vessel" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Position *</label>
                            <select class="form-select" name="position_id" id="postingPosition" required>
                                <option value="">Select Position</option>
                                <?php foreach ($activePositions as $position): ?>
                                <option value="<?= (int) $position['id'] ?>">
                                    <?= htmlspecialchars($position['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small" id="postingPositionInactiveHint" hidden>
                                <i class="bi bi-info-circle me-1"></i>This posting's position was disabled in
                                Position Settings. It stays selected here so this record isn't affected — pick a
                                different position only if you actually want to reassign this posting.
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Short Description</label>
                        <input type="text" class="form-control" name="short_description" id="postingShortDesc"
                            maxlength="255" placeholder="One-line summary shown on the Career section cards">
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Job Description</label>
                        <textarea class="form-control" name="job_description" id="postingJobDescription" rows="4"
                            placeholder="Full description of the role"></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Qualifications / Requirements</label>
                        <textarea class="form-control" name="qualifications" id="postingQualifications" rows="4"
                            placeholder="e.g. Valid SID/POEA documents, minimum 2 years seagoing experience..."></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Responsibilities</label>
                        <textarea class="form-control" name="responsibilities" id="postingResponsibilities" rows="4"
                            placeholder="Key duties for this position"></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Application Instructions</label>
                        <textarea class="form-control" name="application_instructions"
                            id="postingApplicationInstructions" rows="3"
                            placeholder="e.g. Bring original documents to our Lucena office, or apply online below."></textarea>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Contact / Application Email</label>
                            <input type="email" class="form-control" name="contact_email" id="postingContactEmail"
                                maxlength="150" placeholder="careers@starhorseshipping.com">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" name="status" id="postingStatus">
                                <option value="draft">Draft (hidden from public site)</option>
                                <option value="published">Published (visible on Career section)</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Recruitment Image -->
                    <div class="mb-2 d-flex align-items-center justify-content-between flex-wrap gap-1">
                        <label class="form-label small fw-semibold mb-0">Recruitment Image</label>
                        <span class="text-muted small">JPG, PNG, or WEBP &mdash; up to 5MB</span>
                    </div>
                    <div class="alert alert-light border small py-2 px-3 mb-2">
                        <i class="bi bi-aspect-ratio me-1"></i>
                        Recommended: <strong>1350 &times; 1080px</strong> (5:4 aspect ratio) &mdash; the same size
                        as a typical hiring poster shared on social media. Other sizes are still accepted; the full
                        image is always shown and never cropped.
                    </div>
                    <div id="existingPostingImage" class="sh-preview-grid mb-2">
                        <!-- existing image injected here when editing -->
                    </div>
                    <div class="sh-dropzone" id="postingDropzone">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <div class="small fw-semibold">Drag &amp; drop the recruitment image here, or click to
                            browse</div>
                        <div class="text-muted small">One image only &mdash; a new upload replaces the current one
                        </div>
                    </div>
                    <input type="file" id="postingImageInput" accept="image/*" hidden>
                    <div class="sh-preview-grid" id="postingImagePreview">
                        <!-- new file preview -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="postingFormSubmit">
                        <span class="submit-label">Save Job Posting</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="postingFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     View Job Posting Modal (read-only)
     ===================================================================== -->
<div class="modal fade" id="postingViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Job Posting Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="postingViewBody">
                <div class="text-center text-muted py-4">Loading&hellip;</div>
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
<div class="modal fade" id="postingDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Job
                    Posting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deletePostingTitle"></strong>? This
                    will also remove its recruitment image. Applicants who already applied for this posting are
                    kept on record. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeletePostingBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deletePostingSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>