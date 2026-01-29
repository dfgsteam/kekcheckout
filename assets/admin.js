const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
const t = (key, vars) =>
  typeof window.t === "function" ? window.t(key, vars) : key;

const adminTokenInput = document.getElementById("adminToken");
const adminSave = document.getElementById("adminSave");
const adminClear = document.getElementById("adminClear");
const adminStatus = document.getElementById("adminStatus");
const adminAuthCard = document.getElementById("adminAuthCard");
const adminContent = document.getElementById("adminContent");
const adminLogout = document.getElementById("adminLogout") || document.getElementById("accessLogout");

const restartBtn = document.getElementById("restartEvent");
const restartStatus = document.getElementById("restartStatus");
const eventNameInput = document.getElementById("eventNameInput");
const eventNameSave = document.getElementById("eventNameSave");
const eventNameStatus = document.getElementById("eventNameStatus");

const downloadLatest = document.getElementById("downloadLatest");
const refreshArchives = document.getElementById("adminArchiveRefresh");
const archiveStatus = document.getElementById("adminArchiveStatus");
const archiveList = document.getElementById("adminArchiveList");
const archiveSearchInput = document.getElementById("adminArchiveSearch");
const archiveSortSelect = document.getElementById("archiveSort");
const logRefresh = document.getElementById("logRefresh");
const logDownload = document.getElementById("logDownload");
const logStatus = document.getElementById("logStatus");
const logList = document.getElementById("logList");
const logSearchInput = document.getElementById("logSearch");
const logStatusFilter = document.getElementById("logStatusFilter");
const logLimitSelect = document.getElementById("logLimit");

const accessTokenNameInput = document.getElementById("accessTokenNameNew");
const accessTokenInput = document.getElementById("accessTokenNew");
const accessTokenAdd = document.getElementById("accessTokenAdd");
const accessTokenList = document.getElementById("accessTokenList");
const accessTokenStatus = document.getElementById("accessTokenStatus");
const adminTokenInputNew = document.getElementById("adminTokenNew");
const adminTokenSave = document.getElementById("adminTokenSave");
const adminTokenStatus = document.getElementById("adminTokenStatus");
const settingsThreshold = document.getElementById("settingsThreshold");
const settingsMaxPoints = document.getElementById("settingsMaxPoints");
const settingsChartMaxPoints = document.getElementById("settingsChartMaxPoints");
const settingsWindowHours = document.getElementById("settingsWindowHours");
const settingsTickMinutes = document.getElementById("settingsTickMinutes");
const settingsCapacityDefault = document.getElementById("settingsCapacityDefault");
const settingsStornoMinutes = document.getElementById("settingsStornoMinutes");
const settingsStornoBack = document.getElementById("settingsStornoBack");
const settingsTabletTypeReset = document.getElementById("settingsTabletTypeReset");
const settingsSave = document.getElementById("settingsSave");
const settingsStatus = document.getElementById("settingsStatus");
const toastContainer = document.getElementById("toastContainer");
const ACTION_COOLDOWN_MS = 800;
let lastActionAt = 0;
let adminAuthenticated = false;
let archiveItems = [];
let logItems = [];
let logTotal = 0;
let adminValidationTimer = null;
let adminValidationCounter = 0;

/** Read the stored admin token from localStorage. */
function getAdminToken() {
  try {
    return localStorage.getItem(ADMIN_TOKEN_KEY) || "";
  } catch (error) {
    return "";
  }
}

/** Persist the admin token and refresh auth state. */
function setAdminToken(token) {
  try {
    localStorage.setItem(ADMIN_TOKEN_KEY, token);
  } catch (error) {
    console.error(error);
  }
  setAdminAuthState(false);
  refreshAdminStatus();
  if (adminTokenInput) {
    adminTokenInput.value = "";
  }
}

/** Clear the admin token and reset auth state. */
function clearAdminToken() {
  try {
    localStorage.removeItem(ADMIN_TOKEN_KEY);
  } catch (error) {
    console.error(error);
  }
  setAdminAuthState(false);
  refreshAdminStatus();
}

/** Update a status element with optional error styling. */
function setStatus(el, message, isError = false) {
  if (!el) {
    return;
  }
  el.textContent = message;
  el.className = isError ? "text-danger small mt-2" : "text-secondary small mt-2";
}

/** Guard against repeated actions in quick succession. */
function canRunAction() {
  const now = Date.now();
  if (now - lastActionAt < ACTION_COOLDOWN_MS) {
    return false;
  }
  lastActionAt = now;
  return true;
}

/** Show a Bootstrap toast message if available. */
function showToast(message, variant = "secondary") {
  if (!toastContainer || !window.bootstrap?.Toast) {
    return;
  }
  const toast = document.createElement("div");
  toast.className = `toast align-items-center text-bg-${variant} border-0`;
  toast.setAttribute("role", "status");
  toast.setAttribute("aria-live", "polite");
  toast.setAttribute("aria-atomic", "true");
  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="${t("common.close")}"></button>
    </div>
  `;
  toastContainer.appendChild(toast);
  const instance = bootstrap.Toast.getOrCreateInstance(toast, { delay: 2500 });
  toast.addEventListener("hidden.bs.toast", () => {
    toast.remove();
  });
  instance.show();
}

/** Toggle a loading state on a text button. */
function setButtonLoading(button, loading, label) {
  if (!button) {
    return;
  }
  if (loading) {
    if (!button.dataset.originalContent) {
      button.dataset.originalContent = button.innerHTML;
    }
    const text = label || button.getAttribute("data-loading-label") || t("common.loading");
    button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${text}`;
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    return;
  }
  if (button.dataset.originalContent) {
    button.innerHTML = button.dataset.originalContent;
    delete button.dataset.originalContent;
  }
  button.disabled = false;
  button.removeAttribute("aria-busy");
}

