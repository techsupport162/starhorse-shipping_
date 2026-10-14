<?php
/**
 * modules/career/positions/index.php
 * -----------------------------------------------------------------------
 * Career Position Settings screen — manages the reusable list of
 * positions (Captain, Chief Engineer, Able Seaman, ...) that populate the
 * "Position" dropdown on the Career Posting form. The table itself is
 * populated client-side by DataTables from ajax/list.php. Add / Edit /
 * Delete / Enable-Disable all happen inside Bootstrap modals or inline
 * via AJAX — this page never redirects. Mirrors modules/faqs/index.php
 * (no image uploads needed here).
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../functions/helpers.php';

$pageTitle = 'Career Position Settings';
$activeNav = 'career-positions';
$extraScripts = [admin_base_url() . '/modules/career/positions/assets/positions.js'];

require __DIR__ . '/../../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Career Positions</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#positionFormModal"
            id="btnAddPosition">
            <i class="bi bi-plus-lg me-1"></i> Add Position
        </button>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Positions managed here populate the "Position" dropdown when creating a Career Posting. Disabling a
            position removes it from that dropdown for new postings — existing postings that already use it are not
            affected.
        </p>
        <table id="positionsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Position Name</th>
                    <th>Status</th>
                    <th>Postings Using It</th>
                    <th>Created At</th>
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
     Add / Edit Position Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() to ajax/create.php or
     ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="positionFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form id="positionForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="positionFormTitle">Add Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="positionFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="positionId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Position Name *</label>
                        <input type="text" class="form-control" name="name" id="positionName" maxlength="100"
                            placeholder="e.g. Chief Engineer" required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="is_active" id="positionStatus">
                            <option value="1">Active (available when creating a posting)</option>
                            <option value="0">Inactive (hidden from new postings)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="positionFormSubmit">
                        <span class="submit-label">Save Position</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="positionFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     Delete Confirmation Modal
     ===================================================================== -->
<div class="modal fade" id="positionDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Position
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="positionDeleteAlert" class="alert alert-danger d-none mb-3"></div>
                <p class="mb-0">Are you sure you want to delete <strong id="deletePositionName"></strong>? This
                    action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeletePositionBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deletePositionSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>