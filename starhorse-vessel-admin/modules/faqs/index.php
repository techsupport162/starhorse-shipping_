<?php
/**
 * modules/faqs/index.php
 * -----------------------------------------------------------------------
 * FAQ list screen. The table itself is populated client-side by
 * DataTables from ajax/list.php. Add / Edit / View / Delete all happen
 * inside Bootstrap modals via AJAX — this page never redirects.
 * Mirrors modules/fares/index.php (no image uploads needed here).
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../functions/helpers.php';

$pageTitle = 'FAQ Management';
$activeNav = 'faqs';
$extraScripts = [admin_base_url() . '/modules/faqs/assets/faqs.js'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">Frequently Asked Questions</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#faqFormModal"
            id="btnAddFaq">
            <i class="bi bi-plus-lg me-1"></i> Add FAQ
        </button>
    </div>
    <div class="sh-card__body">
        <p class="text-muted small mb-3">
            <i class="bi bi-grip-vertical me-1"></i>
            Drag rows by the handle to set the order FAQs appear in on the public site — no need to add or edit
            them one by one.
        </p>
        <table id="faqsTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th class="sh-drag-th"><span class="visually-hidden">Reorder</span></th>
                    <th>#</th>
                    <th>Question</th>
                    <th>Answer Preview</th>
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
     Add / Edit FAQ Modal
     Same modal is reused for both — JS toggles title/mode and pre-fills
     fields when editing. Submits via fetch() as multipart/form-data to
     ajax/create.php or ajax/update.php.
     ===================================================================== -->
<div class="modal fade" id="faqFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="faqForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="faqFormTitle">Add FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="faqFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="faqId" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Question *</label>
                        <textarea class="form-control" name="question" id="faqQuestion" rows="2" maxlength="255"
                            placeholder="e.g. What are your sailing schedules?" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Answer *</label>
                        <textarea class="form-control" name="answer" id="faqAnswer" rows="5" maxlength="5000"
                            placeholder="e.g. Our vessels sail daily from Lucena to Balanacan..." required></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status" id="faqStatus">
                            <option value="published">Published (visible on site)</option>
                            <option value="draft">Draft (hidden)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="faqFormSubmit">
                        <span class="submit-label">Save FAQ</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="faqFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     View FAQ Modal (read-only)
     ===================================================================== -->
<div class="modal fade" id="faqViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">FAQ Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="faqViewBody">
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
<div class="modal fade" id="faqDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete FAQ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteFaqQuestion"></strong>? This
                    action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteFaqBtn">
                    <span class="submit-label">Delete</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="deleteFaqSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>