/** Toggle a loading state on an icon-only button. */
function setIconButtonLoading(button, loading) {
  if (!button) {
    return;
  }
  if (loading) {
    if (!button.dataset.originalContent) {
      button.dataset.originalContent = button.innerHTML;
    }
    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    return;
  }
  if (button.dataset.originalContent) {
    button.innerHTML = button.dataset.originalContent;
    delete button.dataset.originalContent;
  }
  button.disabled = false;
  button.removeAttribute("aria-busy");
}

/** Initialize Bootstrap tooltips within a container. */
function applyTooltips(scope = document) {
  if (!window.bootstrap?.Tooltip) {
    return;
  }
  const elements = scope.querySelectorAll('[data-bs-toggle="tooltip"]');
  elements.forEach((el) => {
    bootstrap.Tooltip.getOrCreateInstance(el);
  });
}

/** Verify the admin token via the admin API. */
async function verifyAdminToken(token) {
  try {
    const response = await fetch("admin.php?action=get_settings", {
      method: "GET",
      cache: "no-store",
      headers: {
        "X-Requested-With": "fetch",
        "X-Admin-Token": token,
      },
    });
    return response.ok;
  } catch (error) {
    console.error(error);
    return false;
  }
}

/** Debounced token validation while typing. */
function scheduleAdminValidation() {
  if (!adminTokenInput) {
    return;
  }
  const token = adminTokenInput.value.trim();
  if (!token) {
    refreshAdminStatus();
    return;
  }
  setStatus(adminStatus, t("token.adminChecking"));
  const requestId = ++adminValidationCounter;
  if (adminValidationTimer) {
    clearTimeout(adminValidationTimer);
  }
  adminValidationTimer = setTimeout(async () => {
    const ok = await verifyAdminToken(token);
    if (requestId !== adminValidationCounter) {
      return;
    }
    setStatus(adminStatus, ok ? t("token.adminValid") : t("token.adminInvalid"), !ok);
  }, 400);
}

/** Toggle admin-only sections based on auth state. */
function setAdminAuthState(isAuthenticated) {
  adminAuthenticated = isAuthenticated;
  if (adminAuthCard) {
    adminAuthCard.classList.toggle("d-none", isAuthenticated);
  }
  if (adminContent) {
    adminContent.classList.toggle("d-none", !isAuthenticated);
  }
  if (adminLogout) {
    adminLogout.classList.toggle("d-none", !isAuthenticated);
  }
}

/** Update admin status text based on stored state. */
function refreshAdminStatus() {
  const hasToken = getAdminToken() !== "";
  if (!hasToken) {
    setAdminAuthState(false);
  }
  const message = adminAuthenticated
    ? t("token.adminVerified")
    : hasToken
      ? t("token.adminSaved")
      : t("token.noneSaved");
  setStatus(adminStatus, message);
}

/** Ensure an admin token exists or prompt the user. */
function requireAdminToken() {
  const token = getAdminToken();
  if (!token) {
    setStatus(adminStatus, t("token.adminRequired"), true);
    setAdminAuthState(false);
    if (adminTokenInput) {
      adminTokenInput.focus();
    }
    return "";
  }
  return token;
}

/** Perform an authenticated admin API request. */
async function adminFetch(action, options = {}) {
  const token = requireAdminToken();
  if (!token) {
    throw new Error("Admin token missing");
  }
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  const headers = {
    "X-Requested-With": "fetch",
    "X-Admin-Token": token,
  };
  if (csrfToken) {
    headers["X-CSRF-TOKEN"] = csrfToken;
  }
  const fetchOptions = {
    method: options.method || "POST",
    headers,
    cache: "no-store",
  };
  if (options.body) {
    headers["Content-Type"] = "application/json";
    fetchOptions.body = JSON.stringify(options.body);
  }
  const response = await fetch(`admin.php?action=${action}`, fetchOptions);
  if (!response.ok) {
    if (response.status === 403) {
      setStatus(adminStatus, t("token.adminInvalid"), true);
      setAdminAuthState(false);
    }
    if (response.status === 503) {
      setStatus(adminStatus, t("token.adminMissingServer"), true);
      setAdminAuthState(false);
    }
    throw new Error("Request failed");
  }
  setAdminAuthState(true);
  refreshAdminStatus();
  return response;
}

/** Format bytes into a compact string. */
function formatBytes(size) {
  if (size < 1024) {
    return `${size} B`;
  }
  const kb = size / 1024;
  if (kb < 1024) {
    return `${kb.toFixed(1)} KB`;
  }
  const mb = kb / 1024;
  return `${mb.toFixed(1)} MB`;
}

/** Sort archive items based on the selected sort key. */
function sortArchives(items, sort) {
  const list = [...items];
  const getTime = (item) => new Date(item.modified).getTime() || 0;
  const getName = (item) => item.name.toLowerCase();
  const getSize = (item) => item.size || 0;
  list.sort((a, b) => {
    switch (sort) {
      case "modified_asc":
        return getTime(a) - getTime(b);
      case "name_asc":
        return getName(a).localeCompare(getName(b));
      case "name_desc":
        return getName(b).localeCompare(getName(a));
      case "size_desc":
        return getSize(b) - getSize(a);
      case "size_asc":
        return getSize(a) - getSize(b);
      case "modified_desc":
      default:
        return getTime(b) - getTime(a);
    }
  });
  return list;
}

/** Filter and sort archive items using UI filters. */
function getFilteredArchives(items) {
  const query = archiveSearchInput ? archiveSearchInput.value.trim().toLowerCase() : "";
  const filtered = query
    ? items.filter((item) => item.name.toLowerCase().includes(query))
    : items;
  const sortValue = archiveSortSelect ? archiveSortSelect.value : "modified_desc";
  return sortArchives(filtered, sortValue);
}

