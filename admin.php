<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

$csv_path = __DIR__ . '/private/visitors.csv';
$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$archive_dir = __DIR__ . '/archives';
$settings_path = __DIR__ . '/private/settings.json';
$event_name_path = __DIR__ . '/private/.event_name';
$log_path = __DIR__ . '/private/request.log';
require_once __DIR__ . '/private/bootstrap.php';
require_once __DIR__ . '/private/auth.php';

/**
 * Persist validated settings to disk and return the saved values.
 */
function save_settings(string $path, array $payload): array
{
    $defaults = [
        'threshold' => 150,
        'max_points' => 10000,
        'chart_max_points' => 2000,
        'window_hours' => 3,
        'tick_minutes' => 15,
        'capacity_default' => 150,
    ];

    $settings = $defaults;
    foreach ($defaults as $key => $value) {
        if (isset($payload[$key]) && is_numeric($payload[$key])) {
            $num = (int)$payload[$key];
            if ($num > 0) {
                $settings[$key] = $num;
            }
        }
    }

    file_put_contents(
        $path,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    return $settings;
}

/**
 * Normalize and persist the event name to disk.
 */
function save_event_name(string $path, string $name): string
{
    $clean = trim($name);
    $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
    $clean = substr($clean, 0, 80);
    if ($clean === '') {
        if (is_file($path)) {
            @unlink($path);
        }
        return '';
    }
    file_put_contents($path, $clean, LOCK_EX);
    return $clean;
}

/**
 * Build a safe filename slug from an event name.
 */
function slugify_event_name(string $name): string
{
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        return '';
    }
    return substr($slug, 0, 40);
}


/**
 * Send a JSON error response and stop execution.
 */
