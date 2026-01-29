const ANALYSIS_TOKEN_KEY = "kekcounter.analysisToken";
const t = (key, vars) =>
  typeof window.t === "function" ? window.t(key, vars) : key;
const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
const CAPACITY_KEY = "kekcounter.capacity";
const ARCHIVE_KEY = "kekcounter.analysisArchive";
const SETTINGS = window.APP_CONFIG?.settings || window.KEKCOUNTER_SETTINGS || {};
const DEFAULT_CAPACITY =
  Number(SETTINGS.capacity_default) > 0
    ? Number(SETTINGS.capacity_default)
    : null;
const THRESHOLD = Number(SETTINGS.threshold) > 0 ? Number(SETTINGS.threshold) : 150;

const analysisTokenInput = document.getElementById("analysisToken");
const analysisSave = document.getElementById("analysisSave");
const analysisClear = document.getElementById("analysisClear");
const analysisStatus = document.getElementById("analysisStatus");
const analysisAuthCard = document.getElementById("analysisAuthCard");
const analysisRefresh = document.getElementById("analysisRefresh");
const archiveStatus = document.getElementById("analysisArchiveStatus");
const archiveList = document.getElementById("analysisArchiveList");
const archiveSearchInput = document.getElementById("analysisArchiveSearch");
const archiveSortSelect = document.getElementById("analysisArchiveSort");
const analysisContent = document.getElementById("analysisContent");
const analysisLogout = document.getElementById("analysisLogout");
const analysisStats = document.getElementById("analysisStats");
const analysisDuration = document.getElementById("analysisDuration");
const analysisFlows = document.getElementById("analysisFlows");
const analysisRetentionSummary = document.getElementById("analysisRetentionSummary");
const analysisInterpretation = document.getElementById("analysisInterpretation");
const analysisCurrentTitle = document.getElementById("analysisCurrentTitle");
const capacityInput = document.getElementById("capacityInput");
const capacityApply = document.getElementById("capacityApply");
const chartCanvas = document.getElementById("analysisChart");
const retentionCanvas = document.getElementById("analysisRetention");
const toastContainer = document.getElementById("analysisToastContainer");

const MAX_POINTS = Number(SETTINGS.max_points) > 0 ? Number(SETTINGS.max_points) : 10000;
const CHART_MAX_POINTS = Number(SETTINGS.chart_max_points) > 0
  ? Number(SETTINGS.chart_max_points)
  : Math.min(MAX_POINTS, 2000);
const TICK_MINUTES = Number(SETTINGS.tick_minutes) > 0 ? Number(SETTINGS.tick_minutes) : 15;
const FLOW_BUCKET_SECONDS = TICK_MINUTES * 60;
const RETENTION_STEP_MINUTES = TICK_MINUTES;

let chart = null;
let retentionChart = null;
let axisBaseMs = null;
let lastPoints = null;
let lastChartPoints = null;
let lastArchive = "";
let archiveItems = [];
let analysisAuthenticated = false;
let tokenValidationTimer = null;
let tokenValidationCounter = 0;

/** Read a CSS variable from the document root. */
function getThemeColor(varName, fallback) {
  const value = getComputedStyle(document.documentElement)
    .getPropertyValue(varName)
    .trim();
  return value || fallback;
}

/** Build chart colors based on the current theme variables. */
function getChartPalette() {
  return {
    line: getThemeColor("--chart-line", "rgba(37, 99, 235, 1)"),
    fill: getThemeColor("--chart-fill", "rgba(37, 99, 235, 0.12)"),
    danger: getThemeColor("--chart-danger", "rgba(239, 68, 68, 1)"),
    criticalFill: getThemeColor("--chart-critical-fill", "rgba(239, 68, 68, 0.1)"),
    criticalLine: getThemeColor("--chart-critical-line", "rgba(239, 68, 68, 0.7)"),
    retentionLine: getThemeColor("--chart-retention-line", "rgba(6, 182, 212, 1)"),
    retentionFill: getThemeColor("--chart-retention-fill", "rgba(6, 182, 212, 0.12)"),
    tooltipBg: getThemeColor("--chart-tooltip-bg", "rgba(15, 23, 42, 0.9)"),
    tooltipColor: getThemeColor("--chart-tooltip-color", "#ffffff"),
  };
}

const criticalZone = {
  id: "criticalZone",
  beforeDatasetsDraw(chartInstance, args, options) {
    const { ctx, chartArea, scales } = chartInstance;
    if (!chartArea || !scales?.y) {
      return;
    }
    const limit = options?.threshold ?? THRESHOLD;
    const y = scales.y.getPixelForValue(limit);
    const top = chartArea.top;
    const bottom = chartArea.bottom;
    const yClamped = Math.min(Math.max(y, top), bottom);
    const height = yClamped - top;
    if (height > 0) {
      ctx.save();
      ctx.fillStyle = options?.fill ?? "rgba(239, 68, 68, 0.1)";
      ctx.fillRect(chartArea.left, top, chartArea.right - chartArea.left, height);
      ctx.restore();
    }
    if (y >= top && y <= bottom) {
      ctx.save();
      ctx.strokeStyle = options?.line ?? "rgba(239, 68, 68, 0.7)";
      ctx.lineWidth = 1.5;
      ctx.setLineDash([6, 6]);
      ctx.beginPath();
      ctx.moveTo(chartArea.left, y);
      ctx.lineTo(chartArea.right, y);
      ctx.stroke();
      ctx.restore();
    }
  },
};

