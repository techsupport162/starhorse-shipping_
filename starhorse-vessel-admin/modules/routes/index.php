<?php
/**
 * modules/routes/index.php
 * -----------------------------------------------------------------------
 * Routes Gallery Management screen. Two independent panels on one page:
 *   1. Destination gallery — a DataTables list (populated from
 *      ajax/list.php) with Add/Edit/View/Delete modals, same structure as
 *      Vessels/Fares.
 *   2. Routes Hero Management — the singleton banner image shown above
 *      the public carousel. No modal needed (there's only ever one row);
 *      it's a small self-contained card with its own dropzone + save/
 *      remove actions, wired directly to ajax/banner_update.php and
 *      ajax/banner_delete.php.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../functions/helpers.php';
require_once __DIR__ . '/../../functions/routes_functions.php';

$pageTitle = 'Routes Gallery Management';
$activeNav = 'routes';
$extraScripts = [admin_base_url() . '/modules/routes/assets/routes.js'];

$banner = get_route_banner_row();
$bannerUrl = $banner['banner_image']
    ? admin_base_url() . '/uploads/routes_banner/' . rawurlencode($banner['banner_image'])
    : null;

require __DIR__ . '/../../includes/header.php';
?>

<!-- =========================================================================
     Routes Hero Management — singleton banner image
     ========================================================================= -->
<div class="sh-card mb-4">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Routes Hero Management</h2>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-3">
            This is the large banner image (route map) shown above the scrolling port logos on the public Routes section.
            There is only ever one banner — uploading a new image replaces the current one.
        </p>

        <input type="hidden" id="bannerCsrfToken" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div id="bannerCurrentWrap" class="mb-3 <?= $bannerUrl ? '' : 'd-none' ?>">
            <button type="button" class="sh-banner-preview" id="bannerCurrentPreviewBtn"
                aria-label="View full-size banner">
                <img src="<?= htmlspecialchars($bannerUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="Current routes banner"
                    class="sh-banner-preview-img" id="bannerCurrentImg">
                <span class="sh-banner-preview__overlay">
                    <i class="bi bi-zoom-in"></i> View full size
                </span>
            </button>
            <div>
                <button type="button" class="btn btn-sh-outline btn-sm mt-2" id="btnRemoveBanner">
                    <i class="bi bi-trash me-1"></i> Remove Banner
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="bannerRemoveSpinner"></span>
                </button>
            </div>
        </div>

        <div id="bannerEmptyState" class="text-muted small mb-3 <?= $bannerUrl ? 'd-none' : '' ?>">
            <i class="bi bi-image me-1"></i> No banner image uploaded yet.
        </div>

        <div class="mb-2 d-flex align-items-center justify-content-between">
            <label class="form-label small fw-semibold mb-0">Upload / Replace Banner</label>
            <span class="text-muted small">JPG, PNG, WEBP, or SVG — up to 10MB</span>
        </div>
        <div class="sh-dropzone" id="bannerDropzone">
            <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
            <div class="small fw-semibold">Drag &amp; drop a banner image here, or click to browse</div>
            <div class="text-muted small">One image only — a new upload replaces the current one</div>
        </div>
        <input type="file" id="bannerImageInput" name="banner_image"
            accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg" hidden>
        <div class="invalid-feedback d-block d-none" id="bannerImageError"></div>
        <div class="sh-preview-grid" id="bannerImagePreview">
            <!-- new file preview -->
        </div>

        <div class="mt-3">
            <button type="button" class="btn btn-sh-primary" id="btnSaveBanner">
                <span class="submit-label">Save Banner</span>
                <span class="spinner-border spinner-border-sm ms-1 d-none" id="bannerSaveSpinner"></span>
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================
     Ports We Serve — CRUD table (one row = tourism logo + port name, shown
     in the scrolling logo strip on the public Routes section)
     ========================================================================= -->
<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Ports We Serve (Logo Strip)</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#destinationFormModal"
            id="btnAddDestination">
            <i class="bi bi-plus-lg me-1"></i> Add Port
        </button>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-3">
            Each port appears in the scrolling logo strip on the public Routes section, showing its tourism logo
            with the port name underneath.
        </p>
        <table id="destinationsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Port Name</th>
                    <th>Status</th>
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
     Add / Edit Destination Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() as multipart/form-data
     (needed for file upload) to ajax/create.php or ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="destinationFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form id="destinationForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="destinationFormTitle">Add Port</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="destinationFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="destinationId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Port Name *</label>
                        <input type="text" class="form-control" name="location_name" id="destinationLocationName"
                            placeholder="e.g. Romblon" maxlength="150" required>
                        <div class="text-muted small mt-1">Shown under the logo on the public site.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="destinationStatus">
                            <option value="published">Published (visible on site)</option>
                            <option value="draft">Draft (hidden)</option>
                        </select>
                        <div class="text-muted small mt-1">
                            A port only appears in the public logo strip once it is both Published
                            <strong>and</strong> has a logo uploaded below.
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold mb-0">Tourism Logo</label>
                        <span class="text-muted small">PNG, SVG, WEBP, JPG, or GIF — up to 5MB</span>
                    </div>
                    <div class="text-muted small mb-2">
                        Upload a transparent PNG or SVG, square or wide, at least 300px across. The logo is
                        shown whole (never cropped) with no box behind it, so a white background would show.
                    </div>

                    <div id="existingDestinationImage" class="sh-preview-grid mb-2">
                        <!-- existing image injected here when editing -->
                    </div>

                    <div class="sh-dropzone" id="destinationDropzone">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <div class="small fw-semibold">Drag &amp; drop a logo here, or click to browse</div>
                        <div class="text-muted small">One logo only — a new upload replaces the current one</div>
                    </div>
                    <input type="file" id="destinationImageInput" name="image"
                        accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,.svg" hidden>
                    <div class="sh-preview-grid" id="destinationImagePreview">
                        <!-- new file preview -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="destinationFormSubmit">
                        <span class="submit-label">Save Port</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="destinationFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     View Destination Modal (read-only)
     ===================================================================== -->
<div class="modal fade" id="destinationViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Port Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="destinationViewBody">
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
<div class="modal fade" id="destinationDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Port
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteDestinationName"></strong>? This
                    will also remove its logo. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteDestinationBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deleteDestinationSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     Banner Full-Size Preview Modal (view-on-click, image stays compact
     everywhere else on the page)
     ===================================================================== -->
<div class="modal fade" id="bannerPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content sh-banner-preview-modal">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <img src="<?= htmlspecialchars($bannerUrl ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="Current routes banner"
                id="bannerPreviewModalImg">
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>