/**
 * assets/js/modal-guard.js
 * ---------------------------------------------------------------------
 * ShModalGuard — the CMS's single reusable "don't lose my unsaved
 * changes" system for CRUD modals. Pairs with the shared
 * #shDiscardChangesModal markup rendered once in includes/footer.php.
 *
 * What it does NOT do: create or show your CRUD modal. Module JS still
 * calls `new bootstrap.Modal(el).show()` as before — ShModalGuard just
 * intercepts how it gets CLOSED.
 *
 * ------------------------------------------------------------------
 * USAGE (per CRUD modal, once at module init):
 *
 *   ShModalGuard.bind(document.getElementById('vesselFormModal'),
 *                      document.getElementById('vesselForm'));
 *
 * That single call:
 *   - Re-snapshots the form's state every time the modal finishes opening
 *     (`shown.bs.modal`), so "unsaved changes" always means "changed
 *     since THIS open", not since page load.
 *   - Intercepts every element with `data-bs-dismiss="modal"` inside the
 *     modal (the existing X button and Cancel button — no HTML edits
 *     needed, `bind()` strips the attribute itself at runtime so Bootstrap
 *     never dismisses the modal on its own) and routes it through the
 *     guarded close instead.
 *   - Intercepts Escape (Bootstrap's own Escape handling is disabled via
 *     `data-bs-keyboard="false"` on the modal element — see Phase 3 note
 *     below) so it goes through the same guarded path rather than
 *     bypassing confirmation.
 *
 * The modal element itself needs `data-bs-backdrop="static"
 * data-bs-keyboard="false"` in its HTML — that's the one Phase-3 markup
 * change per modal; it's what stops Bootstrap from closing it on an
 * outside click or Escape before our JS ever gets a say.
 *
 * A successful save should still call `bootstrap.Modal.getInstance(el).hide()`
 * directly, NOT `requestClose()` — there's nothing to "discard" after a
 * successful submit, so that path intentionally bypasses the guard.
 *
 * For fields the serializer can't meaningfully diff (e.g. a File chosen
 * in a custom avatar/dropzone picker that isn't a plain form field value),
 * pass `isDirtyExtra` — a function returning true if that side-channel
 * has unsaved changes too.
 * ------------------------------------------------------------------
 */
(function () {
  "use strict";

  var snapshots = new WeakMap(); // form -> serialized initial state
  var discardModalEl = null;
  var pendingClose = null;

  /** Serialize a form's plain field values for comparison. Files and CSRF/id are excluded on purpose. */
  function serialize(form) {
    var data = new FormData(form);
    var parts = [];
    data.forEach(function (value, key) {
      if (key === "csrf_token") return;
      if (value instanceof File) return; // handled via isDirtyExtra by the caller, not here
      parts.push(key + "=" + value);
    });
    return parts.sort().join("&");
  }

  function track(form) {
    snapshots.set(form, serialize(form));
  }

  function isDirty(form, isDirtyExtra) {
    if (typeof isDirtyExtra === "function" && isDirtyExtra()) return true;
    var initial = snapshots.get(form);
    if (initial === undefined) return false; // never tracked (e.g. modal never opened yet) — nothing to lose
    return serialize(form) !== initial;
  }

  // The CRUD modal that most recently asked for the discard-confirmation
  // dialog. Used solely to repair a Bootstrap side effect when the
  // confirmation is dismissed without discarding (see the
  // 'hidden.bs.modal' listener in ensureDiscardModalWired below).
  var activeGuardedModalEl = null;

  function ensureDiscardModalWired() {
    if (discardModalEl) return;
    discardModalEl = document.getElementById("shDiscardChangesModal");
    if (!discardModalEl) return; // shared partial not present on this page — fail safe, just close directly
    document
      .getElementById("shDiscardChangesConfirmBtn")
      .addEventListener("click", function () {
        var instance = bootstrap.Modal.getInstance(discardModalEl);
        if (instance) instance.hide();
        if (typeof pendingClose === "function") {
          var fn = pendingClose;
          pendingClose = null;
          fn();
        }
      });

    // Bootstrap's Modal.hide() unconditionally does
    // `document.body.classList.remove('modal-open')` once its own hide
    // transition finishes — it has no awareness that another modal (the
    // CRUD form modal underneath) is still open. So the instant the
    // confirmation dialog closes for ANY reason (Cancel, its own X,
    // Escape), the body silently loses its scroll-lock even though the
    // form modal is still on screen. If the form modal that spawned this
    // confirmation is still open when that happens, put the lock back.
    discardModalEl.addEventListener("hidden.bs.modal", function () {
      if (
        activeGuardedModalEl &&
        activeGuardedModalEl.classList.contains("show")
      ) {
        document.body.classList.add("modal-open");
      }
      activeGuardedModalEl = null;
    });
  }

  /** The guarded close entry point — every dismiss trigger routes through here. */
  function requestClose(modalEl, form, options) {
    options = options || {};
    var dirty = form ? isDirty(form, options.isDirtyExtra) : false;

    var doClose = function () {
      var instance = bootstrap.Modal.getInstance(modalEl);
      if (instance) instance.hide();
    };

    if (!dirty) {
      doClose();
      return;
    }

    ensureDiscardModalWired();
    if (!discardModalEl) {
      // Defensive fallback if the shared modal markup is missing for some reason.
      if (window.confirm("You have unsaved changes. Discard them and close?"))
        doClose();
      return;
    }

    pendingClose = doClose;
    activeGuardedModalEl = modalEl;
    new bootstrap.Modal(discardModalEl).show();
  }

  /**
   * Wire a CRUD modal + its form into the guard. Safe to call once per
   * modal at module init time.
   */
  function bind(modalEl, form, options) {
    options = options || {};

    modalEl
      .querySelectorAll('[data-bs-dismiss="modal"]')
      .forEach(function (btn) {
        // IMPORTANT: Bootstrap wires up `[data-bs-dismiss="modal"]` with a
        // single delegated listener on `document` (see bootstrap's
        // component-functions.js -> enableDismissTrigger), and it registers
        // that listener in the CAPTURING phase (bootstrap's EventHandler.on
        // passes its `isDelegated` flag straight through as the native
        // addEventListener `useCapture` argument). Capturing runs top-down —
        // document fires before the click ever reaches this button — so by
        // the time OUR bubble-phase listener below runs, Bootstrap has
        // already resolved the closest .modal ancestor and called
        // instance.hide() on it. preventDefault()/stopPropagation() from a
        // bubble-phase handler can't undo something an ancestor's capturing
        // listener already did; that's what was silently closing the form
        // modal out from under the confirmation dialog. Removing the
        // attribute up front is what actually stops Bootstrap's delegated
        // selector from ever matching this button, so our own handler is
        // the only thing driving the close.
        btn.removeAttribute("data-bs-dismiss");
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          requestClose(modalEl, form, options);
        });
      });

    modalEl.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        e.preventDefault();
        requestClose(modalEl, form, options);
      }
    });

    if (form) {
      modalEl.addEventListener("shown.bs.modal", function () {
        track(form);
      });
    }
  }

  window.ShModalGuard = {
    track: track,
    isDirty: isDirty,
    requestClose: requestClose,
    bind: bind,
  };
})();
