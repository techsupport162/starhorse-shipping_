    </main>
    </div>
    </div>

    <!-- =====================================================================
     Shared "Discard Changes?" confirmation modal — one instance, reused
     by every CRUD module through ShModalGuard.bind() (assets/js/modal-guard.js).
     Individual modules never need to define their own copy of this.
     ===================================================================== -->
    <div class="modal fade" id="shDiscardChangesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Discard Changes?
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">You have unsaved changes. Are you sure you want to close this form?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="shDiscardChangesConfirmBtn">Discard
                        Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (required by DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 bundle (modals, dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Admin shell behavior -->
    <script src="<?= $base ?>/assets/js/admin.js"></script>
    <!-- Shared CRUD foundation: inline field validation + unsaved-changes modal guard -->
    <script src="<?= $base ?>/assets/js/form-validator.js"></script>
    <script src="<?= $base ?>/assets/js/modal-guard.js"></script>

    <?php if (!empty($extraScripts)) : foreach ($extraScripts as $src): ?>
    <script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; endif; ?>

    </body>

    </html>