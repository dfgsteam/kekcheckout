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
}
