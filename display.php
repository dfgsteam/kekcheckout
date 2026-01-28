<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

$settings_path = __DIR__ . '/private/settings.json';
$event_name_path = __DIR__ . '/private/.event_name';
require_once __DIR__ . '/private/bootstrap.php';

$settings = load_settings($settings_path);
$event_name = load_event_name($event_name_path);
?>
<?php
require_once __DIR__ . '/private/layout.php';

ob_start();
?>
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-end gap-3 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2" data-i18n="nav.display">Display</div>
    <h1
      id="eventName"
      class="display-4 fw-semibold mb-1"
      <?php if ($event_name === '') { ?>data-i18n="event.unnamed"<?php } ?>
    >
      <?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Unbenannt', ENT_QUOTES, 'UTF-8'); ?>
    </h1>
    <p class="text-secondary mb-0" data-i18n="display.subtitle">Live-Ansicht fuer Anzeigedisplays.</p>
  </div>
  <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
    <div class="d-flex align-items-center gap-2 small text-secondary border rounded-pill px-3 py-2 bg-white shadow-sm" role="status" aria-live="polite">
      <span id="statusDot" class="status-dot rounded-circle bg-success" aria-hidden="true"></span>
      <span class="text-uppercase text-secondary" data-i18n="app.updated">Updated</span>
      <span id="updated" class="fw-semibold text-body">--:--:--</span>
    </div>
    <button
      id="displayFullscreen"
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      type="button"
      data-i18n-aria-label="display.fullscreenAria"
      data-i18n-title="display.fullscreen"
      aria-label="Vollbild"
      title="Vollbild"
    >
      <i class="bi bi-fullscreen" data-fullscreen-icon aria-hidden="true"></i>
      <span class="btn-icon-text" data-fullscreen-label>Vollbild</span>
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
<section class="card shadow-sm border-0 mb-4">
  <div class="card-body">
    <div id="countBlock" class="text-center border border-primary-subtle rounded-4 p-4 bg-body-tertiary w-100">
      <div class="text-uppercase small text-secondary fw-semibold" data-i18n="index.counter.present">Anwesend</div>
      <div id="count" class="display-1 fw-bold mb-1" aria-live="polite" aria-atomic="true">0</div>
      <div class="text-secondary fw-semibold small"><span data-i18n="index.counter.critical">Kritisch ab</span> <span id="thresholdValue">150</span></div>
    </div>
  </div>
</section>

<section class="card shadow-sm border-0">
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
    <div class="display-chart bg-body-tertiary border rounded-4 p-3">
      <canvas id="displayChart" class="w-100 h-100" data-i18n-aria-label="chart.visitorsAria" aria-label="Visitors Verlauf" role="img"></canvas>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();

$inline_scripts = [
    "window.APP_CONFIG = " . json_encode([
        'settings' => $settings,
        'statusUrl' => 'index.php?action=status',
    ], JSON_UNESCAPED_SLASHES) . ";",
];

render_layout([
    'title' => 'Kek-Counter Display',
    'title_i18n' => 'title.display',
    'description' => 'Display-Ansicht fuer den Live-Visitors-Counter.',
    'app_name' => 'Kek-Counter Display',
    'og_title' => 'Kek-Counter Display',
    'og_description' => 'Display-Ansicht fuer den Live-Visitors-Counter.',
    'manifest' => '',
    'main_class' => 'container-fluid py-4 py-lg-5 px-3 px-lg-5',
    'header' => $header,
    'content' => $content,
    'inline_scripts' => $inline_scripts,
    'include_chart_js' => true,
    'scripts' => [
        'assets/display.js',
    ],
]);
