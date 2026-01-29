<?php
declare(strict_types=1);

namespace KekCheckout;

class Settings
{
    private array $settings = [];
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->load();
    }

    public function load(): void
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
            'tablet_type_reset' => 30,
        ];

        if (!is_file($this->path)) {
            $this->settings = $defaults;
            return;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            $this->settings = $defaults;
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->settings = $defaults;
            return;
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
        $this->settings = $settings;
    }

    public function loadEventName(string $path): string
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

    public function saveEventName(string $path, string $name): string
    {
        $clean = trim($name);
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = substr($clean, 0, 80);
        if ($clean === '') {
            if (is_file($path)) {
                @unlink($path);
            }
            return '';
        }
        file_put_contents($path, $clean, LOCK_EX);
        return $clean;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getAll(): array
    {
        return $this->settings;
    }

    public function save(array $payload): void
    {
        foreach ($this->settings as $key => $value) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $num = (int)$payload[$key];
                if ($num > 0) {
                    $this->settings[$key] = $num;
                }
            }
        }

        file_put_contents(
            $this->path,
            json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
