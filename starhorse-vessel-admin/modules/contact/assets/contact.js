/**
 * modules/contact/assets/contact.js
 * ---------------------------------------------------------------------
 * Powers the Contact Management screen:
 *   1. initSettingsForm() — Head Office / Map settings form, with
 *      add/remove repeater rows for phone numbers and emails, saved as
 *      one JSON POST to ajax/settings_update.php (settings + phones +
 *      emails together).
 *   2. initPortsTable()   — Ports DataTable with Add/Edit/View/Delete
 *      modals and drag-and-drop reordering. Structurally identical to
 *      modules/faqs/assets/faqs.js — only field names and endpoint
 *      paths differ.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#contactSettingsForm input[name="csrf_token"]',
  ).value;

  document.addEventListener("DOMContentLoaded", function () {
    initSettingsForm();
    initPortsTable();
  });

  /* =======================================================================
     SECTION 1 — Head Office & Map settings form
     ======================================================================= */

  var phoneRowSeq = 0;
  var emailRowSeq = 0;

  function initSettingsForm() {
    var phones = window.__contactPhonesSeed || [];
    var emails = window.__contactEmailsSeed || [];

    phones.forEach(function (p) {
      addPhoneRow(p);
    });
    if (!phones.length) {
      addPhoneRow();
    }

    emails.forEach(function (e) {
      addEmailRow(e);
    });
    if (!emails.length) {
      addEmailRow();
    }

    document
      .getElementById("btnAddPhoneRow")
      .addEventListener("click", function () {
        addPhoneRow();
      });
    document
      .getElementById("btnAddEmailRow")
      .addEventListener("click", function () {
        addEmailRow();
      });

    document
      .getElementById("contactSettingsForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitSettingsForm();
      });

    // Live map preview: re-render the iframe a moment after the admin
    // stops typing/pasting a new embed URL, so they can sanity-check it
    // before saving.
    var mapInput = document.getElementById("mapEmbedUrl");
    var mapDebounce;
    mapInput.addEventListener("input", function () {
      clearTimeout(mapDebounce);
      mapDebounce = setTimeout(function () {
        updateMapPreview(mapInput.value.trim());
      }, 500);
    });

    var officeStatus = document.getElementById("officeStatus");
    officeStatus.addEventListener("change", function () {
      var pill = document.getElementById("officeStatusPillPreview");
      pill.textContent = officeStatus.value;
      pill.className =
        "sh-status-pill " +
        (officeStatus.value === "published"
          ? "sh-status-pill--published"
          : "sh-status-pill--draft");
    });
  }

  function updateMapPreview(url) {
    var wrap = document.getElementById("mapPreviewWrap");
    if (!url) {
      wrap.innerHTML =
        '<div class="sh-map-preview__empty" id="mapPreviewEmpty">' +
        '<i class="bi bi-map"></i><span>No map URL set yet</span></div>';
      return;
    }
    wrap.innerHTML =
      '<iframe id="mapPreviewFrame" src="' +
      escapeAttr(url) +
      '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
  }

  function addPhoneRow(data) {
    data = data || {};
    var id = "phoneRow" + ++phoneRowSeq;
    var wrap = document.getElementById("phonesRepeater");
    var row = document.createElement("div");
    row.className = "sh-repeater__row";
    row.dataset.id = data.id || "";
    row.innerHTML =
      '<div class="sh-repeater__field">' +
      '<i class="bi bi-telephone"></i>' +
      '<input type="text" class="form-control" data-field="phone_number" maxlength="30" ' +
      'placeholder="e.g. 0995 515 9319" value="' +
      escapeAttr(data.phone_number || "") +
      '">' +
      "</div>" +
      '<select class="form-select sh-repeater__status" data-field="status">' +
      '<option value="published"' +
      ((data.status || "published") === "published" ? " selected" : "") +
      ">Published</option>" +
      '<option value="draft"' +
      (data.status === "draft" ? " selected" : "") +
      ">Draft</option>" +
      "</select>" +
      '<button type="button" class="sh-icon-btn sh-icon-btn--danger sh-repeater__remove" title="Remove">' +
      '<i class="bi bi-trash"></i></button>';
    wrap.appendChild(row);

    row
      .querySelector(".sh-repeater__remove")
      .addEventListener("click", function () {
        row.remove();
      });
  }

  function addEmailRow(data) {
    data = data || {};
    var id = "emailRow" + ++emailRowSeq;
    var wrap = document.getElementById("emailsRepeater");
    var row = document.createElement("div");
    row.className = "sh-repeater__row";
    row.dataset.id = data.id || "";
    row.innerHTML =
      '<div class="sh-repeater__field">' +
      '<i class="bi bi-envelope"></i>' +
      '<input type="email" class="form-control" data-field="email" maxlength="150" ' +
      'placeholder="e.g. inquiry@starhorse.com.ph" value="' +
      escapeAttr(data.email || "") +
      '">' +
      "</div>" +
      '<select class="form-select sh-repeater__status" data-field="status">' +
      '<option value="published"' +
      ((data.status || "published") === "published" ? " selected" : "") +
      ">Published</option>" +
      '<option value="draft"' +
      (data.status === "draft" ? " selected" : "") +
      ">Draft</option>" +
      "</select>" +
      '<button type="button" class="sh-icon-btn sh-icon-btn--danger sh-repeater__remove" title="Remove">' +
      '<i class="bi bi-trash"></i></button>';
    wrap.appendChild(row);

    row
      .querySelector(".sh-repeater__remove")
      .addEventListener("click", function () {
        row.remove();
      });
  }

  function collectRepeaterRows(containerId, fieldName) {
    var rows = document.querySelectorAll("#" + containerId + " .sh-repeater__row");
    var out = [];
    rows.forEach(function (row) {
      var value = row.querySelector('[data-field="' + fieldName + '"]').value.trim();
      if (!value) return; // skip empty rows silently — user just didn't fill it in
      out.push({
        id: row.dataset.id || null,
        status: row.querySelector('[data-field="status"]').value,
        _field: value,
      });
    });
    return out.map(function (r) {
      var obj = { id: r.id, status: r.status };
      obj[fieldName] = r._field;
      return obj;
    });
  }

  function submitSettingsForm() {
    var form = document.getElementById("contactSettingsForm");

    // Minimal required-field check (office name + address) — everything
    // else (phones, emails, map URL, Facebook) is optional so an admin
    // can save partial progress.
    var officeName = document.getElementById("officeName").value.trim();
    var address = document.getElementById("officeAddress").value.trim();
    var alertBox = document.getElementById("settingsFormAlert");
    alertBox.classList.add("d-none");

    if (!officeName || !address) {
      alertBox.textContent = "Office Name and Address are required.";
      alertBox.classList.remove("d-none");
      return;
    }

    var payload = {
      csrf_token: csrfToken,
      office_name: officeName,
      address: address,
      tel_number: document.getElementById("telNumber").value.trim(),
      facebook_name: document.getElementById("facebookName").value.trim(),
      facebook_url: document.getElementById("facebookUrl").value.trim(),
      map_embed_url: document.getElementById("mapEmbedUrl").value.trim(),
      map_status: document.getElementById("mapStatus").value,
      status: document.getElementById("officeStatus").value,
      phones: collectRepeaterRows("phonesRepeater", "phone_number"),
      emails: collectRepeaterRows("emailsRepeater", "email"),
    };

    var submitBtn = document.getElementById("settingsFormSubmit");
    toggleSpinner(submitBtn, "settingsFormSpinner", true);

    fetch(AJAX_BASE + "/settings_update.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (res.ok && res.data.success) {
          ShToast.success(res.data.message || "Saved.");
          return;
        }
        if (res.data.errors) {
          var messages = Object.keys(res.data.errors).map(function (k) {
            return res.data.errors[k];
          });
          alertBox.textContent = messages.join(" ");
          alertBox.classList.remove("d-none");
          return;
        }
        throw new Error(res.data.message || "Save failed.");
      })
      .catch(function (err) {
        alertBox.textContent = err.message;
        alertBox.classList.remove("d-none");
      })
      .finally(function () {
        toggleSpinner(submitBtn, "settingsFormSpinner", false);
      });
  }

  /* =======================================================================
     SECTION 2 — Ports table (DataTable + CRUD + drag reorder)
     Mirrors modules/faqs/assets/faqs.js exactly.
     ======================================================================= */

  var dataTable;
  var dragState = null;
  var reorderInFlight = false;

  var portSchema = {
    port_name: { required: true, maxLength: 150, label: "Port Name" },
    phone_number: { required: true, maxLength: 30, label: "Contact Number" },
  };

  function initPortsTable() {
    initTable();
    bindFormEvents();

    var formEl = document.getElementById("portForm");
    if (window.ShValidator) {
      ShValidator.attachLiveValidation(formEl, portSchema);
    }

    if (window.ShModalGuard) {
      ShModalGuard.bind(document.getElementById("portFormModal"), formEl);
      ShModalGuard.bind(document.getElementById("portDeleteModal"), null);
    }
  }

  function initTable() {
    dataTable = $("#portsTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/ports_list.php",
        dataSrc: "data",
      },
      ordering: false,
      paging: false,
      info: false,
      columns: [
        {
          data: null,
          orderable: false,
          responsivePriority: 1,
          className: "sh-drag-cell",
          render: function () {
            return (
              '<span class="sh-drag-handle" role="button" tabindex="0" ' +
              'aria-label="Drag to reorder this port">' +
              '<i class="bi bi-grip-vertical"></i></span>'
            );
          },
        },
        {
          data: null,
          orderable: false,
          responsivePriority: 6,
          render: function (row, type, full, meta) {
            return meta.row + 1;
          },
        },
        {
          data: "port_name",
          responsivePriority: 2,
          render: function (name) {
            return '<span class="fw-semibold">' + escapeHtml(name) + "</span>";
          },
        },
        {
          data: "phone_number",
          responsivePriority: 3,
          render: function (phone) {
            var digits = String(phone).replace(/[^\d+]/g, "");
            return (
              '<a href="tel:' +
              escapeAttr(digits) +
              '" class="sh-tel-link"><i class="bi bi-telephone me-1"></i>' +
              escapeHtml(phone) +
              "</a>"
            );
          },
        },
        {
          data: "status",
          responsivePriority: 4,
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
            return (
              '<div class="d-inline-flex gap-1">' +
              '<button type="button" class="sh-icon-btn btn-edit" data-id="' +
              row.id +
              '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-name="' +
              escapeAttr(row.port_name) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      createdRow: function (row, data) {
        $(row).attr("data-id", data.id).addClass("sh-faq-row");
      },
      responsive: true,
      language: {
        emptyTable: 'No ports yet. Click "Add Port" to add your first one.',
      },
    });

    $("#portsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#portsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });

    initDragAndDrop();
  }

  function bindFormEvents() {
    document
      .getElementById("btnAddPort")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("portFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document.getElementById("portForm").addEventListener("submit", function (e) {
      e.preventDefault();
      submitPortForm();
    });

    document
      .getElementById("confirmDeletePortBtn")
      .addEventListener("click", confirmDelete);
  }

  function initDragAndDrop() {
    var $tbody = $("#portsTable tbody");

    $tbody.on("pointerdown", ".sh-drag-handle", function (e) {
      if (e.button !== undefined && e.button !== 0) return;
      if (reorderInFlight) return;

      if (isSearchActive()) {
        ShToast.error(
          "Clear the search box to reorder ports — dragging only works on the full list.",
        );
        return;
      }

      var handle = this;
      var row = handle.closest("tr[data-id]");
      if (!row) return;

      collapseResponsiveChildren();

      e.preventDefault();
      document.body.classList.add("sh-dragging-active");
      row.classList.add("sh-faq-row--dragging");

      dragState = {
        pointerId: e.pointerId,
        row: row,
        lastTarget: null,
      };

      try {
        handle.setPointerCapture(e.pointerId);
      } catch (err) {
        /* no-op: pointermove/up still fire via delegated listeners */
      }
    });

    $tbody.on("pointermove", ".sh-drag-handle", function (e) {
      if (!dragState || e.pointerId !== dragState.pointerId) return;

      var el = document.elementFromPoint(e.clientX, e.clientY);
      var targetRow = el ? el.closest("tr[data-id]") : null;
      if (
        !targetRow ||
        targetRow === dragState.row ||
        targetRow === dragState.lastTarget
      ) {
        return;
      }

      var rect = targetRow.getBoundingClientRect();
      var dropBefore = e.clientY < rect.top + rect.height / 2;
      var rows = getOrderedRows();

      animateFlip(rows, function () {
        if (dropBefore) {
          targetRow.parentNode.insertBefore(dragState.row, targetRow);
        } else {
          targetRow.parentNode.insertBefore(
            dragState.row,
            targetRow.nextSibling,
          );
        }
      });

      dragState.lastTarget = targetRow;
    });

    $tbody.on("pointerup pointercancel", ".sh-drag-handle", function (e) {
      if (!dragState || e.pointerId !== dragState.pointerId) return;

      var row = dragState.row;
      row.classList.remove("sh-faq-row--dragging");
      document.body.classList.remove("sh-dragging-active");

      try {
        this.releasePointerCapture(e.pointerId);
      } catch (err) {
        /* ignore */
      }

      var newOrder = getOrderedRows().map(function (r) {
        return r.getAttribute("data-id");
      });
      dragState = null;

      saveNewOrder(newOrder);
    });
  }

  function getOrderedRows() {
    return Array.prototype.slice.call(
      document.querySelectorAll("#portsTable tbody tr[data-id]"),
    );
  }

  function isSearchActive() {
    return !!(dataTable && dataTable.search().trim() !== "");
  }

  function collapseResponsiveChildren() {
    if (!dataTable) return;
    dataTable.rows().every(function () {
      if (this.child && this.child.isShown()) {
        this.child.hide();
        $(this.node()).removeClass("parent");
      }
    });
  }

  function animateFlip(rows, mutate) {
    var firstRects = rows.map(function (row) {
      return row.getBoundingClientRect();
    });

    mutate();

    rows.forEach(function (row, i) {
      var first = firstRects[i];
      var last = row.getBoundingClientRect();
      var deltaY = first.top - last.top;
      if (!deltaY) return;

      row.style.transition = "none";
      row.style.transform = "translateY(" + deltaY + "px)";
      void row.offsetHeight;
      requestAnimationFrame(function () {
        row.style.transition = "";
        row.style.transform = "";
      });
    });
  }

  function toggleDragHandles(enabled) {
    $("#portsTable .sh-drag-handle").toggleClass(
      "sh-drag-handle--disabled",
      !enabled,
    );
  }

  function saveNewOrder(orderedIds) {
    if (reorderInFlight) return;
    reorderInFlight = true;
    toggleDragHandles(false);

    function refreshTable() {
      dataTable.ajax.reload(function () {
        reorderInFlight = false;
        toggleDragHandles(true);
      }, false);
    }

    fetch(AJAX_BASE + "/ports_reorder.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ order: orderedIds, csrf_token: csrfToken }),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data.success) {
          throw new Error(res.data.message || "Could not save the new order.");
        }
        ShToast.success(res.data.message || "Port order updated.");
        refreshTable();
      })
      .catch(function (err) {
        ShToast.error(
          (err.message || "Could not save the new order.") +
            " Restoring the previous order.",
        );
        refreshTable();
      });
  }

  function resetFormForAdd() {
    document.getElementById("portFormTitle").textContent = "Add Port";
    document.getElementById("portForm").reset();
    document.getElementById("portId").value = "";
    document.getElementById("portFormAlert").classList.add("d-none");
    if (window.ShValidator) {
      ShValidator.clearAllErrors(document.getElementById("portForm"));
    }
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/ports_view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var p = res.data;
        document.getElementById("portFormTitle").textContent = "Edit Port";
        document.getElementById("portId").value = p.id;
        document.getElementById("portName").value = p.port_name || "";
        document.getElementById("portPhone").value = p.phone_number || "";
        document.getElementById("portStatus").value = p.status || "published";
        if (window.ShValidator) {
          ShValidator.clearAllErrors(document.getElementById("portForm"));
        }

        new bootstrap.Modal(document.getElementById("portFormModal")).show();
      })
      .catch(function (err) {
        alert(err.message || "Could not load port.");
      });
  }

  function openDeleteModal(id, nameLabel) {
    document.getElementById("deletePortName").textContent = nameLabel;
    document.getElementById("confirmDeletePortBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("portDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeletePortBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deletePortSpinner", true);

    fetch(AJAX_BASE + "/ports_delete.php", {
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
          document.getElementById("portDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "deletePortSpinner", false);
      });
  }

  function submitPortForm() {
    var form = document.getElementById("portForm");
    var id = document.getElementById("portId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/ports_update.php" : "/ports_create.php");

    if (window.ShValidator) {
      var validation = ShValidator.validateForm(form, portSchema);
      if (!validation.valid) {
        ShValidator.focusFirstInvalid(form);
        return;
      }
    }

    var formData = new FormData(form);

    var submitBtn = document.getElementById("portFormSubmit");
    var alertBox = document.getElementById("portFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "portFormSpinner", true);

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
            document.getElementById("portFormModal"),
          ).hide();
          dataTable.ajax.reload(null, false);
          return;
        }
        if (res.data.errors) {
          var unmapped = window.ShValidator
            ? ShValidator.mapBackendErrors(form, res.data.errors)
            : Object.keys(res.data.errors).map(function (k) {
                return res.data.errors[k];
              });
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
        toggleSpinner(submitBtn, "portFormSpinner", false);
      });
  }

  function truncate(str, maxLen) {
    str = String(str == null ? "" : str);
    return str.length > maxLen ? str.slice(0, maxLen).trim() + "…" : str;
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
