<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

$csv_path = __DIR__ . '/private/visitors.csv';
$access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$settings_path = __DIR__ . '/private/settings.json';
$event_name_path = __DIR__ . '/private/.event_name';
$log_path = __DIR__ . '/private/request.log';
require_once __DIR__ . '/private/bootstrap.php';
require_once __DIR__ . '/private/auth.php';

/**
 * Attach the current event name to a response payload.
 */
function with_event_name(array $data, string $path): array
{
    $data['eventName'] = load_event_name($path);
    return $data;
}

/**
 * Ensure the CSV file exists with a header row.
 */
function ensure_csv_exists(string $path): void
{
    if (!file_exists($path) || filesize($path) === 0) {
        $line = "uhrzeit,visitors\n" . date('H:i:s') . ",0\n";
        file_put_contents($path, $line);
    }
}

/**
 * Detect whether a CSV row is a header row.
 */
function is_header_row(array $row): bool
{
    $first = strtolower(trim($row[0] ?? ''));
    return $first === 'uhrzeit' || $first === 'timestamp';
}

/**
 * Load CSV data into arrays for charting and counters.
 */
function load_data(string $path, int $limit): array
{
    ensure_csv_exists($path);
    $rows = [];

    $fp = fopen($path, 'r');
    if ($fp !== false) {
        flock($fp, LOCK_SH);
        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
            if (is_header_row($row) || count($row) < 2) {
                continue;
            }
            $rows[] = [$row[0], (int)$row[1]];
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    if (count($rows) > $limit) {
        $rows = array_slice($rows, -$limit);
    }

    $labels = array_map(fn($r) => $r[0], $rows);
    $values = array_map(fn($r) => (int)$r[1], $rows);
    $count = $values ? $values[count($values) - 1] : 0;
    $updated_at = $labels ? $labels[count($labels) - 1] : date('H:i:s');

    return [
        'count' => $count,
        'labels' => $labels,
        'values' => $values,
        'updatedAt' => $updated_at,
    ];
}

/**
 * Append a delta entry and return updated data.
 */
function update_count(string $path, int $delta, int $limit): array
{
    ensure_csv_exists($path);
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return load_data($path, $limit);
    }

    flock($fp, LOCK_EX);
    $rows = [];
    rewind($fp);
    while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
        if (is_header_row($row) || count($row) < 2) {
            continue;
        }
        $rows[] = [$row[0], (int)$row[1]];
    }

    $last = $rows ? (int)$rows[count($rows) - 1][1] : 0;
    $next = max(0, $last + $delta);
    fseek($fp, 0, SEEK_END);
    fputcsv($fp, [date('H:i:s'), $next], ',', '"', '\\');
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return load_data($path, $limit);
}


