<?php
/**
 * modules/vessels/index.php
 * -----------------------------------------------------------------------
 * Vessel list screen. The table itself is populated client-side by
 * DataTables from ajax/list.php. Add / Edit / View / Delete all happen
 * inside Bootstrap modals via AJAX — this page never redirects.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../functions/helpers.php';

$pageTitle = 'Vessels';
$activeNav = 'vessels';
$extraScripts = [admin_base_url() . '/modules/vessels/assets/vessels.js'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Fleet</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sh-outline" data-bs-toggle="modal"
                data-bs-target="#vesselFeaturesModal" id="btnManageFeatures">
                <i class="bi bi-stars me-1"></i> Manage Features
            </button>
            <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#vesselFormModal"
                id="btnAddVessel">
                <i class="bi bi-plus-lg me-1"></i> Add Vessel
            </button>
        </div>
    </div>
    <div class="sh-card__body">
        <table id="vesselsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Vessel Name</th>
                    <th>Length</th>
                    <th>Breadth</th>
                    <th>Depth</th>
                    <th>Gross Tonnage</th>
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
     Add / Edit Vessel Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() as multipart/form-data
     (needed for file uploads) to ajax/create.php or ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="vesselFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="vesselForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="vesselFormTitle">Add Vessel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="vesselFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="vesselId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Vessel Name *</label>
                        <input type="text" class="form-control" name="name" id="vesselName"
                            placeholder="e.g. M/V VIRGEN DE PEÑAFRANCIA I" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="vesselTagline">Carousel Label</label>
                        <input type="text" class="form-control" name="tagline" id="vesselTagline"
                            list="vesselTaglineSuggestions" maxlength="100"
                            placeholder="e.g. Our Flagship Vessel">
                        <datalist id="vesselTaglineSuggestions">
                            <option value="Our Initial Vessel">
                            <option value="Our Flagship Vessel">
                            <option value="New Cargo Vessel">
                            <option value="Modern Vessel">
                        </datalist>
                        <div class="form-text">Small text shown above the vessel name on the website. Leave blank to show "Our Fleet".</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Length (m)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="length_m"
                                id="vesselLength">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Breadth (m)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="breadth_m"
                                id="vesselBreadth">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Depth (m)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="depth_m"
                                id="vesselDepth">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold">Gross Tonnage</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="gross_tonnage"
                                id="vesselTonnage">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control" name="description" id="vesselDescription" rows="3"
                            placeholder="Optional short description shown on the public site"></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="vesselStatus">
                            <option value="published">Published (visible on site)</option>
                            <option value="draft">Draft (hidden)</option>
                        </select>
                    </div>

                    <hr class="my-3">

                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold mb-0">Vessel Features</label>
                        <button type="button" class="btn btn-link btn-sm p-0" id="vesselFormManageFeaturesLink">
                            <i class="bi bi-gear me-1"></i>Manage list
                        </button>
                    </div>
                    <div id="vesselFeatureChecklist" class="sh-feature-checklist">
                        <!-- populated from ajax/features_list.php -->
                        <div class="text-muted small py-2">Loading features…</div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label small fw-semibold mb-0">Images / Gallery</label>
                        <span class="text-muted small">JPG, PNG, WEBP, or GIF — up to 5MB each</span>
                    </div>

                    <div id="existingImagesHint" class="text-muted small mb-2 d-none">
                        <i class="bi bi-arrows-move me-1"></i>Drag to reorder &middot;
                        <i class="bi bi-star me-1"></i>click the star to set as the primary/featured image
                    </div>
                    <div id="existingImages" class="sh-preview-grid mb-2">
                        <!-- existing images injected here when editing -->
                    </div>

                    <div class="sh-dropzone" id="vesselDropzone">
                        <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                        <div class="small fw-semibold">Drag &amp; drop images here, or click to browse</div>
                        <div class="text-muted small">Multiple images supported</div>
                    </div>
                    <input type="file" id="vesselImagesInput" accept="image/*" multiple hidden>
                    <div class="sh-preview-grid" id="newImagesPreview">
                        <!-- new file previews -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="vesselFormSubmit">
                        <span class="submit-label">Save Vessel</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="vesselFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     View Vessel Modal (read-only)
     ===================================================================== -->
<div class="modal fade" id="vesselViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vessel Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="vesselViewBody">
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
<div class="modal fade" id="vesselDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Vessel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteVesselName"></strong>? This will also
                    remove all of its gallery images. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deleteSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     Feature Manager Modal
     Master list of reusable vessel features (name + Bootstrap Icon).
     A small inline add/edit form at the top, a compact list below —
     deliberately not a full second DataTable/CRUD screen, since the
     feature list is short and lives one level "inside" vessel management.
     ===================================================================== -->
<div class="modal fade" id="vesselFeaturesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stars me-2"></i>Manage Vessel Features</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Features defined here can be assigned to any vessel from its
                    Add/Edit form. Editing a feature's name or icon updates it everywhere it's already assigned.</p>

                <form id="featureForm" class="sh-feature-form mb-4" novalidate>
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" id="featureId" value="">
                    <div id="featureFormAlert" class="alert alert-danger d-none py-2 small"></div>

                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-sm-5">
                            <label class="form-label small fw-semibold">Feature Name</label>
                            <input type="text" class="form-control" name="name" id="featureName"
                                placeholder="e.g. Air Conditioner" maxlength="100" required>
                            <div class="invalid-feedback" data-field="name"></div>
                        </div>
                        <div class="col-12 col-sm-5">
                            <label class="form-label small fw-semibold">Icon</label>
                            <div class="sh-icon-picker">
                                <div class="sh-icon-picker__preview" id="featureIconPreview">
                                    <i class="bi bi-check2-circle"></i>
                                </div>
                                <input type="text" class="form-control" name="icon" id="featureIcon"
                                    placeholder="bi-snow" value="bi-check2-circle" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="sh-icon-swatches" id="featureIconSwatches">
                                <!-- common icon buttons injected by vessels.js -->
                            </div>
                        </div>
                        <div class="col-12 col-sm-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-sh-primary w-100" id="featureFormSubmit">
                                <span class="submit-label" id="featureFormSubmitLabel">Add</span>
                                <span class="spinner-border spinner-border-sm ms-1 d-none"
                                    id="featureFormSpinner"></span>
                            </button>
                        </div>
                    </div>
                    <div class="mt-2" id="featureFormCancelWrap" style="display:none;">
                        <button type="button" class="btn btn-sh-outline btn-sm" id="featureFormCancelEdit">
                            <i class="bi bi-x-lg me-1"></i>Cancel edit
                        </button>
                    </div>
                </form>

                <hr>

                <div id="featureListWrap">
                    <div class="text-center text-muted py-3">Loading…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     Delete Feature Confirmation Modal
     ===================================================================== -->
<div class="modal fade" id="featureDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Feature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteFeatureName"></strong>? It will be
                    removed from every vessel it's currently assigned to. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmFeatureDeleteBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="featureDeleteSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>