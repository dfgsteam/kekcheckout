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
$categories_path = __DIR__ . '/private/menu_categories.json';
$items_path = __DIR__ . '/private/menu_items.json';
require_once __DIR__ . '/private/bootstrap.php';
require_once __DIR__ . '/private/auth.php';
require_once __DIR__ . '/private/menu_lib.php';


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

/**
 * Require same-origin POST requests for public reads.
 */
function require_menu_read(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_error(405, 'Method not allowed');
    }
    if (!is_same_origin()) {
        send_json_error(403, 'Forbidden');
    }
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($requested_with !== 'fetch') {
        send_json_error(403, 'Forbidden');
    }
}

menu_ensure_seed($categories_path, $items_path);

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

    if ($action === 'get_menu') {
        header('Content-Type: application/json; charset=utf-8');
        require_menu_read();
        echo json_encode(menu_get_menu($categories_path, $items_path));
        exit;
    }

    if ($action === 'add_category') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $result = menu_add_category(
            $categories_path,
            $items_path,
            (string)($payload['name'] ?? ''),
            (bool)($payload['active'] ?? false)
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Save failed');
        }
        $menu = menu_get_menu($categories_path, $items_path);
        echo json_encode(['ok' => true, 'category' => $result['category'] ?? [], 'menu' => $menu]);
        exit;
    }

    if ($action === 'add_item') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $result = menu_add_item(
            $categories_path,
            $items_path,
            (string)($payload['categoryId'] ?? ''),
            (string)($payload['name'] ?? ''),
            (string)($payload['price'] ?? '0'),
            $payload['ingredients'] ?? '',
            $payload['tags'] ?? '',
            (string)($payload['preparation'] ?? ''),
            (bool)($payload['active'] ?? false)
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Save failed');
        }
        $menu = menu_get_menu($categories_path, $items_path);
        echo json_encode(['ok' => true, 'item' => $result['item'] ?? [], 'menu' => $menu]);
        exit;
    }

    if ($action === 'update_category') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $result = menu_update_category(
            $categories_path,
            $items_path,
            (string)($payload['id'] ?? ''),
            (string)($payload['name'] ?? ''),
            (bool)($payload['active'] ?? false)
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Save failed');
        }
        $menu = menu_get_menu($categories_path, $items_path);
        echo json_encode(['ok' => true, 'menu' => $menu]);
        exit;
    }

    if ($action === 'update_item') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $result = menu_update_item(
            $items_path,
            (string)($payload['id'] ?? ''),
            (string)($payload['name'] ?? ''),
            (string)($payload['price'] ?? '0'),
            $payload['ingredients'] ?? '',
            $payload['tags'] ?? '',
            (string)($payload['preparation'] ?? ''),
            (bool)($payload['active'] ?? false)
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Save failed');
        }
        $menu = menu_get_menu($categories_path, $items_path);
        echo json_encode(['ok' => true, 'menu' => $menu]);
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
    <h1 class="display-6 fw-semibold mb-2">Sortiment</h1>
    <p class="text-secondary mb-0">Artikel und Kategorien verwalten.</p>
  </div>
  <div class="icon-actions">
    <a
      class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
      href="/admin.php"
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

$modals = <<<HTML
<div class="modal fade" id="menuErrorModal" tabindex="-1" aria-labelledby="menuErrorTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="menuErrorTitle">Fehler</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p id="menuErrorMessage" class="mb-0">Ein Fehler ist aufgetreten.</p>
      </div>
    </div>
  </div>
</div>
HTML;

