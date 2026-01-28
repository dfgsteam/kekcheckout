const settings = window.APP_CONFIG?.settings || window.KEKCOUNTER_SETTINGS || {};
const t = (key, vars) =>
  typeof window.t === "function" ? window.t(key, vars) : key;
const getBaseTitle = () => {
  const translated = t("title.display");
  return translated === "title.display" ? document.title : translated;
};
const statusUrl =
  window.APP_CONFIG?.statusUrl ||
  window.KEKCOUNTER_STATUS_URL ||
  "index.php?action=status";
const threshold = Number(settings.threshold) > 0 ? Number(settings.threshold) : 150;
const windowHours = Number(settings.window_hours) > 0 ? Number(settings.window_hours) : 3;
const tickMinutes = Number(settings.tick_minutes) > 0 ? Number(settings.tick_minutes) : 15;
let baseTitle = getBaseTitle();
let lastCount = 0;
let lastEventName = "";

const countEl = document.getElementById("count");
const countBlock = document.getElementById("countBlock");
const updatedEl = document.getElementById("updated");
const statusDot = document.getElementById("statusDot");
const eventNameEl = document.getElementById("eventName");
const thresholdValueEl = document.getElementById("thresholdValue");
const fullscreenButton = document.getElementById("displayFullscreen");
const fullscreenLabel = fullscreenButton?.querySelector("[data-fullscreen-label]");
const fullscreenIcon = fullscreenButton?.querySelector("[data-fullscreen-icon]");

const REQUEST_TIMEOUT_MS = 8000;
const WINDOW_SECONDS = windowHours * 3600;
const TICK_STEP_SECONDS = tickMinutes * 60;
const MAX_TICKS = Math.max(3, Math.min(20, Math.floor(WINDOW_SECONDS / TICK_STEP_SECONDS) + 1));

let axisBaseMs = null;
let chart = null;
let gradient = null;
let requestCounter = 0;
let lastAppliedRequest = 0;
let statusInFlight = false;

if (thresholdValueEl) {
  thresholdValueEl.textContent = String(threshold);
}

const chartCanvas = document.getElementById("displayChart");

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
    fillStart: getThemeColor("--chart-fill-start", "rgba(37, 99, 235, 0.35)"),
    fillEnd: getThemeColor("--chart-fill-end", "rgba(37, 99, 235, 0)"),
    danger: getThemeColor("--chart-danger", "rgba(239, 68, 68, 1)"),
    criticalFill: getThemeColor("--chart-critical-fill", "rgba(239, 68, 68, 0.1)"),
    criticalLine: getThemeColor("--chart-critical-line", "rgba(239, 68, 68, 0.7)"),
    tooltipBg: getThemeColor("--chart-tooltip-bg", "rgba(15, 23, 42, 0.9)"),
    tooltipColor: getThemeColor("--chart-tooltip-color", "#ffffff"),
  };
}

/** Apply updated theme colors to the chart instance. */
function applyChartTheme() {
  if (!chart) {
    return;
  }
  const tickColor = getThemeColor("--bs-secondary-color", "#64748b");
  const gridColor = getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)");
  const palette = getChartPalette();
  const ctx = chart.ctx;
  if (ctx) {
    const area = chart.chartArea;
    const height = area ? area.bottom - area.top : 420;
    gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, palette.fillStart);
    gradient.addColorStop(1, palette.fillEnd);
  }
  if (chart.data.datasets[0]) {
    chart.data.datasets[0].borderColor = palette.line;
    chart.data.datasets[0].backgroundColor = gradient || palette.fillStart;
    chart.data.datasets[0].segment = {
      borderColor: (segmentCtx) =>
        segmentCtx.p0.parsed.y >= threshold || segmentCtx.p1.parsed.y >= threshold
          ? palette.danger
          : palette.line,
    };
  }
  if (chart.options.plugins?.criticalZone) {
    chart.options.plugins.criticalZone.fill = palette.criticalFill;
    chart.options.plugins.criticalZone.line = palette.criticalLine;
  }
  if (chart.options.plugins?.tooltip) {
    chart.options.plugins.tooltip.backgroundColor = palette.tooltipBg;
    chart.options.plugins.tooltip.titleColor = palette.tooltipColor;
    chart.options.plugins.tooltip.bodyColor = palette.tooltipColor;
  }
  chart.options.scales.x.ticks.color = tickColor;
  chart.options.scales.y.ticks.color = tickColor;
  chart.options.scales.x.grid.color = gridColor;
  chart.options.scales.y.grid.color = gridColor;
  chart.update();
}

