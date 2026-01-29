<?php
declare(strict_types=1);

$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$booking_csv_path = __DIR__ . '/private/bookings.csv';
$settings_path = __DIR__ . '/private/settings.json';
$log_path = __DIR__ . '/private/request.log';

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\Settings;
use KekCheckout\Logger;
use KekCheckout\Auth;
use KekCheckout\MenuManager;
use KekCheckout\SalesManager;
use KekCheckout\Layout;

$settingsManager = new Settings($settings_path);
$logger = new Logger($log_path);
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
$salesManager = new SalesManager($booking_csv_path);
$layoutManager = new Layout();

$action = $_GET['action'] ?? null;

if ($action === 'validate_token') {
    header('Content-Type: application/json; charset=utf-8');
    $payload = read_json_body();
    $token = (string)($payload['token'] ?? '');
    
    $access_tokens = $auth->loadAccessTokens();
    $admin_token = $auth->loadAdminToken();
    
    if ($auth->validateToken($token, $access_tokens, $admin_token)) {
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    }
    exit;
}

if ($action === 'book' || $action === 'storno') {
    header('Content-Type: application/json; charset=utf-8');
    $access_tokens = $auth->loadAccessTokens();
    $admin_token = $auth->loadAdminToken();
    $auth->requireAnyToken($access_tokens, $admin_token);
    $payload = read_json_body();

    // CSRF check for state-changing actions
    $csrf_token = $payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$auth->verifyCsrfToken($csrf_token)) {
        send_json_error(403, 'Invalid CSRF token', $log_path, $action ?? '');
    }

    if ($action === 'book') {
        $product_id = (string)($payload['productId'] ?? '');
        $type = (string)($payload['type'] ?? '');
        if ($product_id === '' || $type === '') {
            send_json_error(400, 'Missing data', $log_path, $action ?? '');
        }

        $menu = $menuManager->getMenu();
        $product = null;
        $category = null;
        foreach ($menu['items'] as $item) {
            if (!is_array($item) || empty($item['active'])) {
                continue;
            }
            if ((string)($item['id'] ?? '') === $product_id) {
                $product = $item;
                break;
            }
        }
        if ($product === null) {
            send_json_error(404, 'Product not found', $log_path, $action ?? '');
        }
        foreach ($menu['categories'] as $cat) {
            if (!$menuManager->isCategoryEffectivelyActive((string)($cat['id'] ?? ''), $menu['categories'])) {
                continue;
            }
            if ((string)($cat['id'] ?? '') === (string)($product['category_id'] ?? '')) {
                $category = $cat;
                break;
            }
        }
        if ($category === null) {
            send_json_error(404, 'Category not found', $log_path, $action ?? '');
        }

        $user = $auth->resolveUserIdentity($access_tokens, $admin_token);
        $booking = $salesManager->buildBooking($user, $product, $category, $type);
        if (!$salesManager->appendBookingCsv($booking)) {
            send_json_error(500, 'Save failed', $log_path, $action ?? '');
        }
        $logger->log('book', 200, ['product' => $product_id, 'type' => $type]);
        echo json_encode(['ok' => true, 'booking' => $booking]);
        exit;
    }

    if ($action === 'storno') {
        $reason = (string)($payload['reason'] ?? '');
        $user = $auth->resolveUserIdentity($access_tokens, $admin_token);
        $settings = $settingsManager->getAll();
        $max_minutes = (int)($settings['storno_max_minutes'] ?? 3);
        $max_back = (int)($settings['storno_max_back'] ?? 5);
        if ($max_minutes < 0) {
            $max_minutes = 0;
        }
        if ($max_back < 0) {
            $max_back = 0;
        }
        $result = $salesManager->stornoLastBookingCsv(
            (string)$user['id'],
            $reason,
            $max_minutes,
            $max_back
        );
        if (!$result['ok']) {
            send_json_error(400, $result['error'] ?? 'Storno failed', $log_path, $action ?? '');
        }
        $logger->log('storno', 200, ['user' => (string)$user['id']]);
        echo json_encode(['ok' => true, 'booking' => $result['booking'] ?? null]);
        exit;
    }
}

$categories_path = __DIR__ . '/private/menu_categories.json';
$items_path = __DIR__ . '/private/menu_items.json';

$menuManager->ensureSeed();
$grouped_categories = $menuManager->buildGroupedMenu();

$all_items = [];
foreach ($grouped_categories as $root_cat) {
    foreach ($root_cat['groups'] as $group) {
        foreach ($group['items'] as $item) {
            $all_items[] = $item;
        }
    }
}
usort($all_items, fn($a, $b) => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));

$categories = $grouped_categories;
$categories[] = [
    'id' => 'all',
    'name' => 'Alle',
    'name_i18n' => 'pos.tabs.all',
    'groups' => [
        [
            'id' => 'all',
            'name' => 'Alle Produkte',
            'is_root' => true,
            'items' => $all_items
        ]
    ]
];

$header_actions_html = <<<HTML
  <a
    class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary"
    href="tablet.php"
    data-i18n-aria-label="nav.tablet"
    data-i18n-title="nav.tablet"
  >
    <i class="bi bi-tablet" aria-hidden="true"></i>
    <span class="btn-icon-text" data-i18n="nav.tablet">Tablet</span>
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
HTML;

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'template' => 'site/index.twig',
    'title' => 'Kek - Checkout',
    'title_h1' => 'Produkte',
    'title_class' => 'display-5',
    'header_class_extra' => 'gap-4',
    'description_p' => 'Artikel nach Kategorien auswaehlen und buchen.',
    'show_status' => true,
    'header_actions_html' => $header_actions_html,
    'categories' => $categories,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '">',
    'inline_scripts' => [
        "(() => { window.kekDisableCounter = true; })();"
    ],
    'scripts' => ['assets/app.js', 'assets/pos.js'],
]);
exit;
