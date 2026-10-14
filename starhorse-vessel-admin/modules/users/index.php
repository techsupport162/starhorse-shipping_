<?php
/**
 * modules/users/index.php
 * -----------------------------------------------------------------------
 * User Management screen. Administrator-only — require_admin() enforces
 * this server-side regardless of what the sidebar shows. Follows the
 * same pattern as modules/vessels/index.php: DataTable list populated
 * via AJAX, all CRUD happens in Bootstrap modals, no page redirects.
 * -----------------------------------------------------------------------
 */
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../functions/helpers.php';

$pageTitle = 'User Management';
$activeNav = 'users';
$extraScripts = [admin_base_url() . '/modules/users/assets/users.js'];

require __DIR__ . '/../../includes/header.php';
?>

<nav class="sh-breadcrumb">
    <a href="<?= admin_base_url() ?>/dashboard.php">Dashboard</a>
    <i class="bi bi-chevron-right small"></i>
    <span class="sh-breadcrumb-current">User Management</span>
</nav>

<div class="sh-card">
    <div class="sh-card__header">
        <h2 class="sh-card__title">System Users</h2>
        <button type="button" class="btn btn-sh-primary" data-bs-toggle="modal" data-bs-target="#userFormModal"
            id="btnAddUser">
            <i class="bi bi-plus-lg me-1"></i> Add User
        </button>
    </div>
    <div class="sh-card__body">
        <table id="usersTable" class="table sh-table align-middle w-100">
            <thead>
                <tr>
                    <th>Profile</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Contact Number</th>
                    <th>User Access</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- populated by DataTables via ajax/fetch.php -->
            </tbody>
        </table>
    </div>
</div>

<!-- =====================================================================
     Add / Edit User Modal
     Add mode shows Username/Password/Confirm; Edit mode hides the
     password fields entirely (password changes happen in their own
     dedicated modal, per spec).
     ===================================================================== -->
<div class="modal fade" id="userFormModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="userForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="userFormTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userFormAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="userId" value="">

                    <div class="text-center mb-3">
                        <label class="form-label small fw-semibold d-block mb-2">Profile Photo</label>
                        <div class="sh-avatar-dropzone" id="userAvatarDropzone">
                            <div class="sh-avatar-dropzone__placeholder" id="userAvatarPlaceholder">
                                <i class="bi bi-camera"></i>
                                <span>Click or drag<br>a photo</span>
                            </div>
                            <img class="sh-avatar-dropzone__img d-none" id="userAvatarImg" alt="">
                            <button type="button" class="sh-avatar-dropzone__remove d-none" id="userAvatarRemoveBtn"
                                title="Remove photo">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <input type="file" id="userImageInput" accept="image/jpeg,image/png,image/webp" hidden>
                        <div class="text-muted small mt-2">JPG, PNG, or WEBP — auto-cropped to a square photo</div>
                    </div>

                    <h6 class="text-uppercase small fw-bold text-muted mb-2">Personal Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" id="userFullName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="userContactNumber"
                                placeholder="e.g. 0917 123 4567">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Email Address *</label>
                            <input type="email" class="form-control" name="email" id="userEmail" required>
                        </div>
                    </div>

                    <h6 class="text-uppercase small fw-bold text-muted mb-2">Account Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username *</label>
                            <input type="text" class="form-control" name="username" id="userUsername" autocomplete="off"
                                required>
                        </div>
                    </div>

                    <div id="passwordFields" class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Password *</label>
                            <input type="password" class="form-control" name="password" id="userPassword"
                                autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" id="userConfirmPassword"
                                autocomplete="new-password">
                        </div>
                    </div>

                    <h6 class="text-uppercase small fw-bold text-muted mb-2">System Settings</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">User Access</label>
                            <select class="form-select" name="user_access" id="userAccess">
                                <option value="user">User (limited access)</option>
                                <option value="administrator">Administrator (full access)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select class="form-select" name="status" id="userStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="userFormSubmit">
                        <span class="submit-label">Save User</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="userFormSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     Change Password Modal (separate from Edit, per spec)
     ===================================================================== -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="changePasswordForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="changePasswordAlert" class="alert alert-danger d-none"></div>
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="cpUserId" value="">
                    <p class="text-muted small">Changing password for <strong id="cpUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="cpNewPassword"
                            autocomplete="new-password" required>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Confirm Password *</label>
                        <input type="password" class="form-control" name="confirm_password" id="cpConfirmPassword"
                            autocomplete="new-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sh-primary" id="changePasswordSubmit">
                        <span class="submit-label">Update Password</span>
                        <span class="spinner-border spinner-border-sm ms-1 d-none" id="changePasswordSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     Delete Confirmation Modal
     ===================================================================== -->
<div class="modal fade" id="userDeleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to permanently delete <strong id="deleteUserName"></strong>? This
                    action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sh-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmUserDeleteBtn">
                    <span class="submit-label">Delete User</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="userDeleteSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>