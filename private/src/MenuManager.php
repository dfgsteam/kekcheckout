<?php
declare(strict_types=1);

namespace KekCheckout;

class MenuManager
{
    private string $categoriesPath;
    private string $itemsPath;

    public function __construct(string $categoriesPath, string $itemsPath)
    {
        $this->categoriesPath = $categoriesPath;
        $this->itemsPath = $itemsPath;
    }

    public function normalizeLabel(string $value, int $max): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        if ($value === '') {
            return '';
        }
        return substr($value, 0, $max);
    }

    public function slugifyId(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value;
    }

    public function normalizePrice(string $value): string
    {
        $value = trim($value);
        $value = str_replace(',', '.', $value);
        $number = (float)$value;
        if ($number < 0) {
            $number = 0;
        }
        return number_format($number, 2, '.', '');
    }

    public function normalizeList($value, int $maxItemLen, int $maxItems): array
    {
        $items = [];
        if (is_string($value)) {
            $items = explode(',', $value);
        } elseif (is_array($value)) {
            $items = $value;
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            $item = preg_replace('/\s+/', ' ', $item) ?? $item;
            if ($item === '') {
                continue;
            }
            $result[] = substr($item, 0, $maxItemLen);
            if (count($result) >= $maxItems) {
                break;
            }
        }
        return $result;
    }

    public function normalizeTags($value, int $maxItemLen, int $maxItems): array
    {
        $items = [];
        if (is_string($value)) {
            $items = explode(',', $value);
        } elseif (is_array($value)) {
            $items = $value;
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            $item = preg_replace('/\s+/', ' ', $item) ?? $item;
            if ($item === '') {
                continue;
            }
            $result[] = substr($item, 0, $maxItemLen);
            if (count($result) >= $maxItems) {
                break;
            }
        }
        return $result;
    }

    public function loadJsonList(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function saveJsonList(string $path, array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($path, $json, LOCK_EX) !== false;
    }

    public function ensureSeed(): void
    {
        if (!is_file($this->categoriesPath)) {
            $this->saveJsonList($this->categoriesPath, [
                ['id' => 'drinks', 'name' => 'Getraenke', 'active' => true],
                ['id' => 'food', 'name' => 'Essen', 'active' => true],
            ]);
        }
        if (!is_file($this->itemsPath)) {
            $this->saveJsonList($this->itemsPath, [
                [
                    'id' => 'cola-03',
                    'category_id' => 'drinks',
                    'name' => 'Cola 0.3l',
                    'price' => '2.50',
                    'active' => true,
                ],
                [
                    'id' => 'bratwurst',
                    'category_id' => 'food',
                    'name' => 'Bratwurst',
                    'price' => '3.50',
                    'active' => true,
                ],
            ]);
        }
    }

    public function getMenu(): array
    {
        return [
            'categories' => $this->loadJsonList($this->categoriesPath),
            'items' => $this->loadJsonList($this->itemsPath),
        ];
    }

    public function addCategory(string $name, bool $active): array
    {
        $name = $this->normalizeLabel($name, 40);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Name empty'];
        }
        $categories = $this->loadJsonList($this->categoriesPath);
        $baseId = $this->slugifyId($name);
        $id = $baseId;
        $suffix = 2;
        $ids = array_map(fn($c) => (string)($c['id'] ?? ''), $categories);
        while (in_array($id, $ids, true)) {
            $id = $baseId . '-' . $suffix;
            $suffix++;
        }
        $categories[] = ['id' => $id, 'name' => $name, 'active' => $active];
        if (!$this->saveJsonList($this->categoriesPath, $categories)) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public function addItem(
        string $categoryId,
        string $name,
        string $price,
        $ingredients,
        $tags,
        string $preparation,
        bool $active
    ): array {
        $name = $this->normalizeLabel($name, 60);
        if ($name === '' || $categoryId === '') {
            return ['ok' => false, 'error' => 'Missing data'];
        }
        $items = $this->loadJsonList($this->itemsPath);
        $baseId = $this->slugifyId($name);
        $id = $baseId;
        $suffix = 2;
        $ids = array_map(fn($i) => (string)($i['id'] ?? ''), $items);
        while (in_array($id, $ids, true)) {
            $id = $baseId . '-' . $suffix;
            $suffix++;
        }
        $items[] = [
            'id' => $id,
            'category_id' => $categoryId,
            'name' => $name,
            'price' => $this->normalizePrice($price),
            'ingredients' => $this->normalizeList($ingredients, 40, 20),
            'tags' => $this->normalizeTags($tags, 20, 10),
            'preparation' => $this->normalizeLabel($preparation, 200),
            'active' => $active,
        ];
        if (!$this->saveJsonList($this->itemsPath, $items)) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public function updateCategory(string $id, string $name, bool $active): array
    {
        $name = $this->normalizeLabel($name, 40);
        if ($id === '' || $name === '') {
            return ['ok' => false, 'error' => 'Missing data'];
        }
        $categories = $this->loadJsonList($this->categoriesPath);
        $found = false;
        foreach ($categories as &$c) {
            if ((string)($c['id'] ?? '') === $id) {
                $c['name'] = $name;
                $c['active'] = $active;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['ok' => false, 'error' => 'Not found'];
        }
        if (!$this->saveJsonList($this->categoriesPath, $categories)) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        return ['ok' => true];
    }

    public function updateItem(
        string $id,
        string $name,
        string $price,
        $ingredients,
        $tags,
        string $preparation,
        bool $active
    ): array {
        $name = $this->normalizeLabel($name, 60);
        if ($id === '' || $name === '') {
            return ['ok' => false, 'error' => 'Missing data'];
        }
        $items = $this->loadJsonList($this->itemsPath);
        $found = false;
        foreach ($items as &$i) {
            if ((string)($i['id'] ?? '') === $id) {
                $i['name'] = $name;
                $i['price'] = $this->normalizePrice($price);
                $i['ingredients'] = $this->normalizeList($ingredients, 40, 20);
                $i['tags'] = $this->normalizeTags($tags, 20, 10);
                $i['preparation'] = $this->normalizeLabel($preparation, 200);
                $i['active'] = $active;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['ok' => false, 'error' => 'Not found'];
        }
        if (!$this->saveJsonList($this->itemsPath, $items)) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        return ['ok' => true];
    }

    public function deleteCategory(string $id): array
    {
        if ($id === '') {
            return ['ok' => false, 'error' => 'Missing ID'];
        }
        $categories = $this->loadJsonList($this->categoriesPath);
        $newCategories = array_filter($categories, fn($c) => (string)($c['id'] ?? '') !== $id);
        if (count($newCategories) === count($categories)) {
            return ['ok' => false, 'error' => 'Not found'];
        }
        if (!$this->saveJsonList($this->categoriesPath, array_values($newCategories))) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        // Also delete items in this category
        $items = $this->loadJsonList($this->itemsPath);
        $newItems = array_filter($items, fn($i) => (string)($i['category_id'] ?? '') !== $id);
        if (count($newItems) !== count($items)) {
            $this->saveJsonList($this->itemsPath, array_values($newItems));
        }
        return ['ok' => true];
    }

    public function deleteItem(string $id): array
    {
        if ($id === '') {
            return ['ok' => false, 'error' => 'Missing ID'];
        }
        $items = $this->loadJsonList($this->itemsPath);
        $newItems = array_filter($items, fn($i) => (string)($i['id'] ?? '') !== $id);
        if (count($newItems) === count($items)) {
            return ['ok' => false, 'error' => 'Not found'];
        }
        if (!$this->saveJsonList($this->itemsPath, array_values($newItems))) {
            return ['ok' => false, 'error' => 'Save failed'];
        }
        return ['ok' => true];
    }

    public function buildDisplayCategories(): array
    {
        $menu = $this->getMenu();
        $categories = [];
        foreach ($menu['categories'] as $cat) {
            if (!is_array($cat) || empty($cat['active'])) {
                continue;
            }
            $catId = (string)($cat['id'] ?? '');
            if ($catId === '') {
                continue;
            }
            $categories[$catId] = $cat;
            $categories[$catId]['items'] = [];
        }
        foreach ($menu['items'] as $item) {
            if (!is_array($item) || empty($item['active'])) {
                continue;
            }
            $catId = (string)($item['category_id'] ?? '');
            if (isset($categories[$catId])) {
                $categories[$catId]['items'][] = $item;
            }
        }
        return array_values(array_filter($categories, fn($c) => !empty($c['items'])));
    }
}
