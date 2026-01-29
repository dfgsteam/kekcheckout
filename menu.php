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

use KekCheckout\Settings;
use KekCheckout\Logger;
use KekCheckout\Auth;
use KekCheckout\MenuManager;
use KekCheckout\Layout;
use KekCheckout\Utils;

$settingsManager = new Settings($settings_path);
$logger = new Logger($log_path);
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$menuManager = new MenuManager($categories_path, $items_path);
$layoutManager = new Layout();

$menuManager->ensureSeed();

$admin_token = $auth->loadAdminToken();
$action = $_GET['action'] ?? null;
if ($action !== null) {
    $auth->requireAdminToken($admin_token);

    if ($action === 'get_menu') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($menuManager->getMenu());
        exit;
    }

    if ($action === 'add_category') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->addCategory(
            (string)($payload['name'] ?? ''),
            (bool)($payload['active'] ?? true),
            (string)($payload['parentId'] ?? '')
        );
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Save failed', $log_path, 'menu:add_category');
        }
        $logger->log('menu:add_category', 200, ['name' => $payload['name'] ?? '']);
        echo json_encode(['ok' => true, 'category' => $res['category'] ?? [], 'menu' => $menuManager->getMenu()]);
        exit;
    }

    if ($action === 'add_item') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->addItem(
            (string)($payload['categoryId'] ?? ''),
            (string)($payload['name'] ?? ''),
            (string)($payload['price'] ?? '0'),
            $payload['ingredients'] ?? '',
            $payload['tags'] ?? '',
            (string)($payload['preparation'] ?? ''),
            (bool)($payload['active'] ?? true)
        );
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Save failed', $log_path, 'menu:add_item');
        }
        $logger->log('menu:add_item', 200, ['name' => $payload['name'] ?? '', 'category' => $payload['categoryId'] ?? '']);
        echo json_encode(['ok' => true, 'item' => $res['item'] ?? [], 'menu' => $menuManager->getMenu()]);
        exit;
    }

    if ($action === 'update_category') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->updateCategory(
            (string)($payload['id'] ?? ''),
            (string)($payload['name'] ?? ''),
            (bool)($payload['active'] ?? true),
            (string)($payload['parentId'] ?? '')
        );
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Save failed', $log_path, 'menu:update_category');
        }
        $logger->log('menu:update_category', 200, ['id' => $payload['id'] ?? '']);
        echo json_encode(['ok' => true, 'menu' => $menuManager->getMenu()]);
        exit;
    }

    if ($action === 'update_item') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->updateItem(
            (string)($payload['id'] ?? ''),
            (string)($payload['name'] ?? ''),
            (string)($payload['price'] ?? '0'),
            $payload['ingredients'] ?? '',
            $payload['tags'] ?? '',
            (string)($payload['preparation'] ?? ''),
            (bool)($payload['active'] ?? true)
        );
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Save failed', $log_path, 'menu:update_item');
        }
        $logger->log('menu:update_item', 200, ['id' => $payload['id'] ?? '']);
        echo json_encode(['ok' => true, 'menu' => $menuManager->getMenu()]);
        exit;
    }

    if ($action === 'delete_category') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->deleteCategory((string)($payload['id'] ?? ''));
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Delete failed', $log_path, 'menu:delete_category');
        }
        $logger->log('menu:delete_category', 200, ['id' => $payload['id'] ?? '']);
        echo json_encode(['ok' => true, 'menu' => $menuManager->getMenu()]);
        exit;
    }

    if ($action === 'delete_item') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = read_json_body();
        $res = $menuManager->deleteItem((string)($payload['id'] ?? ''));
        if (!$res['ok']) {
            send_json_error(400, $res['error'] ?? 'Delete failed', $log_path, 'menu:delete_item');
        }
        $logger->log('menu:delete_item', 200, ['id' => $payload['id'] ?? '']);
        echo json_encode(['ok' => true, 'menu' => $menuManager->getMenu()]);
        exit;
    }

    send_json_error(400, 'Unknown action', $log_path, 'menu');
    exit;
}
?>
<?php
require_once __DIR__ . '/private/layout.php';

$header_actions_html = <<<HTML
<a
  class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
  href="/admin.php"
  data-i18n-aria-label="nav.back"
  data-i18n-title="nav.back"
>
  <i class="bi bi-arrow-left" aria-hidden="true"></i>
  <span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
</a>
HTML;

$menu = $menuManager->getMenu();
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

$layoutManager->render([
    'template' => 'site/menu.twig',
    'title' => 'Kek-Counter Admin',
    'manifest' => '',
    'title_h1' => 'Sortiment',
    'header_class_extra' => 'align-items-lg-end',
    'description_p' => 'Artikel und Kategorien verwalten.',
    'header_actions_html' => $header_actions_html,
    'menu_categories' => $menu_categories,
    'items_by_category' => $items_by_category,
    'header_extra' => '<meta name="robots" content="noindex, nofollow">',
    'scripts' => [
        'assets/admin.js',
        'assets/menu.js',
    ],
]);
exit;
