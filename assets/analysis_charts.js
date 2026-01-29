(() => {
  let salesChart = null;
  let cashierChart = null;
  let revenueChart = null;
  let lastCharts = null;

  if (!window.Chart) {
    return;
  }

  function getThemeColor(varName, fallback) {
    const value = getComputedStyle(document.documentElement)
      .getPropertyValue(varName)
      .trim();
    return value || fallback;
  }

  function buildPalette() {
    return {
      sold: getThemeColor("--chart-line", "rgba(37, 99, 235, 1)"),
      voucher: getThemeColor("--chart-retention-line", "rgba(6, 182, 212, 1)"),
      free: getThemeColor("--chart-fill-start", "rgba(16, 185, 129, 0.9)"),
      storno: getThemeColor("--chart-danger", "rgba(239, 68, 68, 1)"),
      grid: getThemeColor("--bs-border-color", "rgba(148, 163, 184, 0.15)"),
      tick: getThemeColor("--bs-secondary-color", "#64748b"),
      tooltipBg: getThemeColor("--chart-tooltip-bg", "rgba(15, 23, 42, 0.9)"),
      tooltipColor: getThemeColor("--chart-tooltip-color", "#ffffff"),
    };
  }

  function createSalesChart(payload) {
    const canvas = document.getElementById("salesChart");
    if (!canvas || !payload || !Array.isArray(payload.labels)) {
      return null;
    }
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      return null;
    }
    const palette = buildPalette();
    return new Chart(ctx, {
      type: "line",
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: "Verkauft",
            data: payload.series?.Verkauft || [],
            borderColor: palette.sold,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Gutschein",
            data: payload.series?.Gutschein || [],
            borderColor: palette.voucher,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Gratis",
            data: payload.series?.Gratis || [],
            borderColor: palette.free,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Storno",
            data: payload.series?.Storno || [],
            borderColor: palette.storno,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: "index" },
        plugins: {
          legend: { position: "bottom" },
          tooltip: {
            backgroundColor: palette.tooltipBg,
            titleColor: palette.tooltipColor,
            bodyColor: palette.tooltipColor,
            padding: 12,
            cornerRadius: 12,
          },
        },
        scales: {
          x: {
            ticks: { color: palette.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
            grid: { color: palette.grid },
          },
          y: {
            ticks: { color: palette.tick, precision: 0 },
            grid: { color: palette.grid },
            beginAtZero: true,
          },
        },
      },
    });
  }

  function createCashierChart(payload) {
    const canvas = document.getElementById("cashierChart");
    if (!canvas || !payload || !Array.isArray(payload.labels)) {
      return null;
    }
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      return null;
    }
    const palette = buildPalette();
    const colors = [
      palette.sold,
      palette.voucher,
      palette.free,
      "#8b5cf6",
      "#f59e0b",
      "#ec4899",
    ];
    const datasets = [];
    let i = 0;
    for (const [name, data] of Object.entries(payload.series || {})) {
      datasets.push({
        label: name,
        data: data,
        borderColor: colors[i % colors.length],
        backgroundColor: "transparent",
        tension: 0.35,
        borderWidth: 2,
        pointRadius: 0,
      });
      i++;
    }
    return new Chart(ctx, {
      type: "line",
      data: { labels: payload.labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: "index" },
        plugins: {
          legend: { position: "bottom" },
          tooltip: {
            backgroundColor: palette.tooltipBg,
            titleColor: palette.tooltipColor,
            bodyColor: palette.tooltipColor,
            padding: 12,
            cornerRadius: 12,
          },
        },
        scales: {
          x: {
            ticks: { color: palette.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
            grid: { color: palette.grid },
          },
          y: {
            ticks: { color: palette.tick, precision: 0 },
            grid: { color: palette.grid },
            beginAtZero: true,
          },
        },
      },
    });
  }

  function createRevenueChart(payload) {
    const canvas = document.getElementById("revenueChart");
    if (!canvas || !payload || !Array.isArray(payload.labels)) {
      return null;
    }
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      return null;
    }
    const palette = buildPalette();
    return new Chart(ctx, {
      type: "line",
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: "Einnahmen (€)",
            data: payload.series || [],
            borderColor: palette.sold,
            backgroundColor: palette.sold.replace("1)", "0.1)"),
            fill: true,
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: "index" },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: palette.tooltipBg,
            titleColor: palette.tooltipColor,
            bodyColor: palette.tooltipColor,
            padding: 12,
            cornerRadius: 12,
            callbacks: {
              label: (ctx) => `Einnahmen: € ${ctx.parsed.y.toFixed(2)}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: palette.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
            grid: { color: palette.grid },
          },
          y: {
            ticks: {
              color: palette.tick,
              callback: (val) => `€ ${val}`,
            },
            grid: { color: palette.grid },
            beginAtZero: true,
          },
        },
      },
    });
  }

  window.renderKekCharts = (charts) => {
    lastCharts = charts;
    if (salesChart) salesChart.destroy();
    if (cashierChart) cashierChart.destroy();
    if (revenueChart) revenueChart.destroy();

    salesChart = createSalesChart(charts.sales);
    cashierChart = createCashierChart(charts.cashier);
    revenueChart = createRevenueChart(charts.revenue);
  };

  // Initial render if data is already present (legacy support or direct injection)
  if (window.CHART_DATA) {
    window.renderKekCharts(window.CHART_DATA);
  }

  document.addEventListener("themechange", () => {
    if (lastCharts) {
      window.renderKekCharts(lastCharts);
    }
  });
})();