function send_json_error(int $code, string $message): void
{
    global $log_path, $action;
    if ($log_path !== '') {
        $log_action = $action ? 'admin:' . $action : 'admin';
        log_event($log_path, $log_action, $code, ['error' => $message]);
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}


$admin_token = load_token('KEKCOUNTER_ADMIN_TOKEN', $admin_token_path);
$action = $_GET['action'] ?? null;
if ($action !== null) {
    require_admin_token($admin_token);

    if ($action === 'restart') {
        header('Content-Type: application/json; charset=utf-8');
        ensure_archive_dir($archive_dir);

        $archived = false;
        $archive_name = null;
        if (is_file($csv_path) && filesize($csv_path) > 0) {
            $event_name = load_event_name($event_name_path);
            $slug = slugify_event_name($event_name);
            if ($slug !== '') {
                $archive_name = 'visitors-' . $slug . '-' . date('Ymd-His') . '.csv';
            } else {
                $archive_name = 'visitors-' . date('Ymd-His') . '.csv';
            }
            $archive_path = $archive_dir . '/' . $archive_name;
            if (!@rename($csv_path, $archive_path)) {
                if (@copy($csv_path, $archive_path)) {
                    @unlink($csv_path);
                }
            }
            if (is_file($archive_path)) {
                $archived = true;
            } else {
                $archive_name = null;
            }
        }

        $line = "uhrzeit,visitors\n" . date('H:i:s') . ",0\n";
        file_put_contents($csv_path, $line, LOCK_EX);

        $payload = [
            'ok' => true,
            'archived' => $archived,
            'archiveName' => $archive_name,
        ];
        log_event($log_path, 'admin:restart', 200, $payload);
        echo json_encode($payload);
        exit;
    }

    if ($action === 'set_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $token = normalize_access_token_value((string)($payload['token'] ?? ($_POST['token'] ?? '')), 160);
        if ($token === '') {
            send_json_error(400, 'Token missing');
        }
        $tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
        $updated = false;
        foreach ($tokens as $index => $entry) {
            if (($entry['id'] ?? '') === 'default') {
                $tokens[$index]['name'] = 'Default';
                $tokens[$index]['token'] = $token;
                $tokens[$index]['active'] = true;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $tokens[] = [
                'id' => 'default',
                'name' => 'Default',
                'token' => $token,
                'active' => true,
            ];
        }
        if (!save_access_tokens($access_tokens_path, $tokens)) {
            send_json_error(500, 'Save failed');
        }
        log_event($log_path, 'admin:set_access_token', 200);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_access_tokens') {
        header('Content-Type: application/json; charset=utf-8');
        $tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
        echo json_encode(['accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'add_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = normalize_access_label((string)($payload['name'] ?? ''), 40);
        $token = normalize_access_token_value((string)($payload['token'] ?? ''), 160);
        $active = (bool)($payload['active'] ?? true);
        if ($name === '' || $token === '') {
            send_json_error(400, 'Missing data');
        }
        $tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
        $base_id = access_slugify_id($name);
        if ($base_id === '') {
            $base_id = 'key';
        }
        $id = $base_id;
        $suffix = 2;
        $ids = array_map(fn($entry) => (string)($entry['id'] ?? ''), $tokens);
        while (in_array($id, $ids, true)) {
            $id = $base_id . '-' . $suffix;
            $suffix++;
        }
        $tokens[] = [
            'id' => $id,
            'name' => $name,
            'token' => $token,
            'active' => $active,
        ];
        if (!save_access_tokens($access_tokens_path, $tokens)) {
            send_json_error(500, 'Save failed');
        }
        log_event($log_path, 'admin:add_access_token', 200, ['id' => $id, 'name' => $name]);
        echo json_encode(['ok' => true, 'accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'update_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $id = (string)($payload['id'] ?? '');
        $name = normalize_access_label((string)($payload['name'] ?? ''), 40);
        $token = normalize_access_token_value((string)($payload['token'] ?? ''), 160);
        $active = (bool)($payload['active'] ?? true);
        if ($id === '' || $name === '' || $token === '') {
            send_json_error(400, 'Missing data');
        }
        $tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
        $found = false;
        foreach ($tokens as $index => $entry) {
            if (($entry['id'] ?? '') === $id) {
                $tokens[$index]['name'] = $name;
                $tokens[$index]['token'] = $token;
                $tokens[$index]['active'] = $active;
                $found = true;
                break;
            }
        }
        if (!$found) {
            send_json_error(404, 'Not found');
        }
        if (!save_access_tokens($access_tokens_path, $tokens)) {
            send_json_error(500, 'Save failed');
        }
        log_event($log_path, 'admin:update_access_token', 200, ['id' => $id, 'name' => $name]);
        echo json_encode(['ok' => true, 'accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'delete_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $id = (string)($payload['id'] ?? '');
        if ($id === '') {
            send_json_error(400, 'Missing data');
        }
        $tokens = load_access_tokens($access_tokens_path, $legacy_access_token_path);
        $next = [];
        $found = false;
        foreach ($tokens as $entry) {
            if (($entry['id'] ?? '') === $id) {
                $found = true;
                continue;
            }
            $next[] = $entry;
        }
        if (!$found) {
            send_json_error(404, 'Not found');
        }
        if (!save_access_tokens($access_tokens_path, $next)) {
            send_json_error(500, 'Save failed');
        }
        log_event($log_path, 'admin:delete_access_token', 200, ['id' => $id]);
        echo json_encode(['ok' => true, 'accessTokens' => $next]);
        exit;
    }

    if ($action === 'set_admin_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $token = trim((string)($payload['token'] ?? ($_POST['token'] ?? '')));
        if ($token === '') {
            send_json_error(400, 'Token missing');
        }
        file_put_contents($admin_token_path, $token, LOCK_EX);
        log_event($log_path, 'admin:set_admin_token', 200);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_event_name') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['eventName' => load_event_name($event_name_path)]);
        exit;
    }

    if ($action === 'set_event_name') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = (string)($payload['name'] ?? ($_POST['name'] ?? ''));
        $saved = save_event_name($event_name_path, $name);
        log_event($log_path, 'admin:set_event_name', 200, ['name' => $saved]);
        echo json_encode(['ok' => true, 'eventName' => $saved]);
        exit;
    }

    if ($action === 'download_archive') {
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        ensure_archive_dir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    if ($action === 'rename_archive') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        $new_name_raw = (string)($payload['newName'] ?? ($_POST['newName'] ?? ''));
        ensure_archive_dir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found');
        }
        $new_name = normalize_archive_name($new_name_raw);
        if ($new_name === '') {
            send_json_error(400, 'Name missing');
        }
        if (!is_valid_archive_name($new_name)) {
            send_json_error(400, 'Invalid name');
        }
        if ($new_name === $name) {
            echo json_encode(['ok' => true, 'name' => $new_name]);
            exit;
        }
        $real_dir = realpath($archive_dir);
        if ($real_dir === false) {
            send_json_error(500, 'Archive dir missing');
        }
        $new_path = $real_dir . DIRECTORY_SEPARATOR . $new_name;
        if (is_file($new_path)) {
            send_json_error(409, 'Name exists');
        }
        if (!@rename($path, $new_path)) {
            send_json_error(500, 'Rename failed');
        }
        echo json_encode(['ok' => true, 'name' => $new_name]);
        exit;
    }

    if ($action === 'delete_archive') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        ensure_archive_dir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found');
        }
        if (!@unlink($path)) {
            send_json_error(500, 'Delete failed');
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_settings') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(load_settings($settings_path));
        exit;
    }

    if ($action === 'set_settings') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $settings = save_settings($settings_path, $payload);
        log_event($log_path, 'admin:set_settings', 200, ['settings' => $settings]);
        echo json_encode(['ok' => true, 'settings' => $settings]);
        exit;
    }

    if ($action === 'list_logs') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $limit = (int)($payload['limit'] ?? 200);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }
        $entries = [];
        $total = 0;
        if (is_file($log_path)) {
            $lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $total = count($lines);
            $lines = array_reverse($lines);
            $lines = array_slice($lines, 0, $limit);
            foreach ($lines as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $decoded['raw'] = $line;
                    $entries[] = $decoded;
                } else {
                    $entries[] = ['raw' => $line];
                }
            }
        }
        echo json_encode(['entries' => $entries, 'total' => $total]);
        exit;
    }

    if ($action === 'download_log') {
        if (!is_file($log_path)) {
            send_json_error(404, 'Log not found');
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="request.log"');
        readfile($log_path);
        exit;
    }

    if ($action === 'list_archives') {
        header('Content-Type: application/json; charset=utf-8');
        ensure_archive_dir($archive_dir);
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

    if ($action === 'download_latest') {
        ensure_archive_dir($archive_dir);
        $files = glob($archive_dir . '/*.csv') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        if (!$files) {
            send_json_error(404, 'No archives');
        }
        $file = $files[0];
        log_event($log_path, 'admin:download_latest', 200, ['name' => basename($file)]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }

    send_json_error(400, 'Unknown action');
    exit;
}
?>
<?php
require_once __DIR__ . '/private/layout.php';

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-end gap-3 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">Kek - Checkout</div>
    <h1 class="display-6 fw-semibold mb-2">Admin</h1>
    <p class="text-secondary mb-0">Konfiguration und Verwaltung.</p>
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
      id="adminLogout"
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary d-none"
      type="button"
      data-i18n-aria-label="nav.logout"
      data-i18n-title="nav.logout"
    >
      <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.logout">Abmelden</span>
    </button>
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="/menu.php"
      data-i18n-aria-label="nav.back"
      data-i18n-title="nav.back"
    >
      <i class="bi bi-book" aria-hidden="true"></i>
      <span class="btn-icon-text" data-i18n="nav.back">Menu</span>
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
<section id="adminAuthCard" class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <h2 class="h5 mb-2" data-i18n="admin.auth.title">Admin-Token</h2>
    <p class="text-secondary small mb-3" data-i18n="admin.auth.note">Token wird nur lokal im Browser gespeichert.</p>
    <input
      id="adminToken"
      class="form-control"
      type="password"
      data-i18n-placeholder="admin.auth.placeholder"
      placeholder="Admin-Token"
      autocomplete="off"
      inputmode="text"
    >
    <div class="d-flex flex-wrap gap-2 mt-3">
      <button id="adminSave" class="btn btn-primary btn-sm" type="button">
        <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
      </button>
      <button id="adminClear" class="btn btn-outline-danger btn-sm" type="button">
        <i class="bi bi-x-circle me-1" aria-hidden="true"></i><span data-i18n="common.forget">Vergessen</span>
      </button>
    </div>
    <div id="adminStatus" class="text-secondary small mt-2" role="status" aria-live="polite" data-i18n="admin.auth.status.none">Kein Token gespeichert</div>
  </div>
</section>

<div id="adminContent" class="row g-3 d-none">
  <section class="col-12 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-2" data-i18n="admin.event.title">Veranstaltung</h2>
        <p class="text-secondary small mb-3" data-i18n="admin.event.note">Startet eine neue CSV und archiviert die alte.</p>
        <div class="d-flex flex-wrap gap-2">
          <button id="restartEvent" class="btn btn-outline-danger btn-sm" type="button">
            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><span data-i18n="admin.event.restart">Neustarten</span>
          </button>
        </div>
        <div id="restartStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
        <hr class="my-3">
        <label class="form-label small text-secondary" for="eventNameInput" data-i18n="admin.event.nameLabel">Veranstaltungsname</label>
        <input
          id="eventNameInput"
          class="form-control"
          type="text"
          data-i18n-placeholder="admin.event.namePlaceholder"
          placeholder="z.B. Sommerfest"
          autocomplete="off"
          inputmode="text"
        >
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button id="eventNameSave" class="btn btn-primary btn-sm" type="button">
            <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
          </button>
        </div>
        <div id="eventNameStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
      </div>
    </div>
  </section>
  <section class="col-12 col-lg-8">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-2" data-i18n="admin.settings.title">Einstellungen</h2>
        <p class="text-secondary small mb-3" data-i18n="admin.settings.note">Zentrale Defaults fuer App und Analyse.</p>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsThreshold" data-i18n="admin.settings.threshold">Kritisch-Grenze</label>
            <input id="settingsThreshold" class="form-control" type="number" min="1" step="1">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsMaxPoints" data-i18n="admin.settings.maxPoints">Max. Datenpunkte</label>
            <input id="settingsMaxPoints" class="form-control" type="number" min="1" step="1">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsChartMaxPoints" data-i18n="admin.settings.chartMaxPoints">Chart-Max. Punkte</label>
            <input id="settingsChartMaxPoints" class="form-control" type="number" min="1" step="1">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsWindowHours" data-i18n="admin.settings.windowHours">Zeitfenster (Stunden)</label>
            <input id="settingsWindowHours" class="form-control" type="number" min="1" step="1">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsTickMinutes" data-i18n="admin.settings.tickMinutes">Tick-Abstand (Min)</label>
            <input id="settingsTickMinutes" class="form-control" type="number" min="1" step="1">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small text-secondary" for="settingsCapacityDefault" data-i18n="admin.settings.capacityDefault">Kapazitaet (Default)</label>
            <input id="settingsCapacityDefault" class="form-control" type="number" min="1" step="1">
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button id="settingsSave" class="btn btn-primary btn-sm" type="button">
            <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
          </button>
        </div>
        <div id="settingsStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
      </div>
    </div>
  </section>

  <div class="col-12 col-lg-4">
    <div class="row">
      <section class="col-12 mb-lg-3">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body">
            <h2 class="h5 mb-2">Access-Keys</h2>
            <p class="text-secondary small mb-3">Mehrere Kassen-Keys mit Namen anlegen.</p>
            <label class="form-label small text-secondary" for="accessTokenNameNew">Name</label>
            <input
              id="accessTokenNameNew"
              class="form-control"
              type="text"
              placeholder="z.B. Marvin"
              autocomplete="off"
              inputmode="text"
            >
            <label class="form-label small text-secondary mt-2" for="accessTokenNew">Key</label>
            <input
              id="accessTokenNew"
              class="form-control"
              type="password"
              placeholder="Neuer Access-Token"
              autocomplete="off"
              inputmode="text"
            >
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button id="accessTokenAdd" class="btn btn-primary btn-sm" type="button">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span>Anlegen</span>
              </button>
            </div>
            <div id="accessTokenStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
            <h3 class="h6 mt-4 mb-2">Vorhandene Keys</h3>
            <div id="accessTokenList" class="d-flex flex-column gap-2"></div>
          </div>
        </div>
      </section>
      <section class="col-12">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body">
            <h2 class="h5 mb-2" data-i18n="admin.adminToken.title">Admin-Token</h2>
            <p class="text-secondary small mb-3" data-i18n="admin.adminToken.note">Setzt den Admin-Token fuer Analyse & Admin.</p>
            <input
              id="adminTokenNew"
              class="form-control"
              type="password"
              data-i18n-placeholder="admin.adminToken.placeholder"
              placeholder="Neues Admin-Token"
              autocomplete="off"
              inputmode="text"
            >
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button id="adminTokenSave" class="btn btn-primary btn-sm" type="button">
                <i class="bi bi-floppy me-1" aria-hidden="true"></i><span data-i18n="common.save">Speichern</span>
              </button>
            </div>
            <div id="adminTokenStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
          </div>
        </div>
      </section>
    </div>
  </div>

  <section class="col-12 col-lg-8">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1" data-i18n="admin.archive.title">Archiv</h2>
            <p class="text-secondary small mb-0" data-i18n="admin.archive.note">CSV-Archive verwalten und umbenennen.</p>
          </div>
          <button id="adminArchiveRefresh" class="btn btn-outline-secondary btn-sm" type="button">
            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><span data-i18n="common.listRefresh">Liste aktualisieren</span>
          </button>
        </div>
        <div class="input-group input-group-sm archive-search mb-2">
          <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
          <input
            id="adminArchiveSearch"
            class="form-control"
            type="search"
            data-i18n-placeholder="archive.searchPlaceholder"
            data-i18n-aria-label="archive.searchPlaceholder"
            placeholder="Archiv suchen"
            aria-label="Archiv suchen"
            autocomplete="off"
          >
        </div>
        <div id="adminArchiveStatus" class="text-secondary small mb-2" role="status" aria-live="polite" data-i18n="archive.noneLoaded">Keine Archive geladen</div>
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
            <tbody id="adminArchiveList"></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
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

render_layout([
    'title' => 'Kek-Counter Admin',
    'title_i18n' => 'title.admin',
    'manifest' => '',
    'header' => $header,
    'content' => $content,
    'footer' => $footer,
    'head_extra' => '<meta name="robots" content="noindex, nofollow">',
    'scripts' => [
        'assets/admin.js',
    ],
]);