/** Apply updated theme colors to analysis charts. */
function applyAnalysisChartsTheme() {
  const tickColor = getThemeColor("--bs-secondary-color", "#64748b");
  const gridColor = getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)");
  const palette = getChartPalette();
  if (chart) {
    if (chart.data.datasets[0]) {
      chart.data.datasets[0].borderColor = palette.line;
      chart.data.datasets[0].backgroundColor = palette.fill;
      chart.data.datasets[0].segment = {
        borderColor: (segmentCtx) =>
          segmentCtx.p0.parsed.y >= THRESHOLD || segmentCtx.p1.parsed.y >= THRESHOLD
            ? palette.danger
            : palette.line,
      };
    }
    if (chart.options.plugins?.tooltip) {
      chart.options.plugins.tooltip.backgroundColor = palette.tooltipBg;
      chart.options.plugins.tooltip.titleColor = palette.tooltipColor;
      chart.options.plugins.tooltip.bodyColor = palette.tooltipColor;
    }
    if (chart.options.plugins?.criticalZone) {
      chart.options.plugins.criticalZone.fill = palette.criticalFill;
      chart.options.plugins.criticalZone.line = palette.criticalLine;
    }
    chart.options.scales.x.ticks.color = tickColor;
    chart.options.scales.y.ticks.color = tickColor;
    chart.options.scales.x.grid.color = gridColor;
    chart.options.scales.y.grid.color = gridColor;
    chart.update();
  }
  if (retentionChart) {
    if (retentionChart.data.datasets[0]) {
      retentionChart.data.datasets[0].borderColor = palette.retentionLine;
      retentionChart.data.datasets[0].backgroundColor = palette.retentionFill;
    }
    if (retentionChart.options.plugins?.tooltip) {
      retentionChart.options.plugins.tooltip.backgroundColor = palette.tooltipBg;
      retentionChart.options.plugins.tooltip.titleColor = palette.tooltipColor;
      retentionChart.options.plugins.tooltip.bodyColor = palette.tooltipColor;
    }
    retentionChart.options.scales.x.ticks.color = tickColor;
    retentionChart.options.scales.y.ticks.color = tickColor;
    retentionChart.options.scales.x.grid.color = gridColor;
    retentionChart.options.scales.y.grid.color = gridColor;
    retentionChart.update();
  }
}

/** Read the stored token (admin token only). */
function getToken() {
  return getAdminToken();
}

/** Read the stored admin token from localStorage. */
function getAdminToken() {
  try {
    return localStorage.getItem(ADMIN_TOKEN_KEY) || "";
  } catch (error) {
    return "";
  }
}

/** Persist the admin token in localStorage. */
function setAdminToken(token) {
  try {
    localStorage.setItem(ADMIN_TOKEN_KEY, token);
  } catch (error) {
    console.error(error);
  }
}

/** Persist the admin token via the analysis input flow. */
function setToken(token) {
  try {
    localStorage.setItem(ADMIN_TOKEN_KEY, token);
    localStorage.removeItem(ANALYSIS_TOKEN_KEY);
  } catch (error) {
    console.error(error);
  }
  setAnalysisAuthState(false);
  refreshTokenStatus();
  if (analysisTokenInput) {
    analysisTokenInput.value = "";
  }
}

/** Clear stored tokens and reset auth state. */
function clearToken() {
  try {
    localStorage.removeItem(ADMIN_TOKEN_KEY);
    localStorage.removeItem(ANALYSIS_TOKEN_KEY);
  } catch (error) {
    console.error(error);
  }
  setAnalysisAuthState(false);
  refreshTokenStatus();
}