/** Render an archive empty state with optional action. */
function renderArchiveEmptyState(message, actionLabel, actionHandler) {
  if (!archiveList) {
    return;
  }
  archiveList.innerHTML = "";
  const li = document.createElement("li");
  const wrap = document.createElement("div");
  li.className = "list-group-item text-secondary small";
  wrap.className = "d-flex flex-column gap-2";
  const text = document.createElement("div");
  text.textContent = message;
  wrap.appendChild(text);
  if (actionLabel && typeof actionHandler === "function") {
    const button = document.createElement("button");
    button.className = "btn btn-sm btn-outline-secondary align-self-start";
    button.type = "button";
    button.textContent = actionLabel;
    button.addEventListener("click", actionHandler);
    wrap.appendChild(button);
  }
  li.appendChild(wrap);
  archiveList.appendChild(li);
}

/** Apply filters and render the archive list view. */
function updateArchiveView() {
  if (!archiveItems.length) {
    setStatus(archiveStatus, t("archive.none"));
    renderArchiveEmptyState(t("archive.noneMessage"), t("common.listRefresh"), () => {
      loadArchives();
    });
    return;
  }
  const filtered = getFilteredArchives(archiveItems);
  const query = archiveSearchInput ? archiveSearchInput.value.trim() : "";
  if (!filtered.length) {
    setStatus(archiveStatus, t("archive.noMatches"));
    renderArchiveEmptyState(
      query
        ? t("archive.noMatchesQuery", { query })
        : t("archive.noMatchesGeneric"),
      t("common.filterReset"),
      () => {
        if (archiveSearchInput) {
          archiveSearchInput.value = "";
        }
        updateArchiveView();
      }
    );
    return;
  }
  const label = query
    ? t("archive.countFiltered", { count: filtered.length, total: archiveItems.length })
    : t("archive.countAll", { count: filtered.length });
  setStatus(archiveStatus, label);
  renderArchives(filtered);
}

