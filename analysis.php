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

if ($action === 'get_data') {
    header('Content-Type: application/json; charset=utf-8');
    $admin_token = $auth->loadAdminToken();
    $auth->requireAdminToken($admin_token);

    $settings = $settingsManager->getAll();
    $bucket_minutes = (int)($settings['tick_minutes'] ?? 15);
    if ($bucket_minutes <= 0) {
        $bucket_minutes = 15;
    }
    $chart_max_points = (int)($settings['chart_max_points'] ?? 2000);
    if ($chart_max_points <= 0) {
        $chart_max_points = 2000;
    }

    $booking_stats = [
        'revenue' => 0.0,
        'count' => 0,
        'average' => 0.0,
    ];
    $booking_hide_cols = ['user_id', 'produkt_id', 'kategorie_id'];
    $booking_limit = 200;
    $booking_headers = [];
    $booking_rows = [];
    $booking_truncated = false;
    $sales_labels = [];
    $sales_series = ['Verkauft' => [], 'Gutschein' => [], 'Gratis' => [], 'Storno' => []];
    $cashier_labels = [];
    $cashier_series = [];
    $cashier_totals = [];
    $revenue_labels = [];
    $revenue_series = [];

    if (is_file(__DIR__ . '/private/bookings.csv')) {
        $rows = $salesManager->readCsv();
        if ($rows) {
            $headers = array_shift($rows);
            if (is_array($headers)) {
                $booking_headers = $headers;
            }
            $index_map = $booking_headers ? array_flip($booking_headers) : [];
            $earnings_col = $index_map['einnahmen'] ?? null;
            $status_col = $index_map['status'] ?? null;
            $type_col = $index_map['buchungstyp'] ?? null;
            $time_col = $index_map['uhrzeit'] ?? null;
            $user_col = $index_map['user_name'] ?? ($index_map['user_id'] ?? null);
            $tz = new DateTimeZone(date_default_timezone_get());
            $sales_buckets = [];
            $cashier_buckets = [];
            $revenue_buckets = [];

            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $status = $status_col !== null ? (string)($row[$status_col] ?? '') : 'OK';
                if ($status !== 'STORNO') {
                    $booking_stats['count']++;
                    if ($earnings_col !== null) {
                        $raw = str_replace(',', '.', (string)($row[$earnings_col] ?? '0'));
                        $booking_stats['revenue'] += (float)$raw;
                    }
                }
                if ($time_col !== null) {
                    $raw_time = (string)($row[$time_col] ?? '');
                    if ($raw_time !== '') {
                        try {
                            $dt = new DateTimeImmutable($raw_time);
                            $dt = $dt->setTimezone($tz);
                            $hour = (int)$dt->format('H');
                            $minute = (int)$dt->format('i');
                            $bucket = intdiv($minute, $bucket_minutes) * $bucket_minutes;
                            $bucket_key = $dt->setTime($hour, $bucket, 0)->format('Y-m-d H:i');
                            
                            if (!isset($sales_buckets[$bucket_key])) {
                                $sales_buckets[$bucket_key] = ['Verkauft' => 0, 'Gutschein' => 0, 'Gratis' => 0, 'Storno' => 0];
                            }
                            if ($status === 'STORNO') {
                                $sales_buckets[$bucket_key]['Storno']++;
                            } else {
                                $type = $type_col !== null ? (string)($row[$type_col] ?? '') : 'Verkauft';
                                if ($type === 'Freigetraenk') $type = 'Gratis';
                                if (!isset($sales_buckets[$bucket_key][$type])) $type = 'Verkauft';
                                $sales_buckets[$bucket_key][$type]++;
                            }
                            
                            if (!isset($revenue_buckets[$bucket_key])) $revenue_buckets[$bucket_key] = 0.0;
                            if ($status !== 'STORNO' && $earnings_col !== null) {
                                $revenue_buckets[$bucket_key] += (float)str_replace(',', '.', (string)($row[$earnings_col] ?? '0'));
                            }

                            if ($user_col !== null) {
                                $user = trim((string)($row[$user_col] ?? '')) ?: 'Unbekannt';
                                if (!isset($cashier_buckets[$bucket_key])) $cashier_buckets[$bucket_key] = [];
                                if (!isset($cashier_buckets[$bucket_key][$user])) $cashier_buckets[$bucket_key][$user] = 0;
                                $cashier_buckets[$bucket_key][$user]++;
                                if (!isset($cashier_totals[$user])) $cashier_totals[$user] = 0;
                                $cashier_totals[$user]++;
                            }
                        } catch (Exception $e) {}
                    }
                }
            }
            if ($booking_stats['count'] > 0) {
                $booking_stats['average'] = $booking_stats['revenue'] / $booking_stats['count'];
            }
            if (count($rows) > $booking_limit) {
                $booking_rows = array_slice($rows, -$booking_limit);
                $booking_truncated = true;
            } else {
                $booking_rows = $rows;
            }

            if (!empty($sales_buckets)) {
                ksort($sales_buckets);
                $keys = array_slice(array_keys($sales_buckets), -$chart_max_points);
                foreach ($keys as $k) {
                    $sales_labels[] = $k;
                    $sales_series['Verkauft'][] = $sales_buckets[$k]['Verkauft'];
                    $sales_series['Gutschein'][] = $sales_buckets[$k]['Gutschein'];
                    $sales_series['Gratis'][] = $sales_buckets[$k]['Gratis'];
                    $sales_series['Storno'][] = $sales_buckets[$k]['Storno'];
                }
            }

            if (!empty($cashier_buckets)) {
                arsort($cashier_totals);
                $top_users = array_slice(array_keys($cashier_totals), 0, 5);
                ksort($cashier_buckets);
                $keys = array_slice(array_keys($cashier_buckets), -$chart_max_points);
                $cashier_labels = $keys;
                foreach ($top_users as $u) $cashier_series[$u] = [];
                $has_others = count($cashier_totals) > 5;
                if ($has_others) $cashier_series['Andere'] = [];
                foreach ($keys as $k) {
                    $b = $cashier_buckets[$k] ?? [];
                    foreach ($top_users as $u) $cashier_series[$u][] = $b[$u] ?? 0;
                    if ($has_others) {
                        $others = 0;
                        foreach ($b as $user => $count) if (!in_array($user, $top_users, true)) $others += $count;
                        $cashier_series['Andere'][] = $others;
                    }
                }
            }

            if (!empty($revenue_buckets)) {
                ksort($revenue_buckets);
                $keys = array_slice(array_keys($revenue_buckets), -$chart_max_points);
                $revenue_labels = $keys;
                foreach ($keys as $k) $revenue_series[] = round($revenue_buckets[$k], 2);
            }
        }
    }

    $visible_headers = [];
    $visible_rows = [];
    if ($booking_headers) {
        $v_idx = [];
        foreach ($booking_headers as $i => $l) {
            if (!in_array((string)$l, $booking_hide_cols, true)) {
                $v_idx[] = $i;
                $visible_headers[] = (string)$l;
            }
        }
        foreach ($booking_rows as $row) {
            if (!is_array($row)) continue;
            $vr = [];
            foreach ($v_idx as $i) $vr[] = (string)($row[$i] ?? '');
            $visible_rows[] = $vr;
        }
    }

    echo json_encode([
        'stats' => [
            'revenue' => number_format($booking_stats['revenue'], 2, '.', ''),
            'count' => $booking_stats['count'],
            'average' => number_format($booking_stats['average'], 2, '.', ''),
        ],
        'charts' => [
            'sales' => ['labels' => $sales_labels, 'series' => $sales_series],
            'cashier' => ['labels' => $cashier_labels, 'series' => $cashier_series],
            'revenue' => ['labels' => $revenue_labels, 'series' => $revenue_series],
            'bucketMinutes' => $bucket_minutes,
        ],
        'log' => [
            'headers' => $visible_headers,
            'rows' => $visible_rows,
            'truncated' => $booking_truncated,
            'limit' => $booking_limit,
            'hasFile' => is_file(__DIR__ . '/private/bookings.csv'),
        ]
    ]);
    exit;
}

