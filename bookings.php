<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');

require_once __DIR__ . '/private/bootstrap.php';

use KekCheckout\Settings;
use KekCheckout\SalesManager;
use KekCheckout\Layout;
use KekCheckout\Auth;

$access_tokens_path = __DIR__ . '/private/access_tokens.json';
$legacy_access_token_path = __DIR__ . '/private/.access_token';
$admin_token_path = __DIR__ . '/private/.admin_token';
$log_path = __DIR__ . '/private/request.log';

$settingsManager = new Settings(__DIR__ . '/private/settings.json');
$salesManager = new SalesManager(__DIR__ . '/private/bookings.csv');
$auth = new Auth($access_tokens_path, $legacy_access_token_path, $admin_token_path);
$layoutManager = new Layout();

$action = $_GET['action'] ?? null;

if ($action === 'get_bookings') {
    header('Content-Type: application/json; charset=utf-8');
    $admin_token = $auth->loadAdminToken();
    $auth->requireAdminToken($admin_token);

    $headers = [];
    $rows = [];
    $hasFile = is_file(__DIR__ . '/private/bookings.csv');
    $hidden_headers = ['user_id', 'produkt_id', 'kategorie_id'];

    if ($hasFile) {
        $csvData = $salesManager->readCsv();
        if ($csvData) {
            $headers = array_shift($csvData);
            if (is_array($headers)) {
                $visible_indexes = [];
                foreach ($headers as $index => $label) {
                    if (in_array((string)$label, $hidden_headers, true)) {
                        continue;
                    }
                    $visible_indexes[] = $index;
                }
                $filtered_headers = [];
                foreach ($visible_indexes as $index) {
                    $filtered_headers[] = (string)($headers[$index] ?? '');
                }
                $headers = $filtered_headers;

                foreach ($csvData as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $filtered_row = [];
                    foreach ($visible_indexes as $index) {
                        $filtered_row[] = (string)($row[$index] ?? '');
                    }
                    $rows[] = $filtered_row;
                }
            }
            $rows = array_reverse($rows); // Neueste zuerst
        }
    }

    echo json_encode([
        'headers' => $headers,
        'rows' => $rows,
        'hasFile' => $hasFile
    ]);
    exit;
}

$header_actions_html = <<<HTML
<a class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary" href="admin.php" data-i18n-aria-label="nav.back" data-i18n-title="nav.back">
  <i class="bi bi-arrow-left" aria-hidden="true"></i><span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
</a>
HTML;

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'template' => 'site/bookings.twig',
    'title' => 'Kek - Buchungslog',
    'title_h1' => 'Buchungslog',
    'header_class_extra' => 'align-items-lg-end',
    'description_p' => 'Vollstaendige Liste aller aktuellen Buchungen.',
    'header_actions_html' => $header_actions_html,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '"><meta name="robots" content="noindex, nofollow">',
    'scripts' => ['assets/bookings.js'],
    'main_class' => 'container py-4',
]);
exit;