/** Format a log timestamp into a locale string. */
function formatLogTime(value) {
  if (!value) {
    return "-";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleString();
}

/** Map status codes to Bootstrap variants. */
function getStatusVariant(status) {
  if (status >= 500) {
    return "danger";
  }
  if (status >= 400) {
    return "warning";
  }
  if (status >= 200) {
    return "success";
  }
  return "secondary";
}

/** Build a compact log metadata string. */
function buildLogMeta(entry) {
  if (entry.error) {
    return `${t("log.meta.error")}: ${entry.error}`;
  }
  if (entry.ok) {
    return t("log.meta.ok");
  }
  const parts = [];
  if (entry.delta !== undefined) {
    parts.push(`${t("log.meta.delta")} ${entry.delta}`);
  }
  if (entry.count !== undefined) {
    parts.push(`${t("log.meta.count")} ${entry.count}`);
  }
  if (entry.name) {
    parts.push(`${t("log.meta.name")} ${entry.name}`);
  }
  if (entry.archiveName) {
    parts.push(`${t("log.meta.archive")} ${entry.archiveName}`);
  }
  return parts.length ? parts.join(", ") : "-";
}

/** Render a log empty state with optional action. */
function renderLogEmpty(message, actionLabel, actionHandler) {
  if (!logList) {
    return;
  }
  logList.innerHTML = "";
  const row = document.createElement("tr");
  const cell = document.createElement("td");
  const wrap = document.createElement("div");
  cell.colSpan = 5;
  cell.className = "text-secondary small";
  wrap.className = "d-flex flex-column gap-2";
  const text = document.createElement("div");
  text.textContent = message;
  wrap.appendChild(text);
  if (actionLabel && typeof actionHandler === "function") {
    const button = document.createElement("button");
    button.className = "btn btn-sm btn-outline-secondary align-self-start";
    button.type = "button";
    button.textContent = actionLabel;
    button.addEventListener("click", actionHandler);
    wrap.appendChild(button);
  }
  cell.appendChild(wrap);
  row.appendChild(cell);
  logList.appendChild(row);
}

/** Render log rows into the table. */
function renderLogs(items) {
  if (!logList) {
    return;
  }
  logList.innerHTML = "";
  if (!items.length) {
    renderLogEmpty(t("log.none"));
    return;
  }
  items.forEach((entry) => {
    const row = document.createElement("tr");
    const timeCell = document.createElement("td");
    const actionCell = document.createElement("td");
    const statusCell = document.createElement("td");
    const ipCell = document.createElement("td");
    const infoCell = document.createElement("td");

    const statusValue = Number(entry.status);
    const actionText = entry.action || entry.raw || "-";
    const metaText = buildLogMeta(entry);

    timeCell.textContent = formatLogTime(entry.ts);
    actionCell.textContent = actionText;
    actionCell.className = "log-action";
    if (entry.ua) {
      actionCell.title = entry.ua;
    }
    if (Number.isFinite(statusValue)) {
      const badge = document.createElement("span");
      const variant = getStatusVariant(statusValue);
      badge.className = `badge text-bg-${variant}`;
      badge.textContent = String(statusValue);
      statusCell.appendChild(badge);
    } else {
      statusCell.textContent = "-";
    }
    ipCell.textContent = entry.ip || "-";
    infoCell.textContent = metaText;
    infoCell.className = "log-meta";
    if (entry.error) {
      infoCell.classList.add("text-danger");
    }

    row.appendChild(timeCell);
    row.appendChild(actionCell);
    row.appendChild(statusCell);
    row.appendChild(ipCell);
    row.appendChild(infoCell);
    logList.appendChild(row);
  });
}

/** Check if a log entry matches the status filter. */
function matchesLogStatus(entry, filter) {
  const status = Number(entry.status);
  if (filter === "error") {
    return Boolean(entry.error);
  }
  if (!Number.isFinite(status)) {
    return filter === "all";
  }
  if (filter === "2xx") {
    return status >= 200 && status < 300;
  }
  if (filter === "4xx") {
    return status >= 400 && status < 500;
  }
  if (filter === "5xx") {
    return status >= 500 && status < 600;
  }
  return true;
}

/** Filter log entries using search and status filters. */
function getFilteredLogs() {
  const query = logSearchInput ? logSearchInput.value.trim().toLowerCase() : "";
  const statusFilter = logStatusFilter ? logStatusFilter.value : "all";
  return logItems.filter((entry) => {
    if (!matchesLogStatus(entry, statusFilter)) {
      return false;
    }
    if (!query) {
      return true;
    }
    const haystack = [
      entry.action,
      entry.ip,
      entry.error,
      entry.ua,
      entry.raw,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    return haystack.includes(query);
  });
}

/** Apply filters and render the log view. */
function updateLogView() {
  if (!logItems.length) {
    setStatus(logStatus, t("log.entries.none"));
    renderLogEmpty(t("log.noneMessage"), t("common.refresh"), () => {
      loadLogs();
    });
    return;
  }
  const filtered = getFilteredLogs();
  const query = logSearchInput ? logSearchInput.value.trim() : "";
  const statusFilter = logStatusFilter ? logStatusFilter.value : "all";
  const hasFilter = query !== "" || statusFilter !== "all";
  if (!filtered.length) {
    setStatus(logStatus, t("common.noMatches"));
    renderLogEmpty(
      t("log.noMatches"),
      t("common.filterReset"),
      () => {
        if (logSearchInput) {
          logSearchInput.value = "";
        }
        if (logStatusFilter) {
          logStatusFilter.value = "all";
        }
        updateLogView();
      }
    );
    return;
  }
  const label = hasFilter
    ? t("log.entries.countFiltered", { count: filtered.length, total: logTotal })
    : t("log.entries.count", { count: logTotal });
  setStatus(logStatus, label);
  renderLogs(filtered);
}

/** Render archive list items and actions. */
function renderArchives(items) {
  if (!archiveList) {
    return;
  }
  archiveList.innerHTML = "";
  items.forEach((item) => {
    const li = document.createElement("li");
    const info = document.createElement("div");
    const name = document.createElement("span");
    const meta = document.createElement("span");
    const actions = document.createElement("div");
    const renameButton = document.createElement("button");
    const downloadButton = document.createElement("button");
    const deleteButton = document.createElement("button");
    const renameIcon = document.createElement("i");
    const downloadIcon = document.createElement("i");
    const deleteIcon = document.createElement("i");
    li.className = "list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2";
    info.className = "d-flex flex-column flex-grow-1";
    name.className = "fw-semibold";
    meta.className = "text-secondary small";
    actions.className = "d-flex align-items-center gap-2";
    renameButton.className = "btn btn-sm btn-outline-secondary";
    downloadButton.className = "btn btn-sm btn-outline-secondary";
    deleteButton.className = "btn btn-sm btn-outline-danger";
    renameButton.type = "button";
    downloadButton.type = "button";
    deleteButton.type = "button";
    renameButton.setAttribute("aria-label", t("archive.rename"));
    downloadButton.setAttribute("aria-label", t("common.download"));
    deleteButton.setAttribute("aria-label", t("common.delete"));
    renameButton.setAttribute("title", t("archive.rename"));
    downloadButton.setAttribute("title", t("common.download"));
    deleteButton.setAttribute("title", t("common.delete"));
    renameButton.setAttribute("data-bs-toggle", "tooltip");
    downloadButton.setAttribute("data-bs-toggle", "tooltip");
    deleteButton.setAttribute("data-bs-toggle", "tooltip");
    renameIcon.className = "bi bi-pencil";
    downloadIcon.className = "bi bi-download";
    deleteIcon.className = "bi bi-trash";
    renameIcon.setAttribute("aria-hidden", "true");
    downloadIcon.setAttribute("aria-hidden", "true");
    deleteIcon.setAttribute("aria-hidden", "true");
    name.textContent = item.name;
    meta.textContent = `${new Date(item.modified).toLocaleString()} • ${formatBytes(
      item.size
    )}`;
    renameButton.appendChild(renameIcon);
    downloadButton.appendChild(downloadIcon);
    deleteButton.appendChild(deleteIcon);
    renameButton.addEventListener("click", () => {
      renameArchive(item.name, renameButton);
    });
    downloadButton.addEventListener("click", () => {
      downloadArchive(item.name, downloadButton);
    });
    deleteButton.addEventListener("click", () => {
      deleteArchive(item.name, deleteButton);
    });
    info.appendChild(name);
    info.appendChild(meta);
    actions.appendChild(renameButton);
    actions.appendChild(downloadButton);
    actions.appendChild(deleteButton);
    li.appendChild(info);
    li.appendChild(actions);
    archiveList.appendChild(li);
  });
  applyTooltips(archiveList);
}

/** Load archive metadata from the backend. */
async function loadArchives() {
  setStatus(archiveStatus, t("archive.loading"));
  try {
    const response = await adminFetch("list_archives", { method: "POST" });
    const data = await response.json();
    const items = Array.isArray(data.archives) ? data.archives : [];
    archiveItems = items;
    updateArchiveView();
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("archive.loadFailed"), true);
  }
}

/** Load recent request log entries from the backend. */
async function loadLogs() {
  if (!logStatus) {
    return;
  }
  const limit = logLimitSelect ? Number(logLimitSelect.value) : 200;
  setStatus(logStatus, t("log.loading"));
  try {
    const response = await adminFetch("list_logs", {
      method: "POST",
      body: { limit: Number.isFinite(limit) ? limit : 200 },
    });
    const data = await response.json();
    logItems = Array.isArray(data.entries) ? data.entries : [];
    logTotal = Number.isFinite(Number(data.total)) ? Number(data.total) : logItems.length;
    updateLogView();
  } catch (error) {
    console.error(error);
    setStatus(logStatus, t("log.loadFailed"), true);
  }
}

/** Download the request log file. */
async function downloadLog() {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  setStatus(logStatus, t("download.starting"));
  setButtonLoading(logDownload, true, t("common.download"));
  try {
    const response = await adminFetch("download_log");
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "request.log";
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus(logStatus, t("download.started"));
    showToast(t("log.download.started"), "success");
  } catch (error) {
    console.error(error);
    setStatus(logStatus, t("log.download.failed"), true);
    showToast(t("log.download.failed"), "danger");
  } finally {
    setButtonLoading(logDownload, false);
  }
}

/** Download the newest archive CSV. */
async function downloadLatestArchive(triggerButton) {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  setStatus(archiveStatus, t("download.starting"));
  setButtonLoading(triggerButton, true, t("common.download"));
  try {
    const response = await adminFetch("download_latest");
    const blob = await response.blob();
    const disposition = response.headers.get("Content-Disposition") || "";
    const match = /filename="([^"]+)"/.exec(disposition);
    const filename = match ? match[1] : "visitors.csv";
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus(archiveStatus, t("download.done"));
    showToast(t("download.started"), "success");
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("download.failed"), true);
    showToast(t("download.failed"), "danger");
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Download a selected archive CSV. */
async function downloadArchive(name, triggerButton) {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  setStatus(archiveStatus, t("download.starting"));
  setIconButtonLoading(triggerButton, true);
  try {
    const response = await adminFetch("download_archive", { body: { name } });
    const blob = await response.blob();
    const disposition = response.headers.get("Content-Disposition") || "";
    const match = /filename="([^"]+)"/.exec(disposition);
    const filename = match ? match[1] : name;
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus(archiveStatus, t("download.done"));
    showToast(t("download.started"), "success");
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("download.failed"), true);
    showToast(t("download.failed"), "danger");
  } finally {
    setIconButtonLoading(triggerButton, false);
  }
}

