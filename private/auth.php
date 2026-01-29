<?php
declare(strict_types=1);

/**
 * Generate a CSRF token and store it in the session.
 */
function get_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token.
 */
function verify_csrf_token(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Read a token from env or fallback file path.
 */
function load_token(string $env_name, string $path): string
{
    $env = getenv($env_name);
    if ($env !== false && $env !== '') {
        return trim($env);
    }
    if (is_file($path)) {
        $token = trim((string)file_get_contents($path));
        if ($token !== '') {
            return $token;
        }
    }
    return '';
}

/**
 * Normalize a display label for access tokens.
 */
function normalize_access_label(string $value, int $max): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    if ($value === '') {
        return '';
    }
    return substr($value, 0, $max);
}

/**
 * Normalize a token string for storage.
 */
function normalize_access_token_value(string $value, int $max): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return substr($value, 0, $max);
}

/**
 * Create a simple ASCII id from a label.
 */
function access_slugify_id(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');
    return $value;
}

/**
 * Load a list of access tokens (optional legacy fallback).
 */
function load_access_tokens(string $path, string $legacy_path = ''): array
{
    $tokens = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $data = json_decode((string)$raw, true);
        if (is_array($data)) {
            $tokens = $data;
        }
    } elseif ($legacy_path !== '' && is_file($legacy_path)) {
        $legacy = normalize_access_token_value((string)file_get_contents($legacy_path), 160);
        if ($legacy !== '') {
            return [[
                'id' => 'default',
                'name' => 'Default',
                'token' => $legacy,
                'active' => true,
            ]];
        }
    }

    $normalized = [];
    $ids = [];
    foreach ($tokens as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $name = normalize_access_label((string)($entry['name'] ?? ''), 40);
        $token = normalize_access_token_value((string)($entry['token'] ?? ''), 160);
        if ($token === '') {
            continue;
        }
        $id_source = (string)($entry['id'] ?? $name);
        $base_id = access_slugify_id($id_source);
        if ($base_id === '') {
            $base_id = 'key';
        }
        $id = $base_id;
        $suffix = 2;
        while (in_array($id, $ids, true)) {
            $id = $base_id . '-' . $suffix;
            $suffix++;
        }
        $ids[] = $id;
        if ($name === '') {
            $name = $id;
        }
        $normalized[] = [
            'id' => $id,
            'name' => $name,
            'token' => $token,
            'active' => !empty($entry['active']),
        ];
    }
    return $normalized;
}

/**
 * Persist access tokens to disk.
 */
function save_access_tokens(string $path, array $tokens): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return false;
        }
    }
    $payload = json_encode($tokens, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($payload === false) {
        return false;
    }
    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

/**
 * Check if any access tokens are configured.
 */
function access_tokens_empty($access_tokens): bool
{
    if (is_array($access_tokens)) {
        return count($access_tokens) === 0;
    }
    return trim((string)$access_tokens) === '';
}

/**
 * Check if a token matches any active access token.
 */
function access_tokens_match(array $access_tokens, string $candidate): bool
{
    if ($candidate === '') {
        return false;
    }
    foreach ($access_tokens as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (empty($entry['active'])) {
            continue;
        }
        $token = (string)($entry['token'] ?? '');
        if ($token !== '' && hash_equals($token, $candidate)) {
            return true;
        }
    }
    return false;
}

/**
 * Validate origin or referrer against the current host.
 */
function is_same_origin(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        return rtrim($origin, '/') === $expected;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return false;
        }
        $ref_origin = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $ref_origin .= ':' . $parts['port'];
        }
        return $ref_origin === $expected;
    }

    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return $requested_with === 'fetch';
}

/**
 * Extract a bearer token from the Authorization header.
 */
function get_bearer_token(): string
{
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return '';
    }
    if (preg_match('/^Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        return trim($matches[1]);
    }
    return '';
}

/**
 * Validate access/admin tokens against provided headers.
 */
function is_valid_token(
    $access_tokens,
    string $admin_token,
    string $provided_access,
    string $provided_admin,
    string $bearer
): bool {
    $access_matches = function (string $candidate) use ($access_tokens, $admin_token): bool {
        if ($candidate === '') {
            return false;
        }
        if (is_array($access_tokens)) {
            if (access_tokens_match($access_tokens, $candidate)) {
                return true;
            }
        } else {
            $access_token = trim((string)$access_tokens);
            if ($access_token !== '' && hash_equals($access_token, $candidate)) {
                return true;
            }
        }
        if ($admin_token !== '' && hash_equals($admin_token, $candidate)) {
            return true;
        }
        return false;
    };

    if ($access_matches($provided_access)) {
        return true;
    }
    if ($access_matches($provided_admin)) {
        return true;
    }
    if ($bearer !== '' && $access_matches($bearer)) {
        return true;
    }
    return false;
}

/**
 * Validate request method, origin, and tokens for access/admin usage.
 */
function authorize_any_token_request($access_tokens, string $admin_token): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'ok' => false,
            'status' => 405,
            'error' => 'method_not_allowed',
            'message' => 'Method not allowed',
        ];
    }
    if (!is_same_origin()) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'origin_forbidden',
            'message' => 'Forbidden',
        ];
    }
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($requested_with !== 'fetch') {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'request_blocked',
            'message' => 'Forbidden',
        ];
    }
    if (access_tokens_empty($access_tokens) && $admin_token === '') {
        return [
            'ok' => false,
            'status' => 503,
            'error' => 'token_missing',
            'message' => 'Token not configured',
        ];
    }

    $provided_access = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
    $provided_admin = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    $bearer = get_bearer_token();

    if (!is_valid_token($access_tokens, $admin_token, $provided_access, $provided_admin, $bearer)) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'token_invalid',
            'message' => 'Forbidden',
        ];
    }

    return ['ok' => true];
}

/**
 * Require a valid access or admin token for API requests.
 */
function require_any_token($access_tokens, string $admin_token): void
{
    $auth = authorize_any_token_request($access_tokens, $admin_token);
    if (!$auth['ok']) {
        send_json_error($auth['status'], $auth['message']);
    }
}

/**
 * Require a valid admin token for API requests.
 */
function require_admin_token(string $admin_token): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_error(405, 'Method not allowed');
    }
    if (!is_same_origin()) {
        send_json_error(403, 'Forbidden');
    }
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($requested_with !== 'fetch') {
        send_json_error(403, 'Forbidden');
    }
    if ($admin_token === '') {
        send_json_error(503, 'Admin token not configured');
    }

    $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if ($provided === '') {
        $provided = get_bearer_token();
    }
    if (!hash_equals($admin_token, $provided)) {
        send_json_error(403, 'Forbidden');
    }
}
