<?php
declare(strict_types=1);

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

    return false;
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
    string $access_token,
    string $admin_token,
    string $provided_access,
    string $provided_admin,
    string $bearer
): bool {
    if ($access_token !== '' && hash_equals($access_token, $provided_access)) {
        return true;
    }
    if ($admin_token !== '' && hash_equals($admin_token, $provided_access)) {
        return true;
    }
    if ($access_token !== '' && hash_equals($access_token, $provided_admin)) {
        return true;
    }
    if ($admin_token !== '' && hash_equals($admin_token, $provided_admin)) {
        return true;
    }
    if ($bearer !== '') {
        if ($access_token !== '' && hash_equals($access_token, $bearer)) {
            return true;
        }
        if ($admin_token !== '' && hash_equals($admin_token, $bearer)) {
            return true;
        }
    }
    return false;
}

/**
 * Validate request method, origin, and tokens for access/admin usage.
 */
function authorize_any_token_request(string $access_token, string $admin_token): array
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
    if ($access_token === '' && $admin_token === '') {
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

    if (!is_valid_token($access_token, $admin_token, $provided_access, $provided_admin, $bearer)) {
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
function require_any_token(string $access_token, string $admin_token): void
{
    $auth = authorize_any_token_request($access_token, $admin_token);
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
