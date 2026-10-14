/**
 * modules/users/assets/users.js
 * ---------------------------------------------------------------------
 * Powers the User Management page. Mirrors the structure and AJAX
 * conventions established in modules/vessels/assets/vessels.js, plus
 * Change Password and Activate/Deactivate actions specific to this
 * module, and toast notifications (ShToast, from assets/js/admin.js)
 * instead of alert().
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#userForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var avatarPicker;

  // Field-level validation schemas (ShValidator — assets/js/form-validator.js).
  // Password fields only apply in Add mode — Edit mode uses userBaseSchema
  // alone (see submitUserForm()), since password changes live in their own
  // modal. Live blur-validation is safely attached with the full Add
  // schema regardless of mode: the password fields are `.d-none` during
  // Edit, and hidden fields can never receive focus/blur to begin with.
  var userBaseSchema = {
    full_name: { required: true, maxLength: 100, label: "Full Name" },
    email: { required: true, type: "email", label: "Email Address" },
    username: {
      required: true,
      minLength: 3,
      maxLength: 50,
      pattern: /^[a-zA-Z0-9_.]+$/,
      label: "Username",
      messages: {
        pattern:
          "Username can only contain letters, numbers, underscores, and periods.",
      },
    },
  };
  var userAddSchema = Object.assign({}, userBaseSchema, {
    password: { required: true, type: "password", label: "Password" },
    confirm_password: {
      required: true,
      match: "password",
      label: "Confirm Password",
    },
  });
  var changePasswordSchema = {
    new_password: { required: true, type: "password", label: "New Password" },
    confirm_password: {
      required: true,
      match: "new_password",
      label: "Confirm Password",
    },
  };

  // Live "as you type" checklist + strength bar — separate from
  // ShValidator's blur/submit validation above, which only decides
  // pass/fail. Reset() is called whenever a form resets so the checklist
  // doesn't show a stale "all met" state the next time the modal opens.
  var userPasswordMeter;
  var changePasswordMeter;

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    avatarPicker = initAvatarPicker();
    bindFormEvents();
    bindChangePasswordEvents();
    bindDeleteEvents();

    var userFormEl = document.getElementById("userForm");
    ShValidator.attachLiveValidation(userFormEl, userAddSchema);
    userPasswordMeter = ShValidator.attachPasswordStrengthMeter(
      document.getElementById("userPassword"),
      document.getElementById("userPasswordRequirements"),
    );
    ShModalGuard.bind(document.getElementById("userFormModal"), userFormEl, {
      isDirtyExtra: function () {
        return !!avatarPicker.getFile() || avatarPicker.isRemoved();
      },
    });

    var changePasswordFormEl = document.getElementById("changePasswordForm");
    ShValidator.attachLiveValidation(
      changePasswordFormEl,
      changePasswordSchema,
    );
    changePasswordMeter = ShValidator.attachPasswordStrengthMeter(
      document.getElementById("cpNewPassword"),
      document.getElementById("cpPasswordRequirements"),
    );
    ShModalGuard.bind(
      document.getElementById("changePasswordModal"),
      changePasswordFormEl,
    );

    ShModalGuard.bind(document.getElementById("userDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#usersTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/fetch.php",
        dataSrc: "data",
      },
      columns: [
        {
          data: null,
          orderable: false,
          render: function (row) {
            if (row.profile_image_url) {
              return (
                '<img src="' +
                escapeAttr(row.profile_image_url) +
                '" class="sh-avatar-thumb" alt="">'
              );
            }
            var initial = (row.full_name || row.username || "?")
              .trim()
              .charAt(0)
              .toUpperCase();
            return (
              '<div class="sh-avatar-fallback">' +
              escapeHtml(initial) +
              "</div>"
            );
          },
        },
        { data: "username", render: escapeHtml },
        { data: "full_name", render: escapeHtml },
        { data: "email", render: escapeHtml },
        {
          data: "contact_number",
          render: function (v) {
            return v ? escapeHtml(v) : '<span class="text-muted">—</span>';
          },
        },
        {
          data: "user_access",
          render: function (access) {
            var cls =
              access === "administrator"
                ? "sh-access-pill--administrator"
                : "sh-access-pill--user";
            var label = access === "administrator" ? "Administrator" : "User";
            return (
              '<span class="sh-access-pill ' + cls + '">' + label + "</span>"
            );
          },
        },
        {
          data: "status",
          render: function (status) {
            var cls =
              status === "active"
                ? "sh-status-pill--active"
                : "sh-status-pill--inactive";
            return (
              '<span class="sh-status-pill ' +
              cls +
              '">' +
              escapeHtml(status) +
              "</span>"
            );
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-end",
          render: function (row) {
            var toggleIcon =
              row.status === "active" ? "bi-toggle2-off" : "bi-toggle2-on";
            var toggleTitle =
              row.status === "active" ? "Deactivate" : "Activate";
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-edit-user" data-id="' +
              row.id +
              '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-change-password" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.full_name) +
              '" title="Change Password"><i class="bi bi-key"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-toggle-status" data-id="' +
              row.id +
              '" data-status="' +
              row.status +
              '" data-name="' +
              escapeAttr(row.full_name) +
              '" title="' +
              toggleTitle +
              '"><i class="bi ' +
              toggleIcon +
              '"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete-user" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.full_name) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      order: [[2, "asc"]],
      lengthMenu: [5, 10, 25, 50],
      pageLength: 10,
      responsive: true,
      language: {
        emptyTable:
          'No users yet. Click "Add User" to create the first account.',
      },
    });

    $("#usersTable tbody").on("click", ".btn-edit-user", function () {
      openEditModal($(this).data("id"));
    });
    $("#usersTable tbody").on("click", ".btn-change-password", function () {
      openChangePasswordModal($(this).data("id"), $(this).data("name"));
    });
    $("#usersTable tbody").on("click", ".btn-toggle-status", function () {
      toggleStatus(
        $(this).data("id"),
        $(this).data("status"),
        $(this).data("name"),
      );
    });
    $("#usersTable tbody").on("click", ".btn-delete-user", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });
  }

  /**
   * A dedicated single-photo picker for the Profile Photo field — distinct
   * from the Vessel module's multi-image ShDropzone (assets/js/admin.js),
   * because a user avatar is "one photo, replace on change" rather than a
   * growable gallery. Only ever shows ONE preview at a time: either the
   * account's existing photo, or a newly chosen/dropped file — never both,
   * which is what made it look like "multiple images" before.
   */
  function initAvatarPicker() {
    var zone = document.getElementById("userAvatarDropzone");
    var input = document.getElementById("userImageInput");
    var placeholder = document.getElementById("userAvatarPlaceholder");
    var imgEl = document.getElementById("userAvatarImg");
    var removeBtn = document.getElementById("userAvatarRemoveBtn");

    var currentFile = null; // newly chosen File, if any
    var hadExisting = false; // did this account have a saved photo when the modal opened?
    var removed = false; // did the user explicitly clear the photo?

    function showImage(url) {
      imgEl.src = url;
      imgEl.classList.remove("d-none");
      placeholder.classList.add("d-none");
      removeBtn.classList.remove("d-none");
    }

    function showPlaceholder() {
      imgEl.removeAttribute("src");
      imgEl.classList.add("d-none");
      placeholder.classList.remove("d-none");
      removeBtn.classList.add("d-none");
    }

    function setFile(file) {
      if (!file || file.type.indexOf("image/") !== 0) return;
      currentFile = file;
      removed = false;
      showImage(URL.createObjectURL(file));
    }

    zone.addEventListener("click", function () {
      input.click();
    });

    input.addEventListener("change", function () {
      // A <input type="file"> without the `multiple` attribute already only
      // ever yields one file, but guard explicitly regardless.
      if (input.files && input.files[0]) setFile(input.files[0]);
      input.value = "";
    });

    ["dragenter", "dragover"].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add("sh-dropzone--active");
      });
    });
    ["dragleave", "drop"].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove("sh-dropzone--active");
      });
    });
    zone.addEventListener("drop", function (e) {
      // Only ever take the FIRST dropped file — this is a single profile
      // photo, not a gallery, even if the user drags several at once.
      if (
        e.dataTransfer &&
        e.dataTransfer.files &&
        e.dataTransfer.files.length
      ) {
        setFile(e.dataTransfer.files[0]);
      }
    });

    removeBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      currentFile = null;
      removed = hadExisting;
      showPlaceholder();
    });

    return {
      getFile: function () {
        return currentFile;
      },
      isRemoved: function () {
        return removed;
      },
      /** Call when opening for Add, or after a save, to fully reset. */
      reset: function () {
        currentFile = null;
        hadExisting = false;
        removed = false;
        showPlaceholder();
      },
      /** Call when opening for Edit, with the account's current photo URL (or null). */
      setExisting: function (url) {
        currentFile = null;
        removed = false;
        hadExisting = !!url;
        if (url) {
          showImage(url);
        } else {
          showPlaceholder();
        }
      },
    };
  }

  // -----------------------------------------------------------------
  // Add / Edit User
  // -----------------------------------------------------------------
  function bindFormEvents() {
    document
      .getElementById("btnAddUser")
      .addEventListener("click", resetFormForAdd);
    document
      .getElementById("userFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document
      .getElementById("userForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitUserForm();
      });
  }

  function resetFormForAdd() {
    document.getElementById("userFormTitle").textContent = "Add User";
    document.getElementById("userForm").reset();
    document.getElementById("userId").value = "";
    document.getElementById("userFormAlert").classList.add("d-none");
    document.getElementById("passwordFields").classList.remove("d-none");
    document.getElementById("userPassword").required = true;
    document.getElementById("userConfirmPassword").required = true;
    ShValidator.clearAllErrors(document.getElementById("userForm"));
    avatarPicker.reset();
    if (userPasswordMeter) userPasswordMeter.reset();
  }

  function switchToEditMode() {
    document.getElementById("userFormTitle").textContent = "Edit User";
    // Password is changed via its own modal, not here.
    document.getElementById("passwordFields").classList.add("d-none");
    document.getElementById("userPassword").required = false;
    document.getElementById("userConfirmPassword").required = false;
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var u = res.data;
        switchToEditMode();
        document.getElementById("userId").value = u.id;
        document.getElementById("userFullName").value = u.full_name || "";
        document.getElementById("userContactNumber").value =
          u.contact_number || "";
        document.getElementById("userEmail").value = u.email || "";
        document.getElementById("userUsername").value = u.username || "";
        document.getElementById("userAccess").value = u.user_access || "user";
        document.getElementById("userStatus").value = u.status || "active";

        avatarPicker.setExisting(u.profile_image_url || null);
        ShValidator.clearAllErrors(document.getElementById("userForm"));

        new bootstrap.Modal(document.getElementById("userFormModal")).show();
      })
      .catch(function (err) {
        ShToast.error(err.message || "Could not load user.");
      });
  }

  function submitUserForm() {
    var form = document.getElementById("userForm");
    var id = document.getElementById("userId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    // Edit mode never validates the (hidden) password fields — those live
    // in the Change Password modal instead.
    var schema = isEdit ? userBaseSchema : userAddSchema;
    var validation = ShValidator.validateForm(form, schema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);
    var newFile = avatarPicker.getFile();
    if (newFile) {
      formData.append("profile_image", newFile);
    } else if (isEdit && avatarPicker.isRemoved()) {
      formData.append("remove_profile_image", "1");
    }

    var submitBtn = document.getElementById("userFormSubmit");
    var alertBox = document.getElementById("userFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "userFormSpinner", true);

    fetch(url, {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (res.ok && res.data.success) {
          bootstrap.Modal.getInstance(
            document.getElementById("userFormModal"),
          ).hide();
          dataTable.ajax.reload(null, false);
          ShToast.success(
            res.data.message ||
              (isEdit
                ? "User updated successfully."
                : "User added successfully."),
          );
          return;
        }
        if (res.data.errors) {
          // Field-level errors go under their inputs; anything that
          // doesn't map to a field (e.g. the last-administrator rule)
          // falls back to the top alert box.
          var unmapped = ShValidator.mapBackendErrors(form, res.data.errors);
          if (unmapped.length) {
            alertBox.textContent = unmapped.join(" ");
            alertBox.classList.remove("d-none");
          }
          return;
        }
        throw new Error(res.data.message || "Save failed.");
      })
      .catch(function (err) {
        alertBox.textContent = err.message;
        alertBox.classList.remove("d-none");
      })
      .finally(function () {
        toggleSpinner(submitBtn, "userFormSpinner", false);
      });
  }

  // -----------------------------------------------------------------
  // Change Password
  // -----------------------------------------------------------------
  function bindChangePasswordEvents() {
    document
      .getElementById("changePasswordForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitChangePassword();
      });
    document
      .getElementById("changePasswordModal")
      .addEventListener("hidden.bs.modal", function () {
        document.getElementById("changePasswordForm").reset();
        document.getElementById("changePasswordAlert").classList.add("d-none");
        ShValidator.clearAllErrors(
          document.getElementById("changePasswordForm"),
        );
        if (changePasswordMeter) changePasswordMeter.reset();
      });
  }

  function openChangePasswordModal(id, name) {
    document.getElementById("cpUserId").value = id;
    document.getElementById("cpUserName").textContent = name;
    new bootstrap.Modal(document.getElementById("changePasswordModal")).show();
  }

  function submitChangePassword() {
    var form = document.getElementById("changePasswordForm");
    var validation = ShValidator.validateForm(form, changePasswordSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var submitBtn = document.getElementById("changePasswordSubmit");
    var alertBox = document.getElementById("changePasswordAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "changePasswordSpinner", true);

    var formData = new FormData(form);

    fetch(AJAX_BASE + "/change_password.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (res.ok && res.data.success) {
          bootstrap.Modal.getInstance(
            document.getElementById("changePasswordModal"),
          ).hide();
          ShToast.success(res.data.message || "Password changed successfully.");
          return;
        }
        if (res.data.errors) {
          var unmapped = ShValidator.mapBackendErrors(form, res.data.errors);
          if (unmapped.length) {
            alertBox.textContent = unmapped.join(" ");
            alertBox.classList.remove("d-none");
          }
          return;
        }
        throw new Error(res.data.message || "Password change failed.");
      })
      .catch(function (err) {
        alertBox.textContent = err.message;
        alertBox.classList.remove("d-none");
      })
      .finally(function () {
        toggleSpinner(submitBtn, "changePasswordSpinner", false);
      });
  }

  // -----------------------------------------------------------------
  // Activate / Deactivate
  // -----------------------------------------------------------------
  function toggleStatus(id, currentStatus, name) {
    var nextStatus = currentStatus === "active" ? "inactive" : "active";
    var verb = nextStatus === "active" ? "activate" : "deactivate";

    if (
      !window.confirm("Are you sure you want to " + verb + " " + name + "?")
    ) {
      return;
    }

    fetch(AJAX_BASE + "/activate.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        id: id,
        status: nextStatus,
        csrf_token: csrfToken,
      }),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data.success)
          throw new Error(res.data.message || "Could not update status.");
        dataTable.ajax.reload(null, false);
        ShToast.success(res.data.message);
      })
      .catch(function (err) {
        ShToast.error(err.message);
      });
  }

  // -----------------------------------------------------------------
  // Delete
  // -----------------------------------------------------------------
  function bindDeleteEvents() {
    document
      .getElementById("confirmUserDeleteBtn")
      .addEventListener("click", confirmDelete);
  }

  function openDeleteModal(id, name) {
    document.getElementById("deleteUserName").textContent = name;
    document.getElementById("confirmUserDeleteBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("userDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmUserDeleteBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "userDeleteSpinner", true);

    fetch(AJAX_BASE + "/delete.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ id: id, csrf_token: csrfToken }),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data.success)
          throw new Error(res.data.message || "Delete failed.");
        bootstrap.Modal.getInstance(
          document.getElementById("userDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
        ShToast.success(res.data.message || "User deleted successfully.");
      })
      .catch(function (err) {
        ShToast.error(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "userDeleteSpinner", false);
      });
  }

  // -----------------------------------------------------------------
  // Shared helpers
  // -----------------------------------------------------------------
  function toggleSpinner(btn, spinnerId, loading) {
    btn.disabled = loading;
    document.getElementById(spinnerId).classList.toggle("d-none", !loading);
  }

  function fetchJson(url) {
    return fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok || !data.success)
          throw new Error(data.message || "Request failed.");
        return data;
      });
    });
  }

  function escapeHtml(str) {
    return String(str ?? "").replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }

  function escapeAttr(str) {
    return escapeHtml(str);
  }
})(jQuery);
