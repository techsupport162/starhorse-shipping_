/**
 * modules/career/positions/assets/positions.js
 * ---------------------------------------------------------------------
 * Powers the Career Position Settings list page: DataTables list, Add /
 * Edit / Delete inside Bootstrap modals via fetch(), plus a quick inline
 * Enable/Disable toggle per row (mirrors modules/users/assets/users.js'
 * activate/deactivate pattern). No image uploads or reordering here —
 * closest sibling is modules/faqs/assets/faqs.js minus the drag-and-drop.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#positionForm input[name="csrf_token"]',
  ).value;

  var dataTable;

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Duplicate-name detection is a business
  // rule checked server-side (json_field_errors), not expressible here.
  var positionSchema = {
    name: { required: true, maxLength: 100, label: "Position Name" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    bindFormEvents();

    var formEl = document.getElementById("positionForm");
    ShValidator.attachLiveValidation(formEl, positionSchema);

    ShModalGuard.bind(document.getElementById("positionFormModal"), formEl);
    ShModalGuard.bind(document.getElementById("positionDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#positionsTable").DataTable({
      processing: true,
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      order: [[1, "asc"]],
      columns: [
        {
          data: null,
          orderable: false,
          responsivePriority: 6,
          render: function (row, type, full, meta) {
            return meta.row + 1;
          },
        },
        {
          data: "name",
          responsivePriority: 1,
          render: function (name) {
            return '<span class="fw-semibold">' + escapeHtml(name) + "</span>";
          },
        },
        {
          data: "is_active",
          responsivePriority: 2,
          render: function (isActive) {
            var cls = isActive
              ? "sh-status-pill--active"
              : "sh-status-pill--inactive";
            var label = isActive ? "active" : "inactive";
            return (
              '<span class="sh-status-pill ' + cls + '">' + label + "</span>"
            );
          },
        },
        {
          data: "posting_count",
          responsivePriority: 4,
          render: function (count) {
            if (!count) {
              return '<span class="text-muted small">Not used yet</span>';
            }
            return (
              '<span class="badge rounded-pill text-bg-light border">' +
              count +
              (count === 1 ? " posting" : " postings") +
              "</span>"
            );
          },
        },
        {
          data: "created_at",
          responsivePriority: 5,
          render: function (createdAt) {
            return escapeHtml(formatDate(createdAt));
          },
        },
        {
          data: null,
          orderable: false,
          responsivePriority: 1,
          className: "text-end",
          render: function (row) {
            var toggleIcon = row.is_active ? "bi-toggle2-on" : "bi-toggle2-off";
            var toggleTitle = row.is_active
              ? "Disable (hide from new postings)"
              : "Enable (make available for new postings)";
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-toggle" data-id="' +
              row.id +
              '" data-active="' +
              (row.is_active ? "1" : "0") +
              '" title="' +
              escapeAttr(toggleTitle) +
              '" aria-label="' +
              escapeAttr(toggleTitle) +
              '"><i class="bi ' +
              toggleIcon +
              '"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-edit" data-id="' +
              row.id +
              '" title="Edit" aria-label="Edit ' +
              escapeAttr(row.name) +
              '"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.name) +
              '" title="Delete" aria-label="Delete ' +
              escapeAttr(row.name) +
              '"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      createdRow: function (row, data) {
        $(row).attr("data-id", data.id);
      },
      responsive: true,
      language: {
        emptyTable:
          'No positions yet. Click "Add Position" to add your first one.',
      },
    });

    $("#positionsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#positionsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });
    $("#positionsTable tbody").on("click", ".btn-toggle", function () {
      toggleStatus(
        $(this).data("id"),
        $(this).data("active") ? "0" : "1",
        this,
      );
    });
  }

  function bindFormEvents() {
    document
      .getElementById("btnAddPosition")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("positionFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document
      .getElementById("positionForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitPositionForm();
      });

    document
      .getElementById("confirmDeletePositionBtn")
      .addEventListener("click", confirmDelete);
  }

  function resetFormForAdd() {
    document.getElementById("positionFormTitle").textContent = "Add Position";
    document.getElementById("positionForm").reset();
    document.getElementById("positionId").value = "";
    document.getElementById("positionStatus").value = "1";
    document.getElementById("positionFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("positionForm"));
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var p = res.data;
        document.getElementById("positionFormTitle").textContent =
          "Edit Position";
        document.getElementById("positionId").value = p.id;
        document.getElementById("positionName").value = p.name || "";
        document.getElementById("positionStatus").value = p.is_active
          ? "1"
          : "0";
        ShValidator.clearAllErrors(document.getElementById("positionForm"));

        new bootstrap.Modal(
          document.getElementById("positionFormModal"),
        ).show();
      })
      .catch(function (err) {
        ShToast.error(err.message || "Could not load position.");
      });
  }

  function openDeleteModal(id, name) {
    document.getElementById("deletePositionName").textContent = name;
    document.getElementById("positionDeleteAlert").classList.add("d-none");
    document.getElementById("confirmDeletePositionBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("positionDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeletePositionBtn");
    var id = btn.dataset.id;
    var alertBox = document.getElementById("positionDeleteAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(btn, "deletePositionSpinner", true);

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
        if (!res.ok || !res.data.success) {
          throw new Error(res.data.message || "Delete failed.");
        }
        bootstrap.Modal.getInstance(
          document.getElementById("positionDeleteModal"),
        ).hide();
        ShToast.success(res.data.message || "Position deleted.");
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alertBox.textContent = err.message;
        alertBox.classList.remove("d-none");
      })
      .finally(function () {
        toggleSpinner(btn, "deletePositionSpinner", false);
      });
  }

  function toggleStatus(id, newValue, triggerEl) {
    if (triggerEl) triggerEl.disabled = true;

    fetch(AJAX_BASE + "/toggle.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        id: id,
        is_active: newValue,
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

  function submitPositionForm() {
    var form = document.getElementById("positionForm");
    var id = document.getElementById("positionId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, positionSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);

    var submitBtn = document.getElementById("positionFormSubmit");
    var alertBox = document.getElementById("positionFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "positionFormSpinner", true);

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
            document.getElementById("positionFormModal"),
          ).hide();
          ShToast.success(res.data.message || "Position saved.");
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
        toggleSpinner(submitBtn, "positionFormSpinner", false);
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