/** Update a status element with optional error styling. */
function setStatus(el, message, isError = false) {
  if (!el) {
    return;
  }
  el.textContent = message;
  el.classList.toggle("text-danger", isError);
  el.classList.toggle("text-secondary", !isError);
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

/** Toggle a loading state on a button. */
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

/** Read the last selected archive name from localStorage. */
function getStoredArchive() {
  try {
    return localStorage.getItem(ARCHIVE_KEY) || "";
  } catch (error) {
    return "";
  }
}

/** Persist the last selected archive name. */
function setStoredArchive(name) {
  try {
    if (name) {
      localStorage.setItem(ARCHIVE_KEY, name);
    } else {
      localStorage.removeItem(ARCHIVE_KEY);
    }
  } catch (error) {
    console.error(error);
  }
}

/** Verify an admin token via the admin API. */
async function verifyToken(token) {
  try {
    const response = await fetch("admin.php?action=get_settings", {
      method: "POST",
      cache: "no-store",
      headers: {
        "X-Requested-With": "fetch",
        ...(token ? { "X-Admin-Token": token } : {}),
      },
    });
    return response.ok;
  } catch (error) {
    console.error(error);
    return false;
  }
}

/** Debounced token validation while typing. */
function scheduleTokenValidation() {
  if (!analysisTokenInput) {
    return;
  }
  const token = analysisTokenInput.value.trim();
  if (!token) {
    refreshTokenStatus();
    return;
  }
  setStatus(analysisStatus, t("token.adminChecking"));
  const requestId = ++tokenValidationCounter;
  if (tokenValidationTimer) {
    clearTimeout(tokenValidationTimer);
  }
  tokenValidationTimer = setTimeout(async () => {
    const ok = await verifyToken(token);
    if (requestId !== tokenValidationCounter) {
      return;
    }
    setStatus(
      analysisStatus,
      ok ? t("token.adminValid") : t("token.adminInvalid"),
      !ok
    );
  }, 400);
}

/** Toggle auth-protected sections based on auth state. */
function setAnalysisAuthState(isAuthenticated) {
  analysisAuthenticated = isAuthenticated;
  if (analysisAuthCard) {
    analysisAuthCard.classList.toggle("d-none", isAuthenticated);
  }
  if (analysisContent) {
    analysisContent.classList.toggle("d-none", !isAuthenticated);
  }
  if (analysisLogout) {
    analysisLogout.classList.toggle("d-none", !isAuthenticated);
  }
}

/** Update token status text based on stored state. */
function refreshTokenStatus() {
  const hasToken = getToken() !== "";
  if (!hasToken) {
    setAnalysisAuthState(false);
  }
  const message = analysisAuthenticated
    ? t("token.adminVerified")
    : hasToken
      ? t("token.adminSaved")
      : t("analysis.auth.status.none");
  setStatus(analysisStatus, message);
}

/** Ensure a token exists or prompt the user. */
function requireToken() {
  const token = getToken();
  if (!token) {
    setStatus(analysisStatus, t("token.adminRequired"), true);
    setAnalysisAuthState(false);
    if (analysisTokenInput) {
      analysisTokenInput.focus();
    }
    return "";
  }
  return token;
}

/** Store the token as admin if it validates as admin. */
async function maybeStoreAdminToken(token) {
  if (!token || getAdminToken() === token) {
    return;
  }
  try {
    const response = await fetch("admin.php?action=get_settings", {
      method: "POST",
      cache: "no-store",
      headers: {
        "X-Requested-With": "fetch",
        "X-Admin-Token": token,
      },
    });
    if (response.ok) {
      setAdminToken(token);
    }
  } catch (error) {
    console.error(error);
  }
}

/** Perform an authenticated analysis API request. */
async function analysisFetch(action, options = {}) {
  const token = requireToken();
  if (!token) {
    throw new Error("Token missing");
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
  const response = await fetch(`analysis.php?action=${action}`, fetchOptions);
  if (!response.ok) {
    if (response.status === 403) {
      setStatus(analysisStatus, t("token.adminInvalid"), true);
      setAnalysisAuthState(false);
    }
    if (response.status === 503) {
      setStatus(analysisStatus, t("token.adminMissingServer"), true);
      setAnalysisAuthState(false);
    }
    throw new Error("Request failed");
  }
  setAnalysisAuthState(true);
  refreshTokenStatus();
  return response;
}

/** Resolve capacity from input or stored preference. */
function getCapacity() {
  if (capacityInput && capacityInput.value.trim() !== "") {
    const value = Number(capacityInput.value);
    return Number.isFinite(value) && value > 0 ? value : null;
  }
  try {
    const stored = localStorage.getItem(CAPACITY_KEY);
    const value = stored ? Number(stored) : null;
    if (Number.isFinite(value) && value > 0) {
      return value;
    }
  } catch (error) {
    return DEFAULT_CAPACITY;
  }
  return DEFAULT_CAPACITY;
}

/** Persist capacity value to input/localStorage. */
function setCapacity(value) {
  if (!capacityInput) {
    return;
  }
  if (value) {
    capacityInput.value = String(value);
  }
  try {
    if (value) {
      localStorage.setItem(CAPACITY_KEY, String(value));
    } else {
      localStorage.removeItem(CAPACITY_KEY);
    }
  } catch (error) {
    console.error(error);
  }
}

/** Get a local midnight timestamp for chart labels. */
function getMidnightBaseMs() {
  const base = new Date();
  base.setHours(0, 0, 0, 0);
  return base.getTime();
}

/** Parse a HH:MM(:SS) label into seconds from midnight. */
function parseTimeToSeconds(label) {
  if (typeof label !== "string") {
    return null;
  }
  const trimmed = label.trim();
  const match = /^(\d{1,2}):(\d{2})(?::(\d{2}))?$/.exec(trimmed);
  if (!match) {
    return null;
  }
  const hours = Number(match[1]);
  const minutes = Number(match[2]);
  const seconds = match[3] ? Number(match[3]) : 0;
  if (
    Number.isNaN(hours) ||
    Number.isNaN(minutes) ||
    Number.isNaN(seconds)
  ) {
    return null;
  }
  return hours * 3600 + minutes * 60 + seconds;
}

/** Format seconds from midnight into HH:MM. */
function formatTime(seconds) {
  if (!axisBaseMs || Number.isNaN(seconds)) {
    return "";
  }
  const date = new Date(axisBaseMs + seconds * 1000);
  const hh = String(date.getHours()).padStart(2, "0");
  const mm = String(date.getMinutes()).padStart(2, "0");
  return `${hh}:${mm}`;
}

/** Format seconds into a human-friendly duration. */
function formatDuration(seconds) {
  if (!Number.isFinite(seconds) || seconds < 0) {
    return "-";
  }
  const totalMinutes = Math.floor(seconds / 60);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
}

/** Format minutes into a compact duration string. */
function formatMinutes(minutes) {
  if (!Number.isFinite(minutes) || minutes < 0) {
    return "-";
  }
  const hours = Math.floor(minutes / 60);
  const mins = Math.round(minutes % 60);
  if (hours > 0) {
    return `${hours}h ${mins}m`;
  }
  return `${mins}m`;
}

/** Render summary statistics cards. */
function renderStats(items) {
  if (!analysisStats) {
    return;
  }
  analysisStats.innerHTML = "";
  if (!items.length) {
    analysisStats.textContent = t("common.noData");
    return;
  }
  if (analysisCurrentTitle) {
    analysisCurrentTitle.textContent = lastArchive || t("analysis.current.none");
  }
  items.forEach((item) => {
    const col = document.createElement("div");
    const card = document.createElement("div");
    col.className = "col-6 col-lg-3";
    card.className = "border rounded-3 p-2 bg-body-tertiary d-flex flex-column gap-1 h-100";
    const label = document.createElement("span");
    label.className = "text-uppercase small text-secondary fw-semibold";
    label.textContent = item.label;
    const value = document.createElement("strong");
    value.className = "text-body";
    value.textContent = item.value;
    card.appendChild(label);
    card.appendChild(value);
    col.appendChild(card);
    analysisStats.appendChild(col);
  });
}

/** Render skeleton cards for stats. */
function renderStatsSkeleton(count = 12) {
  if (!analysisStats) {
    return;
  }
  analysisStats.innerHTML = "";
  for (let i = 0; i < count; i += 1) {
    const col = document.createElement("div");
    const card = document.createElement("div");
    const label = document.createElement("span");
    const value = document.createElement("span");
    col.className = "col-6 col-lg-3";
    card.className = "border rounded-3 p-2 bg-body-tertiary d-flex flex-column gap-2 h-100";
    label.className = "analysis-skeleton";
    value.className = "analysis-skeleton";
    label.style.width = "60%";
    value.style.width = "80%";
    card.appendChild(label);
    card.appendChild(value);
    col.appendChild(card);
    analysisStats.appendChild(col);
  }
}

/** Render a label/value list into a container. */
function renderList(el, items) {
  if (!el) {
    return;
  }
  el.innerHTML = "";
  if (!items.length) {
    el.textContent = t("common.noData");
    return;
  }
  items.forEach((item) => {
    const row = document.createElement("div");
    row.className = "d-flex justify-content-between align-items-center border-bottom pb-1 gap-2";
    const label = document.createElement("span");
    label.className = "text-secondary";
    label.textContent = item.label;
    const value = document.createElement("strong");
    value.className = "text-body";
    value.textContent = item.value;
    row.appendChild(label);
    row.appendChild(value);
    el.appendChild(row);
  });
}

/** Render skeleton lines in a block container. */
function renderBlockSkeleton(el, rows = 4) {
  if (!el) {
    return;
  }
  el.innerHTML = "";
  const wrap = document.createElement("div");
  wrap.className = "analysis-skeleton-block";
  for (let i = 0; i < rows; i += 1) {
    const bar = document.createElement("span");
    bar.className = "analysis-skeleton";
    wrap.appendChild(bar);
  }
  el.appendChild(wrap);
}

/** Render skeletons for all analysis blocks. */
function renderAnalysisSkeleton() {
  renderStatsSkeleton();
  renderBlockSkeleton(analysisDuration, 4);
  renderBlockSkeleton(analysisFlows, 4);
  renderBlockSkeleton(analysisRetentionSummary, 4);
  renderBlockSkeleton(analysisInterpretation, 4);
}

/** Render a bullet list into a container. */
function renderText(el, items) {
  if (!el) {
    return;
  }
  el.innerHTML = "";
  if (!items.length) {
    el.textContent = t("common.noData");
    return;
  }
  const list = document.createElement("ul");
  list.className = "mb-0 ps-3 d-grid gap-2";
  items.forEach((item) => {
    const li = document.createElement("li");
    li.textContent = item;
    list.appendChild(li);
  });
  el.appendChild(list);
}

/** Build or update the main chart. */
function buildChart(points) {
  if (!chartCanvas || !window.Chart) {
    return;
  }

  if (!chart) {
    const tickColor = getThemeColor("--bs-secondary-color", "#64748b");
    const gridColor = getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)");
    const palette = getChartPalette();
    chart = new Chart(chartCanvas.getContext("2d"), {
      type: "line",
      plugins: [criticalZone],
      data: {
        labels: [],
        datasets: [
          {
            label: t("chart.visitors"),
            data: [],
            borderColor: palette.line,
            backgroundColor: palette.fill,
            fill: true,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: 0,
            parsing: false,
            segment: {
              borderColor: (segmentCtx) =>
                segmentCtx.p0.parsed.y >= THRESHOLD || segmentCtx.p1.parsed.y >= THRESHOLD
                  ? palette.danger
                  : palette.line,
            },
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          intersect: false,
          mode: "index",
        },
        plugins: {
          legend: {
            display: false,
          },
          criticalZone: {
            threshold: THRESHOLD,
            fill: palette.criticalFill,
            line: palette.criticalLine,
          },
          tooltip: {
            backgroundColor: palette.tooltipBg,
            titleColor: palette.tooltipColor,
            bodyColor: palette.tooltipColor,
            padding: 12,
            cornerRadius: 12,
            displayColors: false,
            callbacks: {
              title(items) {
                if (!items?.length) {
                  return "";
                }
                return formatTime(items[0].parsed.x);
              },
              label(item) {
                return t("chart.visitorsLabel", { count: item.parsed.y });
              },
            },
          },
        },
        scales: {
          x: {
            type: "linear",
            ticks: {
              color: tickColor,
              maxRotation: 0,
              maxTicksLimit: 8,
              callback(value) {
                return formatTime(Number(value));
              },
            },
            grid: {
              color: gridColor,
            },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: tickColor,
            },
            grid: {
              color: gridColor,
            },
          },
        },
      },
    });
  }

  chart.data.labels = [];
  chart.data.datasets[0].data = points;
  if (points.length) {
    chart.options.scales.x.min = points[0].x;
    chart.options.scales.x.max = points[points.length - 1].x;
  }
  applyAnalysisChartsTheme();
}

/** Build or update the retention chart. */
function buildRetentionChart(points) {
  if (!retentionCanvas || !window.Chart) {
    return;
  }

  if (!retentionChart) {
    const tickColor = getThemeColor("--bs-secondary-color", "#64748b");
    const gridColor = getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)");
    const palette = getChartPalette();
    retentionChart = new Chart(retentionCanvas.getContext("2d"), {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: t("analysis.retention.label"),
            data: [],
            borderColor: palette.retentionLine,
            backgroundColor: palette.retentionFill,
            fill: true,
            tension: 0.25,
            borderWidth: 2,
            pointRadius: 0,
            parsing: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: palette.tooltipBg,
            titleColor: palette.tooltipColor,
            bodyColor: palette.tooltipColor,
            padding: 12,
            cornerRadius: 12,
            displayColors: false,
            callbacks: {
              title(items) {
                if (!items?.length) {
                  return "";
                }
                const minutes = items[0].parsed.x;
                return formatMinutes(minutes);
              },
              label(item) {
                return t("chart.retentionLabel", { value: item.parsed.y.toFixed(0) });
              },
            },
          },
        },
        scales: {
          x: {
            type: "linear",
            ticks: {
              color: tickColor,
              maxRotation: 0,
              maxTicksLimit: 6,
              callback(value) {
                return formatMinutes(Number(value));
              },
            },
            grid: {
              color: gridColor,
            },
          },
          y: {
            min: 0,
            max: 100,
            ticks: {
              color: tickColor,
              callback(value) {
                return `${value}%`;
              },
            },
            grid: {
              color: gridColor,
            },
          },
        },
      },
    });
  }

  retentionChart.data.labels = [];
  retentionChart.data.datasets[0].data = points;
  applyAnalysisChartsTheme();
}