$menu = menu_get_menu($categories_path, $items_path);
$menu_categories = is_array($menu['categories'] ?? null) ? $menu['categories'] : [];
$menu_items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
$items_by_category = [];
foreach ($menu_items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $category_id = (string)($item['category_id'] ?? '');
    if ($category_id === '') {
        continue;
    }
    if (!isset($items_by_category[$category_id])) {
        $items_by_category[$category_id] = [];
    }
    $items_by_category[$category_id][] = $item;
}

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
  <section class="col-12 col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-2">Kategorien verwalten</h2>
        <p class="text-secondary small mb-3">Bereiche fuer das Sortiment anlegen und aktivieren.</p>

        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <h3 class="h6 mb-2">Neue Kategorie</h3>
            <label class="form-label small text-secondary" for="categoryName">Name</label>
            <input id="categoryName" class="form-control" type="text" placeholder="z.B. Kaffee" autocomplete="off" inputmode="text">
            <div class="form-check mt-2">
              <input id="categoryActive" class="form-check-input" type="checkbox" checked>
              <label class="form-check-label" for="categoryActive">Aktiv</label>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button id="categoryAdd" class="btn btn-primary btn-sm" type="button">Kategorie anlegen</button>
            </div>
            <div id="categoryStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="col-12 col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h2 class="h5 mb-2">Artikel anlegen</h2>
        <p class="text-secondary small mb-3">Artikel mit Preis, Zutaten und Status pflegen.</p>
        <h3 class="h6 mb-2">Neuer Artikel</h3>
        <label class="form-label small text-secondary" for="itemCategory">Kategorie</label>
        <select id="itemCategory" class="form-select">
          <?php foreach ($menu_categories as $category) {
              $cat_id = (string)($category['id'] ?? '');
              $cat_name = (string)($category['name'] ?? '');
              if ($cat_id === '') {
                  continue;
              }
              ?>
            <option value="<?php echo htmlspecialchars($cat_id, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($cat_name !== '' ? $cat_name : 'Kategorie', ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php } ?>
        </select>
        <label class="form-label small text-secondary mt-2" for="itemName">Name</label>
        <input id="itemName" class="form-control" type="text" placeholder="z.B. Espresso" autocomplete="off" inputmode="text">
        <label class="form-label small text-secondary mt-2" for="itemPrice">Preis</label>
        <input id="itemPrice" class="form-control" type="text" placeholder="2.50" autocomplete="off" inputmode="decimal">
        <label class="form-label small text-secondary mt-2" for="itemIngredients">Zutaten</label>
        <input id="itemIngredients" class="form-control" type="text" placeholder="z.B. Limette, Minze, Zucker" autocomplete="off" inputmode="text">
        <label class="form-label small text-secondary mt-2" for="itemTags">Tags</label>
        <input id="itemTags" class="form-control" type="text" placeholder="z.B. gin, wodka" autocomplete="off" inputmode="text">
        <label class="form-label small text-secondary mt-2" for="itemPreparation">Zubereitung</label>
        <textarea id="itemPreparation" class="form-control" rows="2" placeholder="Kurzbeschreibung der Zubereitung"></textarea>
        <div class="form-check mt-2">
          <input id="itemActive" class="form-check-input" type="checkbox" checked>
          <label class="form-check-label" for="itemActive">Aktiv</label>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button id="itemAdd" class="btn btn-primary btn-sm" type="button">Artikel anlegen</button>
        </div>
        <div id="itemStatus" class="text-secondary small mt-2" role="status" aria-live="polite"></div>
      </div>
    </div>
  </section>
  <section class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <h2 class="h5 mb-2">Aktuelles Sortiment</h2>
        <p class="text-secondary small mb-3">Kategorien bearbeiten und Artikel pro Kategorie ausklappen.</p>
        <div id="menuStatus" class="text-secondary small mb-2" role="status" aria-live="polite"></div>
        <div id="menuList" class="d-flex flex-column gap-3">
          <?php if (!$menu_categories) { ?>
            <div class="text-secondary small">Noch keine Kategorien vorhanden.</div>
          <?php } ?>
          <?php foreach ($menu_categories as $category) {
              if (!is_array($category)) {
                  continue;
              }
              $cat_id = (string)($category['id'] ?? '');
              $cat_name = (string)($category['name'] ?? '');
              $cat_active = !empty($category['active']);
              $category_items = $items_by_category[$cat_id] ?? [];
              $category_item_count = is_array($category_items) ? count($category_items) : 0;
              ?>
            <div class="card border-0 shadow-sm" data-category-id="<?php echo htmlspecialchars($cat_id, ENT_QUOTES, 'UTF-8'); ?>">
              <div class="card-body">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2 mb-3">
                  <div class="flex-grow-1">
                    <label class="form-label small text-secondary">Kategorie</label>
                    <input class="form-control form-control-sm js-category-name" value="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="form-check">
                      <input class="form-check-input js-category-active" type="checkbox" <?php echo $cat_active ? 'checked' : ''; ?>>
                      <label class="form-check-label">Aktiv</label>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-action="save-category">Speichern</button>
                  </div>
                  <span class="badge <?php echo $cat_active ? 'text-bg-success' : 'text-bg-secondary'; ?> align-self-start">
                    <?php echo $cat_active ? 'Aktiv' : 'Inaktiv'; ?>
                  </span>
                  <span class="badge text-bg-light text-secondary align-self-start">
                    <?php echo (int)$category_item_count; ?> Artikel
                  </span>
                </div>
                <?php if (!$category_items) { ?>
                  <div class="text-secondary small">Keine Artikel vorhanden.</div>
                <?php } else { ?>
                  <details class="mb-0">
                    <summary class="text-secondary small">Artikel anzeigen</summary>
                    <div class="d-flex flex-column gap-2 mt-2">
                      <?php foreach ($category_items as $item) {
                          $item_id = (string)($item['id'] ?? '');
                          $item_name = (string)($item['name'] ?? '');
                          $item_price = (string)($item['price'] ?? '0.00');
                          $item_ingredients = $item['ingredients'] ?? [];
                          $item_tags = $item['tags'] ?? [];
                          $item_preparation = (string)($item['preparation'] ?? '');
                          $item_ingredients_label = is_array($item_ingredients) ? implode(', ', $item_ingredients) : '';
                          $item_tags_label = is_array($item_tags) ? implode(', ', $item_tags) : '';
                          $item_active = !empty($item['active']);
                          ?>
                        <div class="border rounded px-3 py-2 bg-light" data-item-id="<?php echo htmlspecialchars($item_id, ENT_QUOTES, 'UTF-8'); ?>">
                          <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-4">
                              <label class="form-label small text-secondary">Artikel</label>
                              <input class="form-control form-control-sm js-item-name" value="<?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 col-md-2">
                              <label class="form-label small text-secondary">Preis</label>
                              <input class="form-control form-control-sm js-item-price" value="<?php echo htmlspecialchars($item_price, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                              <label class="form-label small text-secondary">Zutaten</label>
                              <input class="form-control form-control-sm js-item-ingredients" value="<?php echo htmlspecialchars($item_ingredients_label, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 col-md-4">
                              <label class="form-label small text-secondary">Tags</label>
                              <input class="form-control form-control-sm js-item-tags" value="<?php echo htmlspecialchars($item_tags_label, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 col-md-8">
                              <label class="form-label small text-secondary">Zubereitung</label>
                              <input class="form-control form-control-sm js-item-preparation" value="<?php echo htmlspecialchars($item_preparation, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12 col-md-4 d-flex flex-wrap align-items-center gap-2">
                              <div class="form-check">
                                <input class="form-check-input js-item-active" type="checkbox" <?php echo $item_active ? 'checked' : ''; ?>>
                                <label class="form-check-label">Aktiv</label>
                              </div>
                              <button type="button" class="btn btn-outline-primary btn-sm" data-action="save-item">Speichern</button>
                            </div>
                          </div>
                        </div>
                      <?php } ?>
                    </div>
                  </details>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
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
    'modals' => $modals,
    'footer' => $footer,
    'head_extra' => '<meta name="robots" content="noindex, nofollow">',
    'scripts' => [
        'assets/admin.js',
        'assets/menu.js',
    ],
]);
