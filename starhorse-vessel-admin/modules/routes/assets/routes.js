/**
 * modules/routes/assets/routes.js
 * ---------------------------------------------------------------------
 * Powers the Routes Gallery Management screen: two independent pieces
 * on one page.
 *
 *   1. Destination gallery — DataTables list, and Add / Edit / View /
 *      Delete inside Bootstrap modals via fetch(), no page reloads.
 *      Mirrors modules/fares/assets/fares.js, adapted for a single
 *      image field (destination_image) instead of two.
 *
 *   2. Routes Hero Management — the singleton banner image. No modal,
 *      no DataTable — just a dropzone + Save/Remove wired directly to
 *      ajax/banner_update.php and ajax/banner_delete.php, with
 *      ShToast (assets/js/admin.js) for feedback since there's no modal
 *      alert box to show it in.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#destinationForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var destinationDropzone;
  var removeDestinationImage = false;

  // Client-side mirror of validate_image_upload() (functions/helpers.php).
  // The dropzone previously accepted any image/* file and any size, so
  // an oversized or unsupported file (e.g. image/bmp, image/tiff, or a
  // 20MB photo) would sit in the preview looking fine and only fail
  // after a full upload round-trip to the server. Catching it up front
  // gives immediate feedback instead.
  //
  // Destinations and the banner now allow different formats/sizes (see
  // Phase 1 analysis — the banner needed a larger limit, destinations
  // did not), so each gets its own validator rather than one shared one.
  function makeImageValidator(allowedTypes, maxBytes, formatLabel) {
    var maxMb = Math.round((maxBytes / (1024 * 1024)) * 10) / 10;
    return function (file) {
      if (!file) return { ok: true };

      var typeOk = allowedTypes.indexOf(file.type) !== -1;
      if (!typeOk) {
        // Some browsers/OSes report an empty or generic MIME type for
        // SVG when it's chosen via drag-and-drop; fall back to the file
        // extension in that one case rather than rejecting a valid SVG.
        var isSvgByExtension = /\.svg$/i.test(file.name || "");
        var svgAllowed = allowedTypes.indexOf("image/svg+xml") !== -1;
        typeOk = isSvgByExtension && svgAllowed;
      }
      if (!typeOk) {
        return {
          ok: false,
          error: "Please upload a " + formatLabel + " image.",
        };
      }
      if (file.size > maxBytes) {
        return {
          ok: false,
          error: "Image is too large. Maximum file size is " + maxMb + "MB.",
        };
      }
      return { ok: true };
    };
  }

  var validateDestinationImageFile = makeImageValidator(
    ["image/jpeg", "image/png", "image/webp", "image/gif", "image/svg+xml"],
    5 * 1024 * 1024,
    "JPG, PNG, WEBP, GIF, or SVG",
  );

  var validateBannerImageFile = makeImageValidator(
    ["image/jpeg", "image/png", "image/webp", "image/svg+xml"],
    10 * 1024 * 1024,
    "JPG, PNG, WEBP, or SVG",
  );

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Duplicate-location detection is a
  // business rule checked server-side (json_field_errors), not
  // expressible in this generic schema.
  var destinationSchema = {
    location_name: { required: true, maxLength: 150, label: "Port Name" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    initDestinationDropzone();
    bindDestinationFormEvents();

    var formEl = document.getElementById("destinationForm");
    ShValidator.attachLiveValidation(formEl, destinationSchema);

    ShModalGuard.bind(document.getElementById("destinationFormModal"), formEl, {
      isDirtyExtra: function () {
        return (
          destinationDropzone.getFiles().length > 0 || removeDestinationImage
        );
      },
    });
    ShModalGuard.bind(document.getElementById("destinationViewModal"), null);
    ShModalGuard.bind(document.getElementById("destinationDeleteModal"), null);

    initBanner();
    initBannerPreviewModal();
  });

  // -----------------------------------------------------------------------
  // Destination gallery — DataTables list
  // -----------------------------------------------------------------------
  function initTable() {
    dataTable = $("#destinationsTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      columns: [
        {
          data: "image_url",
          orderable: false,
          render: function (url) {
            return thumbOrPlaceholder(url);
          },
        },
        {
          data: "location_name",
          render: function (name) {
            return '<span class="fw-semibold">' + escapeHtml(name) + "</span>";
          },
        },
        {
          data: "status",
          render: function (status) {
            var cls =
              status === "published"
                ? "sh-status-pill--published"
                : "sh-status-pill--draft";
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
          data: "created_at",
          render: function (createdAt) {
            return escapeHtml(formatDate(createdAt));
          },
        },
        {
          data: null,
          orderable: false,
          className: "text-end",
          render: function (row) {
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-view" data-id="' +
              row.id +
              '" title="View"><i class="bi bi-eye"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-edit" data-id="' +
              row.id +
              '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.location_name) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      order: [[3, "desc"]],
      lengthMenu: [5, 10, 25, 50],
      pageLength: 10,
      responsive: true,
      language: {
        emptyTable:
          'No ports yet. Click "Add Port" to add your first one.',
      },
    });

    $("#destinationsTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#destinationsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#destinationsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });
  }

  function thumbOrPlaceholder(url) {
    // --contain: tourism logos must be shown whole, not cropped to a square
    return url
      ? '<img src="' + escapeAttr(url) + '" class="sh-thumb sh-thumb--contain" alt="">'
      : '<div class="sh-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>';
  }

  function initDestinationDropzone() {
    destinationDropzone = window.ShDropzone({
      dropzoneId: "destinationDropzone",
      inputId: "destinationImageInput",
      previewId: "destinationImagePreview",
      maxFiles: 1,
    });
  }

  function bindDestinationFormEvents() {
    document
      .getElementById("btnAddDestination")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("destinationFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document
      .getElementById("destinationForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitDestinationForm();
      });

    document
      .getElementById("confirmDeleteDestinationBtn")
      .addEventListener("click", confirmDelete);
  }

  function resetFormForAdd() {
    document.getElementById("destinationFormTitle").textContent =
      "Add Port";
    document.getElementById("destinationForm").reset();
    document.getElementById("destinationId").value = "";
    document.getElementById("existingDestinationImage").innerHTML = "";
    document.getElementById("destinationFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("destinationForm"));
    removeDestinationImage = false;
    destinationDropzone.reset();
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var d = res.data;
        document.getElementById("destinationFormTitle").textContent =
          "Edit Port";
        document.getElementById("destinationId").value = d.id;
        document.getElementById("destinationLocationName").value =
          d.location_name || "";
        document.getElementById("destinationStatus").value =
          d.status || "published";

        removeDestinationImage = false;
        destinationDropzone.reset();
        renderExistingImage(
          "existingDestinationImage",
          d.image_url,
          function () {
            removeDestinationImage = true;
          },
        );
        ShValidator.clearAllErrors(document.getElementById("destinationForm"));

        new bootstrap.Modal(
          document.getElementById("destinationFormModal"),
        ).show();
      })
      .catch(function (err) {
        alert(err.message || "Could not load port.");
      });
  }

  function renderExistingImage(containerId, url, onRemove) {
    var container = document.getElementById(containerId);
    container.innerHTML = "";
    if (!url) return;

    var item = document.createElement("div");
    item.className = "sh-preview-item";

    var image = document.createElement("img");
    image.src = url;
    item.appendChild(image);

    var removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "sh-preview-remove";
    removeBtn.title = "Remove image";
    removeBtn.innerHTML = '<i class="bi bi-x"></i>';
    removeBtn.addEventListener("click", function () {
      onRemove();
      item.remove();
    });
    item.appendChild(removeBtn);

    container.appendChild(item);
  }

  function openViewModal(id) {
    var body = document.getElementById("destinationViewBody");
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
    new bootstrap.Modal(document.getElementById("destinationViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var d = res.data;
        var cls =
          d.status === "published"
            ? "sh-status-pill--published"
            : "sh-status-pill--draft";
        body.innerHTML =
          '<h4 class="mb-2 text-center">' +
          escapeHtml(d.location_name) +
          "</h4>" +
          '<div class="text-center mb-3">' +
          '<span class="sh-status-pill ' +
          cls +
          '">' +
          escapeHtml(d.status) +
          "</span>" +
          "</div>" +
          '<div class="text-center">' +
          imageOrPlaceholder(d.image_url) +
          "</div>";
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load port.") +
          "</div>";
      });
  }

  function imageOrPlaceholder(url) {
    return url
      ? '<img src="' +
          escapeAttr(url) +
          '" class="img-fluid rounded" style="max-height:320px;object-fit:contain;">'
      : '<div class="text-muted py-5 border rounded"><i class="bi bi-image fs-2 d-block mb-2"></i>No logo uploaded</div>';
  }

  function openDeleteModal(id, locationName) {
    document.getElementById("deleteDestinationName").textContent = locationName;
    document.getElementById("confirmDeleteDestinationBtn").dataset.id = id;
    new bootstrap.Modal(
      document.getElementById("destinationDeleteModal"),
    ).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeleteDestinationBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deleteDestinationSpinner", true);

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
          document.getElementById("destinationDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "deleteDestinationSpinner", false);
      });
  }

  function submitDestinationForm() {
    var form = document.getElementById("destinationForm");
    var id = document.getElementById("destinationId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, destinationSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var alertBox = document.getElementById("destinationFormAlert");
    alertBox.classList.add("d-none");

    var newImageFile = destinationDropzone.getFiles()[0];
    if (newImageFile) {
      var imageCheck = validateDestinationImageFile(newImageFile);
      if (!imageCheck.ok) {
        // Shown directly under the image field (name="image"), matching
        // where the same message would land if the server rejected it
        // instead — not just the modal's generic top alert.
        ShValidator.showFieldError(form, "image", imageCheck.error);
        return;
      }
    }
    ShValidator.clearFieldError(form, "image");

    var formData = new FormData(form);
    if (newImageFile) {
      formData.append("image", newImageFile);
    }
    if (isEdit) {
      formData.append("remove_image", removeDestinationImage ? "1" : "0");
    }

    var submitBtn = document.getElementById("destinationFormSubmit");
    toggleSpinner(submitBtn, "destinationFormSpinner", true);

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
            document.getElementById("destinationFormModal"),
          ).hide();
          dataTable.ajax.reload(null, false);
          return;
        }
        if (res.data.errors) {
          // Field-level errors from json_field_errors() — show each under
          // its input; anything that doesn't map to a field (business
          // rules, duplicates, etc.) falls back to the top alert box.
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
        toggleSpinner(submitBtn, "destinationFormSpinner", false);
      });
  }

  // -----------------------------------------------------------------------
  // Routes Hero Management — singleton banner image
  // -----------------------------------------------------------------------
  var bannerDropzone;

  function initBanner() {
    bannerDropzone = window.ShDropzone({
      dropzoneId: "bannerDropzone",
      inputId: "bannerImageInput",
      previewId: "bannerImagePreview",
      maxFiles: 1,
    });

    // Selecting a new file clears any previous validation error so it
    // doesn't linger under the field after the problem's been fixed.
    document
      .getElementById("bannerImageInput")
      .addEventListener("change", clearBannerError);
    document
      .getElementById("bannerDropzone")
      .addEventListener("drop", clearBannerError);

    document
      .getElementById("btnSaveBanner")
      .addEventListener("click", saveBanner);
    document
      .getElementById("btnRemoveBanner")
      .addEventListener("click", removeBanner);
  }

  function initBannerPreviewModal() {
    var previewBtn = document.getElementById("bannerCurrentPreviewBtn");
    var modalEl = document.getElementById("bannerPreviewModal");
    if (!previewBtn || !modalEl) return;

    previewBtn.addEventListener("click", function () {
      var currentSrc = document.getElementById("bannerCurrentImg").src;
      document.getElementById("bannerPreviewModalImg").src = currentSrc;
      new bootstrap.Modal(modalEl).show();
    });
  }

  function bannerCsrfToken() {
    return document.getElementById("bannerCsrfToken").value;
  }

  function showBannerError(message) {
    var box = document.getElementById("bannerImageError");
    box.textContent = message;
    box.classList.remove("d-none");
  }

  function clearBannerError() {
    var box = document.getElementById("bannerImageError");
    box.textContent = "";
    box.classList.add("d-none");
  }

  function saveBanner() {
    clearBannerError();

    var file = bannerDropzone.getFiles()[0];
    if (!file) {
      showBannerError("Please choose an image to upload first.");
      return;
    }

    var imageCheck = validateBannerImageFile(file);
    if (!imageCheck.ok) {
      // Shown directly below the banner upload field, per the same rule
      // as the destination form — not just a toast in the corner.
      showBannerError(imageCheck.error);
      return;
    }

    var formData = new FormData();
    formData.append("banner_image", file);
    formData.append("csrf_token", bannerCsrfToken());

    var btn = document.getElementById("btnSaveBanner");
    toggleSpinner(btn, "bannerSaveSpinner", true);

    fetch(AJAX_BASE + "/banner_update.php", {
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
        if (!res.ok || !res.data.success) {
          // Field-level errors from json_field_errors() carry the
          // specific reason (format/size/malformed SVG); fall back to
          // the generic message only if the server didn't send one.
          var fieldMessage = res.data.errors && res.data.errors.banner_image;
          throw new Error(
            fieldMessage ||
              res.data.message ||
              "Could not save the banner image.",
          );
        }
        document.getElementById("bannerCurrentImg").src = res.data.image_url;
        var previewModalImg = document.getElementById("bannerPreviewModalImg");
        if (previewModalImg) previewModalImg.src = res.data.image_url;
        document.getElementById("bannerCurrentWrap").classList.remove("d-none");
        document.getElementById("bannerEmptyState").classList.add("d-none");
        bannerDropzone.reset();
        ShToast.success(
          res.data.message || "Banner image updated successfully.",
        );
      })
      .catch(function (err) {
        showBannerError(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "bannerSaveSpinner", false);
      });
  }

  function removeBanner() {
    if (
      !window.confirm("Remove the current banner image? This cannot be undone.")
    ) {
      return;
    }

    var btn = document.getElementById("btnRemoveBanner");
    toggleSpinner(btn, "bannerRemoveSpinner", true);

    fetch(AJAX_BASE + "/banner_delete.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ csrf_token: bannerCsrfToken() }),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data.success) {
          throw new Error(
            res.data.message || "Could not remove the banner image.",
          );
        }
        document.getElementById("bannerCurrentWrap").classList.add("d-none");
        document.getElementById("bannerEmptyState").classList.remove("d-none");
        clearBannerError();
        ShToast.success(res.data.message || "Banner image removed.");
      })
      .catch(function (err) {
        ShToast.error(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "bannerRemoveSpinner", false);
      });
  }

  // -----------------------------------------------------------------------
  // Shared helpers
  // -----------------------------------------------------------------------
  function formatDate(value) {
    if (!value) return "";
    // MySQL DATETIME strings ("YYYY-MM-DD HH:MM:SS") parse more reliably
    // across browsers as "YYYY-MM-DDTHH:MM:SS" than with the raw space.
    var d = new Date(value.replace(" ", "T"));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

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
    return String(str == null ? "" : str).replace(/[&<>"']/g, function (c) {
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