/** Parse CSV into time series points. */
function parseCsv(text) {
  const lines = text.split(/\r?\n/).filter((line) => line.trim() !== "");
  if (!lines.length) {
    return { points: [], chartPoints: [] };
  }
  const points = [];
  let lastSeconds = null;
  let dayOffset = 0;

  for (let i = 0; i < lines.length; i += 1) {
    const line = lines[i];
    if (i === 0 && line.toLowerCase().includes("uhrzeit")) {
      continue;
    }
    const parts = line.split(",");
    if (parts.length < 2) {
      continue;
    }
    const seconds = parseTimeToSeconds(parts[0]);
    if (seconds === null) {
      continue;
    }
    let adjusted = seconds + dayOffset;
    if (lastSeconds !== null && adjusted < lastSeconds) {
      dayOffset += 24 * 3600;
      adjusted = seconds + dayOffset;
    }
    lastSeconds = adjusted;
    const value = Number(parts[1]);
    points.push({ x: adjusted, y: Number.isFinite(value) ? value : 0 });
  }

  axisBaseMs = points.length ? getMidnightBaseMs() : null;

  if (points.length > CHART_MAX_POINTS) {
    const step = Math.ceil(points.length / CHART_MAX_POINTS);
    const sampled = [];
    for (let i = 0; i < points.length; i += step) {
      sampled.push(points[i]);
    }
    if (sampled[sampled.length - 1] !== points[points.length - 1]) {
      sampled.push(points[points.length - 1]);
    }
    return { points, chartPoints: sampled };
  }

  return { points, chartPoints: points };
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

/** Render a table empty state with optional action. */
function renderArchiveEmptyState(message, actionLabel, actionHandler) {
  if (!archiveList) {
    return;
  }
  archiveList.innerHTML = "";
  const row = document.createElement("tr");
  const cell = document.createElement("td");
  const wrap = document.createElement("div");
  cell.colSpan = 4;
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
  archiveList.appendChild(row);
}

/** Apply filters and render the archive list view. */
function updateArchiveView() {
  if (!archiveItems.length) {
    setStatus(archiveStatus, t("archive.none"));
    renderArchiveEmptyState(t("archive.noneMessage"), t("common.listRefresh"), () => {
      loadArchives();
    });
    if (analysisCurrentTitle) {
      analysisCurrentTitle.textContent = t("analysis.current.none");
    }
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

/** Render archive rows into the table. */
function renderArchives(items) {
  if (!archiveList) {
    return;
  }
  archiveList.innerHTML = "";
  if (!items.length) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 4;
    cell.className = "text-secondary small";
    cell.textContent = t("archive.none");
    row.appendChild(cell);
    archiveList.appendChild(row);
    if (analysisCurrentTitle) {
      analysisCurrentTitle.textContent = t("analysis.current.none");
    }
    return;
  }
  items.forEach((item) => {
    const row = document.createElement("tr");
    const nameCell = document.createElement("td");
    const modifiedCell = document.createElement("td");
    const sizeCell = document.createElement("td");
    const actionsCell = document.createElement("td");
    const viewButton = document.createElement("button");
    const downloadButton = document.createElement("button");
    const viewIcon = document.createElement("i");
    const downloadIcon = document.createElement("i");

    nameCell.className = "fw-semibold";
    modifiedCell.className = "text-secondary small text-nowrap";
    sizeCell.className = "text-end text-secondary small";
    actionsCell.className = "text-end";
    viewButton.className = "btn btn-sm btn-primary";
    viewButton.type = "button";
    viewButton.setAttribute("data-loading-label", t("common.loadingShort"));
    viewIcon.className = "bi bi-bar-chart-line me-1";
    viewIcon.setAttribute("aria-hidden", "true");
    viewButton.appendChild(viewIcon);
    viewButton.appendChild(document.createTextNode(t("nav.analysis")));
    downloadButton.className = "btn btn-sm btn-outline-secondary";
    downloadButton.type = "button";
    downloadButton.setAttribute("data-loading-label", t("common.download"));
    downloadIcon.className = "bi bi-download me-1";
    downloadIcon.setAttribute("aria-hidden", "true");
    downloadButton.appendChild(downloadIcon);
    downloadButton.appendChild(document.createTextNode(t("common.download")));

    nameCell.textContent = item.name;
    modifiedCell.textContent = new Date(item.modified).toLocaleString();
    sizeCell.textContent = formatBytes(item.size);

    viewButton.addEventListener("click", () => {
      setButtonLoading(viewButton, true);
      loadArchive(item.name, { notify: true }).finally(() => {
        setButtonLoading(viewButton, false);
      });
    });

    downloadButton.addEventListener("click", () => {
      setButtonLoading(downloadButton, true);
      downloadArchive(item.name).finally(() => {
        setButtonLoading(downloadButton, false);
      });
    });

    const actionWrap = document.createElement("div");
    actionWrap.className = "btn-group btn-group-sm";
    actionWrap.appendChild(viewButton);
    actionWrap.appendChild(downloadButton);
    actionsCell.appendChild(actionWrap);

    row.appendChild(nameCell);
    row.appendChild(modifiedCell);
    row.appendChild(sizeCell);
    row.appendChild(actionsCell);
    archiveList.appendChild(row);
  });
}

/** Render skeleton rows for the archive table. */
function renderArchiveSkeleton(count = 5) {
  if (!archiveList) {
    return;
  }
  archiveList.innerHTML = "";
  for (let i = 0; i < count; i += 1) {
    const row = document.createElement("tr");
    row.className = "analysis-skeleton-row";
    for (let c = 0; c < 4; c += 1) {
      const cell = document.createElement("td");
      const bar = document.createElement("span");
      bar.className = "analysis-skeleton";
      cell.appendChild(bar);
      if (c === 2 || c === 3) {
        cell.className = "text-end";
      }
      row.appendChild(cell);
    }
    archiveList.appendChild(row);
  }
}

/** Load archive metadata from the backend. */
async function loadArchives() {
  setStatus(archiveStatus, t("archive.loading"));
  renderArchiveSkeleton();
  try {
    const response = await analysisFetch("list_archives");
    const data = await response.json();
    const items = Array.isArray(data.archives) ? data.archives : [];
    archiveItems = items;
    updateArchiveView();
    if (!items.length) {
      return;
    }
    const stored = getStoredArchive();
    const storedExists = stored && items.some((item) => item.name === stored);
    const lastExists = lastArchive && items.some((item) => item.name === lastArchive);
    const target = storedExists ? stored : lastExists ? lastArchive : items[0]?.name;
    if (target && target !== lastArchive) {
      loadArchive(target, { notify: false });
    }
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("archive.loadFailed"), true);
  }
}

/** Download a selected archive CSV. */
async function downloadArchive(name) {
  setStatus(archiveStatus, t("download.starting"));
  try {
    const response = await analysisFetch("download", { body: { name } });
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
  }
}

/** Build arrival/departure buckets from points. */
function buildBuckets(points) {
  const arrivals = new Map();
  const departures = new Map();

  for (let i = 1; i < points.length; i += 1) {
    const delta = points[i].y - points[i - 1].y;
    if (delta === 0) {
      continue;
    }
    const bucket = Math.floor(points[i].x / FLOW_BUCKET_SECONDS) * FLOW_BUCKET_SECONDS;
    if (delta > 0) {
      arrivals.set(bucket, (arrivals.get(bucket) || 0) + delta);
    } else {
      departures.set(bucket, (departures.get(bucket) || 0) + Math.abs(delta));
    }
  }

  return { arrivals, departures };
}

/** Return top buckets sorted by volume. */
function topBuckets(map, limit) {
  const items = Array.from(map.entries()).map(([bucket, count]) => ({
    bucket,
    count,
  }));
  items.sort((a, b) => b.count - a.count || a.bucket - b.bucket);
  return items.slice(0, limit);
}

/** Format a bucket interval as a time range. */
function formatBucket(bucket) {
  const start = formatTime(bucket);
  const end = formatTime(bucket + FLOW_BUCKET_SECONDS);
  if (!start || !end) {
    return "-";
  }
  return `${start}-${end}`;
}

/** Compute visit durations from count deltas. */
function computeDurations(points) {
  const queue = [];
  const bins = new Map();
  let totalDepartures = 0;
  let totalDurationSeconds = 0;
  let maxMinutes = 0;

  const startCount = points[0]?.y || 0;
  if (startCount > 0) {
    queue.push({ time: points[0].x, count: startCount });
  }

  for (let i = 1; i < points.length; i += 1) {
    const delta = points[i].y - points[i - 1].y;
    if (delta >= 0) {
      if (delta > 0) {
        queue.push({ time: points[i].x, count: delta });
      }
      continue;
    }
    let remaining = Math.abs(delta);
    while (remaining > 0 && queue.length > 0) {
      const entry = queue[0];
      const take = Math.min(remaining, entry.count);
      const durationSeconds = Math.max(0, points[i].x - entry.time);
      const minutes = Math.max(0, Math.round(durationSeconds / 60));
      bins.set(minutes, (bins.get(minutes) || 0) + take);
      totalDepartures += take;
      totalDurationSeconds += durationSeconds * take;
      if (minutes > maxMinutes) {
        maxMinutes = minutes;
      }
      entry.count -= take;
      remaining -= take;
      if (entry.count === 0) {
        queue.shift();
      }
    }
  }

  return {
    bins,
    totalDepartures,
    totalDurationSeconds,
    maxMinutes,
  };
}

/** Compute the median from binned duration counts. */
function computeMedianFromBins(bins, totalCount) {
  if (totalCount <= 0) {
    return null;
  }
  const keys = Array.from(bins.keys()).sort((a, b) => a - b);
  const target = (totalCount + 1) / 2;
  let cumulative = 0;
  for (const key of keys) {
    cumulative += bins.get(key) || 0;
    if (cumulative >= target) {
      return key;
    }
  }
  return keys.length ? keys[keys.length - 1] : null;
}

/** Build retention curve points from duration bins. */
function buildRetention(bins, totalCount, maxMinutes) {
  if (totalCount <= 0 || maxMinutes <= 0) {
    return { points: [], lookup: () => 0 };
  }
  const counts = new Array(maxMinutes + 1).fill(0);
  bins.forEach((count, minutes) => {
    if (minutes >= 0 && minutes <= maxMinutes) {
      counts[minutes] += count;
    }
  });
  const suffix = new Array(maxMinutes + 2).fill(0);
  for (let i = maxMinutes; i >= 0; i -= 1) {
    suffix[i] = suffix[i + 1] + counts[i];
  }
  const points = [];
  for (let m = 0; m <= maxMinutes; m += RETENTION_STEP_MINUTES) {
    const retention = (suffix[m] / totalCount) * 100;
    points.push({ x: m, y: retention });
  }
  return {
    points,
    lookup(minutes) {
      if (minutes > maxMinutes) {
        return 0;
      }
      return (suffix[Math.max(0, minutes)] / totalCount) * 100;
    },
  };
}

/** Calculate summary metrics from the time series. */
function analyzePoints(points) {
  if (!points.length) {
    return null;
  }
  const start = points[0].x;
  const end = points[points.length - 1].x;
  const duration = Math.max(0, end - start);

  let minCount = points[0].y;
  let maxCount = points[0].y;
  let maxTime = start;

  for (let i = 0; i < points.length; i += 1) {
    const point = points[i];
    if (point.y < minCount) {
      minCount = point.y;
    }
    if (point.y > maxCount) {
      maxCount = point.y;
      maxTime = point.x;
    }
  }

  const peakThreshold = maxCount > 0 ? maxCount * 0.9 : 0;
  let weightedSum = 0;
  let totalTime = 0;
  let totalChange = 0;
  let totalArrivals = 0;
  let totalDepartures = 0;
  let highTotal = 0;
  let highLongest = 0;
  let currentHigh = 0;
  let highSum = 0;
  let highSumSquares = 0;

  for (let i = 0; i < points.length - 1; i += 1) {
    const point = points[i];
    const next = points[i + 1];
    const dt = Math.max(0, next.x - point.x);
    totalTime += dt;
    weightedSum += point.y * dt;

    if (peakThreshold > 0 && point.y >= peakThreshold) {
      highTotal += dt;
      currentHigh += dt;
      highSum += point.y * dt;
      highSumSquares += point.y * point.y * dt;
    } else {
      if (currentHigh > highLongest) {
        highLongest = currentHigh;
      }
      currentHigh = 0;
    }

    const delta = next.y - point.y;
    if (delta !== 0) {
      totalChange += Math.abs(delta);
      if (delta > 0) {
        totalArrivals += delta;
      } else {
        totalDepartures += Math.abs(delta);
      }
    }
  }

  if (currentHigh > highLongest) {
    highLongest = currentHigh;
  }

  const avgCount = totalTime > 0 ? weightedSum / totalTime : points[points.length - 1].y;
  const highMean = highTotal > 0 ? highSum / highTotal : 0;
  const highVariance = highTotal > 0 ? highSumSquares / highTotal - highMean * highMean : 0;
  const highStd = highVariance > 0 ? Math.sqrt(highVariance) : 0;

  const durations = computeDurations(points);
  const medianMinutes = computeMedianFromBins(durations.bins, durations.totalDepartures);
  const avgStaySeconds = durations.totalDepartures > 0
    ? durations.totalDurationSeconds / durations.totalDepartures
    : null;

  const shortLimit = 30;
  const mediumLimit = 90;
  let shortCount = 0;
  let mediumCount = 0;
  let longCount = 0;
  durations.bins.forEach((count, minutes) => {
    if (minutes < shortLimit) {
      shortCount += count;
    } else if (minutes <= mediumLimit) {
      mediumCount += count;
    } else {
      longCount += count;
    }
  });

  const buckets = buildBuckets(points);
  const arrivalPeaks = topBuckets(buckets.arrivals, 3);
  const departurePeaks = topBuckets(buckets.departures, 3);

  const retention = buildRetention(
    durations.bins,
    durations.totalDepartures,
    durations.maxMinutes
  );

  return {
    start,
    end,
    duration,
    minCount,
    maxCount,
    maxTime,
    avgCount,
    totalArrivals,
    totalDepartures,
    totalChange,
    peakThreshold,
    highTotal,
    highLongest,
    highMean,
    highStd,
    avgStaySeconds,
    medianMinutes,
    durations,
    shortCount,
    mediumCount,
    longCount,
    arrivalPeaks,
    departurePeaks,
    retention,
  };
}

/** Render all analysis blocks for a selected archive. */
function renderAnalysis(points, chartPoints, archiveName) {
  if (!points.length) {
    renderStats([]);
    renderList(analysisDuration, []);
    renderList(analysisFlows, []);
    renderList(analysisRetentionSummary, []);
    renderText(analysisInterpretation, []);
    buildChart([]);
    buildRetentionChart([]);
    return;
  }

  const metrics = analyzePoints(points);
  if (!metrics) {
    return;
  }

  const capacity = getCapacity();
  const durationHours = metrics.duration > 0 ? metrics.duration / 3600 : 0;
  const fluctuationRate = durationHours > 0 ? metrics.totalChange / durationHours : 0;
  const highStability = metrics.highMean > 0
    ? Math.max(0, 100 - (metrics.highStd / metrics.highMean) * 100)
    : null;

  const capacityStats = [];
  if (capacity) {
    const avgUtil = (metrics.avgCount / capacity) * 100;
    const peakUtil = (metrics.maxCount / capacity) * 100;
    capacityStats.push({
      label: t("analysis.stat.capacityPeak"),
      value: `${peakUtil.toFixed(0)}%`,
    });
    capacityStats.push({
      label: t("analysis.stat.capacityAvg"),
      value: `${avgUtil.toFixed(0)}%`,
    });
  }

  const stats = [
    { label: t("analysis.stat.start"), value: formatTime(metrics.start) },
    { label: t("analysis.stat.end"), value: formatTime(metrics.end) },
    { label: t("analysis.stat.duration"), value: formatDuration(metrics.duration) },
    {
      label: t("analysis.stat.peak"),
      value: t("analysis.stat.peakValue", {
        count: metrics.maxCount,
        time: formatTime(metrics.maxTime),
      }),
    },
    { label: t("analysis.stat.minimum"), value: String(metrics.minCount) },
    { label: t("analysis.stat.average"), value: metrics.avgCount.toFixed(1) },
    { label: t("analysis.stat.fluctuation"), value: fluctuationRate.toFixed(1) },
    { label: t("analysis.stat.highPhase"), value: formatDuration(metrics.highTotal) },
    { label: t("analysis.stat.highPhaseLongest"), value: formatDuration(metrics.highLongest) },
    {
      label: t("analysis.stat.highPhaseStability"),
      value: highStability !== null ? `${highStability.toFixed(0)}%` : "-",
    },
  ];

  capacityStats.forEach((item) => stats.push(item));
  renderStats(stats);

  const stayItems = [];
  if (metrics.avgStaySeconds !== null && metrics.medianMinutes !== null) {
    stayItems.push({
      label: t("analysis.stay.average"),
      value: formatDuration(metrics.avgStaySeconds),
    });
    stayItems.push({
      label: t("analysis.stay.median"),
      value: formatMinutes(metrics.medianMinutes),
    });
  } else {
    stayItems.push({ label: t("analysis.stay.duration"), value: t("analysis.stay.none") });
  }

  const totalDeparted = metrics.durations.totalDepartures;
  if (totalDeparted > 0) {
    const shortPct = (metrics.shortCount / totalDeparted) * 100;
    const mediumPct = (metrics.mediumCount / totalDeparted) * 100;
    const longPct = (metrics.longCount / totalDeparted) * 100;
    stayItems.push({
      label: t("analysis.stay.short"),
      value: `${metrics.shortCount} (${shortPct.toFixed(0)}%)`,
    });
    stayItems.push({
      label: t("analysis.stay.medium"),
      value: `${metrics.mediumCount} (${mediumPct.toFixed(0)}%)`,
    });
    stayItems.push({
      label: t("analysis.stay.long"),
      value: `${metrics.longCount} (${longPct.toFixed(0)}%)`,
    });
  }
  renderList(analysisDuration, stayItems);

  const flowItems = [];
  if (metrics.arrivalPeaks.length) {
    metrics.arrivalPeaks.forEach((item, index) => {
      flowItems.push({
        label: t("analysis.flow.arrival", { index: index + 1 }),
        value: `${formatBucket(item.bucket)} (${item.count})`,
      });
    });
  } else {
    flowItems.push({ label: t("analysis.flow.arrivalLabel"), value: t("analysis.flow.none") });
  }
  if (metrics.departurePeaks.length) {
    metrics.departurePeaks.forEach((item, index) => {
      flowItems.push({
        label: t("analysis.flow.departure", { index: index + 1 }),
        value: `${formatBucket(item.bucket)} (${item.count})`,
      });
    });
  } else {
    flowItems.push({ label: t("analysis.flow.departureLabel"), value: t("analysis.flow.none") });
  }
  renderList(analysisFlows, flowItems);

  const retentionItems = [];
  if (metrics.retention.points.length) {
    retentionItems.push({
      label: t("analysis.retention.item30"),
      value: `${metrics.retention.lookup(30).toFixed(0)}%`,
    });
    retentionItems.push({
      label: t("analysis.retention.item60"),
      value: `${metrics.retention.lookup(60).toFixed(0)}%`,
    });
    retentionItems.push({
      label: t("analysis.retention.item120"),
      value: `${metrics.retention.lookup(120).toFixed(0)}%`,
    });
  } else {
    retentionItems.push({ label: t("analysis.retention.label"), value: t("analysis.stay.none") });
  }
  renderList(analysisRetentionSummary, retentionItems);

  const suggestions = [];
  if (capacity) {
    const peakUtil = (metrics.maxCount / capacity) * 100;
    if (peakUtil >= 95) {
      suggestions.push(
        t("analysis.suggest.capacity.high")
      );
    } else if (peakUtil >= 80) {
      suggestions.push(
        t("analysis.suggest.capacity.medium")
      );
    } else {
      suggestions.push(t("analysis.suggest.capacity.ok"));
    }
  }

  if (metrics.avgStaySeconds && metrics.avgStaySeconds > 90 * 60) {
    suggestions.push(t("analysis.suggest.stay.long"));
  } else if (metrics.avgStaySeconds && metrics.avgStaySeconds < 45 * 60) {
    suggestions.push(t("analysis.suggest.stay.short"));
  }

  if (metrics.highTotal > 60 * 60) {
    suggestions.push(t("analysis.suggest.highPhase"));
  }

  if (fluctuationRate > 30) {
    suggestions.push(t("analysis.suggest.fluctuation"));
  }

  if (!suggestions.length) {
    suggestions.push(t("analysis.suggest.none"));
  }
  renderText(analysisInterpretation, suggestions);

  buildChart(chartPoints);
  buildRetentionChart(metrics.retention.points);
}

/** Load a specific archive CSV and render analysis. */
async function loadArchive(name, options = {}) {
  const notify = options.notify !== false;
  const previous = {
    points: lastPoints,
    chartPoints: lastChartPoints,
    name: lastArchive,
  };
  setStatus(archiveStatus, t("analysis.loading"));
  renderAnalysisSkeleton();
  if (analysisCurrentTitle) {
    analysisCurrentTitle.textContent = name;
  }
  try {
    const response = await analysisFetch("read", { body: { name } });
    const text = await response.text();
    const parsed = parseCsv(text);
    lastArchive = name;
    setStoredArchive(name);
    if (!parsed.points.length) {
      setStatus(archiveStatus, t("analysis.noDataArchive"), true);
      renderAnalysis([], [], name);
      if (analysisCurrentTitle) {
        analysisCurrentTitle.textContent = name;
      }
      if (notify) {
        showToast(t("analysis.loadedNoDataToast"), "secondary");
      }
      return;
    }

    lastPoints = parsed.points;
    lastChartPoints = parsed.chartPoints;
    if (analysisCurrentTitle) {
      analysisCurrentTitle.textContent = name;
    }
    renderAnalysis(parsed.points, parsed.chartPoints, name);
    setStatus(archiveStatus, t("analysis.loaded"));
    if (notify) {
      showToast(t("analysis.loaded"), "success");
    }
  } catch (error) {
    console.error(error);
    setStatus(archiveStatus, t("analysis.failed"), true);
    if (Array.isArray(previous.points) && previous.points.length) {
      renderAnalysis(previous.points, previous.chartPoints || previous.points, previous.name);
      if (analysisCurrentTitle) {
        analysisCurrentTitle.textContent = previous.name || t("analysis.current.none");
      }
    } else {
      renderAnalysis([], [], "");
      if (analysisCurrentTitle) {
        analysisCurrentTitle.textContent = t("analysis.current.none");
      }
    }
    if (notify) {
      showToast(t("analysis.failed"), "danger");
    }
  }
}

if (analysisSave && analysisTokenInput) {
  analysisSave.addEventListener("click", async () => {
    const token = analysisTokenInput.value.trim();
    if (!token) {
      setStatus(analysisStatus, t("token.adminMissing"), true);
      analysisTokenInput.focus();
      return;
    }
    setStatus(analysisStatus, t("token.adminChecking"));
    const ok = await verifyToken(token);
    if (!ok) {
      setStatus(analysisStatus, t("token.adminInvalid"), true);
      return;
    }
    setToken(token);
    setButtonLoading(analysisSave, true, t("common.savingShort"));
    await loadArchives();
    setButtonLoading(analysisSave, false);
  });
}

if (analysisClear) {
  analysisClear.addEventListener("click", () => {
    clearToken();
    setStatus(archiveStatus, t("archive.noneLoaded"));
    renderArchives([]);
    renderAnalysis([], [], "");
    archiveItems = [];
    if (archiveSearchInput) {
      archiveSearchInput.value = "";
    }
    if (archiveSortSelect) {
      archiveSortSelect.value = "modified_desc";
    }
    lastPoints = null;
    lastChartPoints = null;
    lastArchive = "";
    if (analysisCurrentTitle) {
      analysisCurrentTitle.textContent = t("analysis.current.none");
    }
  });
}

if (analysisLogout) {
  analysisLogout.addEventListener("click", () => {
    clearToken();
  });
}

if (analysisRefresh) {
  analysisRefresh.addEventListener("click", () => {
    setButtonLoading(analysisRefresh, true, t("common.refreshing"));
    loadArchives().finally(() => {
      setButtonLoading(analysisRefresh, false);
    });
  });
}

if (analysisTokenInput) {
  analysisTokenInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      analysisSave?.click();
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

if (capacityApply) {
  capacityApply.addEventListener("click", () => {
    const value = capacityInput ? Number(capacityInput.value) : null;
    if (value && Number.isFinite(value) && value > 0) {
      setCapacity(value);
      if (lastPoints) {
        renderAnalysis(lastPoints, lastChartPoints || lastPoints, lastArchive);
      }
    }
  });
}

if (capacityInput) {
  let savedCapacity = null;
  try {
    savedCapacity = localStorage.getItem(CAPACITY_KEY);
  } catch (error) {
    savedCapacity = null;
  }
  if (savedCapacity) {
    capacityInput.value = String(savedCapacity);
  } else if (DEFAULT_CAPACITY) {
    capacityInput.value = String(DEFAULT_CAPACITY);
  }
  capacityInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      capacityApply?.click();
    }
  });
}

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

refreshTokenStatus();
document.addEventListener("themechange", applyAnalysisChartsTheme);
document.addEventListener("accessibilitychange", applyAnalysisChartsTheme);
document.addEventListener("languagechange", () => {
  refreshTokenStatus();
  updateArchiveView();
  if (chart?.data?.datasets?.[0]) {
    chart.data.datasets[0].label = t("chart.visitors");
    chart.update();
  }
  if (retentionChart?.data?.datasets?.[0]) {
    retentionChart.data.datasets[0].label = t("analysis.retention.label");
    retentionChart.update();
  }
  if (lastPoints) {
    renderAnalysis(lastPoints, lastChartPoints || lastPoints, lastArchive);
  } else {
    renderAnalysis([], [], "");
    if (analysisCurrentTitle) {
      analysisCurrentTitle.textContent = lastArchive || t("analysis.current.none");
    }
  }
});
const existingToken = getToken();
if (existingToken) {
  void maybeStoreAdminToken(existingToken);
  loadArchives();
}