/** Rename an archive entry. */
async function renameArchive(name, triggerButton) {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  const base = name.replace(/\.csv$/i, "");
  const next = window.prompt(t("archive.renamePrompt"), base);
  if (next === null) {
    return;
  }
  setStatus(archiveStatus, t("archive.renaming"));
  setIconButtonLoading(triggerButton, true);
  try {
    const response = await adminFetch("rename_archive", {
      body: { name, newName: next },
    });
    const data = await response.json();
    const newName = typeof data?.name === "string" ? data.name : name;
    setStatus(archiveStatus, t("archive.renamed", { name: newName }));
    loadArchives();
    showToast(t("archive.renamedToast"), "success");
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("archive.renameFailed"), true);
    showToast(t("archive.renameFailed"), "danger");
  } finally {
    setIconButtonLoading(triggerButton, false);
  }
}

/** Delete a selected archive entry. */
async function deleteArchive(name, triggerButton) {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  if (!window.confirm(t("archive.deleteConfirm", { name }))) {
    return;
  }
  setStatus(archiveStatus, t("archive.deleting"));
  setIconButtonLoading(triggerButton, true);
  try {
    await adminFetch("delete_archive", { body: { name } });
    setStatus(archiveStatus, t("archive.deleted"));
    loadArchives();
    showToast(t("archive.deleted"), "success");
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("archive.deleteFailed"), true);
    showToast(t("archive.deleteFailed"), "danger");
  } finally {
    setIconButtonLoading(triggerButton, false);
  }
}

/** Parse a numeric settings input value. */
function readSetting(input) {
  if (!input) {
    return null;
  }
  const value = Number(input.value);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }
  return Math.trunc(value);
}

/** Fill settings inputs from loaded settings. */
function fillSettings(settings) {
  if (settingsThreshold) {
    settingsThreshold.value = settings.threshold ?? "";
  }
  if (settingsMaxPoints) {
    settingsMaxPoints.value = settings.max_points ?? "";
  }
  if (settingsChartMaxPoints) {
    settingsChartMaxPoints.value = settings.chart_max_points ?? "";
  }
  if (settingsWindowHours) {
    settingsWindowHours.value = settings.window_hours ?? "";
  }
  if (settingsTickMinutes) {
    settingsTickMinutes.value = settings.tick_minutes ?? "";
  }
  if (settingsCapacityDefault) {
    settingsCapacityDefault.value = settings.capacity_default ?? "";
  }
  if (settingsStornoMinutes) {
    settingsStornoMinutes.value = settings.storno_max_minutes ?? "";
  }
  if (settingsStornoBack) {
    settingsStornoBack.value = settings.storno_max_back ?? "";
  }
  if (settingsTabletTypeReset) {
    settingsTabletTypeReset.value = settings.tablet_type_reset ?? "";
  }
}

/** Fill the event name input field. */
function fillEventName(name) {
  if (eventNameInput) {
    eventNameInput.value = name || "";
  }
}

/** Load the current event name from the backend. */
async function loadEventName() {
  if (!eventNameInput && !eventNameStatus) {
    return;
  }
  if (eventNameStatus) {
    setStatus(eventNameStatus, t("event.name.loading"));
  }
  try {
    const response = await adminFetch("get_event_name", { method: "GET" });
    const data = await response.json();
    const name = typeof data?.eventName === "string" ? data.eventName : "";
    fillEventName(name);
    if (eventNameStatus) {
      setStatus(eventNameStatus, name ? t("event.name.loaded") : t("event.name.none"));
    }
  } catch (error) {
    console.error(error);
    if (eventNameStatus) {
      setStatus(eventNameStatus, t("event.name.loadFailed"), true);
    }
  }
}

