(() => {
  const t = (key, vars) =>
    typeof window.t === "function" ? window.t(key, vars) : key;

  const dialog = document.getElementById("settingsDialog");
  const openButtons = document.querySelectorAll("[data-settings-open]");
  const THEME_NAME = "settingsTheme";
  const ACCESSIBILITY_NAME = "settingsAccessibility";
  const LANGUAGE_NAME = "settingsLanguage";

  let modal = null;

  /** Read the current settings from helpers/document. */
  function getCurrentState() {
    const themeMode =
      window.kekTheme?.getMode?.() ||
      document.documentElement.getAttribute("data-theme-mode") ||
      "system";
    const accessibility =
      window.kekTheme?.getAccessibility?.() ||
      document.documentElement.getAttribute("data-accessibility") === "true";
    const languageMode =
      window.i18n?.getMode?.() ||
      document.documentElement.getAttribute("data-lang-mode") ||
      "system";
    return {
      themeMode,
      accessibility: accessibility ? "high" : "standard",
      languageMode,
    };
  }

  /** Sync the modal controls with the current settings. */
  function fillForm() {
    const state = getCurrentState();
    setCheckedValue(THEME_NAME, state.themeMode);
    setCheckedValue(ACCESSIBILITY_NAME, state.accessibility);
    setCheckedValue(LANGUAGE_NAME, state.languageMode);
  }

  /** Apply settings from the modal form. */
  function applySettings() {
    const themeMode = getCheckedValue(THEME_NAME, "system");
    const accessibility = getCheckedValue(ACCESSIBILITY_NAME, "standard") === "high";
    const languageMode = getCheckedValue(LANGUAGE_NAME, "system");
    window.kekTheme?.setMode?.(themeMode);
    window.kekTheme?.setAccessibility?.(accessibility);
    window.i18n?.setLanguage?.(languageMode);
  }

  /** Open the modal via Bootstrap. */
  function openDialog() {
    if (!dialog || !window.bootstrap?.Modal) {
      return;
    }
    modal = bootstrap.Modal.getOrCreateInstance(dialog);
    fillForm();
    modal.show();
  }

  openButtons.forEach((button) => {
    button.addEventListener("click", openDialog);
  });

  if (dialog) {
    dialog.addEventListener("shown.bs.modal", () => {
      fillForm();
      const first = dialog.querySelector(`input[name="${THEME_NAME}"]`);
      first?.focus();
    });
  }

  const groups = [THEME_NAME, ACCESSIBILITY_NAME, LANGUAGE_NAME];
  groups.forEach((name) => {
    document.addEventListener("change", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) {
        return;
      }
      if (target.name !== name) {
        return;
      }
      applySettings();
    });
  });

  document.addEventListener("languagechange", () => {
    if (dialog && dialog.classList.contains("show")) {
      fillForm();
    }
  });

  /** Read a checked value from a radio group. */
  function getCheckedValue(name, fallback) {
    const input = document.querySelector(`input[name="${name}"]:checked`);
    return input ? input.value : fallback;
  }

  /** Check the radio input for a given name/value. */
  function setCheckedValue(name, value) {
    const input = document.querySelector(`input[name="${name}"][value="${value}"]`);
    if (input) {
      input.checked = true;
    }
  }
})();
