<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

require_once __DIR__ . '/private/layout.php';
require_once __DIR__ . '/private/sales_lib.php';
require_once __DIR__ . '/private/bootstrap.php';

$booking_path = __DIR__ . '/private/bookings.csv';
$settings_path = __DIR__ . '/private/settings.json';
$settings = load_settings($settings_path);
$bucket_minutes = (int)($settings['tick_minutes'] ?? 15);
if ($bucket_minutes <= 0) {
    $bucket_minutes = 15;
}
$chart_max_points = (int)($settings['chart_max_points'] ?? 2000);
if ($chart_max_points <= 0) {
    $chart_max_points = 2000;
}
$booking_rows = [];
$booking_headers = [];
$booking_truncated = false;
$booking_limit = 200;
$booking_stats = [
    'revenue' => 0.0,
    'count' => 0,
    'average' => 0.0,
];
$booking_hide_cols = ['user_id', 'produkt_id', 'kategorie_id'];
$booking_visible_headers = [];
$booking_visible_rows = [];
$sales_labels = [];
$sales_series = [
    'Verkauft' => [],
    'Gutschein' => [],
    'Freigetraenk' => [],
    'Storno' => [],
];
$cashier_labels = [];
$cashier_series = [];
$cashier_totals = [];
$revenue_labels = [];
$revenue_series = [];

