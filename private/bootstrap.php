<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KekCheckout\Settings;
use KekCheckout\Logger;
use KekCheckout\Utils;

/**
 * @deprecated Use KekCheckout\Settings::load()
 */
function load_settings(string $path): array
{
    $mgr = new Settings($path);
    return $mgr->getAll();
}

/**
 * @deprecated Use KekCheckout\Logger::log()
 */
function log_event(string $path, string $action, int $status, array $extra = []): void
{
    $logger = new Logger($path);
    $logger->log($action, $status, $extra);
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
 * @deprecated Use KekCheckout\Settings::loadEventName()
 */
function load_event_name(string $path): string
{
    $mgr = new Settings('');
    return $mgr->loadEventName($path);
}

/**
 * @deprecated Use KekCheckout\Utils::ensureDir()
 */
function ensure_archive_dir(string $dir): void
{
    Utils::ensureDir($dir);
}

/**
 * @deprecated Use KekCheckout\Utils::readJsonBody()
 */
function read_json_body(): array
{
    return Utils::readJsonBody();
}

/**
 * @deprecated Use KekCheckout\Utils::sanitizeString()
 */
function sanitize_string(string $value, int $max_len = 255): string
{
    return Utils::sanitizeString($value, $max_len);
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
