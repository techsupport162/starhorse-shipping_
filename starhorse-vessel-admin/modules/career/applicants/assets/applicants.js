/**
 * modules/career/applicants/assets/applicants.js
 * ---------------------------------------------------------------------
 * Powers the Career Applicant Management list page: DataTables list
 * (with a status filter), an inline per-row status dropdown that saves
 * immediately on change, a read-only View modal with a resume download
 * link, and Delete. No Add/Edit form — applicants only ever arrive via
 * the public Career application form.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.getElementById("applicantsCsrfToken").value;

  var STATUS_LABELS = {
    new: "New",
    reviewed: "Reviewed",
    shortlisted: "Shortlisted",
    interview: "Interview",
    accepted: "Accepted",
    rejected: "Rejected",
  };
  // No new CSS classes needed — these map onto Bootstrap's existing
  // contextual badge colors already loaded on every admin page.
  var STATUS_BADGE_CLASS = {
    new: "text-bg-primary",
    reviewed: "text-bg-info",
    shortlisted: "text-bg-warning",
    interview: "text-bg-dark",
    accepted: "text-bg-success",
    rejected: "text-bg-danger",
  };

  var dataTable;

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    bindEvents();

    ShModalGuard.bind(document.getElementById("applicantViewModal"), null);
    ShModalGuard.bind(document.getElementById("applicantDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#applicantsTable").DataTable({
      processing: true,
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      order: [[2, "desc"]],
      columns: [
        {
          data: "full_name",
          responsivePriority: 1,
          render: function (name, type, row) {
            return (
              '<span class="fw-semibold">' +
              escapeHtml(name) +
              "</span>" +
              '<div class="text-muted small">' +
              escapeHtml(row.email) +
              "</div>"
            );
          },
        },
        {
          data: "applied_position_title",
          responsivePriority: 2,
          render: function (title, type, row) {
            var postingTag = "";
            if (!row.posting_id) {
              postingTag =
                ' <span class="badge text-bg-secondary" title="The original posting was deleted; this is the position they applied for at the time.">posting removed</span>';
            } else if (row.posting_status === "draft") {
              postingTag =
                ' <span class="badge text-bg-secondary">posting is now draft</span>';
            }
            return escapeHtml(title) + postingTag;
          },
        },
        {
          data: "applied_at",
          responsivePriority: 3,
          render: function (appliedAt) {
            return escapeHtml(formatDate(appliedAt));
          },
        },
        {
          data: "resume_url",
          orderable: false,
          responsivePriority: 4,
          render: function (url) {
            if (!url) {
              return '<span class="text-muted small">None</span>';
            }
            return (
              '<a href="' +
              escapeAttr(url) +
              '" class="sh-icon-btn" title="Download resume" aria-label="Download resume" target="_blank" rel="noopener">' +
              '<i class="bi bi-file-earmark-arrow-down"></i></a>'
            );
          },
        },
        {
          data: "status",
          responsivePriority: 1,
          render: function (status, type, row) {
            // Orthogonal data: the rendered <select> always contains
            // every status label as an <option>, so filtering/sorting
            // must use the raw status string instead of the display
            // HTML, or the status dropdown filter below would match
            // every row regardless of its actual status.
            if (type !== "display") return status;
            return renderStatusSelect(row.id, status);
          },
        },
        {
          data: null,
          orderable: false,
          responsivePriority: 1,
          className: "text-end",
          render: function (row) {
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-view" data-id="' +
              row.id +
              '" title="View Details" aria-label="View details for ' +
              escapeAttr(row.full_name) +
              '"><i class="bi bi-eye"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.full_name) +
              '" title="Delete" aria-label="Delete application from ' +
              escapeAttr(row.full_name) +
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
        emptyTable: "No applicants yet.",
      },
    });

    $("#applicantsTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#applicantsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });
    $("#applicantsTable tbody").on(
      "change",
      ".applicant-status-select",
      function () {
        updateStatus($(this).data("id"), $(this).val(), this);
      },
    );
  }

  function renderStatusSelect(id, currentStatus) {
    var options = Object.keys(STATUS_LABELS)
      .map(function (key) {
        var selected = key === currentStatus ? " selected" : "";
        return (
          '<option value="' +
          key +
          '"' +
          selected +
          ">" +
          STATUS_LABELS[key] +
          "</option>"
        );
      })
      .join("");
    var badgeClass = STATUS_BADGE_CLASS[currentStatus] || "text-bg-secondary";
    return (
      '<select class="form-select form-select-sm applicant-status-select badge-select ' +
      badgeClass +
      '" data-id="' +
      id +
      '" aria-label="Change applicant status" style="min-width:130px;">' +
      options +
      "</select>"
    );
  }

  function bindEvents() {
    document
      .getElementById("applicantStatusFilter")
      .addEventListener("change", function () {
        // Exact match, not a regex/substring search — "new" shouldn't
        // also match rows whose status happens to contain "new".
        dataTable
          .column(4)
          .search(this.value ? "^" + this.value + "$" : "", true, false)
          .draw();
      });

    document
      .getElementById("confirmDeleteApplicantBtn")
      .addEventListener("click", confirmDelete);
  }

  function updateStatus(id, newStatus, selectEl) {
    selectEl.disabled = true;

    fetch(AJAX_BASE + "/update_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        id: id,
        status: newStatus,
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
        // Re-tint the select to match its new status without a full
        // table reload, so the dropdown doesn't visually jump/reset.
        Object.keys(STATUS_BADGE_CLASS).forEach(function (key) {
          selectEl.classList.remove(STATUS_BADGE_CLASS[key]);
        });
        selectEl.classList.add(
          STATUS_BADGE_CLASS[newStatus] || "text-bg-secondary",
        );
      })
      .catch(function (err) {
        ShToast.error(err.message || "Could not update status.");
        dataTable.ajax.reload(null, false); // restore the last known-good status
      })
      .finally(function () {
        selectEl.disabled = false;
      });
  }

  function openViewModal(id) {
    var body = document.getElementById("applicantViewBody");
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
    new bootstrap.Modal(document.getElementById("applicantViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var a = res.data;
        var postingTag = "";
        if (!a.posting_id) {
          postingTag =
            ' <span class="badge text-bg-secondary">original posting deleted</span>';
        } else if (a.posting_status === "draft") {
          postingTag =
            ' <span class="badge text-bg-secondary">posting is now draft</span>';
        }

        body.innerHTML =
          '<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">' +
          "<div>" +
          '<h4 class="mb-1">' +
          escapeHtml(a.full_name) +
          "</h4>" +
          '<div class="text-muted"><i class="bi bi-briefcase me-1"></i>' +
          escapeHtml(a.applied_position_title) +
          postingTag +
          "</div>" +
          "</div>" +
          "<div>" +
          renderStatusSelect(a.id, a.status) +
          "</div>" +
          "</div>" +
          '<div class="row g-3 mb-3">' +
          viewField("Email", a.email, "col-md-6") +
          viewField("Contact Number", a.contact_number, "col-md-6") +
          viewField("Address", a.address, "col-md-12") +
          viewField("Applied On", formatDate(a.applied_at), "col-md-6") +
          "</div>" +
          (a.message
            ? '<div class="mb-3"><div class="small fw-semibold text-muted mb-1">Message / Cover Letter</div>' +
              '<div style="white-space: pre-wrap;">' +
              escapeHtml(a.message) +
              "</div></div>"
            : "") +
          '<div class="mb-1 small fw-semibold text-muted">Resume / CV</div>' +
          (a.resume_url
            ? '<a href="' +
              escapeAttr(a.resume_url) +
              '" class="btn btn-sh-outline btn-sm" target="_blank" rel="noopener">' +
              '<i class="bi bi-file-earmark-arrow-down me-1"></i>Download Resume</a>'
            : '<span class="text-muted small">Not submitted</span>');
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load applicant.") +
          "</div>";
      });
  }

  function viewField(label, value, colClass) {
    return (
      '<div class="' +
      colClass +
      '">' +
      '<div class="small fw-semibold text-muted mb-1">' +
      escapeHtml(label) +
      "</div>" +
      "<div>" +
      (value ? escapeHtml(value) : '<span class="text-muted">\u2014</span>') +
      "</div>" +
      "</div>"
    );
  }

  function openDeleteModal(id, name) {
    document.getElementById("deleteApplicantName").textContent = name;
    document.getElementById("confirmDeleteApplicantBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("applicantDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeleteApplicantBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deleteApplicantSpinner", true);

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
          document.getElementById("applicantDeleteModal"),
        ).hide();
        ShToast.success(res.data.message || "Applicant deleted.");
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        ShToast.error(err.message || "Delete failed.");
      })
      .finally(function () {
        toggleSpinner(btn, "deleteApplicantSpinner", false);
      });
  }

  function formatDate(value) {
    if (!value) return "";
    var d = new Date(value.replace(" ", "T"));
    if (isNaN(d.getTime())) return value;
    return (
      d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
      }) +
      " " +
      d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
    );
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