if (is_file($booking_path)) {
    $rows = sales_read_csv($booking_path);
    if ($rows) {
        $headers = array_shift($rows);
        if (is_array($headers)) {
            $booking_headers = $headers;
        }
        $index_map = $booking_headers ? array_flip($booking_headers) : [];
        $earnings_col = $index_map['einnahmen'] ?? null;
        $status_col = $index_map['status'] ?? null;
        $type_col = $index_map['buchungstyp'] ?? null;
        $time_col = $index_map['uhrzeit'] ?? null;
        $user_col = $index_map['user_name'] ?? ($index_map['user_id'] ?? null);
        $tz = new DateTimeZone(date_default_timezone_get());
        $sales_buckets = [];
        $cashier_buckets = [];
        $revenue_buckets = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = $status_col !== null ? (string)($row[$status_col] ?? '') : 'OK';
            if ($status !== 'STORNO') {
                $booking_stats['count']++;
                if ($earnings_col !== null) {
                    $raw = str_replace(',', '.', (string)($row[$earnings_col] ?? '0'));
                    $value = (float)$raw;
                    if ($value > 0) {
                        $booking_stats['revenue'] += $value;
                    }
                }
            }
            if ($time_col !== null) {
                $raw_time = (string)($row[$time_col] ?? '');
                if ($raw_time !== '') {
                    try {
                        $dt = new DateTimeImmutable($raw_time);
                        $dt = $dt->setTimezone($tz);
                        $hour = (int)$dt->format('H');
                        $minute = (int)$dt->format('i');
                        $bucket = intdiv($minute, $bucket_minutes) * $bucket_minutes;
                        $bucket_dt = $dt->setTime($hour, $bucket, 0);
                        $bucket_key = $bucket_dt->format('Y-m-d H:i');
                        if (!isset($sales_buckets[$bucket_key])) {
                            $sales_buckets[$bucket_key] = [
                                'Verkauft' => 0,
                                'Gutschein' => 0,
                                'Freigetraenk' => 0,
                                'Storno' => 0,
                            ];
                        }
                        if ($status === 'STORNO') {
                            $sales_buckets[$bucket_key]['Storno']++;
                        } else {
                            $type = $type_col !== null ? (string)($row[$type_col] ?? '') : '';
                            if (!isset($sales_buckets[$bucket_key][$type])) {
                                $type = 'Verkauft';
                            }
                            $sales_buckets[$bucket_key][$type]++;
                        }
                        if (!isset($revenue_buckets[$bucket_key])) {
                            $revenue_buckets[$bucket_key] = 0.0;
                        }
                        if ($status !== 'STORNO' && $earnings_col !== null) {
                            $raw_earnings = str_replace(',', '.', (string)($row[$earnings_col] ?? '0'));
                            $revenue_buckets[$bucket_key] += (float)$raw_earnings;
                        }

                        if ($user_col !== null) {
                            $user = trim((string)($row[$user_col] ?? ''));
                            if ($user === '') {
                                $user = 'Unbekannt';
                            }
                            if (!isset($cashier_buckets[$bucket_key])) {
                                $cashier_buckets[$bucket_key] = [];
                            }
                            if (!isset($cashier_buckets[$bucket_key][$user])) {
                                $cashier_buckets[$bucket_key][$user] = 0;
                            }
                            $cashier_buckets[$bucket_key][$user]++;
                            if (!isset($cashier_totals[$user])) {
                                $cashier_totals[$user] = 0;
                            }
                            $cashier_totals[$user]++;
                        }
                    } catch (Exception $e) {
                    }
                }
            }
        }
        if ($booking_stats['count'] > 0) {
            $booking_stats['average'] = $booking_stats['revenue'] / $booking_stats['count'];
        }
        if (count($rows) > $booking_limit) {
            $booking_rows = array_slice($rows, -$booking_limit);
            $booking_truncated = true;
        } else {
            $booking_rows = $rows;
        }
        if (!empty($sales_buckets)) {
            ksort($sales_buckets);
            $bucket_keys = array_keys($sales_buckets);
            if (count($bucket_keys) > $chart_max_points) {
                $bucket_keys = array_slice($bucket_keys, -$chart_max_points);
            }
            foreach ($bucket_keys as $bucket_key) {
                $sales_labels[] = $bucket_key;
                $bucket = $sales_buckets[$bucket_key];
                $sales_series['Verkauft'][] = (int)($bucket['Verkauft'] ?? 0);
                $sales_series['Gutschein'][] = (int)($bucket['Gutschein'] ?? 0);
                $sales_series['Freigetraenk'][] = (int)($bucket['Freigetraenk'] ?? 0);
                $sales_series['Storno'][] = (int)($bucket['Storno'] ?? 0);
            }
        }
        if (!empty($cashier_buckets)) {
            arsort($cashier_totals);
            $top_users = array_slice(array_keys($cashier_totals), 0, 5);
            $include_others = count($cashier_totals) > count($top_users);
            ksort($cashier_buckets);
            $bucket_keys = array_keys($cashier_buckets);
            if (count($bucket_keys) > $chart_max_points) {
                $bucket_keys = array_slice($bucket_keys, -$chart_max_points);
            }
            $cashier_labels = $bucket_keys;
            foreach ($top_users as $user) {
                $cashier_series[$user] = [];
            }
            if ($include_others) {
                $cashier_series['Andere'] = [];
            }
            foreach ($bucket_keys as $bucket_key) {
                $bucket = $cashier_buckets[$bucket_key] ?? [];
                foreach ($top_users as $user) {
                    $cashier_series[$user][] = (int)($bucket[$user] ?? 0);
                }
                if ($include_others) {
                    $other_count = 0;
                    foreach ($bucket as $user => $count) {
                        if (!in_array($user, $top_users, true)) {
                            $other_count += (int)$count;
                        }
                    }
                    $cashier_series['Andere'][] = $other_count;
                }
            }
        }
        if (!empty($revenue_buckets)) {
            ksort($revenue_buckets);
            $bucket_keys = array_keys($revenue_buckets);
            if (count($bucket_keys) > $chart_max_points) {
                $bucket_keys = array_slice($bucket_keys, -$chart_max_points);
            }
            $revenue_labels = $bucket_keys;
            foreach ($bucket_keys as $bucket_key) {
                $revenue_series[] = round((float)($revenue_buckets[$bucket_key] ?? 0.0), 2);
            }
        }
    }
}

if ($booking_headers) {
    $visible_indexes = [];
    foreach ($booking_headers as $index => $label) {
        if (in_array((string)$label, $booking_hide_cols, true)) {
            continue;
        }
        $visible_indexes[] = $index;
        $booking_visible_headers[] = (string)$label;
    }
    foreach ($booking_rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $visible_row = [];
        foreach ($visible_indexes as $index) {
            $visible_row[] = (string)($row[$index] ?? '');
        }
        $booking_visible_rows[] = $visible_row;
    }
} else {
    $booking_visible_rows = $booking_rows;
}

