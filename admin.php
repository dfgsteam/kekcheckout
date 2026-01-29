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

use KekCheckout\Settings;
use KekCheckout\Logger;
use KekCheckout\Auth;
use KekCheckout\MenuManager;
use KekCheckout\SalesManager;
use KekCheckout\Layout;
use KekCheckout\Utils;

$settingsManager = new Settings($settings_path);
$logger = new Logger($log_path);
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
$salesManager = new SalesManager($csv_path);
$layoutManager = new Layout();

    $admin_token = $auth->loadAdminToken();
    $action = $_GET['action'] ?? null;
    if ($action !== null) {
        $is_get = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ($is_get ? ($_GET['token'] ?? '') : '');
        
        if ($admin_token !== '' || $action !== 'set_admin_token') {
            if ($admin_token === '' || !hash_equals($admin_token, $provided)) {
                send_json_error(403, 'Invalid admin token', $log_path, 'admin');
            }
        }

        // CSRF protection for state-changing actions
        $safe_actions = ['get_access_tokens', 'get_menu', 'get_logs', 'list_archives', 'download_archive', 'get_event_name', 'get_settings', 'list_logs'];
        if (!$is_get && !in_array($action, $safe_actions, true)) {
        $body = read_json_body();
        $csrf_token = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$auth->verifyCsrfToken($csrf_token)) {
            send_json_error(403, 'Invalid CSRF token', $log_path, 'admin:' . $action);
        }
    }

    if ($action === 'restart') {
        header('Content-Type: application/json; charset=utf-8');
        Utils::ensureDir($archive_dir);

        $archived = false;
        $archive_name = null;
        if (is_file($csv_path) && filesize($csv_path) > 0) {
            $event_name = $settingsManager->loadEventName($event_name_path);
            $slug = Utils::slugify($event_name);
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
        $logger->log('admin:restart', 200, $payload);
        echo json_encode($payload);
        exit;
    }

    if ($action === 'set_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $token = $auth->normalizeAccessTokenValue((string)($payload['token'] ?? ($_POST['token'] ?? '')), 160);
        if ($token === '') {
            send_json_error(400, 'Token missing', $log_path, 'admin:' . $action);
        }
        $tokens = $auth->loadAccessTokens();
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
        if (!$auth->saveAccessTokens($tokens)) {
            send_json_error(500, 'Save failed', $log_path, 'admin:' . $action);
        }
        $logger->log('admin:set_access_token', 200);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_access_tokens') {
        header('Content-Type: application/json; charset=utf-8');
        $tokens = $auth->loadAccessTokens();
        echo json_encode(['accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'add_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = $auth->normalizeAccessLabel((string)($payload['name'] ?? ''), 40);
        $token = $auth->normalizeAccessTokenValue((string)($payload['token'] ?? ''), 160);
        $active = (bool)($payload['active'] ?? true);
        if ($name === '' || $token === '') {
            send_json_error(400, 'Missing data', $log_path, "admin:" . ($action ?? "error"));
        }
        $tokens = $auth->loadAccessTokens();
        $base_id = $auth->slugifyId($name);
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
        if (!$auth->saveAccessTokens($tokens)) {
            send_json_error(500, 'Save failed', $log_path, "admin:" . ($action ?? "error"));
        }
        $logger->log('admin:add_access_token', 200, ['id' => $id, 'name' => $name]);
        echo json_encode(['ok' => true, 'accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'update_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $id = (string)($payload['id'] ?? '');
        $name = $auth->normalizeAccessLabel((string)($payload['name'] ?? ''), 40);
        $token = $auth->normalizeAccessTokenValue((string)($payload['token'] ?? ''), 160);
        $active = (bool)($payload['active'] ?? true);
        if ($id === '' || $name === '' || $token === '') {
            send_json_error(400, 'Missing data', $log_path, "admin:" . ($action ?? "error"));
        }
        $tokens = $auth->loadAccessTokens();
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
            send_json_error(404, 'Not found', $log_path, "admin:" . ($action ?? "error"));
        }
        if (!$auth->saveAccessTokens($tokens)) {
            send_json_error(500, 'Save failed', $log_path, "admin:" . ($action ?? "error"));
        }
        $logger->log('admin:update_access_token', 200, ['id' => $id, 'name' => $name]);
        echo json_encode(['ok' => true, 'accessTokens' => $tokens]);
        exit;
    }

    if ($action === 'delete_access_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $id = (string)($payload['id'] ?? '');
        if ($id === '') {
            send_json_error(400, 'Missing data', $log_path, "admin:" . ($action ?? "error"));
        }
        $tokens = $auth->loadAccessTokens();
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
            send_json_error(404, 'Not found', $log_path, "admin:" . ($action ?? "error"));
        }
        if (!$auth->saveAccessTokens($next)) {
            send_json_error(500, 'Save failed', $log_path, "admin:" . ($action ?? "error"));
        }
        $logger->log('admin:delete_access_token', 200, ['id' => $id]);
        echo json_encode(['ok' => true, 'accessTokens' => $next]);
        exit;
    }

    if ($action === 'set_admin_token') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $token = trim((string)($payload['token'] ?? ($_POST['token'] ?? '')));
        if ($token === '') {
            send_json_error(400, 'Token missing', $log_path, "admin:" . ($action ?? "error"));
        }
        file_put_contents($admin_token_path, $token, LOCK_EX);
        $logger->log('admin:set_admin_token', 200);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_event_name') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['eventName' => $settingsManager->loadEventName($event_name_path)]);
        exit;
    }

    if ($action === 'set_event_name') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = (string)($payload['name'] ?? ($_POST['name'] ?? ''));
        $saved = $settingsManager->saveEventName($event_name_path, $name);
        $logger->log('admin:set_event_name', 200, ['name' => $saved]);
        echo json_encode(['ok' => true, 'eventName' => $saved]);
        exit;
    }

    if ($action === 'download_archive') {
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        Utils::ensureDir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found', $log_path, "admin:" . ($action ?? "error"));
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
        Utils::ensureDir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found', $log_path, "admin:" . ($action ?? "error"));
        }
        $new_name = normalize_archive_name($new_name_raw);
        if ($new_name === '') {
            send_json_error(400, 'Name missing', $log_path, "admin:" . ($action ?? "error"));
        }
        if (!is_valid_archive_name($new_name)) {
            send_json_error(400, 'Invalid name', $log_path, "admin:" . ($action ?? "error"));
        }
        if ($new_name === $name) {
            echo json_encode(['ok' => true, 'name' => $new_name]);
            exit;
        }
        $real_dir = realpath($archive_dir);
        if ($real_dir === false) {
            send_json_error(500, 'Archive dir missing', $log_path, "admin:" . ($action ?? "error"));
        }
        $new_path = $real_dir . DIRECTORY_SEPARATOR . $new_name;
        if (is_file($new_path)) {
            send_json_error(409, 'Name exists', $log_path, "admin:" . ($action ?? "error"));
        }
        if (!@rename($path, $new_path)) {
            send_json_error(500, 'Rename failed', $log_path, "admin:" . ($action ?? "error"));
        }
        echo json_encode(['ok' => true, 'name' => $new_name]);
        exit;
    }

    if ($action === 'delete_archive') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $name = trim((string)($payload['name'] ?? ($_POST['name'] ?? '')));
        Utils::ensureDir($archive_dir);
        $path = resolve_archive_path($archive_dir, $name);
        if ($path === null || !is_file($path)) {
            send_json_error(404, 'Not found', $log_path, "admin:" . ($action ?? "error"));
        }
        if (!@unlink($path)) {
            send_json_error(500, 'Delete failed', $log_path, "admin:" . ($action ?? "error"));
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'get_settings') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($settingsManager->getAll());
        exit;
    }

    if ($action === 'set_settings') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $settings = $settingsManager->save($payload);
        $logger->log('admin:set_settings', 200, ['settings' => $settings]);
        echo json_encode(['ok' => true, 'settings' => $settings]);
        exit;
    }

    if ($action === 'list_logs') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $limit = (int)($payload['limit'] ?? ($_GET['limit'] ?? 200));
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
            send_json_error(404, 'Log not found', $log_path, "admin:" . ($action ?? "error"));
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="request.log"');
        readfile($log_path);
        exit;
    }

    if ($action === 'list_archives') {
        header('Content-Type: application/json; charset=utf-8');
        Utils::ensureDir($archive_dir);
        $files = glob($archive_dir . '/*.csv') ?: [];
        usort($files, function($a, $b) {
            $ma = @filemtime($a) ?: 0;
            $mb = @filemtime($b) ?: 0;
            return $mb <=> $ma;
        });
        $items = [];
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $items[] = [
                'name' => basename($file),
                'size' => @filesize($file) ?: 0,
                'modified' => date('c', @filemtime($file) ?: time()),
            ];
        }
        echo json_encode(['archives' => $items]);
        exit;
    }

    if ($action === 'download_latest') {
        Utils::ensureDir($archive_dir);
        $files = glob($archive_dir . '/*.csv') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        if (!$files) {
            send_json_error(404, 'No archives', $log_path, "admin:" . ($action ?? "error"));
        }
        $file = $files[0];
        $logger->log('admin:download_latest', 200, ['name' => basename($file)]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }

    send_json_error(400, 'Unknown action', $log_path, 'admin');
    exit;
}
?>
<?php
require_once __DIR__ . '/private/layout.php';

$header_actions_html = <<<HTML
<a
  class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
  href="/"
  data-i18n-aria-label="nav.back"
  data-i18n-title="nav.back"
>
  <i class="bi bi-arrow-left" aria-hidden="true"></i>
  <span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
</a>
<a
  class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
  href="/menu.php"
  data-i18n-aria-label="nav.menu"
  data-i18n-title="nav.menu"
>
  <i class="bi bi-book" aria-hidden="true"></i>
  <span class="btn-icon-text" data-i18n="nav.menu">Menue</span>
</a>
HTML;

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'template' => 'site/admin.twig',
    'title' => 'Kek-Counter Admin',
    'manifest' => '',
    'title_h1' => 'Admin',
    'header_class_extra' => 'align-items-lg-end',
    'description_p' => 'Kasse konfigurieren und Auswertungen verwalten.',
    'header_actions_html' => $header_actions_html,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '"><meta name="robots" content="noindex, nofollow">',
    'scripts' => [
        'assets/admin.js',
    ],
]);
exit;
