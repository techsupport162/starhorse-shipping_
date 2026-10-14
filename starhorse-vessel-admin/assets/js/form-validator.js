/**
 * assets/js/form-validator.js
 * ---------------------------------------------------------------------
 * ShValidator — the CMS's single reusable field-level validation engine.
 * Used by every CRUD form (Vessels, Users, and future modules) so
 * validation behaves identically everywhere: errors render directly
 * under their field, never as a single top-of-form alert.
 *
 * Deliberately built on Bootstrap's OWN validation styling
 * (`.is-invalid` + `.invalid-feedback`) rather than inventing a new
 * visual language — that's already in the CSS you're loading, keeps
 * every form consistent with the existing design system for free, and
 * needs zero new theme CSS.
 *
 * ------------------------------------------------------------------
 * USAGE (a module's own JS file does this — see Phase 3 for the
 * Vessel module wiring):
 *
 *   const schema = {
 *     name:  { required: true, label: 'Vessel Name', maxLength: 150 },
 *     email: { required: true, type: 'email' },
 *     length_m: { type: 'number', min: 0 },
 *     confirm_password: { required: true, match: 'password' },
 *   };
 *
 *   ShValidator.attachLiveValidation(formEl, schema);   // once, on init
 *
 *   // on submit:
 *   const result = ShValidator.validateForm(formEl, schema);
 *   if (!result.valid) { ShValidator.focusFirstInvalid(formEl); return; }
 *
 *   // after a failed AJAX call that returned { errors: {...} }:
 *   const unmapped = ShValidator.mapBackendErrors(formEl, response.errors);
 *   // `unmapped` = messages whose key didn't match any field name —
 *   // show those in the form's existing top alert box as a fallback.
 * ------------------------------------------------------------------
 */
