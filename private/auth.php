<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KekCheckout\Auth;

/**
 * @deprecated Use KekCheckout\Auth::getCsrfToken()
 */
function get_csrf_token(): string
{
    $auth = new Auth('', '', '');
    return $auth->getCsrfToken();
}

/**
 * @deprecated Use KekCheckout\Auth::verifyCsrfToken()
 */
function verify_csrf_token(?string $token): bool
{
    $auth = new Auth('', '', '');
    return $auth->verifyCsrfToken($token);
}

/**
 * @deprecated Use KekCheckout\Auth::loadAdminToken()
 */
function load_token(string $env_name, string $path): string
{
    $auth = new Auth('', '', $path);
    return $auth->loadAdminToken();
}

/**
 * @deprecated Use KekCheckout\Auth::normalizeAccessLabel()
 */
function normalize_access_label(string $value, int $max): string
{
    $auth = new Auth('', '', '');
    return $auth->normalizeAccessLabel($value, $max);
}

/**
 * @deprecated Use KekCheckout\Auth::normalizeAccessTokenValue()
 */
function normalize_access_token_value(string $value, int $max): string
{
    $auth = new Auth('', '', '');
    return $auth->normalizeAccessTokenValue($value, $max);
}

/**
 * @deprecated Use KekCheckout\Auth::slugifyId()
 */
function access_slugify_id(string $value): string
{
    $auth = new Auth('', '', '');
    return $auth->slugifyId($value);
}

/**
 * @deprecated Use KekCheckout\Auth::loadAccessTokens()
 */
function load_access_tokens(string $path, string $legacy_path = ''): array
{
    $auth = new Auth($path, $legacy_path, '');
    return $auth->loadAccessTokens();
}

/**
 * @deprecated Use KekCheckout\Auth::saveAccessTokens()
 */
function save_access_tokens(string $path, array $tokens): bool
{
    $auth = new Auth($path, '', '');
    return $auth->saveAccessTokens($tokens);
}

/**
 * @deprecated
 */
function access_tokens_empty($access_tokens): bool
{
    if (is_array($access_tokens)) {
        return count($access_tokens) === 0;
    }
    return trim((string)$access_tokens) === '';
}

/**
 * @deprecated
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
 * @deprecated
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
 * @deprecated Use KekCheckout\Auth::getBearerToken()
 */
function get_bearer_token(): string
{
    $auth = new Auth('', '', '');
    return $auth->getBearerToken();
}

/**
 * @deprecated
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
 * @deprecated
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
 * @deprecated
 */
function require_any_token($access_tokens, string $admin_token): void
{
    $auth = authorize_any_token_request($access_tokens, $admin_token);
    if (!$auth['ok']) {
        send_json_error($auth['status'], $auth['message']);
    }
}

/**
 * @deprecated
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
