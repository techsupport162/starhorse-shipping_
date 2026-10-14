/**
 * modules/vessels/assets/vessels.js
 * ---------------------------------------------------------------------
 * Powers the Vessel list page: DataTables list, and Add / Edit / View /
 * Delete — all inside Bootstrap modals via fetch(), no page reloads.
 * ---------------------------------------------------------------------
 */
(function ($) {
  "use strict";

  var AJAX_BASE =
    window.location.pathname.replace(/\/index\.php$/, "") + "/ajax";
  var csrfToken = document.querySelector(
    '#vesselForm input[name="csrf_token"]',
  ).value;

  var dataTable;
  var dropzone;
  var removedImageIds = [];
  var existingImageOrder = []; // ordered image IDs for the vessel currently open in the Add/Edit form
  var imagesReordered = false; // true once the admin drags a thumbnail or clicks "set as primary"
  var featuresCache = []; // [{id, name, icon}, ...] — master feature list, loaded once and refreshed after Feature Manager edits

  // A curated set of maritime/amenity-relevant Bootstrap Icons offered as
  // one-click swatches in the icon picker. The text input still accepts
  // any valid "bi-*" class, so this is a shortcut, not a hard limit.
  var ICON_SWATCHES = [
    "bi-snow",
    "bi-bed",
    "bi-cup-hot",
    "bi-wifi",
    "bi-tv",
    "bi-plug",
    "bi-life-preserver",
    "bi-water",
    "bi-anchor",
    "bi-compass",
    "bi-shield-check",
    "bi-cart-check",
    "bi-basket",
    "bi-cash-coin",
    "bi-truck",
    "bi-box-seam",
    "bi-person-arms-up",
    "bi-umbrella",
    "bi-brightness-high",
    "bi-moon-stars",
    "bi-cup-straw",
    "bi-egg-fried",
    "bi-door-open",
    "bi-stairs",
    "bi-camera-video",
    "bi-speaker",
    "bi-usb-plug",
    "bi-battery-charging",
    "bi-droplet",
    "bi-fan",
  ];

  // Field-level validation schema for the Add/Edit form (ShValidator —
  // assets/js/form-validator.js). Spec fields are optional, so only their
  // format/range is checked, not presence.
  var vesselSchema = {
    name: { required: true, maxLength: 150, label: "Vessel Name" },
    length_m: { type: "number", min: 0, label: "Length" },
    breadth_m: { type: "number", min: 0, label: "Breadth" },
    depth_m: { type: "number", min: 0, label: "Depth" },
    gross_tonnage: { type: "number", min: 0, label: "Gross Tonnage" },
  };

  document.addEventListener("DOMContentLoaded", function () {
    initTable();
    initDropzone();
    bindFormEvents();
    bindFeatureManagerEvents();
    loadFeatures(); // populate featuresCache, then render the (empty-selection) checklist for the Add form

    var formEl = document.getElementById("vesselForm");
    ShValidator.attachLiveValidation(formEl, vesselSchema);

    // Unsaved-changes guard (assets/js/modal-guard.js) — the Add/Edit
    // modal also counts a newly dropped image, a removed existing image,
    // or a reordered gallery as "dirty" even though those aren't plain
    // form field values the guard's own serializer can see.
    ShModalGuard.bind(document.getElementById("vesselFormModal"), formEl, {
      isDirtyExtra: function () {
        return (
          dropzone.getFiles().length > 0 ||
          removedImageIds.length > 0 ||
          imagesReordered
        );
      },
    });
    // View is read-only (no form) and Delete only ever has an explicit
    // confirm/cancel — both still get the guard so an accidental outside
    // click or Escape can't dismiss them, per the CRUD modal standard.
    ShModalGuard.bind(document.getElementById("vesselViewModal"), null);
    ShModalGuard.bind(document.getElementById("vesselDeleteModal"), null);
    ShModalGuard.bind(document.getElementById("vesselFeaturesModal"), null);
    ShModalGuard.bind(document.getElementById("featureDeleteModal"), null);
  });

  function initTable() {
    dataTable = $("#vesselsTable").DataTable({
      ajax: {
        url: AJAX_BASE + "/list.php",
        dataSrc: "data",
      },
      columns: [
        {
          data: "thumbnail_url",
          orderable: false,
          render: function (url, type, row) {
            return url
              ? '<img src="' + escapeAttr(url) + '" class="sh-thumb" alt="">'
              : '<div class="sh-thumb d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image"></i></div>';
          },
        },
        { data: "name", render: escapeHtml },
        { data: "length_m", render: fmtNum },
        { data: "breadth_m", render: fmtNum },
        { data: "depth_m", render: fmtNum },
        { data: "gross_tonnage", render: fmtNum },
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
              escapeAttr(row.name) +
              '" title="Delete"><i class="bi bi-trash"></i></button>' +
              "</div>"
            );
          },
        },
      ],
      order: [[1, "asc"]],
      lengthMenu: [5, 10, 25, 50],
      pageLength: 10,
      responsive: true,
      language: {
        emptyTable:
          'No vessels yet. Click "Add Vessel" to add your first ship.',
      },
    });

    $("#vesselsTable tbody").on("click", ".btn-view", function () {
      openViewModal($(this).data("id"));
    });
    $("#vesselsTable tbody").on("click", ".btn-edit", function () {
      openEditModal($(this).data("id"));
    });
    $("#vesselsTable tbody").on("click", ".btn-delete", function () {
      openDeleteModal($(this).data("id"), $(this).data("name"));
    });
  }

  function initDropzone() {
    dropzone = window.ShDropzone({
      dropzoneId: "vesselDropzone",
      inputId: "vesselImagesInput",
      previewId: "newImagesPreview",
    });
  }

  function bindFormEvents() {
    document
      .getElementById("btnAddVessel")
      .addEventListener("click", resetFormForAdd);

    document
      .getElementById("vesselFormModal")
      .addEventListener("hidden.bs.modal", resetFormForAdd);

    document
      .getElementById("vesselForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitVesselForm();
      });

    document
      .getElementById("confirmDeleteBtn")
      .addEventListener("click", confirmDelete);
  }

  function resetFormForAdd() {
    document.getElementById("vesselFormTitle").textContent = "Add Vessel";
    document.getElementById("vesselForm").reset();
    document.getElementById("vesselId").value = "";
    document.getElementById("existingImages").innerHTML = "";
    document.getElementById("existingImagesHint").classList.add("d-none");
    document.getElementById("vesselFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("vesselForm"));
    removedImageIds = [];
    existingImageOrder = [];
    imagesReordered = false;
    dropzone.reset();
    renderFeatureChecklist([]);
  }

  function openEditModal(id) {
    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var v = res.data;
        document.getElementById("vesselFormTitle").textContent = "Edit Vessel";
        document.getElementById("vesselId").value = v.id;
        document.getElementById("vesselName").value = v.name || "";
        document.getElementById("vesselTagline").value = v.tagline || "";
        document.getElementById("vesselLength").value = v.length_m ?? "";
        document.getElementById("vesselBreadth").value = v.breadth_m ?? "";
        document.getElementById("vesselDepth").value = v.depth_m ?? "";
        document.getElementById("vesselTonnage").value = v.gross_tonnage ?? "";
        document.getElementById("vesselDescription").value =
          v.description || "";
        document.getElementById("vesselStatus").value = v.status || "published";

        removedImageIds = [];
        imagesReordered = false;
        dropzone.reset();
        renderExistingImages(v.images || []);
        renderFeatureChecklist(v.feature_ids || []);
        ShValidator.clearAllErrors(document.getElementById("vesselForm"));

        new bootstrap.Modal(document.getElementById("vesselFormModal")).show();
      })
      .catch(function (err) {
        alert(err.message || "Could not load vessel.");
      });
  }

  function renderExistingImages(images) {
    var container = document.getElementById("existingImages");
    var hint = document.getElementById("existingImagesHint");
    container.innerHTML = "";
    existingImageOrder = images.map(function (img) {
      return img.id;
    });
    hint.classList.toggle("d-none", images.length < 2);

    images.forEach(function (img, index) {
      container.appendChild(buildExistingImageItem(img, index === 0));
    });
  }

  function buildExistingImageItem(img, isPrimary) {
    var item = document.createElement("div");
    item.className =
      "sh-preview-item sh-preview-item--existing" +
      (isPrimary ? " is-primary" : "");
    item.dataset.imageId = img.id;
    item.draggable = true;

    var image = document.createElement("img");
    image.src = img.url;
    item.appendChild(image);

    var primaryBadge = document.createElement("span");
    primaryBadge.className = "sh-preview-primary-badge";
    primaryBadge.textContent = "Primary";
    item.appendChild(primaryBadge);

    var starBtn = document.createElement("button");
    starBtn.type = "button";
    starBtn.className = "sh-preview-star";
    starBtn.title = "Set as primary/featured image";
    starBtn.innerHTML =
      '<i class="bi ' + (isPrimary ? "bi-star-fill" : "bi-star") + '"></i>';
    starBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      setPrimaryImage(img.id);
    });
    item.appendChild(starBtn);

    var removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "sh-preview-remove";
    removeBtn.innerHTML = '<i class="bi bi-x"></i>';
    removeBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      removedImageIds.push(img.id);
      existingImageOrder = existingImageOrder.filter(function (id) {
        return id !== img.id;
      });
      item.remove();
      var remaining = document.querySelectorAll(
        "#existingImages .sh-preview-item",
      );
      document
        .getElementById("existingImagesHint")
        .classList.toggle("d-none", remaining.length < 2);
      refreshPrimaryMarkers();
    });
    item.appendChild(removeBtn);

    bindDragReorder(item);

    return item;
  }

  // Marks whichever thumbnail is now first (in DOM/order) as the primary
  // image — used after a drag reorder, a star click, or a removal changes
  // which image is first.
  function refreshPrimaryMarkers() {
    var items = document.querySelectorAll("#existingImages .sh-preview-item");
    items.forEach(function (el, index) {
      var isPrimary = index === 0;
      el.classList.toggle("is-primary", isPrimary);
      var starIcon = el.querySelector(".sh-preview-star i");
      if (starIcon)
        starIcon.className = "bi " + (isPrimary ? "bi-star-fill" : "bi-star");
    });
  }

  function setPrimaryImage(imageId) {
    var container = document.getElementById("existingImages");
    var item = container.querySelector('[data-image-id="' + imageId + '"]');
    if (!item) return;
    container.insertBefore(item, container.firstChild);
    existingImageOrder = [imageId].concat(
      existingImageOrder.filter(function (id) {
        return id !== imageId;
      }),
    );
    imagesReordered = true;
    refreshPrimaryMarkers();
  }

  // Minimal native HTML5 drag-and-drop reordering — no extra library,
  // consistent with "reuse existing libraries, avoid a heavy framework".
  var dragSourceItem = null;

  function bindDragReorder(item) {
    item.addEventListener("dragstart", function () {
      dragSourceItem = item;
      item.classList.add("is-dragging");
    });
    item.addEventListener("dragend", function () {
      item.classList.remove("is-dragging");
      dragSourceItem = null;
    });
    item.addEventListener("dragover", function (e) {
      e.preventDefault();
    });
    item.addEventListener("drop", function (e) {
      e.preventDefault();
      if (!dragSourceItem || dragSourceItem === item) return;
      var container = document.getElementById("existingImages");
      var items = Array.prototype.slice.call(container.children);
      var sourceIndex = items.indexOf(dragSourceItem);
      var targetIndex = items.indexOf(item);
      if (sourceIndex < targetIndex) {
        container.insertBefore(dragSourceItem, item.nextSibling);
      } else {
        container.insertBefore(dragSourceItem, item);
      }
      existingImageOrder = Array.prototype.map.call(
        container.children,
        function (el) {
          return parseInt(el.dataset.imageId, 10);
        },
      );
      imagesReordered = true;
      refreshPrimaryMarkers();
    });
  }

  function openViewModal(id) {
    var body = document.getElementById("vesselViewBody");
    body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
    new bootstrap.Modal(document.getElementById("vesselViewModal")).show();

    fetchJson(AJAX_BASE + "/view.php?id=" + encodeURIComponent(id))
      .then(function (res) {
        var v = res.data;
        var gallery = (v.images || [])
          .map(function (img) {
            return (
              '<div class="col-4"><img src="' +
              escapeAttr(img.url) +
              '" class="rounded" style="width:100%;height:120px;object-fit:cover;"></div>'
            );
          })
          .join("");

        var featureChips = (v.features || [])
          .map(function (f) {
            return (
              '<span class="sh-feature-chip sh-feature-chip--readonly">' +
              '<span class="sh-feature-chip__icon"><i class="bi ' +
              escapeAttr(f.icon) +
              '"></i></span>' +
              '<span class="sh-feature-chip__label">' +
              escapeHtml(f.name) +
              "</span></span>"
            );
          })
          .join("");

        body.innerHTML =
          '<p class="text-muted small text-uppercase fw-semibold mb-1">' +
          escapeHtml(v.tagline || "Our Fleet") +
          "</p>" +
          '<h4 class="mb-3">' +
          escapeHtml(v.name) +
          "</h4>" +
          (gallery ? '<div class="row g-2 mb-3">' + gallery + "</div>" : "") +
          '<table class="table table-sm">' +
          specRow("Length", v.length_m, "m") +
          specRow("Breadth", v.breadth_m, "m") +
          specRow("Depth", v.depth_m, "m") +
          specRow("Gross Tonnage", v.gross_tonnage, "") +
          "</table>" +
          (v.description
            ? '<p class="text-muted mb-0 mb-3">' +
              escapeHtml(v.description) +
              "</p>"
            : "") +
          (featureChips
            ? '<div class="sh-feature-checklist sh-feature-checklist--readonly">' +
              featureChips +
              "</div>"
            : "");
      })
      .catch(function (err) {
        body.innerHTML =
          '<div class="alert alert-danger mb-0">' +
          escapeHtml(err.message || "Could not load vessel.") +
          "</div>";
      });
  }

  function specRow(label, value, unit) {
    if (value === null || value === undefined || value === "") return "";
    return (
      '<tr><th class="text-muted">' +
      label +
      '</th><td class="text-end fw-semibold">' +
      Number(value).toFixed(2) +
      (unit ? " " + unit : "") +
      "</td></tr>"
    );
  }

  function openDeleteModal(id, name) {
    document.getElementById("deleteVesselName").textContent = name;
    document.getElementById("confirmDeleteBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("vesselDeleteModal")).show();
  }

  function confirmDelete() {
    var btn = document.getElementById("confirmDeleteBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "deleteSpinner", true);

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
          document.getElementById("vesselDeleteModal"),
        ).hide();
        dataTable.ajax.reload(null, false);
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "deleteSpinner", false);
      });
  }

  function submitVesselForm() {
    var form = document.getElementById("vesselForm");
    var id = document.getElementById("vesselId").value;
    var isEdit = !!id;
    var url = AJAX_BASE + (isEdit ? "/update.php" : "/create.php");

    var validation = ShValidator.validateForm(form, vesselSchema);
    if (!validation.valid) {
      ShValidator.focusFirstInvalid(form);
      return;
    }

    var formData = new FormData(form);
    dropzone.getFiles().forEach(function (file) {
      formData.append("images[]", file);
    });
    if (isEdit) {
      formData.append("remove_image_ids", JSON.stringify(removedImageIds));
      if (imagesReordered) {
        formData.append("image_order", JSON.stringify(existingImageOrder));
      }
    }
    getCheckedFeatureIds().forEach(function (id) {
      formData.append("feature_ids[]", id);
    });

    var submitBtn = document.getElementById("vesselFormSubmit");
    var alertBox = document.getElementById("vesselFormAlert");
    alertBox.classList.add("d-none");
    toggleSpinner(submitBtn, "vesselFormSpinner", true);

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
            document.getElementById("vesselFormModal"),
          ).hide();
          dataTable.ajax.reload(null, false);
          return;
        }
        if (res.data.errors) {
          // Field-level errors from json_field_errors() — show each under
          // its input; anything that doesn't map to a field (business
          // rules, etc.) falls back to the top alert box.
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
        toggleSpinner(submitBtn, "vesselFormSpinner", false);
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

  function fmtNum(value) {
    return value === null || value === undefined || value === ""
      ? '<span class="text-muted">—</span>'
      : Number(value).toFixed(2);
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

  /* =====================================================================
     Vessel Features — checklist (inside the Add/Edit Vessel form) and the
     Feature Manager modal (master list CRUD). Both read from the same
     featuresCache, loaded once and refreshed after any manager change.
     ===================================================================== */

  function loadFeatures() {
    return fetchJson(AJAX_BASE + "/features_list.php")
      .then(function (res) {
        featuresCache = res.data || [];
        renderFeatureChecklist(getCheckedFeatureIds()); // preserve any current selection
        renderFeatureManagerList();
      })
      .catch(function () {
        document.getElementById("vesselFeatureChecklist").innerHTML =
          '<div class="text-danger small py-2">Could not load features.</div>';
      });
  }

  function getCheckedFeatureIds() {
    return Array.prototype.map.call(
      document.querySelectorAll(
        '#vesselFeatureChecklist input[type="checkbox"]:checked',
      ),
      function (cb) {
        return cb.value;
      },
    );
  }

  function renderFeatureChecklist(selectedIds) {
    var container = document.getElementById("vesselFeatureChecklist");
    var selected = (selectedIds || []).map(String);

    if (!featuresCache.length) {
      container.innerHTML =
        '<div class="text-muted small py-2">No features yet — click "Manage list" to add one.</div>';
      return;
    }

    container.innerHTML = "";
    featuresCache.forEach(function (feature) {
      var id = "vesselFeature" + feature.id;
      var wrap = document.createElement("label");
      wrap.className = "sh-feature-chip";
      wrap.setAttribute("for", id);

      var checked = selected.indexOf(String(feature.id)) !== -1;
      wrap.className += checked ? " is-checked" : "";
      wrap.innerHTML =
        '<input type="checkbox" id="' +
        id +
        '" value="' +
        feature.id +
        '"' +
        (checked ? " checked" : "") +
        ">" +
        '<span class="sh-feature-chip__icon"><i class="bi ' +
        escapeAttr(feature.icon) +
        '"></i></span>' +
        '<span class="sh-feature-chip__label">' +
        escapeHtml(feature.name) +
        "</span>";

      wrap.querySelector("input").addEventListener("change", function () {
        wrap.classList.toggle("is-checked", this.checked);
      });

      container.appendChild(wrap);
    });
  }

  /* -------------------------------------------------------------------
     Feature Manager modal
     ------------------------------------------------------------------- */

  function bindFeatureManagerEvents() {
    buildIconSwatches();

    document
      .getElementById("vesselFormManageFeaturesLink")
      .addEventListener("click", function () {
        new bootstrap.Modal(
          document.getElementById("vesselFeaturesModal"),
        ).show();
      });

    document
      .getElementById("vesselFeaturesModal")
      .addEventListener("hidden.bs.modal", function () {
        resetFeatureForm();
        // The Add/Edit Vessel modal (if still open behind this one) should
        // reflect any renames/additions/deletions made in the manager.
        loadFeatures();
      });

    document
      .getElementById("featureIcon")
      .addEventListener("input", function () {
        updateIconPreview(this.value.trim());
      });

    document
      .getElementById("featureForm")
      .addEventListener("submit", function (e) {
        e.preventDefault();
        submitFeatureForm();
      });

    document
      .getElementById("featureFormCancelEdit")
      .addEventListener("click", resetFeatureForm);

    document
      .getElementById("featureListWrap")
      .addEventListener("click", function (e) {
        var editBtn = e.target.closest(".btn-feature-edit");
        var deleteBtn = e.target.closest(".btn-feature-delete");
        if (editBtn) openFeatureEdit(editBtn.dataset.id);
        if (deleteBtn)
          openFeatureDeleteModal(deleteBtn.dataset.id, deleteBtn.dataset.name);
      });

    document
      .getElementById("confirmFeatureDeleteBtn")
      .addEventListener("click", confirmFeatureDelete);
  }

  function buildIconSwatches() {
    var wrap = document.getElementById("featureIconSwatches");
    wrap.innerHTML = "";
    ICON_SWATCHES.forEach(function (icon) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "sh-icon-swatch";
      btn.dataset.icon = icon;
      btn.title = icon;
      btn.innerHTML = '<i class="bi ' + icon + '"></i>';
      btn.addEventListener("click", function () {
        document.getElementById("featureIcon").value = icon;
        updateIconPreview(icon);
      });
      wrap.appendChild(btn);
    });
  }

  function updateIconPreview(icon) {
    var preview = document.getElementById("featureIconPreview");
    var valid = /^bi-[a-z0-9-]+$/.test(icon);
    preview.innerHTML =
      '<i class="bi ' +
      (valid ? escapeAttr(icon) : "bi-question-circle") +
      '"></i>';
    preview.classList.toggle("is-invalid-icon", !valid);

    document
      .querySelectorAll("#featureIconSwatches .sh-icon-swatch")
      .forEach(function (el) {
        el.classList.toggle("is-active", el.dataset.icon === icon);
      });
  }

  function renderFeatureManagerList() {
    var wrap = document.getElementById("featureListWrap");

    if (!featuresCache.length) {
      wrap.innerHTML =
        '<p class="text-muted small text-center py-3 mb-0">No features yet. Add your first one above.</p>';
      return;
    }

    var rows = featuresCache
      .map(function (f) {
        return (
          '<div class="sh-feature-row" data-id="' +
          f.id +
          '">' +
          '<span class="sh-feature-row__icon"><i class="bi ' +
          escapeAttr(f.icon) +
          '"></i></span>' +
          '<span class="sh-feature-row__name">' +
          escapeHtml(f.name) +
          "</span>" +
          '<span class="text-muted small sh-feature-row__code">' +
          escapeHtml(f.icon) +
          "</span>" +
          '<div class="d-inline-flex gap-1">' +
          '<button type="button" class="sh-icon-btn btn-feature-edit" data-id="' +
          f.id +
          '" title="Edit"><i class="bi bi-pencil"></i></button>' +
          '<button type="button" class="sh-icon-btn sh-icon-btn--danger btn-feature-delete" data-id="' +
          f.id +
          '" data-name="' +
          escapeAttr(f.name) +
          '" title="Delete"><i class="bi bi-trash"></i></button>' +
          "</div>" +
          "</div>"
        );
      })
      .join("");

    wrap.innerHTML = '<div class="sh-feature-list">' + rows + "</div>";
  }

  function openFeatureEdit(id) {
    var feature = featuresCache.find(function (f) {
      return String(f.id) === String(id);
    });
    if (!feature) return;

    document.getElementById("featureId").value = feature.id;
    document.getElementById("featureName").value = feature.name;
    document.getElementById("featureIcon").value = feature.icon;
    updateIconPreview(feature.icon);
    document.getElementById("featureFormSubmitLabel").textContent = "Save";
    document.getElementById("featureFormCancelWrap").style.display = "";
    document.getElementById("featureFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("featureForm"));
    document.getElementById("featureName").focus();
  }

  function resetFeatureForm() {
    document.getElementById("featureForm").reset();
    document.getElementById("featureId").value = "";
    document.getElementById("featureIcon").value = "bi-check2-circle";
    updateIconPreview("bi-check2-circle");
    document.getElementById("featureFormSubmitLabel").textContent = "Add";
    document.getElementById("featureFormCancelWrap").style.display = "none";
    document.getElementById("featureFormAlert").classList.add("d-none");
    ShValidator.clearAllErrors(document.getElementById("featureForm"));
  }

  function submitFeatureForm() {
    var form = document.getElementById("featureForm");
    var id = document.getElementById("featureId").value;
    var isEdit = !!id;
    var url =
      AJAX_BASE + (isEdit ? "/features_update.php" : "/features_create.php");

    var formData = new FormData(form);
    if (isEdit) formData.append("id", id);

    var submitBtn = document.getElementById("featureFormSubmit");
    var alertBox = document.getElementById("featureFormAlert");
    alertBox.classList.add("d-none");
    ShValidator.clearAllErrors(form);
    toggleSpinner(submitBtn, "featureFormSpinner", true);

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
          resetFeatureForm();
          return loadFeatures();
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
        toggleSpinner(submitBtn, "featureFormSpinner", false);
      });
  }

  function openFeatureDeleteModal(id, name) {
    document.getElementById("deleteFeatureName").textContent = name;
    document.getElementById("confirmFeatureDeleteBtn").dataset.id = id;
    new bootstrap.Modal(document.getElementById("featureDeleteModal")).show();
  }

  function confirmFeatureDelete() {
    var btn = document.getElementById("confirmFeatureDeleteBtn");
    var id = btn.dataset.id;
    toggleSpinner(btn, "featureDeleteSpinner", true);

    fetch(AJAX_BASE + "/features_delete.php", {
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
          document.getElementById("featureDeleteModal"),
        ).hide();
        return loadFeatures();
      })
      .catch(function (err) {
        alert(err.message);
      })
      .finally(function () {
        toggleSpinner(btn, "featureDeleteSpinner", false);
      });
  }
})(jQuery);