$header_actions_html = <<<HTML
<a class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary" href="index.php" data-i18n-aria-label="nav.back" data-i18n-title="nav.back">
  <i class="bi bi-arrow-left" aria-hidden="true"></i><span class="btn-icon-text" data-i18n="nav.back">Zurueck</span>
</a>
<a class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary" href="admin.php" data-i18n-aria-label="nav.admin" data-i18n-title="nav.admin">
  <i class="bi bi-person" aria-hidden="true"></i><span class="btn-icon-text" data-i18n="nav.admin">Admin</span>
</a>
<a class="btn btn-link btn-sm btn-icon text-decoration-none text-secondary" href="bookings.php" data-i18n-aria-label="nav.bookings" data-i18n-title="nav.bookings">
  <i class="bi bi-list-ul" aria-hidden="true"></i><span class="btn-icon-text" data-i18n="nav.bookings">Buchungen</span>
</a>
HTML;

$csrf_token_val = $auth->getCsrfToken();

$layoutManager->render([
    'template' => 'site/analysis.twig',
    'title' => 'Kek - Checkout Analyse',
    'title_h1' => 'Analyse',
    'header_class_extra' => 'align-items-lg-end',
    'description_p' => 'Auswertungen fuer den Kassenbetrieb.',
    'header_actions_html' => $header_actions_html,
    'header_extra' => '<meta name="csrf-token" content="' . $csrf_token_val . '"><meta name="robots" content="noindex, nofollow">',
    'manifest' => '',
    'scripts' => ['assets/analysis.js', 'assets/analysis_charts.js'],
]);
exit;