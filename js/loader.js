/**
 * loader.js
 * ---------------------------------------------------------------------
 * Starhorse Shipping Line, Inc. — Maritime Loader controller
 *
 * Pure vanilla JS (ES6+), no dependencies. Exposes a single global
 * `SHLoader` object used to show/hide the loader for:
 *   - the full page (during page transitions / critical loads)
 *   - any inline container (sections, cards, tables)
 *
 * The full-page instance is expected to already exist in the DOM
 * (see includes/loader.php, included once near the top of <body>).
 * Inline instances are created on demand and cached per target so
 * repeat calls are cheap and don't duplicate markup.
 *
 * Public API
 * ----------
 *   SHLoader.show()                                   // full-page
 *   SHLoader.hide()                                    // full-page
 *   SHLoader.show({ target: '#panel' })                // inline, by selector
 *   SHLoader.show({ target: elementRef })              // inline, by element
 *   SHLoader.hide({ target: '#panel' })
 *   SHLoader.wrap(promiseOrFn, options)                 // auto show/hide
 *                                                        // around a fetch /
 *                                                        // async operation
 * ---------------------------------------------------------------------
 */
(function (window, document) {
  "use strict";

  const PAGE_LOADER_ID = "sh-loader-page";
  const ACTIVE_CLASS = "is-active";

  // Cache of inline loader instances keyed by their host element,
  // so repeated show/hide calls on the same container reuse one node.
  const inlineRegistry = new WeakMap();

  /**
   * Returns the SVG scene markup shared by every loader instance.
   * Kept in one place so inline loaders stay visually identical to the
   * full-page instance without duplicating the markup by hand elsewhere.
   */
  function buildStageHTML() {
    const pageLoader = document.getElementById(PAGE_LOADER_ID);
    if (pageLoader) {
      // Clone the already-authored stage from includes/loader.php —
      // guarantees the inline version always matches the page version.
      const stage = pageLoader.querySelector(".sh-loader__stage");
      if (stage) return stage.outerHTML;
    }
    // Fallback: if the page partial isn't present for some reason,
    // render nothing rather than guessing at markup.
    return "";
  }

  /**
   * Lazily creates (or retrieves) the inline loader element that lives
   * inside a given container.
   * @param {HTMLElement} container
   * @returns {HTMLElement} the .sh-loader wrapper
   */
  function getOrCreateInline(container) {
    if (inlineRegistry.has(container)) {
      return inlineRegistry.get(container);
    }

    const wrapper = document.createElement("div");
    wrapper.className = "sh-loader";
    wrapper.setAttribute("data-sh-mode", "inline");
    wrapper.setAttribute("aria-hidden", "true");
    wrapper.innerHTML = buildStageHTML();

    // Ensure the host can position/center the loader sensibly without
    // requiring the caller to change their existing layout/CSS.
    const computedPosition = window.getComputedStyle(container).position;
    if (computedPosition === "static") {
      container.style.position = "relative";
    }

    container.appendChild(wrapper);
    inlineRegistry.set(container, wrapper);
    return wrapper;
  }

  /**
   * Resolves a target option (selector string, element, or undefined)
   * to a concrete element, or null for the full-page loader.
   */
  function resolveTarget(target) {
    if (!target) return null;
    if (typeof target === "string") return document.querySelector(target);
    if (target instanceof HTMLElement) return target;
    return null;
  }

  /**
   * Show the loader.
   * @param {{target?: (string|HTMLElement)}} [options]
   */
  function show(options) {
    const opts = options || {};
    const targetEl = resolveTarget(opts.target);

    if (!targetEl) {
      // Full-page mode
      const pageLoader = document.getElementById(PAGE_LOADER_ID);
      if (!pageLoader) {
        console.warn(
          "[SHLoader] Full-page loader not found. Make sure includes/loader.php is included near the top of <body>.",
        );
        return;
      }
      pageLoader.setAttribute("aria-hidden", "false");
      pageLoader.classList.add(ACTIVE_CLASS);
      return;
    }

    // Inline mode
    const inlineLoader = getOrCreateInline(targetEl);
    inlineLoader.setAttribute("aria-hidden", "false");
    inlineLoader.classList.add(ACTIVE_CLASS);
  }

  /**
   * Hide the loader.
   * @param {{target?: (string|HTMLElement)}} [options]
   */
  function hide(options) {
    const opts = options || {};
    const targetEl = resolveTarget(opts.target);

    if (!targetEl) {
      const pageLoader = document.getElementById(PAGE_LOADER_ID);
      if (!pageLoader) return;
      pageLoader.classList.remove(ACTIVE_CLASS);
      pageLoader.setAttribute("aria-hidden", "true");
      return;
    }

    if (!inlineRegistry.has(targetEl)) return;
    const inlineLoader = inlineRegistry.get(targetEl);
    inlineLoader.classList.remove(ACTIVE_CLASS);
    inlineLoader.setAttribute("aria-hidden", "true");
  }

  /**
   * Convenience wrapper: shows the loader, runs an async operation,
   * hides the loader when it settles (success or failure), and
   * re-throws any error so calling code can still handle it.
   *
   * Usage:
   *   SHLoader.wrap(fetch('/api/vessels'), { target: '#vessels-panel' })
   *     .then(res => res.json())
   *     .then(renderVessels)
   *     .catch(handleError);
   *
   *   // or with a function that returns a promise:
   *   SHLoader.wrap(() => fetch('/api/rates'));
   *
   * @param {Promise|Function} promiseOrFn
   * @param {{target?: (string|HTMLElement)}} [options]
   * @returns {Promise}
   */
  function wrap(promiseOrFn, options) {
    show(options);
    const settle = () => hide(options);

    let promise;
    try {
      promise = typeof promiseOrFn === "function" ? promiseOrFn() : promiseOrFn;
    } catch (err) {
      settle();
      return Promise.reject(err);
    }

    return Promise.resolve(promise).then(
      (value) => {
        settle();
        return value;
      },
      (err) => {
        settle();
        throw err;
      },
    );
  }

  // ---------------------------------------------------------------------
  // Optional: show the full-page loader automatically during native page
  // navigations (regular links, non-AJAX form submits), and hide it as
  // soon as the new page has finished loading. Safe no-op if the site
  // uses in-page navigation only.
  // ---------------------------------------------------------------------
  function autoHookNavigation() {
    window.addEventListener("beforeunload", () => {
      show();
    });
    window.addEventListener("pageshow", (event) => {
      // pageshow also fires on back/forward-cache restores, where the
      // loader element may still be mid-fade from the previous state.
      hide();
    });
  }

  // Public API
  window.SHLoader = {
    show,
    hide,
    wrap,
  };

  // Initialize once the DOM is ready enough to query the page loader.
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoHookNavigation);
  } else {
    autoHookNavigation();
  }
})(window, document);
