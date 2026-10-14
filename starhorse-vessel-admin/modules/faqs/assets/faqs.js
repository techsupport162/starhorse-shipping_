/**
 * modules/faqs/assets/faqs.js
 * ---------------------------------------------------------------------
 * Powers the FAQ Management list page: DataTables list, Add / Edit /
 * View / Delete inside Bootstrap modals via fetch() (mirrors
 * modules/fares/assets/fares.js, minus image handling), plus drag-and-
 * drop reordering.
 *
 * Reordering uses pointer events (not the HTML5 drag-and-drop API) so
 * the same code path works with mouse, touch, and pen input — HTML5 DnD
 * has patchy/no touch support on its own. The dragged row is moved in
 * the live DOM as the pointer moves (a FLIP transform animates the
 * displaced rows so nothing "jumps"), then on drop the resulting id
 * order is posted to ajax/reorder.php. Either way the table is then
 * refreshed from the server via dataTable.ajax.reload() — on success to
 * pick up clean row numbers, on failure to restore the last saved order
 * — so the on-screen table and the database can never drift apart.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#faqForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var dragState = null;
  var reorderInFlight = false;

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Duplicate-question detection is a
  // business rule checked server-side (json_field_errors), not
  // expressible in this generic schema.
  var faqSchema = {
    question: { required: true, maxLength: 255, label: "Question" },
    answer: { required: true, maxLength: 5000, label: "Answer" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    bindFormEvents();

    var formEl = document.getElementById("faqForm");
    ShValidator.attachLiveValidation(formEl, faqSchema);

    ShModalGuard.bind(document.getElementById("faqFormModal"), formEl);
    ShModalGuard.bind(document.getElementById("faqViewModal"), null);
    ShModalGuard.bind(document.getElementById("faqDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#faqsTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      // Manual drag order is now the single source of truth for FAQ
      // display order, so column-click sorting is turned off entirely —
      // it would otherwise let the admin "sort" the table into a view
      // that has nothing to do with what gets saved. Paging is off too:
      // dragging only reorders what's currently in the DOM, so every FAQ
      // needs to be on screen at once for a drag to mean what it looks
      // like it means. FAQ lists are small enough that this is fine.
      ordering: false,
      paging: false,
      info: false,
      columns: [
        {
          // Drag handle — always visible (responsivePriority: 1) since
          // it's useless hidden behind Responsive's "+" toggle.
          data: null,
          orderable: false,
          responsivePriority: 1,
          className: "sh-drag-cell",
          render: function () {
            return (
              '<span class="sh-drag-handle" role="button" tabindex="0" ' +
              'aria-label="Drag to reorder this FAQ">' +
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
          data: "question",
          responsivePriority: 2,
          render: function (question) {
            return (
              '<span class="fw-semibold">' + escapeHtml(question) + "</span>"
            );
          },
        },
        {
          data: "answer",
          orderable: false,
          responsivePriority: 5,
          render: function (answer) {
            return (
              '<span class="text-muted">' +
              escapeHtml(truncate(answer, 80)) +
              "</span>"
            );
          },
        },
        {
          data: "status",
          responsivePriority: 3,
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
          responsivePriority: 4,
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
              '<button type="button" class="sh-icon-btn btn-view" data-id="' +
              row.id +
              '" title="View"><i class="bi bi-eye"></i></button>' +
              '<button type="button" class="sh-icon-btn btn-edit" data-id="' +
              row.id +
              '" title="Edit"><i class="bi bi-pencil"></i></button>' +
              '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-delete" data-id="' +
              row.id +
              '" data-question="' +
              escapeAttr(truncate(row.question, 60)) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      // Tag each <tr> with its FAQ id and a marker class so the drag
      // logic (plain DOM, not the DataTables row API) can find rows
      // and read/write order without needing to know column indexes.
      createdRow: function (row, data) {
        $(row).attr("data-id", data.id).addClass("sh-faq-row");
      },
      responsive: true,
      language: {
        emptyTable: 'No FAQs yet. Click "Add FAQ" to add your first one.',
      },
    });

    $("#faqsTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#faqsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#faqsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("question"));
    });

    initDragAndDrop();
  }

  function bindFormEvents() {
    document
      .getElementById("btnAddFaq")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("faqFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document.getElementById("faqForm").addEventListener("submit", function (e) {
      e.preventDefault();
      submitFaqForm();
    });

    document
      .getElementById("confirmDeleteFaqBtn")
      .addEventListener("click", confirmDelete);
  }

  /**
   * Wires up drag-and-drop reordering on the FAQ table body. Delegated on
   * the tbody (not per-row) since DataTables replaces every row on each
   * ajax.reload(), and pointer events (rather than HTML5 dragstart/drop)
   * so mouse, touch, and pen all use the same code path.
   */
  function initDragAndDrop() {
    var $tbody = $("#faqsTable tbody");

    $tbody.on("pointerdown", ".sh-drag-handle", function (e) {
      if (e.button !== undefined && e.button !== 0) return; // primary button/touch only
      if (reorderInFlight) return;

      if (isSearchActive()) {
        ShToast.error(
          "Clear the search box to reorder FAQs — dragging only works on the full list.",
        );
        return;
      }

      var handle = this;
      var row = handle.closest("tr[data-id]");
      if (!row) return;

      // Collapse any open Responsive "+" detail rows first so one doesn't
      // get left behind, detached from the row it belongs to, once that
      // row moves elsewhere in the tbody.
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
        /* Pointer capture isn't supported everywhere — pointermove/up
           still fire normally via the delegated listeners below. */
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

  /** All current FAQ rows, in their current on-screen (drag) order. */
  function getOrderedRows() {
    return Array.prototype.slice.call(
      document.querySelectorAll("#faqsTable tbody tr[data-id]"),
    );
  }

  function isSearchActive() {
    return !!(dataTable && dataTable.search().trim() !== "");
  }

  /** Close any open DataTables Responsive "+" detail rows. */
  function collapseResponsiveChildren() {
    if (!dataTable) return;
    dataTable.rows().every(function () {
      if (this.child && this.child.isShown()) {
        this.child.hide();
        $(this.node()).removeClass("parent");
      }
    });
  }

  /**
   * FLIP-animates `rows` (First → Last → Invert → Play) around a DOM
   * mutation, so a row moving to a new position visibly slides there
   * instead of the table just snapping into its new layout. No animation
   * library needed: record each row's position, apply the change,
   * measure the new position, then instantly offset each row back to
   * where it *was* with a transform and let the CSS transition on
   * .sh-faq-row (see admin.css) animate that offset back to zero.
   */
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
      void row.offsetHeight; // force reflow before clearing, so the jump above isn't animated too
      requestAnimationFrame(function () {
        row.style.transition = "";
        row.style.transform = "";
      });
    });
  }

  /** Toggle handles between draggable and (visually + functionally) disabled. */
  function toggleDragHandles(enabled) {
    $("#faqsTable .sh-drag-handle").toggleClass(
      "sh-drag-handle--disabled",
      !enabled,
    );
  }

  function saveNewOrder(orderedIds) {
    if (reorderInFlight) return;
    reorderInFlight = true;
    toggleDragHandles(false);

    function refreshTable() {
      // Reload from the server either way: on success this picks up
      // clean "#" numbers from the now-authoritative order; on failure
      // it restores whatever order the database still actually has,
      // satisfying the "restore the previous order" requirement without
      // needing to separately remember what that order was.
      dataTable.ajax.reload(function () {
        reorderInFlight = false;
        toggleDragHandles(true);
      }, false);
    }

    fetch(AJAX_BASE + "/reorder.php", {
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
        ShToast.success(res.data.message || "FAQ order updated.");
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
    document.getElementById("faqFormTitle").textContent = "Add FAQ";
    document.getElementById("faqForm").reset();
    document.getElementById("faqId").value = "";
    document.getElementById("faqFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("faqForm"));
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var f = res.data;
        document.getElementById("faqFormTitle").textContent = "Edit FAQ";
        document.getElementById("faqId").value = f.id;
        document.getElementById("faqQuestion").value = f.question || "";
        document.getElementById("faqAnswer").value = f.answer || "";
        document.getElementById("faqStatus").value = f.status || "published";
        ShValidator.clearAllErrors(document.getElementById("faqForm"));

        new bootstrap.Modal(document.getElementById("faqFormModal")).show();
      })
      .catch(function (err) {
        alert(err.message || "Could not load FAQ.");
      });
  }

  function openViewModal(id) {
    var body = document.getElementById("faqViewBody");
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
    new bootstrap.Modal(document.getElementById("faqViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var f = res.data;
        var statusCls =
          f.status === "published"
            ? "sh-status-pill--published"
            : "sh-status-pill--draft";
        body.innerHTML =
          '<div class="d-flex justify-content-between align-items-start mb-3">' +
          '<h5 class="mb-0">' +
          escapeHtml(f.question) +
          "</h5>" +
          '<span class="sh-status-pill ' +
          statusCls +
          ' ms-3 flex-shrink-0">' +
          escapeHtml(f.status) +
          "</span>" +
          "</div>" +
          '<p class="mb-0" style="white-space: pre-wrap;">' +
          escapeHtml(f.answer) +
          "</p>";
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load FAQ.") +
          "</div>";
      });
  }

  function openDeleteModal(id, questionLabel) {
    document.getElementById("deleteFaqQuestion").textContent = questionLabel;
    document.getElementById("confirmDeleteFaqBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("faqDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeleteFaqBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deleteFaqSpinner", true);

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
          document.getElementById("faqDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "deleteFaqSpinner", false);
      });
  }

  function submitFaqForm() {
    var form = document.getElementById("faqForm");
    var id = document.getElementById("faqId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, faqSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);

    var submitBtn = document.getElementById("faqFormSubmit");
    var alertBox = document.getElementById("faqFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "faqFormSpinner", true);

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
            document.getElementById("faqFormModal"),
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
        toggleSpinner(submitBtn, "faqFormSpinner", false);
      });
  }

  function truncate(str, maxLen) {
    str = String(str == null ? "" : str);
    return str.length > maxLen ? str.slice(0, maxLen).trim() + "…" : str;
  }

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
