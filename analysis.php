<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

$archive_dir = __DIR__ . '/archives';
$access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$settings_path = __DIR__ . '/private/settings.json';
$event_name_path = __DIR__ . '/private/.event_name';
require_once __DIR__ . '/private/bootstrap.php';
require_once __DIR__ . '/private/auth.php';

$access_token = load_token('KEKCOUNTER_ACCESS_TOKEN', $access_token_path);
$admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
$settings = load_settings($settings_path);
$event_name = load_event_name($event_name_path);
$action = $_GET['action'] ?? null;
if ($action !== null) {
    require_any_token($access_token, $admin_token);
    ensure_archive_dir($archive_dir);

    if ($action === 'list_archives') {
        header('Content-Type: application/json; charset=utf-8');
        $files = glob($archive_dir . '/*.csv') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $items = array_map(function (string $file): array {
            return [
                'name' => basename($file),
                'size' => filesize($file),
                'modified' => date('c', filemtime($file)),
            ];
        }, $files);
        echo json_encode(['archives' => $items]);
        exit;
    }

    if ($action === 'download') {
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    if ($action === 'read') {
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found');
        }
        header('Content-Type: text/csv; charset=utf-8');
        readfile($path);
        exit;
    }

    send_json_error(400, 'Unknown action');
}
?>
<?php
require_once __DIR__ . '/private/layout.php';

ob_start();
?>
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-end gap-3 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2" data-i18n="analysis.tagline">Analyse</div>
    <h1 class="display-6 fw-semibold mb-2" data-i18n="analysis.title">Kek-Counter Analyse</h1>
    <p class="text-secondary mb-0" data-i18n="analysis.subtitle">Archivierte Veranstaltungen durchsuchen.</p>
    <div class="d-flex align-items-center gap-2 small text-secondary mt-2">
      <i class="bi bi-calendar-event" aria-hidden="true"></i>
      <span class="text-uppercase text-secondary" data-i18n="event.active">Aktiv</span>
      <span
        class="text-body fw-semibold"
        <?php if ($event_name === '') { ?>data-i18n="event.unnamed"<?php } ?>
      >
        <?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Unbenannt', ENT_QUOTES, 'UTF-8'); ?>
      </span>
    </div>
  </div>
  <div class="icon-actions">
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="/"
      data-i18n-aria-label="nav.back"
      data-i18n-title="nav.back"
    >
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
    </a>
    <button
      id="analysisLogout"
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary d-none"
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
  </div>
</header>
<?php
$header = ob_get_clean();

ob_start();
?>
<section id="analysisAuthCard" class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <h2 class="h5 mb-2" data-i18n="analysis.auth.title">Zugriff</h2>
    <p class="text-secondary small mb-3" data-i18n="analysis.auth.note">Admin-Token eingeben.</p>
    <input
      id="analysisToken"
      class="form-control"
      type="password"
      data-i18n-placeholder="analysis.auth.placeholder"
      placeholder="Admin-Token"
      autocomplete="off"
      inputmode="text"
    >
    <div class="d-flex flex-wrap gap-2 mt-3">
      <button id="analysisSave" class="btn btn-primary btn-sm" type="button">
        <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
      </button>
      <button id="analysisClear" class="btn btn-outline-danger btn-sm" type="button">
        <i class="bi bi-x-circle me-1" aria-hidden="true"></i><span data-i18n="common.forget">Vergessen</span>
      </button>
    </div>
    <div id="analysisStatus" class="text-secondary small mt-2" role="status" aria-live="polite" data-i18n="analysis.auth.status.none">Kein Admin-Token gespeichert</div>
  </div>
</section>

