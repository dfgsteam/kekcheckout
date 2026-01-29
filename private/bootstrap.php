<?php
declare(strict_types=1);

/**
 * Load numeric settings with defaults from a JSON file.
 */
function load_settings(string $path): array
{
    $defaults = [
        'threshold' => 150,
        'max_points' => 10000,
        'chart_max_points' => 2000,
        'window_hours' => 3,
        'tick_minutes' => 15,
        'capacity_default' => 150,
        'storno_max_minutes' => 3,
        'storno_max_back' => 5,
    ];

    if (!is_file($path)) {
        return $defaults;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $defaults;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $defaults;
    }

    $settings = $defaults;
    foreach ($defaults as $key => $value) {
        if (isset($data[$key]) && is_numeric($data[$key])) {
            $num = (int)$data[$key];
            if ($num > 0) {
                $settings[$key] = $num;
            }
        }
    }

    return $settings;
}

/**
 * Append a structured log entry to the request log file.
 */
function log_event(string $path, string $action, int $status, array $extra = []): void
{
    $entry = array_merge([
        'ts' => date('c'),
        'action' => $action,
        'status' => $status,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ], $extra);

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
    }

    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return;
    }

    flock($fp, LOCK_EX);
    rewind($fp);
    $content = stream_get_contents($fp);
    $lines = [];
    if ($content !== false && $content !== '') {
        $lines = array_filter(explode("\n", trim($content)), 'strlen');
    }
    $lines[] = $line;
    $max_lines = 200;
    if (count($lines) > $max_lines) {
        $lines = array_slice($lines, -$max_lines);
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, implode("\n", $lines) . "\n");
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Send a JSON error response and stop execution.
 */
function send_json_error(int $code, string $message, string $log_path = '', string $action = ''): void
{
    if ($log_path !== '') {
        log_event($log_path, $action !== '' ? $action : 'error', $code, ['error' => $message]);
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Read the saved event name from disk.
 */
function load_event_name(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $name = trim((string)file_get_contents($path));
    if ($name === '') {
        return '';
    }
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    return substr($name, 0, 80);
}

/**
 * Ensure the archive directory exists.
 */
function ensure_archive_dir(string $dir): void
{
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
    }
}

/**
 * Decode the JSON request body into an array.
 */
function read_json_body(): array
{
    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Sanitize a string to be safe for display or storage.
 */
function sanitize_string(string $value, int $max_len = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return mb_substr($value, 0, $max_len);
}

/**
 * Validate an archive filename.
 */
function is_valid_archive_name(string $name): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]+\\.csv$/', $name);
}

/**
 * Normalize a user-provided archive name to a CSV filename.
 */
function normalize_archive_name(string $name): string
{
    $clean = trim($name);
    if ($clean === '') {
        return '';
    }
    if (!preg_match('/\\.csv$/i', $clean)) {
        $clean .= '.csv';
    }
    $clean = preg_replace('/\\s+/', '-', $clean) ?? $clean;
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '', $clean) ?? $clean;
    if (!preg_match('/\\.csv$/i', $clean)) {
        $clean .= '.csv';
    }
    $base = preg_replace('/\\.csv$/i', '', $clean) ?? '';
    if ($base === '') {
        return '';
    }
    return $clean;
}

/**
 * Resolve and validate a CSV archive path under the archive dir.
 */
function resolve_archive_path(string $dir, string $name): ?string
{
    if (!is_valid_archive_name($name)) {
        return null;
    }
    $path = $dir . '/' . $name;
    $real = realpath($path);
    $real_dir = realpath($dir);
    if ($real === false || $real_dir === false) {
        return null;
    }
    if (strpos($real, $real_dir . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/**
 * Send common security headers for HTML pages.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/**
 * Send a Content-Security-Policy header with a script nonce.
 */
function send_csp_header(string $nonce): void
{
    if (headers_sent()) {
        return;
    }
    $policy = [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "img-src 'self' data:",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
        "script-src 'self' https://cdn.jsdelivr.net 'nonce-{$nonce}'",
        "connect-src 'self'",
        "worker-src 'self'",
        "manifest-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $policy));
}
