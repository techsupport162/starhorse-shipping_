<?php
/**
 * modules/contact/index.php
 * -----------------------------------------------------------------------
 * Contact Management screen. Two sections on one page:
 *   1. Head Office & Map — a settings-style form for the singleton
 *      contact_settings row, plus repeatable phone/email fields, saved
 *      together in one request (ajax/settings_update.php). Mirrors the
 *      route_banner singleton pattern from modules/routes.
 *   2. Ports — a DataTable-driven CRUD list with drag-to-reorder,
 *      identical in shape to modules/faqs/index.php.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../functions/helpers.php';
require_once __DIR__ . '/../../functions/contact_functions.php';

$pageTitle = 'Contact Management';
$activeNav = 'contact';
$extraScripts = [admin_base_url() . '/modules/contact/assets/contact.js'];

$settings = get_contact_settings() ?? [
    'office_name'   => 'Head Office',
    'address'       => '',
    'tel_number'    => '',
    'facebook_name' => '',
    'facebook_url'  => '',
    'map_embed_url' => '',
    'map_status'    => 'published',
    'status'        => 'published',
];
$phones = get_contact_phones();
$emails = get_contact_emails();

require __DIR__ . '/../../includes/header.php';
?>

<!-- =========================================================================
     Section 1 — Head Office & Map
     ========================================================================= -->
<div class="sh-card mb-4">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Head Office &amp; Map</h2>
        <span class="sh-status-pill <?= $settings['status'] === 'published' ? 'sh-status-pill--published' : 'sh-status-pill--draft' ?>"
            id="officeStatusPillPreview"><?= htmlspecialchars($settings['status'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-4">
            <i class="bi bi-info-circle me-1"></i>
            This information powers the "Head Office" card and Google Map on the public Contact section. Set
            status to Draft to hide it from the public site without deleting your data.
        </p>

        <form id="contactSettingsForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <div id="settingsFormAlert" class="alert alert-danger d-none"></div>

            <div class="row g-4">
                <!-- Left column: core details -->
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Office Name *</label>
                            <input type="text" class="form-control" name="office_name" id="officeName"
                                maxlength="150" value="<?= htmlspecialchars($settings['office_name'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="e.g. Head Office" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Telephone (Landline)</label>
                            <input type="text" class="form-control" name="tel_number" id="telNumber" maxlength="50"
                                value="<?= htmlspecialchars($settings['tel_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="e.g. +63 (42) 710-7403">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address *</label>
                            <textarea class="form-control" name="address" id="officeAddress" rows="2" maxlength="255"
                                placeholder="Street, Subdivision, Barangay, City, Province"
                                required><?= htmlspecialchars($settings['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text">Used both for display and as the default Google Maps search
                                query.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Facebook Page Name</label>
                            <input type="text" class="form-control" name="facebook_name" id="facebookName"
                                maxlength="150"
                                value="<?= htmlspecialchars($settings['facebook_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="e.g. starhorseshippinglines">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Facebook URL</label>
                            <input type="url" class="form-control" name="facebook_url" id="facebookUrl"
                                maxlength="255"
                                value="<?= htmlspecialchars($settings['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="https://www.facebook.com/yourpage">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Head Office Visibility</label>
                            <select class="form-select" name="status" id="officeStatus">
                                <option value="published" <?= $settings['status'] === 'published' ? 'selected' : '' ?>>
                                    Published (visible on site)</option>
                                <option value="draft" <?= $settings['status'] === 'draft' ? 'selected' : '' ?>>Draft
                                    (hidden)</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Phones repeater -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-semibold mb-0">Mobile / Phone Numbers</label>
                        <button type="button" class="btn btn-sh-outline btn-sm" id="btnAddPhoneRow">
                            <i class="bi bi-plus-lg me-1"></i> Add Number
                        </button>
                    </div>
                    <div id="phonesRepeater" class="sh-repeater"></div>

                    <hr class="my-4">

                    <!-- Emails repeater -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-semibold mb-0">Email Addresses</label>
                        <button type="button" class="btn btn-sh-outline btn-sm" id="btnAddEmailRow">
                            <i class="bi bi-plus-lg me-1"></i> Add Email
                        </button>
                    </div>
                    <div id="emailsRepeater" class="sh-repeater"></div>
                </div>

                <!-- Right column: map -->
                <div class="col-lg-5">
                    <label class="form-label small fw-semibold">Google Maps Embed URL</label>
                    <textarea class="form-control" name="map_embed_url" id="mapEmbedUrl" rows="3"
                        placeholder="https://www.google.com/maps?q=...&output=embed"><?= htmlspecialchars($settings['map_embed_url'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="form-text mb-3">
                        In Google Maps: search the address → Share → Embed a map → copy the <code>src</code> URL
                        from the iframe code, or use the "Search" link URL. No API key needed.
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label small fw-semibold">Map Visibility</label>
                        <select class="form-select" name="map_status" id="mapStatus">
                            <option value="published" <?= $settings['map_status'] === 'published' ? 'selected' : '' ?>>
                                Published (visible on site)</option>
                            <option value="draft" <?= $settings['map_status'] === 'draft' ? 'selected' : '' ?>>Draft
                                (hidden)</option>
                        </select>
                    </div>

                    <label class="form-label small fw-semibold">Live Preview</label>
                    <div class="sh-map-preview" id="mapPreviewWrap">
                        <?php if (!empty($settings['map_embed_url'])): ?>
                        <iframe id="mapPreviewFrame" src="<?= htmlspecialchars($settings['map_embed_url'], ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <?php else: ?>
                        <div class="sh-map-preview__empty" id="mapPreviewEmpty">
                            <i class="bi bi-map"></i>
                            <span>No map URL set yet</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-sh-primary" id="settingsFormSubmit">
                    <span class="submit-label"><i class="bi bi-check-lg me-1"></i> Save Changes</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="settingsFormSpinner"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     Section 2 — Ports
     ========================================================================= -->
<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Ports</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#portFormModal"
            id="btnAddPort">
            <i class="bi bi-plus-lg me-1"></i> Add Port
        </button>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-3">
            <i class="bi bi-grip-vertical me-1"></i>
            Drag rows by the handle to set the order ports appear in on the public site.
        </p>
        <table id="portsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th class="sh-drag-th"><span class="visually-hidden">Reorder</span></th>
                    <th>#</th>
                    <th>Port Name</th>
                    <th>Contact Number</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- populated by DataTables via ajax/ports_list.php -->
            </tbody>
        </table>
    </div>
</div>

<!-- =====================================================================
     Add / Edit Port Modal
     ===================================================================== -->
<div class="modal fade" id="portFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form id="portForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="portFormTitle">Add Port</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="portFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="portId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Port Name *</label>
                        <input type="text" class="form-control" name="port_name" id="portName" maxlength="150"
                            placeholder="e.g. Lucena City / Dalahican Port" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contact Number *</label>
                        <input type="text" class="form-control" name="phone_number" id="portPhone" maxlength="30"
                            placeholder="e.g. +63 995 515 9265" required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="portStatus">
                            <option value="published">Published (visible on site)</option>
                            <option value="draft">Draft (hidden)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="portFormSubmit">
                        <span class="submit-label">Save Port</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="portFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     Delete Confirmation Modal
     ===================================================================== -->
<div class="modal fade" id="portDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Port</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deletePortName"></strong>? This action
                    cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeletePortBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deletePortSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Seed data for contact.js (avoids a round-trip AJAX call just to populate the repeaters on first paint) -->
<script>
window.__contactPhonesSeed = <?= json_encode($phones, JSON_UNESCAPED_UNICODE) ?>;
window.__contactEmailsSeed = <?= json_encode($emails, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
