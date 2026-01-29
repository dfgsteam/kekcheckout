<?php
declare(strict_types=1);

namespace KekCheckout;

class Auth
{
    private string $accessTokensPath;
    private string $legacyAccessTokenPath;
    private string $adminTokenPath;

    public function __construct(
        string $accessTokensPath,
        string $legacyAccessTokenPath,
        string $adminTokenPath
    ) {
        $this->accessTokensPath = $accessTokensPath;
        $this->legacyAccessTokenPath = $legacyAccessTokenPath;
        $this->adminTokenPath = $adminTokenPath;
    }

    public function getCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function verifyCsrfToken(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public function loadAdminToken(): string
    {
        $env = getenv('KEKCOUNTER_ADMIN_TOKEN');
        if ($env !== false && $env !== '') {
            return trim($env);
        }
        if (is_file($this->adminTokenPath)) {
            $token = trim((string)file_get_contents($this->adminTokenPath));
            if ($token !== '') {
                return $token;
            }
        }
        return '';
    }

    public function loadAccessTokens(): array
    {
        $tokens = [];
        if (is_file($this->accessTokensPath)) {
            $raw = file_get_contents($this->accessTokensPath);
            $data = json_decode((string)$raw, true);
            if (is_array($data)) {
                $tokens = $data;
            }
        } elseif ($this->legacyAccessTokenPath !== '' && is_file($this->legacyAccessTokenPath)) {
            $legacy = $this->normalizeAccessTokenValue((string)file_get_contents($this->legacyAccessTokenPath), 160);
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
            $name = $this->normalizeAccessLabel((string)($entry['name'] ?? ''), 40);
            $token = $this->normalizeAccessTokenValue((string)($entry['token'] ?? ''), 160);
            if ($token === '') {
                continue;
            }
            $id = (string)($entry['id'] ?? $this->slugifyId($name));
            if ($id === '' || in_array($id, $ids, true)) {
                $id = bin2hex(random_bytes(4));
            }
            $ids[] = $id;
            $normalized[] = [
                'id' => $id,
                'name' => $name === '' ? 'Key' : $name,
                'token' => $token,
                'active' => (bool)($entry['active'] ?? true),
            ];
        }
        return $normalized;
    }

    public function normalizeAccessLabel(string $value, int $max): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return substr($value, 0, $max);
    }

    public function normalizeAccessTokenValue(string $value, int $max): string
    {
        $value = trim($value);
        return substr($value, 0, $max);
    }

    public function slugifyId(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    public function saveAccessTokens(array $tokens): bool
    {
        $data = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->accessTokensPath, $data, LOCK_EX) !== false;
    }

    public function resolveUserIdentity(array $accessTokens, string $adminToken): array
    {
        $providedAccess = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
        $providedAdmin = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        $bearer = $this->getBearerToken();
        $candidates = array_filter([$providedAccess, $providedAdmin, $bearer], 'strlen');

        foreach ($candidates as $candidate) {
            foreach ($accessTokens as $entry) {
                if (!is_array($entry) || empty($entry['active'])) {
                    continue;
                }
                $token = (string)($entry['token'] ?? '');
                if ($token !== '' && hash_equals($token, $candidate)) {
                    return [
                        'id' => (string)($entry['id'] ?? 'user'),
                        'name' => (string)($entry['name'] ?? 'User'),
                    ];
                }
            }
            if ($adminToken !== '' && hash_equals($adminToken, $candidate)) {
                return ['id' => 'admin', 'name' => 'Admin'];
            }
        }
        return ['id' => 'unknown', 'name' => 'Unknown'];
    }

    public function getBearerToken(): string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return '';
    }

    public function isSameOrigin(): bool
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

    public function validateToken(string $candidate, array $accessTokens, string $adminToken): bool
    {
        if ($candidate === '') {
            return false;
        }
        foreach ($accessTokens as $entry) {
            if (!is_array($entry) || empty($entry['active'])) {
                continue;
            }
            $token = (string)($entry['token'] ?? '');
            if ($token !== '' && hash_equals($token, $candidate)) {
                return true;
            }
        }
        if ($adminToken !== '' && hash_equals($adminToken, $candidate)) {
            return true;
        }
        return false;
    }

    public function authorizeAnyTokenRequest(array $accessTokens, string $adminToken): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [
                'ok' => false,
                'status' => 405,
                'message' => 'Method not allowed',
            ];
        }
        if (!$this->isSameOrigin()) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Forbidden (Origin)',
            ];
        }
        $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if ($requested_with !== 'fetch') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Forbidden (Request)',
            ];
        }
        if (empty($accessTokens) && $adminToken === '') {
            return [
                'ok' => false,
                'status' => 503,
                'message' => 'Token not configured',
            ];
        }

        $providedAccess = $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? '';
        $providedAdmin = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        $bearer = $this->getBearerToken();

        $candidates = array_filter([$providedAccess, $providedAdmin, $bearer], 'strlen');
        foreach ($candidates as $candidate) {
            foreach ($accessTokens as $entry) {
                if (!is_array($entry) || empty($entry['active'])) {
                    continue;
                }
                $token = (string)($entry['token'] ?? '');
                if ($token !== '' && hash_equals($token, $candidate)) {
                    return ['ok' => true];
                }
            }
            if ($adminToken !== '' && hash_equals($adminToken, $candidate)) {
                return ['ok' => true];
            }
        }

        return [
            'ok' => false,
            'status' => 403,
            'message' => 'Forbidden (Invalid token)',
        ];
    }

    public function requireAnyToken(array $accessTokens, string $adminToken): void
    {
        $auth = $this->authorizeAnyTokenRequest($accessTokens, $adminToken);
        if (!$auth['ok']) {
            \send_json_error($auth['status'] ?? 403, $auth['message'] ?? 'Forbidden');
        }
    }

    public function requireAdminToken(string $adminToken): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            \send_json_error(405, 'Method not allowed');
        }
        if (!$this->isSameOrigin()) {
            \send_json_error(403, 'Forbidden (Origin)');
        }
        $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if ($requested_with !== 'fetch') {
            \send_json_error(403, 'Forbidden (Request)');
        }
        if ($adminToken === '') {
            \send_json_error(503, 'Admin token not configured');
        }

        $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
        if ($provided === '') {
            $provided = $this->getBearerToken();
        }
        if ($provided === '' || !hash_equals($adminToken, $provided)) {
            \send_json_error(403, 'Forbidden (Invalid admin token)');
        }
    }
}
