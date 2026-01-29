(() => {
  const ACCESS_TOKEN_KEY = "kekcounter.accessToken";
  const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
  const REQUEST_TIMEOUT_MS = 8000;

  const t = (key, vars) => (typeof window.t === "function" ? window.t(key, vars) : key);

  const accessOpen = document.getElementById("accessOpen");
  const accessLogout = document.getElementById("accessLogout");
  const accessDialog = document.getElementById("accessDialog");
  const accessInput = document.getElementById("accessToken");
  const accessSave = document.getElementById("accessSave");
  const accessClear = document.getElementById("accessClear");
  const accessStatus = document.getElementById("accessStatus");

  let accessModal = null;
  let accessValidationTimer = null;
  let accessValidationCounter = 0;

  /** Fetch with a timeout signal. */
  async function fetchWithTimeout(url, options, timeoutMs) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetch(url, { ...options, signal: controller.signal });
      clearTimeout(timeoutId);
      return response;
    } catch (error) {
      clearTimeout(timeoutId);
      throw error;
    }
  }

  /** Read the stored access token from localStorage. */
  function getAccessToken() {
    try {
      return localStorage.getItem(ACCESS_TOKEN_KEY) || "";
    } catch (error) {
      return "";
    }
  }

  /** Read the stored admin token from localStorage. */
  function getAdminToken() {
    try {
      return localStorage.getItem(ADMIN_TOKEN_KEY) || "";
    } catch (error) {
      return "";
    }
  }

  /** Persist the access token and refresh dependent UI. */
  function setAccessToken(token) {
    try {
      localStorage.setItem(ACCESS_TOKEN_KEY, token);
    } catch (error) {
      console.error(error);
    }
    void maybeStoreAdminToken(token);
    refreshAccessStatus();
    if (accessInput) {
      accessInput.value = "";
    }
    window.dispatchEvent(new Event("storage"));
  }

  /** Clear the access token and refresh dependent UI. */
  function clearAccessToken() {
    try {
      localStorage.removeItem(ACCESS_TOKEN_KEY);
    } catch (error) {
      console.error(error);
    }
    refreshAccessStatus();
    if (accessInput) {
      accessInput.value = "";
    }
    window.dispatchEvent(new Event("storage"));
  }

  /** Clear the admin token. */
  function clearAdminToken() {
    try {
      localStorage.removeItem(ADMIN_TOKEN_KEY);
    } catch (error) {
      console.error(error);
    }
    window.dispatchEvent(new Event("storage"));
  }

  /** Verify a token against the backend. */
  async function verifyToken(token) {
    if (!token) {
      return false;
    }
    try {
      const response = await fetchWithTimeout("index.php?action=validate_token", {
        method: "POST",
        cache: "no-store",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "fetch",
        },
        body: JSON.stringify({ token }),
      }, REQUEST_TIMEOUT_MS);
      return response.ok;
    } catch (error) {
      console.error(error);
      return false;
    }
  }

  /** Promote a token to admin if it has admin rights. */
  async function maybeStoreAdminToken(token) {
    if (!token || getAdminToken() === token) {
      return;
    }
    try {
      const response = await fetchWithTimeout("admin.php?action=get_settings", {
        method: "POST",
        cache: "no-store",
        headers: {
          "X-Requested-With": "fetch",
          "X-Admin-Token": token,
        },
      }, REQUEST_TIMEOUT_MS);
      if (response.ok) {
        try {
          localStorage.setItem(ADMIN_TOKEN_KEY, token);
          window.dispatchEvent(new Event("storage"));
        } catch (e) {}
      }
    } catch (error) {
      console.error(error);
    }
  }

  /** Update the visual status in the access modal. */
  function setAccessStatus(message, isError = false) {
    if (!accessStatus) {
      return;
    }
    accessStatus.textContent = message;
    accessStatus.classList.remove("d-none", "alert-secondary", "alert-danger", "alert-success");
    accessStatus.classList.add(isError ? "alert-danger" : "alert-secondary");
  }

  /** Recompute access status from stored tokens. */
  function refreshAccessStatus() {
    const hasAccess = getAccessToken() !== "";
    const hasAdmin = getAdminToken() !== "";

    if (accessInput) {
      const container = accessInput.closest(".mb-3");
      if (container) {
        container.classList.toggle("d-none", hasAccess || hasAdmin);
      }
    }

    if (hasAccess) {
      setAccessStatus(t("token.saved"));
    } else if (hasAdmin) {
      setAccessStatus(t("token.adminSaved"));
    } else {
      setAccessStatus(t("token.noneSaved"));
    }
    updateAccessButtons();
  }

  /** Show/hide the access/logout buttons in the navigation. */
  function updateAccessButtons() {
    const isAuthed = getAccessToken() !== "" || getAdminToken() !== "";
    const authOnly = document.querySelectorAll(".js-auth-only");
    authOnly.forEach((el) => {
      el.classList.toggle("d-none", !isAuthed);
    });
    if (accessLogout) {
      accessLogout.classList.toggle("d-none", !isAuthed);
    }
  }

  /** Open the access modal. */
  function openAccessDialog() {
    if (!accessDialog || !window.bootstrap?.Modal) {
      return false;
    }
    accessModal = bootstrap.Modal.getOrCreateInstance(accessDialog);
    refreshAccessStatus();
    accessModal.show();
    return true;
  }

  /** Close the access modal. */
  function closeAccessDialog() {
    if (accessModal) {
      accessModal.hide();
    } else if (accessDialog && window.bootstrap?.Modal) {
      bootstrap.Modal.getInstance(accessDialog)?.hide();
    }
  }

  /** Debounced token validation while typing. */
  function scheduleAccessValidation() {
    if (!accessInput) {
      return;
    }
    const token = accessInput.value.trim();
    if (!token) {
      refreshAccessStatus();
      return;
    }
    setAccessStatus(t("token.checking"));
    const requestId = ++accessValidationCounter;
    if (accessValidationTimer) {
      clearTimeout(accessValidationTimer);
    }
    accessValidationTimer = setTimeout(async () => {
      const ok = await verifyToken(token);
      if (requestId !== accessValidationCounter) {
        return;
      }
      setAccessStatus(ok ? t("token.valid") : t("token.invalid"), !ok);
    }, 400);
  }

  // Event Listeners
  if (accessOpen) {
    accessOpen.addEventListener("click", openAccessDialog);
  }

  if (accessLogout) {
    accessLogout.addEventListener("click", () => {
      clearAccessToken();
      clearAdminToken();
      closeAccessDialog();
    });
  }

  if (accessSave && accessInput) {
    accessSave.addEventListener("click", async () => {
      const token = accessInput.value.trim();
      if (!token) {
        setAccessStatus(t("token.missing"), true);
        accessInput.focus();
        return;
      }
      setAccessStatus(t("token.checking"));
      const ok = await verifyToken(token);
      if (!ok) {
        setAccessStatus(t("token.invalid"), true);
        return;
      }
      setAccessToken(token);
      closeAccessDialog();
    });
  }

  if (accessClear) {
    accessClear.addEventListener("click", () => {
      clearAccessToken();
    });
  }

  if (accessInput) {
    accessInput.addEventListener("input", scheduleAccessValidation);
    accessInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        accessSave?.click();
      }
    });
  }

  if (accessDialog && accessInput) {
    accessDialog.addEventListener("shown.bs.modal", () => {
      accessInput.focus();
    });
  }

  // Initial update
  refreshAccessStatus();
  window.addEventListener("storage", refreshAccessStatus);

  // Global access object for other scripts
  window.kekAccess = {
    getAccessToken,
    getAdminToken,
    open: openAccessDialog,
    close: closeAccessDialog,
    refresh: refreshAccessStatus
  };
})();
