/**
 * assets/js/admin.js
 * ---------------------------------------------------------------------
 * Shell-level behavior shared by every admin page: collapsible sidebar,
 * mobile sidebar toggle, and a reusable drag-and-drop image dropzone
 * factory used by the vessel Add/Edit modals.
 * ---------------------------------------------------------------------
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initSidebarToggle();
    initFlashAutoDismiss();
  });

  function initSidebarToggle() {
    var shell = document.querySelector(".sh-shell");
    var desktopToggle = document.getElementById("shSidebarToggle");
    var mobileToggle = document.getElementById("shMobileToggle");
    if (!shell) return;

    if (desktopToggle) {
      desktopToggle.addEventListener("click", function () {
        shell.classList.toggle("sh-collapsed");
        try {
          localStorage.setItem(
            "sh_sidebar_collapsed",
            shell.classList.contains("sh-collapsed") ? "1" : "0",
          );
        } catch (e) {
          /* localStorage unavailable — non-critical */
        }
      });

      try {
        if (localStorage.getItem("sh_sidebar_collapsed") === "1") {
          shell.classList.add("sh-collapsed");
        }
      } catch (e) {
        /* ignore */
      }
    }

    if (mobileToggle) {
      mobileToggle.addEventListener("click", function () {
        shell.classList.toggle("sh-mobile-open");
      });
    }
  }

  function initFlashAutoDismiss() {
    var flash = document.querySelector(".sh-flash-alert");
    if (!flash) return;
    setTimeout(function () {
      flash.classList.add("fade");
      setTimeout(function () {
        flash.remove();
      }, 300);
    }, 4000);
  }

  /**
   * Lightweight Bootstrap-toast wrapper for AJAX success/error feedback.
   * Usage: ShToast.success('User added successfully.'); ShToast.error('...');
   * Creates its own toast container on first use, so any module can call
   * this without extra markup.
   */
  window.ShToast = (function () {
    function ensureContainer() {
      var container = document.getElementById("shToastContainer");
      if (!container) {
        container = document.createElement("div");
        container.id = "shToastContainer";
        container.className =
          "toast-container position-fixed bottom-0 end-0 p-3";
        container.style.zIndex = 1080;
        document.body.appendChild(container);
      }
      return container;
    }

    function escapeHtml(str) {
      var div = document.createElement("div");
      div.textContent = String(str ?? "");
      return div.innerHTML;
    }

    function show(message, variant) {
      var container = ensureContainer();
      var toastEl = document.createElement("div");
      toastEl.className =
        "toast align-items-center border-0 text-white bg-" +
        (variant === "error" ? "danger" : "success");
      toastEl.setAttribute("role", "alert");
      toastEl.innerHTML =
        '<div class="d-flex">' +
        '<div class="toast-body">' +
        escapeHtml(message) +
        "</div>" +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
        "</div>";
      container.appendChild(toastEl);

      var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
      toastEl.addEventListener("hidden.bs.toast", function () {
        toastEl.remove();
      });
      toast.show();
    }

    return {
      success: function (message) {
        show(message, "success");
      },
      error: function (message) {
        show(message, "error");
      },
    };
  })();

  /**
   * Wires a dropzone container + hidden file input + preview grid together.
   * Supports click-to-browse, drag & drop, multi-file selection, per-file
   * removal, and keeps an in-memory FileList-like array (window.File objects)
   * that the caller reads at submit time via getFiles().
   *
   * @param {Object} opts
   * @param {string} opts.dropzoneId
   * @param {string} opts.inputId
   * @param {string} opts.previewId
   * @returns {{getFiles: function(): File[], reset: function(): void}}
   */
  window.ShDropzone = function (opts) {
    var dropzone = document.getElementById(opts.dropzoneId);
    var input = document.getElementById(opts.inputId);
    var preview = document.getElementById(opts.previewId);
    var maxFiles = opts.maxFiles || null; // null = unlimited (Vessel module default)
    var files = [];

    if (!dropzone || !input || !preview) {
      return {
        getFiles: function () {
          return [];
        },
        reset: function () {},
      };
    }

    function render() {
      preview.innerHTML = "";
      files.forEach(function (file, index) {
        var item = document.createElement("div");
        item.className = "sh-preview-item";

        var img = document.createElement("img");
        img.src = URL.createObjectURL(file);
        item.appendChild(img);

        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "sh-preview-remove";
        removeBtn.innerHTML = '<i class="bi bi-x"></i>';
        removeBtn.addEventListener("click", function () {
          files.splice(index, 1);
          render();
        });
        item.appendChild(removeBtn);

        preview.appendChild(item);
      });
    }

    function addFiles(fileList) {
      var incoming = Array.prototype.filter.call(fileList, function (file) {
        return file.type.indexOf("image/") === 0;
      });
      if (maxFiles && maxFiles === 1) {
        // Single-file mode (e.g. profile picture): a new selection replaces the old one.
        files = incoming.slice(0, 1);
      } else {
        files = files.concat(incoming);
        if (maxFiles) {
          files = files.slice(0, maxFiles);
        }
      }
      render();
    }

    dropzone.addEventListener("click", function () {
      input.click();
    });

    input.addEventListener("change", function () {
      addFiles(input.files);
      input.value = "";
    });

    ["dragenter", "dragover"].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add("sh-dropzone--active");
      });
    });

    ["dragleave", "drop"].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove("sh-dropzone--active");
      });
    });

    dropzone.addEventListener("drop", function (e) {
      if (e.dataTransfer && e.dataTransfer.files) {
        addFiles(e.dataTransfer.files);
      }
    });

    return {
      getFiles: function () {
        return files;
      },
      reset: function () {
        files = [];
        render();
      },
    };
  };
})();
