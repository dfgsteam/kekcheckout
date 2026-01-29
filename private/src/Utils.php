<?php
declare(strict_types=1);

namespace KekCheckout;

class Utils
{
    public static function slugify(string $value, int $maxLength = 40): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            return '';
        }
        return substr($slug, 0, $maxLength);
    }

    public static function sanitizeString(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return mb_substr($value, 0, $maxLength);
    }

    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
                return;
            }
        }
    }

    public static function readJsonBody(): array
    {
        $raw = (string)file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