$revenue_formatted = number_format($booking_stats['revenue'], 2, '.', '');
$count_formatted = number_format($booking_stats['count'], 0, '.', '');
$average_formatted = number_format($booking_stats['average'], 2, '.', '');
$sales_chart_payload = [
    'labels' => $sales_labels,
    'series' => $sales_series,
    'bucketMinutes' => $bucket_minutes,
];
$sales_chart_json = json_encode($sales_chart_payload, JSON_UNESCAPED_SLASHES);
if ($sales_chart_json === false) {
    $sales_chart_json = 'null';
}
$cashier_chart_payload = [
    'labels' => $cashier_labels,
    'series' => $cashier_series,
    'bucketMinutes' => $bucket_minutes,
];
$cashier_chart_json = json_encode($cashier_chart_payload, JSON_UNESCAPED_SLASHES);
if ($cashier_chart_json === false) {
    $cashier_chart_json = 'null';
}
$revenue_chart_payload = [
    'labels' => $revenue_labels,
    'series' => $revenue_series,
    'bucketMinutes' => $bucket_minutes,
];
$revenue_chart_json = json_encode($revenue_chart_payload, JSON_UNESCAPED_SLASHES);
if ($revenue_chart_json === false) {
    $revenue_chart_json = 'null';
}

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-4 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">Kek - Checkout</div>
    <h1 class="display-6 fw-semibold mb-2">Analyse</h1>
    <p class="text-secondary mb-0">Auswertungen und Trends fuer den Checkout.</p>
  </div>
  <div class="icon-actions">
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="index.php"
      data-i18n-aria-label="nav.back"
      data-i18n-title="nav.back"
    >
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
    </a>
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="display.php"
      data-i18n-aria-label="nav.display"
      data-i18n-title="nav.display"
    >
      <i class="bi bi-tv" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.display">Display</span>
    </a>
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="admin.php"
      data-i18n-aria-label="nav.admin"
      data-i18n-title="nav.admin"
    >
      <i class="bi bi-person" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.admin">Admin</span>
    </a>
    <button
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      type="button"
      data-settings-open
      data-i18n-aria-label="settings.button"
      data-i18n-title="settings.button"
    >
      <i class="bi bi-sliders2" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="settings.button">Einstellungen</span>
    </button>
  </div>
</header>
HTML;

