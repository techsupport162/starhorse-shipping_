/**
 * modules/fares/assets/fares.js
 * ---------------------------------------------------------------------
 * Powers the Fare Route list page: DataTables list, and Add / Edit /
 * View / Delete — all inside Bootstrap modals via fetch(), no page
 * reloads. Mirrors modules/vessels/assets/vessels.js, adapted for two
 * independent single-image fields (Passenger / Cargo) instead of one
 * multi-image gallery.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#fareForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var passengerDropzone, cargoDropzone;
  var removePassengerImage = false;
  var removeCargoImage = false;

  // Message shown in the tooltip attached to the small bidirectional-arrow
  // icon (replaces the old "Vice-Versa" text badge everywhere in the UI).
  var TWO_WAY_TOOLTIP_TEXT = "This route is available in both directions.";

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Origin/Destination equality and route
  // duplication are business rules checked server-side (json_field_errors),
  // not expressible in this generic schema.
  var fareSchema = {
    origin: { required: true, maxLength: 100, label: "From" },
    destination: { required: true, maxLength: 100, label: "To" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    initDropzones();
    bindFormEvents();
    populateLocationSuggestions();

    var formEl = document.getElementById("fareForm");
    ShValidator.attachLiveValidation(formEl, fareSchema);

    // Unsaved-changes guard also needs to count a newly dropped image or
    // a removed existing image as "dirty" — same approach as Vessels.
    ShModalGuard.bind(document.getElementById("fareFormModal"), formEl, {
      isDirtyExtra: function () {
        return (
          passengerDropzone.getFiles().length > 0 ||
          cargoDropzone.getFiles().length > 0 ||
          removePassengerImage ||
          removeCargoImage
        );
      },
    });
    ShModalGuard.bind(document.getElementById("fareViewModal"), null);
    ShModalGuard.bind(document.getElementById("fareDeleteModal"), null);

    // Mobile/touch: tapping the icon toggles its tooltip (handled in
    // initTwoWayTooltips' click listener); tapping anywhere else closes
    // whatever tooltip is currently open.
    document.addEventListener("click", function (e) {
      if (e.target.closest(".sh-two-way-icon")) return;
      hideAllTwoWayTooltips();
    });
  });

  function initTable() {
    dataTable = $("#faresTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      columns: [
        {
          data: null,
          render: function (row) {
            var arrowIcon = row.is_vice_versa
              ? "bi-arrow-left-right"
              : "bi-arrow-right";
            var icon = row.is_vice_versa
              ? '<span class="sh-two-way-icon" tabindex="0" role="img" ' +
                'aria-label="Two-way route \u2014 available in both directions">' +
                '<i class="bi bi-arrow-left-right"></i></span>'
              : "";
            return (
              '<span class="fw-semibold">' +
              escapeHtml(row.origin) +
              "</span>" +
              ' <i class="bi ' +
              arrowIcon +
              ' mx-1 text-muted"></i> ' +
              '<span class="fw-semibold">' +
              escapeHtml(row.destination) +
              "</span>" +
              icon
            );
          },
        },
        {
          data: "passenger_fare_url",
          orderable: false,
          render: function (url) {
            return thumbOrPlaceholder(url);
          },
        },
        {
          data: "cargo_fare_url",
          orderable: false,
          render: function (url) {
            return thumbOrPlaceholder(url);
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
          data: null,
          orderable: false,
          className: "text-end",
          render: function (row) {
            var routeLabel = row.origin + " \u2192 " + row.destination;
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
              '" data-route="' +
              escapeAttr(routeLabel) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      order: [[0, "asc"]],
      lengthMenu: [5, 10, 25, 50],
      pageLength: 10,
      responsive: true,
      language: {
        emptyTable:
          'No fare routes yet. Click "Add Fare Route" to add your first one.',
      },
      drawCallback: function () {
        // Keep the From/To datalist suggestions fresh with any routes
        // that were typed in manually rather than picked from the list.
        populateLocationSuggestions();
        initTwoWayTooltips(document.getElementById("faresTable"));
      },
    });

    $("#faresTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#faresTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#faresTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("route"));
    });
  }

  // ---------------------------------------------------------------------
  // Two-way route icon tooltip (Bootstrap Tooltip component, already
  // bundled via bootstrap.bundle.min.js in includes/footer.php — Popper
  // is included, so placement auto-flips to stay on screen).
  //
  // Trigger is "manual" so the same code path drives hover, keyboard
  // focus, AND tap-to-toggle on touch devices, instead of relying on
  // Bootstrap's default hover-only behavior (which is unreliable on
  // touchscreens).
  // ---------------------------------------------------------------------
  function initTwoWayTooltips(scopeEl) {
    // Safety net: if a previous render's tooltip popup is still floating
    // in <body> (e.g. DataTables redrew the row while it was open),
    // remove it before wiring up the fresh icons.
    cleanupOrphanedTwoWayTooltips();

    scopeEl.querySelectorAll(".sh-two-way-icon").forEach(function (el) {
      var existing = bootstrap.Tooltip.getInstance(el);
      if (existing) {
        existing.dispose();
      }

      var tip = new bootstrap.Tooltip(el, {
        trigger: "manual",
        placement: "top",
        fallbackPlacements: ["top", "bottom", "right", "left"],
        customClass: "sh-two-way-tooltip",
        title: TWO_WAY_TOOLTIP_TEXT,
      });

      el.addEventListener("mouseenter", function () {
        tip.show();
      });
      el.addEventListener("mouseleave", function () {
        tip.hide();
      });
      el.addEventListener("focus", function () {
        tip.show();
      });
      el.addEventListener("blur", function () {
        tip.hide();
      });
      // Tap on touch devices: toggle this tooltip, close any other open
      // one first. stopPropagation keeps the document click-outside
      // handler (below) from immediately closing it again.
      el.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (el.hasAttribute("aria-describedby")) {
          tip.hide();
        } else {
          hideAllTwoWayTooltips();
          tip.show();
        }
      });
    });
  }

  function hideAllTwoWayTooltips() {
    document
      .querySelectorAll(".sh-two-way-icon[aria-describedby]")
      .forEach(function (el) {
        var inst = bootstrap.Tooltip.getInstance(el);
        if (inst) {
          inst.hide();
        }
      });
  }

  function cleanupOrphanedTwoWayTooltips() {
    document
      .querySelectorAll(".tooltip.sh-two-way-tooltip")
      .forEach(function (el) {
        el.remove();
      });
  }

  function thumbOrPlaceholder(url) {
    return url
      ? '<img src="' + escapeAttr(url) + '" class="sh-thumb" alt="">'
      : '<div class="sh-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>';
  }

  function initDropzones() {
    passengerDropzone = window.ShDropzone({
      dropzoneId: "passengerDropzone",
      inputId: "passengerImageInput",
      previewId: "passengerImagePreview",
      maxFiles: 1,
    });
    cargoDropzone = window.ShDropzone({
      dropzoneId: "cargoDropzone",
      inputId: "cargoImageInput",
      previewId: "cargoImagePreview",
      maxFiles: 1,
    });
  }

  function bindFormEvents() {
    document
      .getElementById("btnAddFare")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("fareFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document
      .getElementById("fareForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitFareForm();
      });

    document
      .getElementById("confirmDeleteFareBtn")
      .addEventListener("click", confirmDelete);

    document
      .getElementById("fareViewModal")
      .addEventListener("hidden.bs.modal", function () {
        hideAllTwoWayTooltips();
        cleanupOrphanedTwoWayTooltips();
      });
  }

  function resetFormForAdd() {
    document.getElementById("fareFormTitle").textContent = "Add Fare Route";
    document.getElementById("fareForm").reset();
    document.getElementById("fareId").value = "";
    document.getElementById("fareIsViceVersa").checked = false;
    document.getElementById("existingPassengerImage").innerHTML = "";
    document.getElementById("existingCargoImage").innerHTML = "";
    document.getElementById("fareFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("fareForm"));
    removePassengerImage = false;
    removeCargoImage = false;
    passengerDropzone.reset();
    cargoDropzone.reset();
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var f = res.data;
        document.getElementById("fareFormTitle").textContent =
          "Edit Fare Route";
        document.getElementById("fareId").value = f.id;
        document.getElementById("fareOrigin").value = f.origin || "";
        document.getElementById("fareDestination").value = f.destination || "";
        document.getElementById("fareStatus").value = f.status || "published";
        document.getElementById("fareIsViceVersa").checked = !!f.is_vice_versa;

        removePassengerImage = false;
        removeCargoImage = false;
        passengerDropzone.reset();
        cargoDropzone.reset();
        renderExistingImage(
          "existingPassengerImage",
          f.passenger_fare_url,
          function () {
            removePassengerImage = true;
          },
        );
        renderExistingImage(
          "existingCargoImage",
          f.cargo_fare_url,
          function () {
            removeCargoImage = true;
          },
        );
        ShValidator.clearAllErrors(document.getElementById("fareForm"));

        new bootstrap.Modal(document.getElementById("fareFormModal")).show();
      })
      .catch(function (err) {
        alert(err.message || "Could not load fare route.");
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
    var body = document.getElementById("fareViewBody");
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
    cleanupOrphanedTwoWayTooltips();
    new bootstrap.Modal(document.getElementById("fareViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var f = res.data;
        var viceVersaNote = f.is_vice_versa
          ? '<div class="text-center mb-3">' +
            '<span class="sh-two-way-icon sh-two-way-icon--lg" tabindex="0" role="img" ' +
            'aria-label="Two-way route \u2014 available in both directions">' +
            '<i class="bi bi-arrow-left-right"></i></span>' +
            '<div class="text-muted small mt-2">This single record also serves the reverse trip: <strong>' +
            escapeHtml(f.destination) +
            '</strong> <i class="bi bi-arrow-right mx-1"></i> <strong>' +
            escapeHtml(f.origin) +
            "</strong>.</div>" +
            "</div>"
          : "";
        body.innerHTML =
          '<h4 class="mb-2 text-center">' +
          escapeHtml(f.origin) +
          ' <i class="bi bi-arrow-right mx-1 text-muted"></i> ' +
          escapeHtml(f.destination) +
          "</h4>" +
          viceVersaNote +
          '<div class="row g-3">' +
          '<div class="col-md-6 text-center">' +
          '<div class="small fw-semibold text-muted mb-2">Passenger Fare</div>' +
          imageOrPlaceholder(f.passenger_fare_url) +
          "</div>" +
          '<div class="col-md-6 text-center">' +
          '<div class="small fw-semibold text-muted mb-2">Cargo / Vehicle Fare</div>' +
          imageOrPlaceholder(f.cargo_fare_url) +
          "</div>" +
          "</div>";
        initTwoWayTooltips(body);
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load fare route.") +
          "</div>";
      });
  }

  function imageOrPlaceholder(url) {
    return url
      ? '<img src="' +
          escapeAttr(url) +
          '" class="img-fluid rounded" style="max-height:320px;object-fit:contain;">'
      : '<div class="text-muted py-5 border rounded"><i class="bi bi-image fs-2 d-block mb-2"></i>No image uploaded</div>';
  }

  function openDeleteModal(id, routeLabel) {
    document.getElementById("deleteFareRoute").textContent = routeLabel;
    document.getElementById("confirmDeleteFareBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("fareDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeleteFareBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deleteFareSpinner", true);

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
          document.getElementById("fareDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "deleteFareSpinner", false);
      });
  }

  function submitFareForm() {
    var form = document.getElementById("fareForm");
    var id = document.getElementById("fareId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, fareSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    // Client-side same-origin/destination check as an early UX signal —
    // the authoritative check still happens server-side.
    var origin = document
      .getElementById("fareOrigin")
      .value.trim()
      .toLowerCase();
    var destination = document
      .getElementById("fareDestination")
      .value.trim()
      .toLowerCase();
    var alertBox = document.getElementById("fareFormAlert");
    if (origin && destination && origin === destination) {
      ShValidator.showFieldError(
        form,
        "destination",
        "Origin and destination cannot be the same.",
      );
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);
    if (passengerDropzone.getFiles()[0]) {
      formData.append("passenger_image", passengerDropzone.getFiles()[0]);
    }
    if (cargoDropzone.getFiles()[0]) {
      formData.append("cargo_image", cargoDropzone.getFiles()[0]);
    }
    if (isEdit) {
      formData.append(
        "remove_passenger_image",
        removePassengerImage ? "1" : "0",
      );
      formData.append("remove_cargo_image", removeCargoImage ? "1" : "0");
    }

    var submitBtn = document.getElementById("fareFormSubmit");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "fareFormSpinner", true);

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
            document.getElementById("fareFormModal"),
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
        toggleSpinner(submitBtn, "fareFormSpinner", false);
      });
  }

  function populateLocationSuggestions() {
    if (!dataTable) return;
    var datalist = document.getElementById("fareLocationSuggestions");
    if (!datalist) return;

    var known = {};
    Array.prototype.forEach.call(datalist.options, function (opt) {
      known[opt.value] = true;
    });

    dataTable
      .rows()
      .data()
      .toArray()
      .forEach(function (row) {
        [row.origin, row.destination].forEach(function (place) {
          if (place && !known[place]) {
            known[place] = true;
            var option = document.createElement("option");
            option.value = place;
            datalist.appendChild(option);
          }
        });
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