/** Save the event name to the backend. */
async function saveEventName(triggerButton) {
  if (!eventNameInput) {
    return;
  }
  const name = eventNameInput.value.trim();
  if (eventNameStatus) {
    setStatus(eventNameStatus, t("common.saving"));
  }
  setButtonLoading(triggerButton, true, t("common.savingShort"));
  try {
    const response = await adminFetch("set_event_name", { body: { name } });
    const data = await response.json();
    const saved = typeof data?.eventName === "string" ? data.eventName : "";
    fillEventName(saved);
    if (eventNameStatus) {
      setStatus(eventNameStatus, saved ? t("event.name.saved") : t("event.name.removed"));
    }
  } catch (error) {
    console.error(error);
    if (eventNameStatus) {
      setStatus(eventNameStatus, t("common.saveFailed"), true);
    }
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Load settings from the backend. */
async function loadSettings() {
  if (!settingsStatus) {
    return;
  }
  setStatus(settingsStatus, t("settings.loading"));
  try {
    const response = await adminFetch("get_settings", { method: "GET" });
    const data = await response.json();
    if (data && typeof data === "object") {
      fillSettings(data);
      setStatus(settingsStatus, t("settings.loaded"));
      return;
    }
    setStatus(settingsStatus, t("settings.loadFailed"), true);
  } catch (error) {
    console.error(error);
    setStatus(settingsStatus, t("settings.loadFailed"), true);
  }
}

/** Save settings to the backend. */
async function saveSettings(triggerButton) {
  if (!settingsStatus) {
    return;
  }
  const payload = {
    threshold: readSetting(settingsThreshold),
    max_points: readSetting(settingsMaxPoints),
    chart_max_points: readSetting(settingsChartMaxPoints),
    window_hours: readSetting(settingsWindowHours),
    tick_minutes: readSetting(settingsTickMinutes),
    capacity_default: readSetting(settingsCapacityDefault),
    storno_max_minutes: readSetting(settingsStornoMinutes),
    storno_max_back: readSetting(settingsStornoBack),
    tablet_type_reset: readSetting(settingsTabletTypeReset),
  };

  if (
    payload.threshold === null ||
    payload.max_points === null ||
    payload.chart_max_points === null ||
    payload.window_hours === null ||
    payload.tick_minutes === null ||
    payload.capacity_default === null ||
    payload.storno_max_minutes === null ||
    payload.storno_max_back === null ||
    payload.tablet_type_reset === null
  ) {
    setStatus(settingsStatus, t("settings.fillAll"), true);
    return;
  }

  setStatus(settingsStatus, t("settings.saving"));
  setButtonLoading(triggerButton, true, t("common.savingShort"));
  try {
    const response = await adminFetch("set_settings", { body: payload });
    const data = await response.json();
    if (data?.settings) {
      fillSettings(data.settings);
    }
    setStatus(settingsStatus, t("settings.saved"));
  } catch (error) {
    console.error(error);
    setStatus(settingsStatus, t("common.saveFailed"), true);
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Restart the event and archive the current CSV. */
async function restartEvent(triggerButton) {
  if (!canRunAction()) {
    showToast(t("common.wait"), "secondary");
    return;
  }
  if (!window.confirm(t("event.restart.confirm"))) {
    return;
  }
  setStatus(restartStatus, t("event.restart.running"));
  setButtonLoading(triggerButton, true, t("admin.event.restart"));
  try {
    const response = await adminFetch("restart");
    const data = await response.json();
    if (data.archived && data.archiveName) {
      setStatus(restartStatus, t("event.restart.archived", { name: data.archiveName }));
    } else {
      setStatus(restartStatus, t("event.restart.done"));
    }
    loadArchives();
    showToast(t("event.restart.toast"), "success");
  } catch (error) {
    console.error(error);
    setStatus(restartStatus, t("event.restart.failed"), true);
    showToast(t("event.restart.failed"), "danger");
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Render access token list rows. */
function renderAccessTokens(tokens) {
  if (!accessTokenList) {
    return;
  }
  accessTokenList.innerHTML = "";
  if (!tokens.length) {
    const empty = document.createElement("div");
    empty.className = "text-secondary small";
    empty.textContent = t("admin.accessKeys.none");
    accessTokenList.appendChild(empty);
    return;
  }
  tokens.forEach((entry) => {
    const row = document.createElement("div");
    row.className = "border rounded px-3 py-2 bg-light";
    row.dataset.tokenId = entry.id || "";
    const rowGrid = document.createElement("div");
    rowGrid.className = "row g-2 align-items-center";

    const colName = document.createElement("div");
    colName.className = "col-12 col-md-4";
    const nameLabel = document.createElement("label");
    nameLabel.className = "form-label small text-secondary";
    nameLabel.textContent = "Name";
    const nameInput = document.createElement("input");
    nameInput.className = "form-control form-control-sm js-access-token-name";
    nameInput.value = entry.name || "";
    colName.appendChild(nameLabel);
    colName.appendChild(nameInput);

    const colToken = document.createElement("div");
    colToken.className = "col-12 col-md-4";
    const tokenLabel = document.createElement("label");
    tokenLabel.className = "form-label small text-secondary";
    tokenLabel.textContent = "Key";
    const tokenInput = document.createElement("input");
    tokenInput.className = "form-control form-control-sm js-access-token-value";
    tokenInput.value = entry.token || "";
    colToken.appendChild(tokenLabel);
    colToken.appendChild(tokenInput);

    const colActive = document.createElement("div");
    colActive.className = "col-12 col-md-2";
    const activeLabel = document.createElement("label");
    activeLabel.className = "form-label small text-secondary";
    activeLabel.textContent = "Aktiv";
    const activeWrap = document.createElement("div");
    activeWrap.className = "form-check";
    const activeInput = document.createElement("input");
    activeInput.className = "form-check-input js-access-token-active";
    activeInput.type = "checkbox";
    activeInput.checked = Boolean(entry.active);
    activeWrap.appendChild(activeInput);
    colActive.appendChild(activeLabel);
    colActive.appendChild(activeWrap);

    const colActions = document.createElement("div");
    colActions.className = "col-12 col-md-2 d-flex flex-wrap align-items-center gap-2";
    const saveBtn = document.createElement("button");
    saveBtn.type = "button";
    saveBtn.className = "btn btn-outline-primary btn-sm";
    saveBtn.dataset.action = "save-access-token";
    saveBtn.innerHTML = `<i class="bi bi-check2 me-1" aria-hidden="true"></i>${t("common.save")}`;
    const deleteBtn = document.createElement("button");
    deleteBtn.type = "button";
    deleteBtn.className = "btn btn-outline-danger btn-sm";
    deleteBtn.dataset.action = "delete-access-token";
    deleteBtn.innerHTML = `<i class="bi bi-trash me-1" aria-hidden="true"></i>${t("common.delete")}`;
    colActions.appendChild(saveBtn);
    colActions.appendChild(deleteBtn);

    rowGrid.appendChild(colName);
    rowGrid.appendChild(colToken);
    rowGrid.appendChild(colActive);
    rowGrid.appendChild(colActions);
    row.appendChild(rowGrid);
    accessTokenList.appendChild(row);
  });
}

/** Fetch access tokens from the backend. */
async function loadAccessTokens() {
  if (!accessTokenList) {
    return;
  }
  setStatus(accessTokenStatus, t("admin.accessKeys.loading"));
  try {
    const response = await adminFetch("get_access_tokens");
    const data = await response.json().catch(() => ({}));
    const tokens = Array.isArray(data.accessTokens) ? data.accessTokens : [];
    renderAccessTokens(tokens);
    setStatus(accessTokenStatus, t("admin.accessKeys.loaded"));
  } catch (error) {
    console.error(error);
    setStatus(accessTokenStatus, t("admin.accessKeys.loadFailed"), true);
  }
}

/** Add a new access token. */
async function addAccessToken(triggerButton) {
  const name = accessTokenNameInput ? accessTokenNameInput.value.trim() : "";
  const token = accessTokenInput ? accessTokenInput.value.trim() : "";
  if (!name || !token) {
    setStatus(accessTokenStatus, t("admin.accessKeys.missing"), true);
    if (!name) {
      accessTokenNameInput?.focus();
    } else {
      accessTokenInput?.focus();
    }
    return;
  }
  setStatus(accessTokenStatus, t("common.saving"));
  setButtonLoading(triggerButton, true, t("common.savingShort"));
  try {
    const response = await adminFetch("add_access_token", {
      body: { name, token, active: true },
    });
    const data = await response.json().catch(() => ({}));
    const tokens = Array.isArray(data.accessTokens) ? data.accessTokens : [];
    renderAccessTokens(tokens);
    setStatus(accessTokenStatus, t("admin.accessKeys.saved"));
    if (accessTokenNameInput) {
      accessTokenNameInput.value = "";
    }
    if (accessTokenInput) {
      accessTokenInput.value = "";
    }
  } catch (error) {
    console.error(error);
    setStatus(accessTokenStatus, t("common.saveFailed"), true);
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Update an existing access token. */
async function updateAccessToken(row, triggerButton) {
  const id = row.dataset.tokenId || "";
  const nameInput = row.querySelector(".js-access-token-name");
  const tokenInput = row.querySelector(".js-access-token-value");
  const activeInput = row.querySelector(".js-access-token-active");
  const name = nameInput ? nameInput.value.trim() : "";
  const token = tokenInput ? tokenInput.value.trim() : "";
  const active = activeInput ? activeInput.checked : false;
  if (!id || !name || !token) {
    setStatus(accessTokenStatus, t("admin.accessKeys.missing"), true);
    return;
  }
  setStatus(accessTokenStatus, t("common.saving"));
  setButtonLoading(triggerButton, true, t("common.savingShort"));
  try {
    const response = await adminFetch("update_access_token", {
      body: { id, name, token, active },
    });
    const data = await response.json().catch(() => ({}));
    const tokens = Array.isArray(data.accessTokens) ? data.accessTokens : [];
    renderAccessTokens(tokens);
    setStatus(accessTokenStatus, t("admin.accessKeys.saved"));
  } catch (error) {
    console.error(error);
    setStatus(accessTokenStatus, t("common.saveFailed"), true);
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Delete an access token. */
async function deleteAccessToken(row, triggerButton) {
  const id = row.dataset.tokenId || "";
  if (!id) {
    return;
  }
  if (!window.confirm(t("admin.accessKeys.deleteConfirm"))) {
    return;
  }
  setButtonLoading(triggerButton, true, t("common.loading"));
  try {
    const response = await adminFetch("delete_access_token", { body: { id } });
    const data = await response.json().catch(() => ({}));
    const tokens = Array.isArray(data.accessTokens) ? data.accessTokens : [];
    renderAccessTokens(tokens);
    setStatus(accessTokenStatus, t("admin.accessKeys.deleted"));
  } catch (error) {
    console.error(error);
    setStatus(accessTokenStatus, t("common.saveFailed"), true);
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

/** Save a new admin token to the backend. */
async function saveAdminToken(triggerButton) {
  const token = adminTokenInputNew ? adminTokenInputNew.value.trim() : "";
  if (!token) {
    setStatus(adminTokenStatus, t("token.missing"), true);
    adminTokenInputNew?.focus();
    return;
  }
  setStatus(adminTokenStatus, t("common.saving"));
  setButtonLoading(triggerButton, true, t("common.savingShort"));
  try {
    await adminFetch("set_admin_token", { body: { token } });
    setStatus(adminTokenStatus, t("token.saved"));
    if (adminTokenInputNew) {
      adminTokenInputNew.value = "";
    }
  } catch (error) {
    console.error(error);
    setStatus(adminTokenStatus, t("common.saveFailed"), true);
  } finally {
    setButtonLoading(triggerButton, false);
  }
}

if (adminSave && adminTokenInput) {
  adminSave.addEventListener("click", async () => {
    const token = adminTokenInput.value.trim();
    if (!token) {
      setStatus(adminStatus, t("token.adminMissing"), true);
      adminTokenInput.focus();
      return;
    }
    setStatus(adminStatus, t("token.adminChecking"));
    const ok = await verifyAdminToken(token);
    if (!ok) {
      setStatus(adminStatus, t("token.adminInvalid"), true);
      return;
    }
    setAdminToken(token);
    setButtonLoading(adminSave, true, t("common.savingShort"));
    await Promise.all([
      loadArchives(),
      loadEventName(),
      loadSettings(),
      loadLogs(),
      loadAccessTokens(),
    ]);
    setButtonLoading(adminSave, false);
  });
}

if (adminClear) {
  adminClear.addEventListener("click", () => {
    clearAdminToken();
    setStatus(archiveStatus, t("archive.noneLoaded"));
    if (archiveList) {
      archiveList.innerHTML = "";
    }
    archiveItems = [];
    if (archiveSearchInput) {
      archiveSearchInput.value = "";
    }
    if (archiveSortSelect) {
      archiveSortSelect.value = "modified_desc";
    }
    logItems = [];
    logTotal = 0;
    if (logSearchInput) {
      logSearchInput.value = "";
    }
    if (logStatusFilter) {
      logStatusFilter.value = "all";
    }
    if (logLimitSelect) {
      logLimitSelect.value = "200";
    }
    if (logList) {
      logList.innerHTML = "";
    }
    if (logStatus) {
      setStatus(logStatus, t("log.status.noneLoaded"));
    }
    if (settingsStatus) {
      setStatus(settingsStatus, t("settings.noneLoaded"));
    }
    if (settingsThreshold) {
      settingsThreshold.value = "";
    }
    if (settingsMaxPoints) {
      settingsMaxPoints.value = "";
    }
    if (settingsChartMaxPoints) {
      settingsChartMaxPoints.value = "";
    }
    if (settingsWindowHours) {
      settingsWindowHours.value = "";
    }
    if (settingsTickMinutes) {
      settingsTickMinutes.value = "";
    }
    if (settingsCapacityDefault) {
      settingsCapacityDefault.value = "";
    }
    if (settingsStornoMinutes) {
      settingsStornoMinutes.value = "";
    }
    if (settingsStornoBack) {
      settingsStornoBack.value = "";
    }
    if (eventNameInput) {
      eventNameInput.value = "";
    }
    if (eventNameStatus) {
      setStatus(eventNameStatus, t("event.name.noneLoaded"));
    }
  });
}

if (restartBtn) {
  restartBtn.addEventListener("click", () => {
    restartEvent(restartBtn);
  });
}

if (eventNameSave) {
  eventNameSave.addEventListener("click", () => {
    saveEventName(eventNameSave);
  });
}

if (downloadLatest) {
  downloadLatest.addEventListener("click", () => {
    downloadLatestArchive(downloadLatest);
  });
}

if (refreshArchives) {
  refreshArchives.addEventListener("click", () => {
    setButtonLoading(refreshArchives, true, t("common.refreshing"));
    loadArchives().finally(() => {
      setButtonLoading(refreshArchives, false);
    });
  });
}

if (logRefresh) {
  logRefresh.addEventListener("click", () => {
    setButtonLoading(logRefresh, true, t("common.refreshing"));
    loadLogs().finally(() => {
      setButtonLoading(logRefresh, false);
    });
  });
}

if (logDownload) {
  logDownload.addEventListener("click", () => {
    downloadLog();
  });
}

if (accessTokenAdd) {
  accessTokenAdd.addEventListener("click", () => {
    addAccessToken(accessTokenAdd);
  });
}

if (settingsSave) {
  settingsSave.addEventListener("click", () => {
    saveSettings(settingsSave);
  });
}

if (adminTokenSave) {
  adminTokenSave.addEventListener("click", () => {
    saveAdminToken(adminTokenSave);
  });
}

if (adminTokenInput) {
  adminTokenInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      adminSave?.click();
    }
  });
}

if (accessTokenInput) {
  accessTokenInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      accessTokenAdd?.click();
    }
  });
}

if (accessTokenNameInput) {
  accessTokenNameInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      accessTokenAdd?.click();
    }
  });
}

if (accessTokenList) {
  accessTokenList.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) {
      return;
    }
    const row = button.closest("[data-token-id]");
    if (!row) {
      return;
    }
    const action = button.dataset.action;
    if (action === "save-access-token") {
      updateAccessToken(row, button);
    }
    if (action === "delete-access-token") {
      deleteAccessToken(row, button);
    }
  });
}