/** Sync fullscreen button label/icon with state. */
function updateFullscreenButton() {
  if (!fullscreenButton) {
    return;
  }
  if (!document.fullscreenEnabled) {
    fullscreenButton.classList.add("d-none");
    return;
  }
  const isFullscreen = Boolean(document.fullscreenElement);
  const labelText = isFullscreen ? t("display.fullscreenExit") : t("display.fullscreen");
  if (fullscreenLabel) {
    fullscreenLabel.textContent = labelText;
  }
  fullscreenButton.setAttribute(
    "aria-label",
    isFullscreen ? t("display.fullscreenExitAria") : t("display.fullscreenAria")
  );
  fullscreenButton.setAttribute("title", labelText);
  if (fullscreenIcon) {
    fullscreenIcon.classList.toggle("bi-fullscreen", !isFullscreen);
    fullscreenIcon.classList.toggle("bi-fullscreen-exit", isFullscreen);
  }
}

/** Toggle fullscreen mode for the document. */
async function toggleFullscreen() {
  if (!document.fullscreenEnabled) {
    return;
  }
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else {
      await document.documentElement.requestFullscreen();
    }
  } catch (error) {
    console.error(error);
  }
}

const criticalZone = {
  id: "criticalZone",
  beforeDatasetsDraw(chartInstance, args, options) {
    const { ctx, chartArea, scales } = chartInstance;
    if (!chartArea || !scales?.y) {
      return;
    }
    const limit = options?.threshold ?? threshold;
    const y = scales.y.getPixelForValue(limit);
    const top = chartArea.top;
    const bottom = chartArea.bottom;
    const yClamped = Math.min(Math.max(y, top), bottom);
    const height = yClamped - top;
    if (height > 0) {
      ctx.save();
      ctx.fillStyle = options?.fill ?? "rgba(239, 68, 68, 0.08)";
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

if (chartCanvas && window.Chart) {
  const ctx = chartCanvas.getContext("2d");
  if (ctx) {
    const tickColor = getThemeColor("--bs-secondary-color", "#64748b");
    const gridColor = getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)");
    const palette = getChartPalette();
    gradient = ctx.createLinearGradient(0, 0, 0, 420);
    gradient.addColorStop(0, palette.fillStart);
    gradient.addColorStop(1, palette.fillEnd);

    chart = new Chart(ctx, {
      type: "line",
      plugins: [criticalZone],
      data: {
        labels: [],
        datasets: [
          {
            label: t("chart.visitors"),
            data: [],
            borderColor: palette.line,
            backgroundColor: gradient,
            fill: true,
            tension: 0.35,
            borderWidth: 3,
            pointRadius: 0,
            parsing: false,
            segment: {
              borderColor: (segmentCtx) =>
                segmentCtx.p0.parsed.y >= threshold || segmentCtx.p1.parsed.y >= threshold
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
            threshold,
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
                const seconds = items[0].parsed.x;
                return formatTime(seconds);
              },
              label(item) {
                return t("chart.occupancyLabel", { count: item.parsed.y });
              },
            },
          },
        },
        scales: {
          x: {
            type: "linear",
            grid: {
              color: gridColor,
            },
            ticks: {
              color: tickColor,
              maxRotation: 0,
              maxTicksLimit: MAX_TICKS,
              stepSize: TICK_STEP_SECONDS,
              autoSkip: false,
              callback(value) {
                return formatTime(Number(value));
              },
            },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: gridColor,
            },
            ticks: {
              color: tickColor,
            },
          },
        },
      },
    });
    applyChartTheme();
  }
}

/** Get a local midnight timestamp for chart time labels. */
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

/** Toggle danger UI state based on the threshold. */
function setButtonsState(danger) {
  if (countEl) {
    countEl.classList.toggle("text-danger", danger);
  }
  if (statusDot) {
    statusDot.classList.toggle("bg-danger", danger);
    statusDot.classList.toggle("bg-success", !danger);
  }
  if (countBlock) {
    countBlock.classList.toggle("border-danger", danger);
    countBlock.classList.toggle("bg-danger-subtle", danger);
    countBlock.classList.toggle("border-primary-subtle", !danger);
    countBlock.classList.toggle("bg-body-tertiary", !danger);
  }
}

