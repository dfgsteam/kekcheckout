<?php
declare(strict_types=1);

/**
 * Sales and booking helpers for POS flow.
 */

/**
 * Create a short random id.
 */
function sales_new_id(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Normalize a value to a trimmed string.
 */
function sales_trim(string $value, int $max): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return substr($value, 0, $max);
}

/**
 * Normalize a numeric price string to 2 decimals.
 */
function sales_normalize_price($value): string
{
    $raw = trim((string)$value);
    $raw = str_replace(',', '.', $raw);
    $number = (float)$raw;
    if ($number < 0) {
        $number = 0;
    }
    return number_format($number, 2, '.', '');
}

/**
 * Calculate earnings based on booking type.
 */
function sales_calc_earnings(string $type, string $price): string
{
    $normalized = sales_normalize_price($price);
    if ($type === 'Verkauft') {
        return $normalized;
    }
    return '0.00';
}

/**
 * Load a JSON list from disk.
 */
function sales_load_list(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Persist a JSON list to disk.
 */
function sales_save_list(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }
    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

/**
 * Build a booking snapshot.
 */
function sales_build_booking(array $user, array $product, array $category, string $type): array
{
    $price = sales_normalize_price($product['price'] ?? '0');
    $type = sales_trim($type, 24);
    $user_id = sales_trim((string)($user['id'] ?? ''), 40);
    $user_name = sales_trim((string)($user['name'] ?? ''), 80);
    $product_id = sales_trim((string)($product['id'] ?? ''), 40);
    $product_name = sales_trim((string)($product['name'] ?? ''), 120);
    $category_id = sales_trim((string)($category['id'] ?? ''), 40);
    $category_name = sales_trim((string)($category['name'] ?? ''), 120);

    return [
        'id' => sales_new_id(),
        'status' => 'OK',
        'uhrzeit' => date('c'),
        'user' => [
            'id' => $user_id,
            'name' => $user_name,
        ],
        'produkt' => [
            'id' => $product_id,
            'name' => $product_name,
        ],
        'kategorie' => [
            'id' => $category_id,
            'name' => $category_name,
        ],
        'preis' => $price,
        'buchungstyp' => $type,
        'einnahmen' => sales_calc_earnings($type, $price),
    ];
}

/**
 * Append a booking to the log.
 */
function sales_append_booking(string $path, array $booking): bool
{
    $entries = sales_load_list($path);
    $entries[] = $booking;
    return sales_save_list($path, $entries);
}

/**
 * Ensure the CSV log exists with header.
 */
function sales_ensure_csv(string $path): void
{
    if (!is_file($path) || filesize($path) === 0) {
        $header = "id,uhrzeit,user_id,user_name,produkt_id,produkt_name,kategorie_id,kategorie_name,preis,buchungstyp,einnahmen,status,storno_reason,storno_at\n";
        file_put_contents($path, $header, LOCK_EX);
    }
}

/**
 * Append a booking to a CSV log.
 */
function sales_append_booking_csv(string $path, array $booking): bool
{
    sales_ensure_csv($path);
    $row = [
        (string)($booking['id'] ?? ''),
        (string)($booking['uhrzeit'] ?? ''),
        (string)($booking['user']['id'] ?? ''),
        (string)($booking['user']['name'] ?? ''),
        (string)($booking['produkt']['id'] ?? ''),
        (string)($booking['produkt']['name'] ?? ''),
        (string)($booking['kategorie']['id'] ?? ''),
        (string)($booking['kategorie']['name'] ?? ''),
        (string)($booking['preis'] ?? ''),
        (string)($booking['buchungstyp'] ?? ''),
        (string)($booking['einnahmen'] ?? ''),
        (string)($booking['status'] ?? ''),
        (string)($booking['storno_reason'] ?? ''),
        (string)($booking['storno_at'] ?? ''),
    ];
    $fp = fopen($path, 'a');
    if ($fp === false) {
        return false;
    }
    flock($fp, LOCK_EX);
    $result = fputcsv($fp, $row, ',', '"', '\\') !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

/**
 * Read CSV log into an array of rows (including header).
 */
function sales_read_csv(string $path): array
{
    $rows = [];
    if (!is_file($path)) {
        return $rows;
    }
    $fp = fopen($path, 'r');
    if ($fp === false) {
        return $rows;
    }
    while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
        $rows[] = $row;
    }
    fclose($fp);
    return $rows;
}

/**
 * Write CSV log with header and rows.
 */
function sales_write_csv(string $path, array $rows): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $fp = fopen($path, 'w');
    if ($fp === false) {
        return false;
    }
    foreach ($rows as $row) {
        fputcsv($fp, $row, ',', '"', '\\');
    }
    fclose($fp);
    return true;
}

/**
 * Mark the last OK booking for a user as storno in the CSV log.
 */
