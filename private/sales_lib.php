<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KekCheckout\SalesManager;

/**
 * @deprecated Use KekCheckout\SalesManager::newId()
 */
function sales_new_id(): string
{
    $mgr = new SalesManager('');
    return $mgr->newId();
}

/**
 * @deprecated Use KekCheckout\SalesManager::trim()
 */
function sales_trim(string $value, int $max): string
{
    $mgr = new SalesManager('');
    return $mgr->trim($value, $max);
}

/**
 * @deprecated Use KekCheckout\SalesManager::normalizePrice()
 */
function sales_normalize_price($value): string
{
    $mgr = new SalesManager('');
    return $mgr->normalizePrice($value);
}

/**
 * @deprecated Use KekCheckout\SalesManager::calcEarnings()
 */
function sales_calc_earnings(string $type, string $price): string
{
    $mgr = new SalesManager('');
    return $mgr->calcEarnings($type, $price);
}

/**
 * @deprecated
 */
function sales_load_list(string $path): array
{
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    return json_decode((string)$raw, true) ?: [];
}

/**
 * @deprecated
 */
function sales_save_list(string $path, array $data): bool
{
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

/**
 * @deprecated Use KekCheckout\SalesManager::buildBooking()
 */
function sales_build_booking(array $user, array $product, array $category, string $type): array
{
    $mgr = new SalesManager('');
    return $mgr->buildBooking($user, $product, $category, $type);
}

/**
 * @deprecated
 */
function sales_append_booking(string $path, array $booking): bool
{
    $list = sales_load_list($path);
    $list[] = $booking;
    return sales_save_list($path, $list);
}

/**
 * @deprecated Use KekCheckout\SalesManager::ensureCsv()
 */
function sales_ensure_csv(string $path): void
{
    $mgr = new SalesManager($path);
    $mgr->ensureCsv();
}

/**
 * @deprecated Use KekCheckout\SalesManager::appendBookingCsv()
 */
function sales_append_booking_csv(string $path, array $booking): bool
{
    $mgr = new SalesManager($path);
    return $mgr->appendBookingCsv($booking);
}

/**
 * @deprecated Use KekCheckout\SalesManager::readCsv()
 */
function sales_read_csv(string $path): array
{
    $mgr = new SalesManager($path);
    return $mgr->readCsv();
}

/**
 * @deprecated Use KekCheckout\SalesManager::writeCsv()
 */
function sales_write_csv(string $path, array $rows): bool
{
    $mgr = new SalesManager($path);
    return $mgr->writeCsv($rows);
}

/**
 * @deprecated Use KekCheckout\SalesManager::stornoLastBookingCsv()
 */
function sales_storno_last_booking_csv(string $path, string $user_id, string $reason, int $max_age_minutes, int $max_back): array
{
    $mgr = new SalesManager($path);
    return $mgr->stornoLastBookingCsv($user_id, $reason, $max_age_minutes, $max_back);
}

/**
 * @deprecated
 */
function sales_get_last_booking_for_user(string $path, string $user_id): ?array
{
    $rows = sales_read_csv($path);
    if (count($rows) <= 1) return null;
    $headers = array_shift($rows);
    $headerMap = array_flip($headers);
    $uidIdx = $headerMap['user_id'] ?? null;
    $statusIdx = $headerMap['status'] ?? null;
    if ($uidIdx === null) return null;
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        if (($rows[$i][$uidIdx] ?? '') === $user_id && ($rows[$i][$statusIdx] ?? '') === 'OK') {
            return array_combine($headers, $rows[$i]);
        }
    }
    return null;
}

/**
 * @deprecated
 */
function sales_storno_last_booking(string $path, string $user_id, string $reason, int $max_age_minutes, int $max_back): array
{
    return sales_storno_last_booking_csv($path, $user_id, $reason, $max_age_minutes, $max_back);
}

/**
 * @deprecated
 */
function sales_queue_add(string $queue_path, array $booking): bool
{
    $list = sales_load_list($queue_path);
    $list[] = $booking;
    return sales_save_list($queue_path, $list);
}

/**
 * @deprecated
 */
function sales_queue_remove(string $queue_path, array $ids): bool
{
    $list = sales_load_list($queue_path);
    $newList = array_filter($list, fn($b) => !in_array($b['id'] ?? '', $ids, true));
    return sales_save_list($queue_path, array_values($newList));
}

/**
 * @deprecated
 */
function sales_queue_count(string $queue_path): int
{
    return count(sales_load_list($queue_path));
}