/** Apply incoming status data to the UI and chart. */
function applyData(data) {
  const count = Number(data.count ?? 0);
  const labels = Array.isArray(data.labels) ? data.labels : [];
  const values = Array.isArray(data.values) ? data.values : [];
  const eventName =
    typeof data.eventName === "string" ? data.eventName.trim() : "";

  if (countEl) {
    countEl.textContent = String(count);
  }
  if (updatedEl) {
    updatedEl.textContent = data.updatedAt ?? "--:--:--";
  }
  if (eventNameEl) {
    if (eventName) {
      eventNameEl.textContent = eventName;
      eventNameEl.removeAttribute("data-i18n");
    } else {
      eventNameEl.textContent = t("event.unnamed");
      eventNameEl.setAttribute("data-i18n", "event.unnamed");
    }
  }

  const danger = count >= threshold;
  setButtonsState(danger);

  if (chart) {
    const points = [];
    const limit = Math.min(labels.length, values.length);
    let lastSeconds = null;
    let dayOffset = 0;

    for (let i = 0; i < limit; i += 1) {
      const seconds = parseTimeToSeconds(labels[i]);
      if (seconds === null) {
        continue;
      }
      let adjusted = seconds + dayOffset;
      if (lastSeconds !== null && adjusted < lastSeconds) {
        dayOffset += 24 * 3600;
        adjusted = seconds + dayOffset;
      }
      lastSeconds = adjusted;
      points.push({ x: adjusted, y: Number(values[i] ?? 0) });
    }

    axisBaseMs = points.length ? getMidnightBaseMs() : null;

    let windowStart = null;
    let windowEnd = null;
    if (points.length) {
      windowEnd = points[points.length - 1].x;
      windowStart = windowEnd - WINDOW_SECONDS;
    }

    const filtered = windowStart === null
      ? points
      : points.filter((point) => point.x >= windowStart);

    chart.data.labels = [];
    chart.data.datasets[0].data = filtered;
    chart.options.scales.x.min = windowStart ?? undefined;
    chart.options.scales.x.max = windowEnd ?? undefined;
    chart.update();
  }

  if (countEl) {
    countEl.classList.remove("pulse");
    void countEl.offsetWidth;
    countEl.classList.add("pulse");
  }

  lastCount = count;
  lastEventName = eventName;
  updateDocumentTitle();
}

/** Update the document title using the latest count data. */
function updateDocumentTitle() {
  baseTitle = getBaseTitle();
  if (lastEventName) {
    document.title = t("title.eventCount", { event: lastEventName, count: lastCount });
    return;
  }
  document.title = t("title.indexCount", { base: baseTitle, count: lastCount });
}

/** Fetch with an abort timeout for reliability. */
async function fetchWithTimeout(url, options, timeoutMs) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, { ...options, signal: controller.signal });
  } finally {
    clearTimeout(timeoutId);
  }
}

/** Fetch the current counter status and update the UI. */
async function fetchStatus() {
  if (statusInFlight) {
    return;
  }
  statusInFlight = true;
  const requestId = ++requestCounter;
  try {
    const response = await fetchWithTimeout(statusUrl, { cache: "no-store" }, REQUEST_TIMEOUT_MS);
    if (!response.ok) {
      throw new Error("Request failed");
    }
    const data = await response.json();
    if (requestId < lastAppliedRequest) {
      return;
    }
    applyData(data);
    lastAppliedRequest = requestId;
  } catch (error) {
    console.error(error);
  } finally {
    statusInFlight = false;
  }
}

fetchStatus();
setInterval(fetchStatus, 15000);
document.addEventListener("themechange", applyChartTheme);
document.addEventListener("accessibilitychange", applyChartTheme);
document.addEventListener("fullscreenchange", updateFullscreenButton);
document.addEventListener("languagechange", () => {
  updateFullscreenButton();
  if (chart?.data?.datasets?.[0]) {
    chart.data.datasets[0].label = t("chart.visitors");
    chart.update();
  }
  if (eventNameEl && !lastEventName) {
    eventNameEl.textContent = t("event.unnamed");
  }
  updateDocumentTitle();
});

if (fullscreenButton) {
  updateFullscreenButton();
  fullscreenButton.addEventListener("click", () => {
    toggleFullscreen();
  });
}
