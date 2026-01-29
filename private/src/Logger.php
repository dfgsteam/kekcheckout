<?php
declare(strict_types=1);

namespace KekCheckout;

class Logger
{
    private string $path;
    private int $maxLines;

    public function __construct(string $path, int $maxLines = 200)
    {
        $this->path = $path;
        $this->maxLines = $maxLines;
    }

    public function log(string $action, int $status, array $extra = []): void
    {
        if ($this->path === '') {
            return;
        }

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

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
                return;
            }
        }

        $fp = @fopen($this->path, 'c+');
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
        if (count($lines) > $this->maxLines) {
            $lines = array_slice($lines, -$this->maxLines);
        }
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, implode("\n", $lines) . "\n");
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
