(() => {
  const STORAGE_KEY = "kekcounter.theme";
  const ACCESS_KEY = "kekcounter.accessibility";
  const root = document.documentElement;
  const THEME_MODES = ["system", "light", "dark"];
  const t = (key, vars) =>
    typeof window.t === "function" ? window.t(key, vars) : key;

  /** Read the stored theme mode from localStorage. */
  function getStoredTheme() {
    try {
      const value = localStorage.getItem(STORAGE_KEY);
      return value === "light" || value === "dark" || value === "system"
        ? value
        : "";
    } catch (error) {
      return "";
    }
  }

  /** Read the stored accessibility preference from localStorage. */
  function getStoredAccessibility() {
    try {
      const value = localStorage.getItem(ACCESS_KEY);
      return value === "true" ? true : value === "false" ? false : null;
    } catch (error) {
      return null;
    }
  }

  /** Persist the selected theme mode. */
  function setStoredTheme(theme) {
    try {
      if (THEME_MODES.includes(theme)) {
        localStorage.setItem(STORAGE_KEY, theme);
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch (error) {
      return;
    }
  }

  /** Persist the accessibility preference. */
  function setStoredAccessibility(enabled) {
    try {
      localStorage.setItem(ACCESS_KEY, enabled ? "true" : "false");
    } catch (error) {
      return;
    }
  }

  /** Resolve the OS-preferred color scheme. */
  function getPreferredTheme() {
    if (window.matchMedia?.("(prefers-color-scheme: dark)").matches) {
      return "dark";
    }
    return "light";
  }

  /** Resolve OS-level high-contrast preferences. */
  function getPreferredAccessibility() {
    if (window.matchMedia?.("(prefers-contrast: more)").matches) {
      return true;
    }
    if (window.matchMedia?.("(forced-colors: active)").matches) {
      return true;
    }
    return false;
  }

  /** Update theme toggle labels/icons for the current mode. */
  function updateToggleButtons(mode, theme) {
    const buttons = document.querySelectorAll("[data-theme-toggle]");
    const labelText =
      mode === "system"
        ? t("theme.system")
        : theme === "dark"
          ? t("theme.dark")
          : t("theme.light");
    const iconClass =
      mode === "system"
        ? "bi-circle-half"
        : theme === "dark"
          ? "bi-moon-stars"
          : "bi-sun";
    buttons.forEach((button) => {
      const labelEl = button.querySelector("[data-theme-label]");
      if (labelEl) {
        labelEl.textContent = labelText;
      } else {
        button.textContent = labelText;
      }
      const icon = button.querySelector("[data-theme-icon]");
      if (icon) {
        icon.classList.remove("bi-moon-stars", "bi-sun", "bi-circle-half");
        icon.classList.add(iconClass);
      }
      button.setAttribute("aria-label", t("theme.toggle"));
      button.setAttribute("title", t("theme.toggle"));
      if (mode === "system") {
        button.setAttribute("aria-pressed", "mixed");
      } else {
        button.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
      }
    });
  }

  /** Update accessibility toggle labels/icons. */
  function updateAccessibilityButtons(enabled) {
    const buttons = document.querySelectorAll("[data-accessibility-toggle]");
    const nextLabel = enabled ? t("accessibility.on") : t("accessibility.off");
    buttons.forEach((button) => {
      const label = button.querySelector("[data-accessibility-label]");
      if (label) {
        label.textContent = nextLabel;
      } else {
        button.textContent = nextLabel;
      }
      const icon = button.querySelector("[data-accessibility-icon]");
      if (icon) {
        icon.classList.toggle("bi-universal-access", !enabled);
        icon.classList.toggle("bi-universal-access-circle", enabled);
      }
      button.setAttribute("aria-label", t("accessibility.toggle"));
      button.setAttribute("title", t("accessibility.toggle"));
      button.setAttribute("aria-pressed", enabled ? "true" : "false");
    });
  }

  /** Resolve a theme mode to the actual theme value. */
  function resolveTheme(mode) {
    if (mode === "light" || mode === "dark") {
      return mode;
    }
    return getPreferredTheme();
  }

  /** Apply the theme and emit a themechange event. */
  function applyTheme(mode, persist) {
    const theme = resolveTheme(mode);
    root.setAttribute("data-bs-theme", theme);
    root.setAttribute("data-theme-mode", mode);
    updateToggleButtons(mode, theme);
    if (persist) {
      setStoredTheme(mode);
    }
    document.dispatchEvent(
      new CustomEvent("themechange", { detail: { theme, mode } })
    );
  }

  /** Apply accessibility mode and emit a change event. */
  function applyAccessibility(enabled, persist) {
    root.setAttribute("data-accessibility", enabled ? "true" : "false");
    updateAccessibilityButtons(enabled);
    if (persist) {
      setStoredAccessibility(enabled);
    }
    document.dispatchEvent(
      new CustomEvent("accessibilitychange", { detail: { enabled } })
    );
  }

  /** Cycle through theme modes. */
  function toggleTheme() {
    const current = root.getAttribute("data-theme-mode") || getStoredTheme() || "system";
    const index = THEME_MODES.indexOf(current);
    const next = THEME_MODES[(index + 1) % THEME_MODES.length];
    applyTheme(next, true);
  }

  /** Toggle accessibility mode. */
  function toggleAccessibility() {
    const current = root.getAttribute("data-accessibility") === "true";
    applyAccessibility(!current, true);
  }

  /** Initialize theme/accessibility state and listeners. */
  function initTheme() {
    const stored = getStoredTheme();
    const mode = stored || "system";
    applyTheme(mode, false);

    const storedAccessibility = getStoredAccessibility();
    const accessibility =
      storedAccessibility !== null
        ? storedAccessibility
        : getPreferredAccessibility();
    applyAccessibility(accessibility, false);

    const buttons = document.querySelectorAll("[data-theme-toggle]");
    buttons.forEach((button) => {
      button.addEventListener("click", toggleTheme);
    });

    const a11yButtons = document.querySelectorAll(
      "[data-accessibility-toggle]"
    );
    a11yButtons.forEach((button) => {
      button.addEventListener("click", toggleAccessibility);
    });

    const media = window.matchMedia?.("(prefers-color-scheme: dark)");
    if (media?.addEventListener) {
      media.addEventListener("change", (event) => {
        const mode = getStoredTheme();
        if (mode === "dark" || mode === "light") {
          return;
        }
        applyTheme("system", false);
      });
    }

    const contrastMedia = window.matchMedia?.("(prefers-contrast: more)");
    if (contrastMedia?.addEventListener) {
      contrastMedia.addEventListener("change", (event) => {
        if (getStoredAccessibility() !== null) {
          return;
        }
        applyAccessibility(event.matches, false);
      });
    }

    document.addEventListener("languagechange", () => {
      const mode = root.getAttribute("data-theme-mode") || getStoredTheme() || "system";
      updateToggleButtons(mode, resolveTheme(mode));
      const enabled = root.getAttribute("data-accessibility") === "true";
      updateAccessibilityButtons(enabled);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTheme);
  } else {
    initTheme();
  }

  window.kekTheme = {
    setMode(mode) {
      applyTheme(mode, true);
    },
    setAccessibility(enabled) {
      applyAccessibility(Boolean(enabled), true);
    },
    getMode() {
      return root.getAttribute("data-theme-mode") || getStoredTheme() || "system";
    },
    getAccessibility() {
      return root.getAttribute("data-accessibility") === "true";
    },
  };
})();
