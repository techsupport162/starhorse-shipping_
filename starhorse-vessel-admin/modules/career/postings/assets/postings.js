/**
 * modules/career/postings/assets/postings.js
 * ---------------------------------------------------------------------
 * Powers the Career Job Postings list page: DataTables list, Add / Edit
 * / View / Delete inside Bootstrap modals via fetch(), a single
 * recruitment-image dropzone (window.ShDropzone, same factory used by
 * Vessels/Fares), and a one-click Publish/Draft toggle per row. Mirrors
 * modules/fares/assets/fares.js, adapted from two images down to one and
 * with a Position dropdown instead of free-text fields.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#postingForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var postingDropzone;
  var removePostingImage = false;

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Position-in-use / disabled-position
  // handling is a business rule, not expressible here.
  var postingSchema = {
    title: { required: true, maxLength: 150, label: "Job Title" },
    position_id: { required: true, label: "Position" },
    contact_email: { type: "email", label: "Contact / Application Email" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    initDropzone();
    bindFormEvents();

    var formEl = document.getElementById("postingForm");
    if (!formEl) return; // Add form is omitted when there are no active positions yet.

    ShValidator.attachLiveValidation(formEl, postingSchema);

    ShModalGuard.bind(document.getElementById("postingFormModal"), formEl, {
      isDirtyExtra: function () {
        return postingDropzone.getFiles().length > 0 || removePostingImage;
      },
    });
    ShModalGuard.bind(document.getElementById("postingViewModal"), null);
    ShModalGuard.bind(document.getElementById("postingDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#postingsTable").DataTable({
      processing: true,
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      order: [[4, "desc"]],
      columns: [
        {
          data: "recruitment_image_url",
          orderable: false,
          responsivePriority: 3,
          render: function (url) {
            return thumbOrPlaceholder(url);
          },
        },
        {
          data: "title",
          responsivePriority: 1,
          render: function (title, type, row) {
            return '<span class="fw-semibold">' + escapeHtml(title) + "</span>";
          },
        },
        {
          data: "position_name",
          responsivePriority: 4,
          render: function (name, type, row) {
            var inactive = !row.position_is_active
              ? ' <span class="text-muted small">(disabled)</span>'
              : "";
            return escapeHtml(name) + inactive;
          },
        },
        {
          data: "status",
          responsivePriority: 2,
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
          data: "updated_at",
          responsivePriority: 5,
          render: function (updatedAt) {
            return escapeHtml(formatDate(updatedAt));
          },
        },
        {
          data: null,
          orderable: false,
          responsivePriority: 1,
          className: "text-end",
          render: function (row) {
            var toggleToPublished = row.status !== "published";
            var toggleIcon = toggleToPublished ? "bi-eye" : "bi-eye-slash";
            var toggleTitle = toggleToPublished
              ? "Publish (make visible on the public Career section)"
              : "Move to Draft (hide from the public site)";
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-view" data-id="' +
              row.id +
              '" title="View" aria-label="View ' +
              escapeAttr(row.title) +
              '"><i class="bi bi-eye-fill"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-edit" data-id="' +
              row.id +
              '" title="Edit" aria-label="Edit ' +
              escapeAttr(row.title) +
              '"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-toggle-status" data-id="' +
              row.id +
              '" data-target-status="' +
              (toggleToPublished ? "published" : "draft") +
              '" title="' +
              escapeAttr(toggleTitle) +
              '" aria-label="' +
              escapeAttr(toggleTitle) +
              '"><i class="bi ' +
              toggleIcon +
              '"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-title="' +
              escapeAttr(row.title) +
              '" title="Delete" aria-label="Delete ' +
              escapeAttr(row.title) +
              '"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      lengthMenu: [10, 25, 50],
      pageLength: 10,
      responsive: true,
      language: {
        emptyTable:
          'No job postings yet. Click "Add Job Posting" to add your first one.',
      },
    });

    $("#postingsTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#postingsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#postingsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("title"));
    });
    $("#postingsTable tbody").on("click", ".btn-toggle-status", function () {
      toggleStatus($(this).data("id"), $(this).data("target-status"), this);
    });
  }

  function thumbOrPlaceholder(url) {
    return url
      ? '<img src="' + escapeAttr(url) + '" class="sh-thumb" alt="">'
      : '<div class="sh-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>';
  }

  function initDropzone() {
    if (!document.getElementById("postingDropzone")) return;
    postingDropzone = window.ShDropzone({
      dropzoneId: "postingDropzone",
      inputId: "postingImageInput",
      previewId: "postingImagePreview",
      maxFiles: 1,
    });
  }

  function bindFormEvents() {
    var addBtn = document.getElementById("btnAddPosting");
    if (addBtn) addBtn.addEventListener("click", resetFormForAdd);

    var formModal = document.getElementById("postingFormModal");
    if (formModal) {
      formModal.addEventListener("hidden.bs.modal", resetFormForAdd);
    }

    var form = document.getElementById("postingForm");
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        submitPostingForm();
      });
    }

    document
      .getElementById("confirmDeletePostingBtn")
      .addEventListener("click", confirmDelete);
  }

  function resetFormForAdd() {
    document.getElementById("postingFormTitle").textContent = "Add Job Posting";
    document.getElementById("postingForm").reset();
    document.getElementById("postingId").value = "";
    document.getElementById("postingStatus").value = "draft";
    document.getElementById("existingPostingImage").innerHTML = "";
    document.getElementById("postingFormAlert").classList.add("d-none");
    document
      .getElementById("postingPositionInactiveHint")
      .setAttribute("hidden", "hidden");
    removeExtraInactivePositionOption();
    ShValidator.clearAllErrors(document.getElementById("postingForm"));
    removePostingImage = false;
    if (postingDropzone) postingDropzone.reset();
  }

  /**
   * If the posting being edited uses a position that has since been
   * disabled in Position Settings, that position won't be among the
   * <select>'s normal (active-only) options. Rather than leaving the
   * dropdown showing nothing selected — which would silently reassign
   * the posting to a different position on save — inject a labeled
   * extra option for it and keep it selected, and show an explanatory
   * hint. This is purely a display/selection convenience; the backend
   * independently accepts the existing position_id on update even
   * though it's excluded from get_active_positions().
   */
  function ensurePositionOptionPresent(positionId, positionName, isActive) {
    var select = document.getElementById("postingPosition");
    removeExtraInactivePositionOption();

    var exists = Array.prototype.some.call(select.options, function (opt) {
      return opt.value === String(positionId);
    });

    if (!exists) {
      var option = document.createElement("option");
      option.value = String(positionId);
      option.textContent = positionName + " (disabled)";
      option.dataset.injected = "1";
      select.appendChild(option);
    }

    document
      .getElementById("postingPositionInactiveHint")
      .toggleAttribute("hidden", !!isActive);
  }

  function removeExtraInactivePositionOption() {
    var select = document.getElementById("postingPosition");
    if (!select) return;
    Array.prototype.slice
      .call(select.querySelectorAll('option[data-injected="1"]'))
      .forEach(function (opt) {
        opt.remove();
      });
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var p = res.data;
        document.getElementById("postingFormTitle").textContent =
          "Edit Job Posting";
        document.getElementById("postingId").value = p.id;
        document.getElementById("postingTitle").value = p.title || "";
        document.getElementById("postingShortDesc").value =
          p.short_description || "";
        document.getElementById("postingJobDescription").value =
          p.job_description || "";
        document.getElementById("postingQualifications").value =
          p.qualifications || "";
        document.getElementById("postingResponsibilities").value =
          p.responsibilities || "";
        document.getElementById("postingApplicationInstructions").value =
          p.application_instructions || "";
        document.getElementById("postingContactEmail").value =
          p.contact_email || "";
        document.getElementById("postingStatus").value = p.status || "draft";

        ensurePositionOptionPresent(
          p.position_id,
          p.position_name,
          p.position_is_active,
        );
        document.getElementById("postingPosition").value = String(
          p.position_id,
        );

        removePostingImage = false;
        if (postingDropzone) postingDropzone.reset();
        renderExistingImage(
          "existingPostingImage",
          p.recruitment_image_url,
          function () {
            removePostingImage = true;
          },
        );
        ShValidator.clearAllErrors(document.getElementById("postingForm"));

        new bootstrap.Modal(document.getElementById("postingFormModal")).show();
      })
      .catch(function (err) {
        ShToast.error(err.message || "Could not load job posting.");
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
    var body = document.getElementById("postingViewBody");
    body.innerHTML =
      '<div class="text-center text-muted py-4">Loading&hellip;</div>';
    new bootstrap.Modal(document.getElementById("postingViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var p = res.data;
        var statusCls =
          p.status === "published"
            ? "sh-status-pill--published"
            : "sh-status-pill--draft";

        var image = p.recruitment_image_url
          ? '<img src="' +
            escapeAttr(p.recruitment_image_url) +
            '" class="img-fluid rounded mb-3" style="max-height:360px;object-fit:contain;">'
          : '<div class="text-muted py-5 border rounded mb-3"><i class="bi bi-image fs-2 d-block mb-2"></i>No recruitment image uploaded</div>';

        body.innerHTML =
          '<div class="text-center">' +
          image +
          "</div>" +
          '<div class="d-flex justify-content-between align-items-start mb-2">' +
          '<h5 class="mb-0">' +
          escapeHtml(p.title) +
          "</h5>" +
          '<span class="sh-status-pill ' +
          statusCls +
          ' ms-3 flex-shrink-0">' +
          escapeHtml(p.status) +
          "</span>" +
          "</div>" +
          '<div class="text-muted small mb-3"><i class="bi bi-person-badge me-1"></i>' +
          escapeHtml(p.position_name) +
          "</div>" +
          (p.short_description
            ? '<p class="fw-semibold">' +
              escapeHtml(p.short_description) +
              "</p>"
            : "") +
          detailBlock("Job Description", p.job_description) +
          detailBlock("Qualifications / Requirements", p.qualifications) +
          detailBlock("Responsibilities", p.responsibilities) +
          detailBlock("Application Instructions", p.application_instructions) +
          (p.contact_email
            ? '<div class="mt-3"><span class="fw-semibold small">Contact / Application Email:</span> ' +
              '<a href="mailto:' +
              escapeAttr(p.contact_email) +
              '">' +
              escapeHtml(p.contact_email) +
              "</a></div>"
            : "");
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load job posting.") +
          "</div>";
      });
  }

  function detailBlock(label, value) {
    if (!value) return "";
    return (
      '<div class="mt-3">' +
      '<div class="small fw-semibold text-muted mb-1">' +
      escapeHtml(label) +
      "</div>" +
      '<div style="white-space: pre-wrap;">' +
      escapeHtml(value) +
      "</div>" +
      "</div>"
    );
  }

  function openDeleteModal(id, title) {
    document.getElementById("deletePostingTitle").textContent = title;
    document.getElementById("confirmDeletePostingBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("postingDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeletePostingBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deletePostingSpinner", true);

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
          document.getElementById("postingDeleteModal"),
        ).hide();
        ShToast.success(res.data.message || "Job posting deleted.");
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        ShToast.error(err.message || "Delete failed.");
      })
      .finally(function () {
        toggleSpinner(btn, "deletePostingSpinner", false);
      });
  }

  function toggleStatus(id, targetStatus, triggerEl) {
    if (triggerEl) triggerEl.disabled = true;

    fetch(AJAX_BASE + "/toggle_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        id: id,
        status: targetStatus,
        csrf_token: csrfToken,
      }),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data.success) {
          throw new Error(res.data.message || "Could not update status.");
        }
        ShToast.success(res.data.message || "Status updated.");
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        ShToast.error(err.message || "Could not update status.");
        if (triggerEl) triggerEl.disabled = false;
      });
  }

  function submitPostingForm() {
    var form = document.getElementById("postingForm");
    var id = document.getElementById("postingId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, postingSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);
    if (postingDropzone && postingDropzone.getFiles()[0]) {
      formData.append("recruitment_image", postingDropzone.getFiles()[0]);
    }
    if (isEdit) {
      formData.append(
        "remove_recruitment_image",
        removePostingImage ? "1" : "0",
      );
    }

    var submitBtn = document.getElementById("postingFormSubmit");
    var alertBox = document.getElementById("postingFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "postingFormSpinner", true);

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
            document.getElementById("postingFormModal"),
          ).hide();
          ShToast.success(res.data.message || "Job posting saved.");
          // A non-blocking dimension notice (image accepted but not an
          // exact/close 5:4 match) is surfaced as its own toast so it
          // doesn't get lost under the save-success toast.
          if (res.data.image_warning) {
            setTimeout(function () {
              ShToast.error(res.data.image_warning);
            }, 400);
          }
          dataTable.ajax.reload(null, false);
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
        throw new Error(res.data.message || "Save failed.");
      })
      .catch(function (err) {
        alertBox.textContent = err.message;
        alertBox.classList.remove("d-none");
      })
      .finally(function () {
        toggleSpinner(submitBtn, "postingFormSpinner", false);
      });
  }

  function formatDate(value) {
    if (!value) return "";
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