(function () {
  "use strict";

  var DEFAULT_MESSAGES = {
    required: function (label) {
      return label + " is required.";
    },
    email: function () {
      return "Please enter a valid email address.";
    },
    number: function () {
      return "Please enter a valid number.";
    },
    minLength: function (label, n) {
      return label + " must be at least " + n + " characters.";
    },
    maxLength: function (label, n) {
      return label + " must be " + n + " characters or fewer.";
    },
    min: function (label, n) {
      return label + " must be at least " + n + ".";
    },
    max: function (label, n) {
      return label + " must be " + n + " or less.";
    },
    pattern: function (label) {
      return label + " has an invalid format.";
    },
    match: function (label, otherLabel) {
      return label + " does not match " + otherLabel + ".";
    },
  };

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /**
   * Password complexity engine — reusable anywhere a "Password" field
   * needs the CMS's standard rule set (Add User, Change Password, and any
   * future module). Kept as small, independent, testable functions so the
   * live checklist UI and the required-on-submit check always agree.
   */
  var PASSWORD_RULES = [
    {
      key: "length",
      label: "At least 8 characters",
      test: function (v) {
        return v.length >= 8;
      },
    },
    {
      key: "uppercase",
      label: "One uppercase letter (A-Z)",
      test: function (v) {
        return /[A-Z]/.test(v);
      },
    },
    {
      key: "lowercase",
      label: "One lowercase letter (a-z)",
      test: function (v) {
        return /[a-z]/.test(v);
      },
    },
    {
      key: "symbol",
      label: "One symbol (e.g. ! @ # $ %)",
      test: function (v) {
        return /[^A-Za-z0-9]/.test(v);
      },
    },
  ];

  function passwordRuleResults(value) {
    value = value || "";
    var results = {};
    PASSWORD_RULES.forEach(function (rule) {
      results[rule.key] = rule.test(value);
    });
    return results;
  }

  function isPasswordComplex(value) {
    var results = passwordRuleResults(value);
    return PASSWORD_RULES.every(function (rule) {
      return results[rule.key];
    });
  }

  /**
   * Wire a live, typing-as-you-go checklist + strength meter under a
   * password field. `checklistEl` should contain one child per rule with
   * `data-rule="length|uppercase|lowercase|symbol"` (see users.js markup);
   * missing items are simply skipped, so a caller can show a subset.
   *
   * Returns { update, reset } so the caller can re-sync it (e.g. reset
   * the display to its empty state when a modal closes and the form
   * resets, since resetting the input's value doesn't fire an 'input'
   * event on its own).
   */
  function attachPasswordStrengthMeter(inputEl, checklistEl, options) {
    if (!inputEl) return { update: function () {}, reset: function () {} };
    options = options || {};
    var meterFillEl =
      options.meterFillEl ||
      (checklistEl &&
        checklistEl.querySelector(".sh-password-strength-bar__fill"));
    var meterLabelEl =
      options.meterLabelEl ||
      (checklistEl && checklistEl.querySelector(".sh-password-strength-label"));
    var STRENGTH_LABELS = ["Too weak", "Weak", "Fair", "Good", "Strong"];
    var STRENGTH_CLASSES = [
      "sh-password-strength-bar__fill--0",
      "sh-password-strength-bar__fill--1",
      "sh-password-strength-bar__fill--2",
      "sh-password-strength-bar__fill--3",
      "sh-password-strength-bar__fill--4",
    ];

    function update() {
      var value = inputEl.value || "";
      var results = passwordRuleResults(value);
      var metCount = 0;

      if (checklistEl) {
        PASSWORD_RULES.forEach(function (rule) {
          var item = checklistEl.querySelector(
            '[data-rule="' + rule.key + '"]',
          );
          if (!item) return;
          var met = results[rule.key];
          if (met) metCount++;
          item.classList.toggle("sh-password-requirements__item--met", met);
          item.classList.toggle("sh-password-requirements__item--unmet", !met);
          var icon = item.querySelector("i");
          if (icon)
            icon.className = met ? "bi bi-check-circle-fill" : "bi bi-circle";
        });
      } else {
        metCount = PASSWORD_RULES.filter(function (rule) {
          return results[rule.key];
        }).length;
      }

      if (meterFillEl) {
        var score = value === "" ? 0 : metCount; // 0-4
        meterFillEl.parentElement.classList.toggle("d-none", value === "");
        STRENGTH_CLASSES.forEach(function (cls) {
          meterFillEl.classList.remove(cls);
        });
        meterFillEl.classList.add(STRENGTH_CLASSES[score]);
        if (meterLabelEl)
          meterLabelEl.textContent = value === "" ? "" : STRENGTH_LABELS[score];
      }

      return results;
    }

    inputEl.addEventListener("input", update);
    update();

    return { update: update, reset: update };
  }

  /** Human-readable label for a field: explicit rule.label, else its <label for>, else the name humanized. */
  function fieldLabel(form, name, rule) {
    if (rule && rule.label) return rule.label;
    var labelEl = form.querySelector(
      'label[for="' + cssEscape(fieldId(form, name)) + '"]',
    );
    if (labelEl) return labelEl.textContent.replace(/\*\s*$/, "").trim();
    return name.replace(/_/g, " ").replace(/\b\w/g, function (c) {
      return c.toUpperCase();
    });
  }

  function fieldEl(form, name) {
    return form.querySelector('[name="' + cssEscape(name) + '"]');
  }

  function fieldId(form, name) {
    var el = fieldEl(form, name);
    return el ? el.id : name;
  }

  function cssEscape(s) {
    return window.CSS && CSS.escape
      ? CSS.escape(s)
      : s.replace(/([^\w-])/g, "\\$1");
  }

  /** Find (or create) the .invalid-feedback element that sits right after this field. */
  function errorContainer(el) {
    var next = el.nextElementSibling;
    if (next && next.classList.contains("invalid-feedback")) {
      return next;
    }
    var div = document.createElement("div");
    div.className = "invalid-feedback";
    if (el.id) div.id = el.id + "-error";
    el.insertAdjacentElement("afterend", div);
    return div;
  }

  function showFieldError(form, name, message) {
    var el = fieldEl(form, name);
    if (!el) return;
    el.classList.add("is-invalid");
    el.setAttribute("aria-invalid", "true");
    var box = errorContainer(el);
    box.textContent = message;
    if (box.id) el.setAttribute("aria-describedby", box.id);
  }

  function clearFieldError(form, name) {
    var el = fieldEl(form, name);
    if (!el) return;
    el.classList.remove("is-invalid");
    el.removeAttribute("aria-invalid");
    var next = el.nextElementSibling;
    if (next && next.classList.contains("invalid-feedback")) {
      next.textContent = "";
    }
  }

  function clearAllErrors(form) {
    form.querySelectorAll(".is-invalid").forEach(function (el) {
      el.classList.remove("is-invalid");
      el.removeAttribute("aria-invalid");
    });
    form.querySelectorAll(".invalid-feedback").forEach(function (box) {
      box.textContent = "";
    });
  }

  /**
   * Validate one field against its rule. Returns an error message string,
   * or null if valid. Does NOT touch the DOM — callers decide whether to
   * render it (so live-validation can choose to only *clear* errors, not
   * add new ones, while the user is still typing).
   */
  function checkField(form, name, rule) {
    var el = fieldEl(form, name);
    if (!el) return null;
    var value = (el.value ?? "").toString().trim();
    var label = fieldLabel(form, name, rule);

    var isEmpty = value === "";

    if (rule.required && isEmpty) {
      return (
        (rule.messages && rule.messages.required) ||
        DEFAULT_MESSAGES.required(label)
      );
    }

    // Everything below only applies when there's a value — optional fields
    // stay valid when left blank.
    if (isEmpty) return null;

    if (rule.type === "email" && !EMAIL_RE.test(value)) {
      return (rule.messages && rule.messages.email) || DEFAULT_MESSAGES.email();
    }

    if (rule.type === "number") {
      if (value !== "" && isNaN(Number(value))) {
        return (
          (rule.messages && rule.messages.number) || DEFAULT_MESSAGES.number()
        );
      }
    }

    if (rule.type === "password" && !isPasswordComplex(value)) {
      return (
        (rule.messages && rule.messages.password) ||
        "Password must meet all requirements below."
      );
    }

    if (rule.minLength && value.length < rule.minLength) {
      return (
        (rule.messages && rule.messages.minLength) ||
        DEFAULT_MESSAGES.minLength(label, rule.minLength)
      );
    }
    if (rule.maxLength && value.length > rule.maxLength) {
      return (
        (rule.messages && rule.messages.maxLength) ||
        DEFAULT_MESSAGES.maxLength(label, rule.maxLength)
      );
    }

    if (
      (rule.min !== undefined || rule.max !== undefined) &&
      !isNaN(Number(value))
    ) {
      var num = Number(value);
      if (rule.min !== undefined && num < rule.min) {
        return (
          (rule.messages && rule.messages.min) ||
          DEFAULT_MESSAGES.min(label, rule.min)
        );
      }
      if (rule.max !== undefined && num > rule.max) {
        return (
          (rule.messages && rule.messages.max) ||
          DEFAULT_MESSAGES.max(label, rule.max)
        );
      }
    }

    if (
      rule.pattern &&
      !(
        rule.pattern instanceof RegExp ? rule.pattern : new RegExp(rule.pattern)
      ).test(value)
    ) {
      return (
        (rule.messages && rule.messages.pattern) ||
        DEFAULT_MESSAGES.pattern(label)
      );
    }

    if (rule.match) {
      var otherEl = fieldEl(form, rule.match);
      if (otherEl && value !== otherEl.value) {
        var otherLabel = fieldLabel(form, rule.match, null);
        return (
          (rule.messages && rule.messages.match) ||
          DEFAULT_MESSAGES.match(label, otherLabel)
        );
      }
    }

    if (typeof rule.custom === "function") {
      var result = rule.custom(value, new FormData(form), el);
      if (result === false)
        return (
          (rule.messages && rule.messages.custom) || label + " is invalid."
        );
      if (typeof result === "string") return result;
    }

    return null;
  }

  /** Validate + render one field. Returns true if valid. */
  function validateField(form, name, schema) {
    var rule = schema[name];
    if (!rule) return true;
    var error = checkField(form, name, rule);
    if (error) {
      showFieldError(form, name, error);
      return false;
    }
    clearFieldError(form, name);
    return true;
  }

  /** Validate the whole form against its schema. Renders every field's state. */
  function validateForm(form, schema) {
    var errors = {};
    var valid = true;
    Object.keys(schema).forEach(function (name) {
      if (!validateField(form, name, schema)) {
        valid = false;
        errors[name] = errorContainer(fieldEl(form, name)).textContent;
      }
    });
    return { valid: valid, errors: errors };
  }

  /** Scroll to + focus the first invalid field (handles fields inside a scrollable modal body). */
  function focusFirstInvalid(form) {
    var first = form.querySelector(".is-invalid");
    if (!first) return;
    if (typeof first.scrollIntoView === "function") {
      first.scrollIntoView({ block: "center", behavior: "smooth" });
    }
    first.focus({ preventScroll: true });
  }

  /**
   * Map a backend { fieldName: 'message' } error object onto the form.
   * Returns the array of messages that didn't match any field on the form
   * (business-rule errors, etc.) so the caller can show those separately.
   */
  function mapBackendErrors(form, fieldErrors) {
    var unmapped = [];
    Object.keys(fieldErrors || {}).forEach(function (name) {
      if (fieldEl(form, name)) {
        showFieldError(form, name, fieldErrors[name]);
      } else {
        unmapped.push(fieldErrors[name]);
      }
    });
    focusFirstInvalid(form);
    return unmapped;
  }

  /**
   * Wire blur/change validation for every field in the schema, plus a
   * "clear as you fix it" listener: once a field is marked invalid, typing
   * that makes it valid again clears the error immediately. We never
   * *introduce* a new error while the user is still typing — only on
   * blur, change, or submit — per the "don't validate too aggressively"
   * requirement.
   */
  function attachLiveValidation(form, schema) {
    Object.keys(schema).forEach(function (name) {
      var el = fieldEl(form, name);
      if (!el) return;
      var eventName =
        el.tagName === "SELECT" || el.type === "checkbox" || el.type === "radio"
          ? "change"
          : "blur";

      el.addEventListener(eventName, function () {
        validateField(form, name, schema);
      });

      el.addEventListener("input", function () {
        if (el.classList.contains("is-invalid")) {
          validateField(form, name, schema);
        }
      });
    });
  }

  window.ShValidator = {
    validateForm: validateForm,
    validateField: validateField,
    showFieldError: showFieldError,
    clearFieldError: clearFieldError,
    clearAllErrors: clearAllErrors,
    mapBackendErrors: mapBackendErrors,
    focusFirstInvalid: focusFirstInvalid,
    attachLiveValidation: attachLiveValidation,
    passwordRuleResults: passwordRuleResults,
    isPasswordComplex: isPasswordComplex,
    attachPasswordStrengthMeter: attachPasswordStrengthMeter,
  };
})();