<div id="analysisContent" class="row g-3 d-none">
  <section class="col-12">
    <div class="d-grid gap-3">
      <div class="card shadow-sm border-0 h-100 analysis-card analysis-card--archives">
        <div class="card-body">
          <h2 class="h5 mb-2" data-i18n="analysis.archive.title">Archivierte Veranstaltungen</h2>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <button id="analysisRefresh" class="btn btn-outline-secondary btn-sm" type="button">
              <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><span data-i18n="common.listRefresh">Liste aktualisieren</span>
            </button>
            <div class="input-group input-group-sm archive-search">
              <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
              <input
                id="analysisArchiveSearch"
                class="form-control"
                type="search"
                data-i18n-placeholder="archive.searchPlaceholder"
                data-i18n-aria-label="archive.searchPlaceholder"
                placeholder="Archiv suchen"
                aria-label="Archiv suchen"
                autocomplete="off"
              >
            </div>
            <select id="analysisArchiveSort" class="form-select form-select-sm w-auto" data-i18n-aria-label="archive.sort.label" aria-label="Archiv sortieren">
              <option value="modified_desc" selected data-i18n="archive.sort.modifiedDesc">Neueste zuerst</option>
              <option value="modified_asc" data-i18n="archive.sort.modifiedAsc">Aelteste zuerst</option>
              <option value="name_asc" data-i18n="archive.sort.nameAsc">Name A-Z</option>
              <option value="name_desc" data-i18n="archive.sort.nameDesc">Name Z-A</option>
              <option value="size_desc" data-i18n="archive.sort.sizeDesc">Groesse absteigend</option>
              <option value="size_asc" data-i18n="archive.sort.sizeAsc">Groesse aufsteigend</option>
            </select>
          </div>
          <div id="analysisArchiveStatus" class="text-secondary small mb-2" role="status" aria-live="polite" data-i18n="archive.noneLoaded">Keine Archive geladen</div>
          <div class="table-responsive analysis-table">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col" data-i18n="analysis.archive.table.event">Veranstaltung</th>
                  <th scope="col" class="text-nowrap" data-i18n="analysis.archive.table.modified">Geaendert</th>
                  <th scope="col" class="text-end" data-i18n="analysis.archive.table.size">Groesse</th>
                  <th scope="col" class="text-end" data-i18n="analysis.archive.table.actions">Aktionen</th>
                </tr>
              </thead>
              <tbody id="analysisArchiveList"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div class="col-12 col-lg-8">
    <div class="row">
      <section class="col-12 mb-3">
        <div class="d-grid gap-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <h2 class="h5 mb-2" data-i18n="analysis.chart.title">Verlauf</h2>
              <p class="text-secondary small mb-0" data-i18n="analysis.chart.subtitle">Zeitlicher Verlauf der Belegung.</p>
              <div class="analysis-chart bg-body-tertiary border rounded-4 p-3 mt-3">
                <canvas id="analysisChart" class="w-100 h-100" data-i18n-aria-label="chart.visitorsAria" aria-label="Visitors Verlauf" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section class="col-12 mb-3">
        <div class="d-grid gap-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h2 class="h5 mb-1" data-i18n="analysis.retention.title">Retention</h2>
                  <p class="text-secondary small mb-0" data-i18n="analysis.retention.subtitle">Vergleich: Start vs. aktuelle Belegung.</p>
                </div>
                <div class="text-secondary small" data-i18n="analysis.retention.help">Live-Verbleib in %</div>
              </div>
              <div class="analysis-chart bg-body-tertiary border rounded-4 p-3">
                <canvas id="retentionChart" class="w-100 h-100" data-i18n-aria-label="analysis.retention.aria" aria-label="Retention" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="row">
      <section class="col-12 mb-3">
        <div class="d-grid gap-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                  <h2 class="h5 mb-1" data-i18n="analysis.metrics.title">Kennzahlen</h2>
                  <p class="text-secondary small mb-0" data-i18n="analysis.metrics.subtitle">Metriken der Auswahl.</p>
                </div>
                <button id="analysisRefreshMetrics" class="btn btn-outline-secondary btn-sm" type="button">
                  <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><span data-i18n="common.refresh">Aktualisieren</span>
                </button>
              </div>
              <div id="analysisMetrics" class="d-grid gap-2"></div>
            </div>
          </div>
        </div>
      </section>
      <section class="col-12">
        <div class="d-grid gap-3">
          <div class="card shadow-sm border-0">
            <div class="card-body">
              <h2 class="h5 mb-2" data-i18n="analysis.archive.details.title">Archivdetails</h2>
              <p class="text-secondary small mb-3" data-i18n="analysis.archive.details.note">Details zur Auswahl.</p>
              <div id="analysisArchiveDetails" class="d-grid gap-2"></div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
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

$inline_scripts = [
    "window.APP_CONFIG = " . json_encode([
        'settings' => $settings,
    ], JSON_UNESCAPED_SLASHES) . ";",
];

render_layout([
    'title' => 'Kek-Counter Analyse',
    'title_i18n' => 'title.analysis',
    'description' => 'Analyse fuer archivierte Visitors-Veranstaltungen mit Verlauf und Kennzahlen.',
    'app_name' => 'Kek-Counter',
    'og_title' => 'Visitors-Analyse',
    'og_description' => 'Analyse fuer archivierte Visitors-Veranstaltungen mit Verlauf und Kennzahlen.',
    'manifest' => '',
    'header' => $header,
    'content' => $content,
    'footer' => $footer,
    'head_extra' => '<meta name="robots" content="noindex, nofollow">',
    'inline_scripts' => $inline_scripts,
    'include_chart_js' => true,
    'scripts' => [
        'assets/analysis.js',
    ],
]);