ob_start();
?>
<section class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <h2 class="h5 mb-3">Kennzahlen</h2>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="border rounded p-3 bg-light h-100 analysis-block">
          <div class="text-secondary small">Umsatz</div>
          <div class="h4 mb-0">€ <?php echo htmlspecialchars($revenue_formatted, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="border rounded p-3 bg-light h-100 analysis-block">
          <div class="text-secondary small">Transaktionen</div>
          <div class="h4 mb-0"><?php echo htmlspecialchars($count_formatted, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="border rounded p-3 bg-light h-100 analysis-block">
          <div class="text-secondary small">Durchschnitt</div>
          <div class="h4 mb-0">€ <?php echo htmlspecialchars($average_formatted, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0">
  <div class="card-body">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Verkaufsverlauf</h2>
        <p class="text-secondary small mb-0">Buchungen je <?php echo htmlspecialchars((string)$bucket_minutes, ENT_QUOTES, 'UTF-8'); ?> Minuten.</p>
      </div>
    </div>
    <div class="border rounded p-3 bg-light">
      <div class="analysis-chart">
        <canvas id="salesChart" aria-label="Sales chart" role="img"></canvas>
      </div>
      <?php if (!$sales_labels) { ?>
        <p class="text-secondary small mt-3 mb-0">Keine Buchungsdaten fuer die Darstellung.</p>
      <?php } ?>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0 mt-4">
  <div class="card-body">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Buchungen pro Kasse</h2>
        <p class="text-secondary small mb-0">Top 5 Kassen je <?php echo htmlspecialchars((string)$bucket_minutes, ENT_QUOTES, 'UTF-8'); ?> Minuten.</p>
      </div>
    </div>
    <div class="border rounded p-3 bg-light">
      <div class="analysis-chart">
        <canvas id="cashierChart" aria-label="Cashier chart" role="img"></canvas>
      </div>
      <?php if (!$cashier_labels) { ?>
        <p class="text-secondary small mt-3 mb-0">Keine Buchungsdaten fuer die Darstellung.</p>
      <?php } ?>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0 mt-4">
  <div class="card-body">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1">Gesamt Einnahmen</h2>
        <p class="text-secondary small mb-0">Umsatz je <?php echo htmlspecialchars((string)$bucket_minutes, ENT_QUOTES, 'UTF-8'); ?> Minuten.</p>
      </div>
    </div>
    <div class="border rounded p-3 bg-light">
      <div class="analysis-chart">
        <canvas id="revenueChart" aria-label="Revenue chart" role="img"></canvas>
      </div>
      <?php if (!$revenue_labels) { ?>
        <p class="text-secondary small mt-3 mb-0">Keine Umsatzdaten fuer die Darstellung.</p>
      <?php } ?>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0 mt-4">
  <div class="card-body">
    <h2 class="h5 mb-3">Buchungslog</h2>
    <?php if (!is_file($booking_path)) { ?>
      <p class="text-secondary mb-0">Kein Buchungslog gefunden. Datei fehlt.</p>
    <?php } elseif (!$booking_rows) { ?>
      <p class="text-secondary mb-0">Keine Buchungen vorhanden.</p>
    <?php } else { ?>
      <div class="table-responsive analysis-table">
        <table class="table table-sm align-middle mb-0">
          <?php if ($booking_visible_headers) { ?>
            <thead>
              <tr>
                <?php foreach ($booking_visible_headers as $header) { ?>
                  <th scope="col"><?php echo htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php } ?>
              </tr>
            </thead>
          <?php } ?>
          <tbody>
            <?php foreach ($booking_visible_rows as $row) { ?>
              <tr>
                <?php foreach ($row as $cell) { ?>
                  <td><?php echo htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8'); ?></td>
                <?php } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($booking_truncated) { ?>
        <p class="text-secondary small mt-2 mb-0">Letzte <?php echo htmlspecialchars((string)$booking_limit, ENT_QUOTES, 'UTF-8'); ?> Buchungen angezeigt.</p>
      <?php } ?>
    <?php } ?>
  </div>
</section>
<?php
$content = ob_get_clean();

render_layout([
    'title' => 'Kek - Checkout Analyse',
    'description' => 'Analyse fuer den Checkout.',
    'header' => $header,
    'content' => $content,
    'head_extra' => '<meta name="robots" content="noindex, nofollow">',
    'manifest' => '',
    'include_chart_js' => true,
    'inline_scripts' => [
        <<<JS
(() => {
  const salesPayload = $sales_chart_json;
  const cashierPayload = $cashier_chart_json;
  const revenuePayload = $revenue_chart_json;
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

  function createSalesChart() {
    const canvas = document.getElementById("salesChart");
    if (!canvas || !salesPayload || !Array.isArray(salesPayload.labels)) {
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
        labels: salesPayload.labels,
        datasets: [
          {
            label: "Verkauft",
            data: salesPayload.series?.Verkauft || [],
            borderColor: palette.sold,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Gutschein",
            data: salesPayload.series?.Gutschein || [],
            borderColor: palette.voucher,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Freigetraenk",
            data: salesPayload.series?.Freigetraenk || [],
            borderColor: palette.free,
            backgroundColor: "transparent",
            tension: 0.35,
            borderWidth: 2,
            pointRadius: 0,
          },
          {
            label: "Storno",
            data: salesPayload.series?.Storno || [],
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

  function createCashierChart() {
    const canvas = document.getElementById("cashierChart");
    if (!canvas || !cashierPayload || !Array.isArray(cashierPayload.labels)) {
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
      palette.storno,
      "rgba(168, 85, 247, 1)",
      "rgba(234, 179, 8, 1)",
      "rgba(59, 130, 246, 1)",
      "rgba(14, 165, 233, 1)",
    ];
    const series = cashierPayload.series || {};
    const labels = Object.keys(series);
    const datasets = labels.map((label, index) => ({
      label,
      data: series[label] || [],
      borderColor: colors[index % colors.length],
      backgroundColor: "transparent",
      tension: 0.35,
      borderWidth: 2,
      pointRadius: 0,
    }));
    return new Chart(ctx, {
      type: "line",
      data: {
        labels: cashierPayload.labels,
        datasets,
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

  function createRevenueChart() {
    const canvas = document.getElementById("revenueChart");
    if (!canvas || !revenuePayload || !Array.isArray(revenuePayload.labels)) {
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
        labels: revenuePayload.labels,
        datasets: [
          {
            label: "Umsatz",
            data: revenuePayload.series || [],
            borderColor: palette.sold,
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
            callbacks: {
              label(item) {
                const value = typeof item.parsed?.y === "number" ? item.parsed.y : item.raw;
                if (typeof value === "number") {
                  return "Umsatz: " + value.toFixed(2) + " EUR";
                }
                return "Umsatz: " + value;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: palette.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
            grid: { color: palette.grid },
          },
          y: {
            ticks: { color: palette.tick },
            grid: { color: palette.grid },
            beginAtZero: true,
          },
        },
      },
    });
  }

  const salesChart = createSalesChart();
  const cashierChart = createCashierChart();
  const revenueChart = createRevenueChart();

  function applyTheme() {
    const next = buildPalette();
    if (salesChart) {
      salesChart.data.datasets[0].borderColor = next.sold;
      salesChart.data.datasets[1].borderColor = next.voucher;
      salesChart.data.datasets[2].borderColor = next.free;
      salesChart.data.datasets[3].borderColor = next.storno;
      salesChart.options.scales.x.ticks.color = next.tick;
      salesChart.options.scales.y.ticks.color = next.tick;
      salesChart.options.scales.x.grid.color = next.grid;
      salesChart.options.scales.y.grid.color = next.grid;
      if (salesChart.options.plugins?.tooltip) {
        salesChart.options.plugins.tooltip.backgroundColor = next.tooltipBg;
        salesChart.options.plugins.tooltip.titleColor = next.tooltipColor;
        salesChart.options.plugins.tooltip.bodyColor = next.tooltipColor;
      }
      salesChart.update();
    }
    if (cashierChart) {
      cashierChart.options.scales.x.ticks.color = next.tick;
      cashierChart.options.scales.y.ticks.color = next.tick;
      cashierChart.options.scales.x.grid.color = next.grid;
      cashierChart.options.scales.y.grid.color = next.grid;
      if (cashierChart.options.plugins?.tooltip) {
        cashierChart.options.plugins.tooltip.backgroundColor = next.tooltipBg;
        cashierChart.options.plugins.tooltip.titleColor = next.tooltipColor;
        cashierChart.options.plugins.tooltip.bodyColor = next.tooltipColor;
      }
      cashierChart.update();
    }
    if (revenueChart) {
      revenueChart.data.datasets[0].borderColor = next.sold;
      revenueChart.options.scales.x.ticks.color = next.tick;
      revenueChart.options.scales.y.ticks.color = next.tick;
      revenueChart.options.scales.x.grid.color = next.grid;
      revenueChart.options.scales.y.grid.color = next.grid;
      if (revenueChart.options.plugins?.tooltip) {
        revenueChart.options.plugins.tooltip.backgroundColor = next.tooltipBg;
        revenueChart.options.plugins.tooltip.titleColor = next.tooltipColor;
        revenueChart.options.plugins.tooltip.bodyColor = next.tooltipColor;
      }
      revenueChart.update();
    }
  }

  document.addEventListener("themechange", applyTheme);
  document.addEventListener("accessibilitychange", applyTheme);
})();
JS
    ],
]);