$settings = load_settings($settings_path);
$event_name = load_event_name($event_name_path);
$max_points = $settings['max_points'];
$action = $_GET['action'] ?? null;
if ($action !== null) {
    if ($action !== 'download') {
        header('Content-Type: application/json; charset=utf-8');
    }
    if ($action === 'status') {
        echo json_encode(with_event_name(load_data($csv_path, $max_points), $event_name_path));
        exit;
    }
    if ($action === 'verify') {
        $access_token = load_token('KEKCOUNTER_ACCESS_TOKEN', $access_token_path);
        $admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
        $auth = authorize_any_token_request($access_token, $admin_token);
        if (!$auth['ok']) {
            http_response_code($auth['status']);
            log_event($log_path, $action, $auth['status'], ['error' => $auth['error']]);
            echo json_encode(['error' => $auth['message']]);
            exit;
        }
        log_event($log_path, $action, 200, ['ok' => true]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'download') {
        $access_token = load_token('KEKCOUNTER_ACCESS_TOKEN', $access_token_path);
        $admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
        $auth = authorize_any_token_request($access_token, $admin_token);
        if (!$auth['ok']) {
            http_response_code($auth['status']);
            log_event($log_path, $action, $auth['status'], ['error' => $auth['error']]);
            echo json_encode(['error' => $auth['message']]);
            exit;
        }
        ensure_csv_exists($csv_path);
        $filename = 'visitors-current-' . date('Ymd-His') . '.csv';
        log_event($log_path, $action, 200, ['name' => $filename]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($csv_path);
        exit;
    }
    if ($action === 'inc' || $action === 'dec') {
        $access_token = load_token('KEKCOUNTER_ACCESS_TOKEN', $access_token_path);
        $admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
        $auth = authorize_any_token_request($access_token, $admin_token);
        if (!$auth['ok']) {
            http_response_code($auth['status']);
            log_event($log_path, $action, $auth['status'], ['error' => $auth['error']]);
            echo json_encode(['error' => $auth['message']]);
            exit;
        }
        $delta = $action === 'inc' ? 1 : -1;
        $data = with_event_name(update_count($csv_path, $delta, $max_points), $event_name_path);
        log_event($log_path, $action, 200, ['delta' => $delta, 'count' => $data['count'] ?? null]);
        echo json_encode($data);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?>
<?php
require_once __DIR__ . '/private/layout.php';

ob_start();
?>
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-4 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2" data-i18n="index.tagline">Live counter</div>
    <h1 class="display-5 fw-semibold mb-2" data-i18n="title.index">Kek-Counter</h1>
    <p class="text-secondary mb-0" data-i18n="index.subtitle">Schneller Überblick und Verlauf in Echtzeit.</p>
    <div class="d-flex align-items-center gap-2 small text-secondary mt-2">
      <i class="bi bi-calendar-event" aria-hidden="true"></i>
      <span
        id="eventName"
        <?php if ($event_name === '') { ?>data-i18n="event.unnamed"<?php } ?>
      ><?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Unbenannt', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>
  <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
    <div class="d-flex align-items-center gap-2 small text-secondary border rounded-pill px-3 py-2 bg-white shadow-sm" role="status" aria-live="polite">
      <span id="statusDot" class="status-dot rounded-circle bg-success" aria-hidden="true"></span>
      <span class="text-uppercase text-secondary" data-i18n="app.updated">Updated</span>
      <span id="updated" class="fw-semibold text-body">--:--:--</span>
    </div>
    <div class="icon-actions icon-actions--split">
      <button
        id="accessOpen"
        class="btn btn-outline-primary btn-sm btn-icon"
        type="button"
        data-i18n-aria-label="nav.access"
        data-i18n-title="nav.access"
      >
        <i class="bi bi-key" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.access">Access</span>
      </button>
      <button
        id="accessLogout"
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        type="button"
        data-i18n-aria-label="nav.logout"
        data-i18n-title="nav.logout"
      >
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.logout">Abmelden</span>
      </button>
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
      <button
        id="downloadCurrent"
        class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
        type="button"
        data-i18n-aria-label="nav.csv"
        data-i18n-title="nav.csvDownload"
      >
        <i class="bi bi-download" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.csv">CSV</span>
      </button>
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
        href="analysis.php"
        data-i18n-aria-label="nav.analysis"
        data-i18n-title="nav.analysis"
      >
        <i class="bi bi-bar-chart" aria-hidden="true"></i>
        <span class="btn-icon-text" data-i18n="nav.analysis">Analyse</span>
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
    </div>
  </div>
</header>
<?php
$header = ob_get_clean();

ob_start();
?>
<section class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-stretch">
      <div class="col-12 col-lg-4 d-grid">
        <button id="dec" class="btn btn-outline-danger btn-lg py-4" type="button">
          <span class="d-flex flex-column align-items-center gap-1">
            <i class="bi bi-person-dash fs-3" aria-hidden="true"></i>
            <!--<span class="fw-semibold">1 weniger</span>-->
          </span>
        </button>
      </div>
      <div class="col-12 col-lg-4 d-flex align-items-center justify-content-center">
        <div id="countBlock" class="text-center border border-primary-subtle rounded-4 p-3 bg-body-tertiary w-100">
          <div class="text-uppercase small text-secondary fw-semibold" data-i18n="index.counter.present">Anwesend</div>
          <div id="count" class="display-1 fw-bold mb-1" aria-live="polite" aria-atomic="true">0</div>
          <div class="text-secondary fw-semibold small"><span data-i18n="index.counter.critical">Kritisch ab</span> <span id="thresholdValue">150</span></div>
        </div>
      </div>
      <div class="col-12 col-lg-4 d-grid">
        <button id="inc" class="btn btn-primary btn-lg py-4" type="button">
          <span class="d-flex flex-column align-items-center gap-1">
            <i class="bi bi-person-plus fs-3" aria-hidden="true"></i>
            <!--<span class="fw-semibold">1 mehr</span>-->
          </span>
        </button>
      </div>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0 d-none d-md-block">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h2 class="h4 mb-1" data-i18n="index.chart.title">Verlauf</h2>
        <p class="text-secondary small mb-0" data-i18n="index.chart.subtitle">Letzte Updates im Blick.</p>
      </div>
      <div class="d-flex align-items-center gap-2 small text-secondary">
        <span class="d-inline-block rounded-pill bg-primary" style="width: 2rem; height: 0.25rem;"></span>
        <span data-i18n="index.chart.series">Belegung</span>
      </div>
    </div>
    <div class="chart-wrap bg-body-tertiary border rounded-4 p-3">
      <canvas id="visitorsChart" class="w-100 h-100" data-i18n-aria-label="chart.visitorsAria" aria-label="Visitors Verlauf" role="img"></canvas>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();

$footer = <<<HTML
<footer class="mt-4 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 small text-secondary">
  <div class="d-flex flex-wrap gap-2">
    <a class="link-secondary text-decoration-none" href="https://julius-hunold.de/datenschutz" target="_blank" rel="noopener" data-i18n="footer.privacy">Datenschutz</a>
    <span class="text-secondary" aria-hidden="true">•</span>
    <a class="link-secondary text-decoration-none" href="https://julius-hunold.de/impressum" target="_blank" rel="noopener" data-i18n="footer.imprint">Impressum</a>
  </div>
  <div>
    <span data-i18n="footer.builtBy">Erstellt von</span>
    <a class="link-secondary text-decoration-none" href="https://hunold24.de" target="_blank" rel="noopener">Julius Hunold</a>
  </div>
</footer>
HTML;

$modals = <<<HTML
<div class="modal fade" id="accessDialog" tabindex="-1" aria-labelledby="accessTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="accessTitle" data-i18n="index.modal.title">Access-Token</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" data-i18n-aria-label="common.close" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small mb-3" data-i18n="index.modal.note">Token wird nur lokal im Browser gespeichert.</p>
        <input
          id="accessToken"
          class="form-control"
          type="password"
          data-i18n-placeholder="index.modal.placeholder"
          placeholder="Token"
          autocomplete="off"
          inputmode="text"
        >
        <div id="accessStatus" class="text-secondary small mt-2" data-i18n="index.modal.status.none">Kein Token gespeichert</div>
      </div>
      <div class="modal-footer">
        <button id="accessSave" class="btn btn-primary" type="button">
          <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
        </button>
        <button id="accessClear" class="btn btn-outline-danger" type="button">
          <i class="bi bi-trash me-1" aria-hidden="true"></i><span data-i18n="common.delete">Loeschen</span>
        </button>
        <button id="accessClose" class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1" aria-hidden="true"></i><span data-i18n="common.close">Schliessen</span>
        </button>
      </div>
    </div>
  </div>
</div>
HTML;

$inline_scripts = [
    "window.APP_CONFIG = " . json_encode([
        'settings' => $settings,
    ], JSON_UNESCAPED_SLASHES) . ";",
];

render_layout([
    'title' => 'Kek-Counter',
    'title_i18n' => 'title.index',
    'description' => 'Live-Visitors-Counter fuer Veranstaltungen mit Verlauf und Analyse. Entwickelt von hunold24 (Julius Hunold).',
    'app_name' => 'Kek-Counter',
    'og_title' => 'Kek-Counter',
    'og_description' => 'Live-Visitors-Counter fuer Veranstaltungen mit Verlauf und Analyse. Entwickelt von hunold24 (Julius Hunold).',
    'header' => $header,
    'content' => $content,
    'footer' => $footer,
    'modals' => $modals,
    'inline_scripts' => $inline_scripts,
    'include_chart_js' => true,
    'scripts' => [
        'assets/app.js',
    ],
]);