if (archiveSearchInput) {
  archiveSearchInput.addEventListener("input", () => {
    updateArchiveView();
  });
  archiveSearchInput.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      archiveSearchInput.value = "";
      updateArchiveView();
    }
  });
}

if (archiveSortSelect) {
  archiveSortSelect.addEventListener("change", () => {
    updateArchiveView();
  });
}

if (logSearchInput) {
  logSearchInput.addEventListener("input", () => {
    updateLogView();
  });
  logSearchInput.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      logSearchInput.value = "";
      updateLogView();
    }
  });
}

if (logStatusFilter) {
  logStatusFilter.addEventListener("change", () => {
    updateLogView();
  });
}

if (logLimitSelect) {
  logLimitSelect.addEventListener("change", () => {
    loadLogs();
  });
}

const settingsInputs = [
  settingsThreshold,
  settingsMaxPoints,
  settingsChartMaxPoints,
  settingsWindowHours,
  settingsTickMinutes,
  settingsCapacityDefault,
  settingsStornoMinutes,
  settingsStornoBack,
];
settingsInputs.forEach((input) => {
  if (!input) {
    return;
  }
  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      settingsSave?.click();
    }
  });
});

if (adminTokenInputNew) {
  adminTokenInputNew.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      adminTokenSave?.click();
    }
  });
}

if (eventNameInput) {
  eventNameInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      eventNameSave?.click();
    }
  });
}

if (adminLogout) {
  adminLogout.addEventListener("click", () => {
    clearAdminToken();
    setAdminAuthState(false);
  });
}

document.addEventListener("languagechange", () => {
  refreshAdminStatus();
  updateArchiveView();
  updateLogView();
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey) {
    return;
  }
  const active = document.activeElement;
  if (
    active &&
    (active.tagName === "INPUT" ||
      active.tagName === "TEXTAREA" ||
      active.tagName === "SELECT" ||
      active.isContentEditable)
  ) {
    return;
  }
  if (archiveSearchInput) {
    event.preventDefault();
    archiveSearchInput.focus();
  }
});

refreshAdminStatus();
if (getAdminToken()) {
  loadArchives();
  loadEventName();
  loadSettings();
  loadLogs();
  loadAccessTokens();
}
