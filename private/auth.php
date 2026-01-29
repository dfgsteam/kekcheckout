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
    $auth = new Auth('', '', '');
    return $auth->isSameOrigin();
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
    $auth = new Auth('', '', '');
    $tokens = is_array($access_tokens) ? $access_tokens : [];
    
    // Manual check for compatibility with legacy single token string
    $candidates = array_filter([$provided_access, $provided_admin, $bearer], 'strlen');
    foreach ($candidates as $candidate) {
        if (is_array($access_tokens)) {
            foreach ($access_tokens as $entry) {
                if (is_array($entry) && !empty($entry['active']) && hash_equals((string)($entry['token'] ?? ''), $candidate)) {
                    return true;
                }
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
    }
    return false;
}

/**
 * @deprecated
 */
function authorize_any_token_request($access_tokens, string $admin_token): array
{
    $auth = new Auth('', '', '');
    $tokens = is_array($access_tokens) ? $access_tokens : [];
    
    // For legacy single token string, we handle it manually or wrap it
    if (!is_array($access_tokens)) {
        $token = trim((string)$access_tokens);
        if ($token !== '') {
            $tokens = [['id' => 'default', 'token' => $token, 'active' => true]];
        }
    }

    return $auth->authorizeAnyTokenRequest($tokens, $admin_token);
}

/**
 * @deprecated
 */
function require_any_token($access_tokens, string $admin_token): void
{
    $auth = new Auth('', '', '');
    $tokens = is_array($access_tokens) ? $access_tokens : [];
    if (!is_array($access_tokens)) {
        $token = trim((string)$access_tokens);
        if ($token !== '') {
            $tokens = [['id' => 'default', 'token' => $token, 'active' => true]];
        }
    }
    $auth->requireAnyToken($tokens, $admin_token);
}

/**
 * @deprecated
 */
function require_admin_token(string $admin_token): void
{
    $auth = new Auth('', '', '');
    $auth->requireAdminToken($admin_token);
}
