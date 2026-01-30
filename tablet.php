<?php
declare(strict_types=1);

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\MenuManager;
use KekCheckout\Layout;
use KekCheckout\Auth;
use KekCheckout\Settings;


$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$settings_path = __DIR__ . '/private/settings.json';

$settingsManager = new Settings($settings_path);
$menuManager = new MenuManager(__DIR__ . '/private/menu_categories.json', __DIR__ . '/private/menu_items.json');
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$layoutManager = new Layout();

$menuManager->ensureSeed();
$categories = $menuManager->buildGroupedMenu();
$settings = $settingsManager->getAll();

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'template' => 'site/tablet.twig',
    'title' => 'Kek - Checkout Tablet',
    'categories' => $categories,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">',
    'inline_scripts' => [
        "(() => { window.kekDisableCounter = true; })();",
        "(() => { if (window.kekTheme && typeof window.kekTheme.setAccessibility === 'function') { window.kekTheme.setAccessibility(true); } })();",
        "(() => { window.kekTabletSettings = " . json_encode(['typeResetSeconds' => $settings['tablet_type_reset'] ?? 30]) . "; })();",
        "(() => {" .
            "document.addEventListener('gesturestart', (e) => e.preventDefault());" .
            "document.addEventListener('gesturechange', (e) => e.preventDefault());" .
            "document.addEventListener('gestureend', (e) => e.preventDefault());" .
            "let lastTouchEnd = 0;" .
            "document.addEventListener('touchend', (e) => {" .
                "const now = Date.now();" .
                "if (now - lastTouchEnd < 300) { e.preventDefault(); }" .
                "lastTouchEnd = now;" .
            "}, { passive: false });" .
        "})();"
    ],
    'scripts' => ['assets/app.js', 'assets/pos.js', 'assets/tablet_pos.js'],
    'main_class' => 'container-fluid py-2 px-2 tablet-page',
    'body_class' => 'bg-light tablet-body',
]);
exit;