function sales_storno_last_booking_csv(
    string $path,
    string $user_id,
    string $reason = '',
    int $max_age_minutes = 0,
    int $max_back = 0
): array
{
    sales_ensure_csv($path);
    $rows = sales_read_csv($path);
    if (!$rows) {
        return ['ok' => false, 'error' => 'No booking found'];
    }
    $header = array_shift($rows);
    if (!is_array($header)) {
        return ['ok' => false, 'error' => 'Invalid log'];
    }
    $user_id = sales_trim($user_id, 40);
    $reason = sales_trim($reason, 160);
    $index_map = array_flip($header);
    $user_col = $index_map['user_id'] ?? null;
    $status_col = $index_map['status'] ?? null;
    $time_col = $index_map['uhrzeit'] ?? null;
    if ($user_col === null || $status_col === null) {
        return ['ok' => false, 'error' => 'Invalid log'];
    }

    $max_age_seconds = $max_age_minutes > 0 ? $max_age_minutes * 60 : 0;
    $checked = 0;
    for ($i = count($rows) - 1; $i >= 0; $i--) {
        $row = $rows[$i] ?? null;
        if (!is_array($row)) {
            continue;
        }
        $row_user = (string)($row[$user_col] ?? '');
        $row_status = (string)($row[$status_col] ?? '');
        if ($row_user === $user_id && $row_status === 'OK') {
            $checked++;
            if ($max_back > 0 && $checked > $max_back) {
                return ['ok' => false, 'error' => 'Storno limit reached'];
            }
            if ($max_age_seconds > 0) {
                if ($time_col === null) {
                    return ['ok' => false, 'error' => 'Invalid log'];
                }
                $time_value = (string)($row[$time_col] ?? '');
                $timestamp = strtotime($time_value);
                if ($timestamp === false) {
                    return ['ok' => false, 'error' => 'Invalid booking time'];
                }
                if ((time() - $timestamp) > $max_age_seconds) {
                    return ['ok' => false, 'error' => 'Storno too old'];
                }
            }
            $row[$status_col] = 'STORNO';
            if (isset($index_map['einnahmen'])) {
                $row[$index_map['einnahmen']] = '0.00';
            }
            if (isset($index_map['storno_reason'])) {
                $row[$index_map['storno_reason']] = $reason;
            }
            if (isset($index_map['storno_at'])) {
                $row[$index_map['storno_at']] = date('c');
            }
            $rows[$i] = $row;
            array_unshift($rows, $header);
            if (!sales_write_csv($path, $rows)) {
                return ['ok' => false, 'error' => 'Save failed'];
            }
            return ['ok' => true];
        }
    }
    return ['ok' => false, 'error' => 'No booking found'];
}

/**
 * Find the last OK booking for a user.
 */
function sales_get_last_booking_for_user(string $path, string $user_id): ?array
{
    $entries = sales_load_list($path);
    $user_id = sales_trim($user_id, 40);
    for ($i = count($entries) - 1; $i >= 0; $i--) {
        $entry = $entries[$i] ?? null;
        if (!is_array($entry)) {
            continue;
        }
        $entry_user = $entry['user']['id'] ?? '';
        if ($entry_user === $user_id && ($entry['status'] ?? '') === 'OK') {
            return $entry;
        }
    }
    return null;
}

/**
 * Mark the last OK booking for a user as storno.
 */
function sales_storno_last_booking(
    string $path,
    string $user_id,
    string $reason = '',
    int $max_age_minutes = 0,
    int $max_back = 0
): array
{
    $entries = sales_load_list($path);
    $user_id = sales_trim($user_id, 40);
    $reason = sales_trim($reason, 160);
    $max_age_seconds = $max_age_minutes > 0 ? $max_age_minutes * 60 : 0;
    $checked = 0;
    for ($i = count($entries) - 1; $i >= 0; $i--) {
        $entry = $entries[$i] ?? null;
        if (!is_array($entry)) {
            continue;
        }
        $entry_user = $entry['user']['id'] ?? '';
        if ($entry_user === $user_id && ($entry['status'] ?? '') === 'OK') {
            $checked++;
            if ($max_back > 0 && $checked > $max_back) {
                return ['ok' => false, 'error' => 'Storno limit reached'];
            }
            if ($max_age_seconds > 0) {
                $time_value = (string)($entry['uhrzeit'] ?? '');
                $timestamp = strtotime($time_value);
                if ($timestamp === false) {
                    return ['ok' => false, 'error' => 'Invalid booking time'];
                }
                if ((time() - $timestamp) > $max_age_seconds) {
                    return ['ok' => false, 'error' => 'Storno too old'];
                }
            }
            $entries[$i]['status'] = 'STORNO';
            $entries[$i]['einnahmen'] = '0.00';
            $entries[$i]['storno_reason'] = $reason;
            $entries[$i]['storno_at'] = date('c');
            if (!sales_save_list($path, $entries)) {
                return ['ok' => false, 'error' => 'Save failed'];
            }
            return ['ok' => true, 'booking' => $entries[$i]];
        }
    }
    return ['ok' => false, 'error' => 'No booking found'];
}

/**
 * Queue a booking for offline sync.
 */
function sales_queue_add(string $queue_path, array $booking): bool
{
    $queue = sales_load_list($queue_path);
    $queue[] = [
        'id' => sales_new_id(),
        'queued_at' => date('c'),
        'booking' => $booking,
    ];
    return sales_save_list($queue_path, $queue);
}

/**
 * Remove queued entries by id.
 */
function sales_queue_remove(string $queue_path, array $ids): bool
{
    $queue = sales_load_list($queue_path);
    $ids = array_map('strval', $ids);
    $filtered = array_values(array_filter($queue, function ($entry) use ($ids): bool {
        if (!is_array($entry)) {
            return false;
        }
        $id = (string)($entry['id'] ?? '');
        return $id !== '' && !in_array($id, $ids, true);
    }));
    return sales_save_list($queue_path, $filtered);
}

/**
 * Return a queue count.
 */
function sales_queue_count(string $queue_path): int
{
    return count(sales_load_list($queue_path));
}
