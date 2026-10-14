<?php
/**
 * modules/fares/index.php
 * -----------------------------------------------------------------------
 * Fare route list screen. The table itself is populated client-side by
 * DataTables from ajax/list.php. Add / Edit / View / Delete all happen
 * inside Bootstrap modals via AJAX — this page never redirects.
 *
 * Replaces the old modules/news/ placeholder in the admin sidebar.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../functions/helpers.php';

$pageTitle = 'Fare Management';
$activeNav = 'fares';
$extraScripts = [admin_base_url() . '/modules/fares/assets/fares.js'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Fare Routes</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#fareFormModal"
            id="btnAddFare">
            <i class="bi bi-plus-lg me-1"></i> Add Fare Route
        </button>
    </div>
    <div class="sh-card__body">
        <table id="faresTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Passenger Fare</th>
                    <th>Cargo / Vehicle Fare</th>
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

<!-- Shared location suggestions for the From / To fields. Users can still
     type any value manually — this datalist only powers the searchable
     dropdown affordance, it is never validated against server-side. -->
<datalist id="fareLocationSuggestions">
    <option value="Lucena">
    <option value="Balanacan">
    <option value="Masbate">
</datalist>

<!-- =====================================================================
     Add / Edit Fare Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() as multipart/form-data
     (needed for file uploads) to ajax/create.php or ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="fareFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="fareForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="fareFormTitle">Add Fare Route</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="fareFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="fareId" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">From *</label>
                            <input type="text" class="form-control" name="origin" id="fareOrigin"
                                list="fareLocationSuggestions" placeholder="e.g. Lucena" autocomplete="off" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">To *</label>
                            <input type="text" class="form-control" name="destination" id="fareDestination"
                                list="fareLocationSuggestions" placeholder="e.g. Balanacan" autocomplete="off" required>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3 sh-two-way-toggle">
                        <span class="sh-two-way-toggle__icon" aria-hidden="true">
                            <i class="bi bi-arrow-left-right"></i>
                        </span>
                        <input class="form-check-input" type="checkbox" role="switch" name="is_vice_versa"
                            id="fareIsViceVersa" value="1" title="Two-way route" aria-describedby="fareTwoWayHint">
                        <label class="form-check-label small fw-semibold" for="fareIsViceVersa">
                            Two-way route
                        </label>
                        <div class="text-muted small mt-1" id="fareTwoWayHint">
                            When enabled, one fare record covers both directions — e.g. <strong>Lucena →
                                Balanacan</strong> will automatically also serve <strong>Balanacan →
                                Lucena</strong>. You won't need to create a second route for the reverse trip.
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="fareStatus">
                            <option value="published">Published (visible on site)</option>
                            <option value="draft">Draft (hidden)</option>
                        </select>
                    </div>

                    <hr class="my-3">

                    <!-- Passenger Fare Image -->
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold mb-0">Passenger Fare Image</label>
                        <span class="text-muted small">JPG, PNG, WEBP, or GIF — up to 5MB</span>
                    </div>
                    <div id="existingPassengerImage" class="sh-preview-grid mb-2">
                        <!-- existing image injected here when editing -->
                    </div>
                    <div class="sh-dropzone" id="passengerDropzone">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <div class="small fw-semibold">Drag &amp; drop the passenger fare image here, or click to browse
                        </div>
                        <div class="text-muted small">One image only — a new upload replaces the current one</div>
                    </div>
                    <input type="file" id="passengerImageInput" accept="image/*" hidden>
                    <div class="sh-preview-grid" id="passengerImagePreview">
                        <!-- new file preview -->
                    </div>

                    <hr class="my-3">

                    <!-- Cargo / Vehicle Fare Image -->
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold mb-0">Cargo / Vehicle Fare Image</label>
                        <span class="text-muted small">JPG, PNG, WEBP, or GIF — up to 5MB</span>
                    </div>
                    <div id="existingCargoImage" class="sh-preview-grid mb-2">
                        <!-- existing image injected here when editing -->
                    </div>
                    <div class="sh-dropzone" id="cargoDropzone">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <div class="small fw-semibold">Drag &amp; drop the cargo / vehicle fare image here, or click to
                            browse</div>
                        <div class="text-muted small">One image only — a new upload replaces the current one</div>
                    </div>
                    <input type="file" id="cargoImageInput" accept="image/*" hidden>
                    <div class="sh-preview-grid" id="cargoImagePreview">
                        <!-- new file preview -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="fareFormSubmit">
                        <span class="submit-label">Save Fare Route</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="fareFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     View Fare Modal (read-only)
     ===================================================================== -->
<div class="modal fade" id="fareViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fare Route Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="fareViewBody">
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
<div class="modal fade" id="fareDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Fare Route
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteFareRoute"></strong>? This will also
                    remove its fare images. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteFareBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deleteFareSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>