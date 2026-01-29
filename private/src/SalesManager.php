<?php
declare(strict_types=1);

namespace KekCheckout;

class SalesManager
{
    private string $bookingCsvPath;

    public function __construct(string $bookingCsvPath)
    {
        $this->bookingCsvPath = $bookingCsvPath;
    }

    public function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function trim(string $value, int $max): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return substr($value, 0, $max);
    }

    public function normalizePrice($value): string
    {
        $value = str_replace(',', '.', (string)$value);
        $number = (float)$value;
        if ($number < 0) {
            $number = 0.0;
        }
        return number_format($number, 2, '.', '');
    }

    public function calcEarnings(string $type, string $price): string
    {
        if ($type === 'Verkauft') {
            return $this->normalizePrice($price);
        }
        return '0.00';
    }

    public function buildBooking(array $user, array $product, array $category, string $type): array
    {
        $price = (string)($product['price'] ?? '0.00');
        $earnings = $this->calcEarnings($type, $price);

        return [
            'id' => $this->newId(),
            'uhrzeit' => date('c'),
            'user_id' => (string)($user['id'] ?? ''),
            'user_name' => (string)($user['name'] ?? ''),
            'produkt_id' => (string)($product['id'] ?? ''),
            'produkt_name' => (string)($product['name'] ?? ''),
            'kategorie_id' => (string)($category['id'] ?? ''),
            'kategorie_name' => (string)($category['name'] ?? ''),
            'buchungstyp' => $type,
            'preis' => $this->normalizePrice($price),
            'einnahmen' => $earnings,
            'status' => 'OK',
            'storno_grund' => '',
            'storno_zeit' => '',
        ];
    }

    public function ensureCsv(): void
    {
        if (!is_file($this->bookingCsvPath)) {
            $header = [
                'id',
                'uhrzeit',
                'user_id',
                'user_name',
                'produkt_id',
                'produkt_name',
                'kategorie_id',
                'kategorie_name',
                'buchungstyp',
                'preis',
                'einnahmen',
                'status',
                'storno_grund',
                'storno_zeit'
            ];
            $fp = fopen($this->bookingCsvPath, 'w');
            if ($fp) {
                fputcsv($fp, $header, ",", "\"", "\\");
                fclose($fp);
            }
        }
    }

    public function appendBookingCsv(array $booking): bool
    {
        $this->ensureCsv();
        $fp = fopen($this->bookingCsvPath, 'a');
        if (!$fp) {
            return false;
        }
        flock($fp, LOCK_EX);
        $row = [
            $booking['id'] ?? '',
            $booking['uhrzeit'] ?? '',
            $booking['user_id'] ?? '',
            $booking['user_name'] ?? '',
            $booking['produkt_id'] ?? '',
            $booking['produkt_name'] ?? '',
            $booking['kategorie_id'] ?? '',
            $booking['kategorie_name'] ?? '',
            $booking['buchungstyp'] ?? '',
            $booking['preis'] ?? '0.00',
            $booking['einnahmen'] ?? '0.00',
            $booking['status'] ?? 'OK',
            $booking['storno_grund'] ?? '',
            $booking['storno_zeit'] ?? '',
        ];
        $result = fputcsv($fp, $row, ",", "\"", "\\");
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result !== false;
    }

    public function readCsv(): array
    {
        if (!is_file($this->bookingCsvPath)) {
            return [];
        }
        $rows = [];
        $fp = fopen($this->bookingCsvPath, 'r');
        if ($fp) {
            flock($fp, LOCK_SH);
            while (($row = fgetcsv($fp, 0, ",", "\"", "\\")) !== false) {
                $rows[] = $row;
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $rows;
    }

    public function writeCsv(array $rows): bool
    {
        $fp = fopen($this->bookingCsvPath, 'w');
        if (!$fp) {
            return false;
        }
        flock($fp, LOCK_EX);
        foreach ($rows as $row) {
            fputcsv($fp, $row, ",", "\"", "\\");
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public function stornoLastBookingCsv(
        string $userId,
        string $reason,
        int $maxAgeMinutes,
        int $maxBack
    ): array {
        $rows = $this->readCsv();
        if (count($rows) <= 1) {
            return ['ok' => false, 'error' => 'No bookings'];
        }

        $headers = array_shift($rows);
        $headerMap = array_flip($headers);

        $statusIdx = $headerMap['status'] ?? null;
        $userIdIdx = $headerMap['user_id'] ?? null;
        $timeIdx = $headerMap['uhrzeit'] ?? null;
        $reasonIdx = $headerMap['storno_grund'] ?? null;
        $stornoTimeIdx = $headerMap['storno_zeit'] ?? null;

        if ($statusIdx === null || $userIdIdx === null || $timeIdx === null) {
            return ['ok' => false, 'error' => 'Invalid CSV'];
        }

        $foundIndex = -1;
        $countBack = 0;
        $now = time();

        for ($i = count($rows) - 1; $i >= 0; $i--) {
            if ($countBack >= $maxBack) {
                break;
            }
            $row = $rows[$i];
            if (($row[$statusIdx] ?? '') === 'OK' && ($row[$userIdIdx] ?? '') === $userId) {
                $bookingTime = strtotime((string)($row[$timeIdx] ?? ''));
                if ($bookingTime > 0) {
                    $age = ($now - $bookingTime) / 60;
                    if ($age <= $maxAgeMinutes) {
                        $foundIndex = $i;
                        break;
                    }
                }
            }
            $countBack++;
        }

        if ($foundIndex === -1) {
            return ['ok' => false, 'error' => 'No stornable booking found'];
        }

        $rows[$foundIndex][$statusIdx] = 'STORNO';
        if ($reasonIdx !== null) {
            $rows[$foundIndex][$reasonIdx] = $this->trim($reason, 200);
        }
        if ($stornoTimeIdx !== null) {
            $rows[$foundIndex][$stornoTimeIdx] = date('c');
        }

        array_unshift($rows, $headers);
        if ($this->writeCsv($rows)) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => 'Save failed'];
    }
}
