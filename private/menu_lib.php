<?php
declare(strict_types=1);

/**
 * Normalize a simple label string.
 */
function menu_normalize_label(string $value, int $max): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    if ($value === '') {
        return '';
    }
    return substr($value, 0, $max);
}

/**
 * Create a simple ASCII id from a label.
 */
function menu_slugify_id(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');
    return $value;
}

/**
 * Normalize a price string to 2 decimals.
 */
function menu_normalize_price(string $value): string
{
    $value = trim($value);
    $value = str_replace(',', '.', $value);
    $number = (float)$value;
    if ($number < 0) {
        $number = 0;
    }
    return number_format($number, 2, '.', '');
}

/**
 * Normalize a comma-separated list (or array) into trimmed items.
 */
function menu_normalize_list($value, int $max_item_len, int $max_items): array
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
        $result[] = substr($item, 0, $max_item_len);
        if (count($result) >= $max_items) {
            break;
        }
    }
    return $result;
}

/**
 * Normalize tags to simple ASCII slugs.
 */
function menu_normalize_tags($value, int $max_item_len, int $max_items): array
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
        $item = menu_slugify_id(trim($item));
        if ($item === '') {
            continue;
        }
        $result[] = substr($item, 0, $max_item_len);
        if (count($result) >= $max_items) {
            break;
        }
    }
    return $result;
}

/**
 * Load a JSON list from disk.
 */
function menu_load_json_list(string $path): array
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

/**
 * Persist a JSON list to disk.
 */
function menu_save_json_list(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }
    return file_put_contents($path, $payload, LOCK_EX) !== false;
}

/**
 * Ensure menu JSON files exist with example data.
 */
function menu_ensure_seed(string $categories_path, string $items_path): void
{
    $current_categories = menu_load_json_list($categories_path);
    $current_items = menu_load_json_list($items_path);
    $has_categories = count($current_categories) > 0;
    $has_items = count($current_items) > 0;
    $categories = [
        ['id' => 'kaffee', 'name' => 'Kaffee', 'active' => true],
        ['id' => 'snacks', 'name' => 'Snacks', 'active' => true],
        ['id' => 'getraenke', 'name' => 'Getraenke', 'active' => true],
    ];
    $items = [
        ['id' => 'classic-shot', 'category_id' => 'kaffee', 'name' => 'Classic Shot', 'price' => '2.50', 'ingredients' => ['Kaffee'], 'tags' => ['kaffee'], 'preparation' => 'Frisch gebrueht', 'active' => true],
        ['id' => 'hafer-latte', 'category_id' => 'kaffee', 'name' => 'Hafer Latte', 'price' => '3.80', 'ingredients' => ['Espresso', 'Haferdrink'], 'tags' => ['kaffee'], 'preparation' => 'Mit Milchschaum serviert', 'active' => true],
        ['id' => 'kaffee-des-tages', 'category_id' => 'kaffee', 'name' => 'Kaffee des Tages', 'price' => '2.90', 'ingredients' => ['Kaffee'], 'tags' => ['kaffee'], 'preparation' => 'Frisch gebrueht', 'active' => true],
        ['id' => 'butter-croissant', 'category_id' => 'snacks', 'name' => 'Butter Croissant', 'price' => '2.20', 'ingredients' => ['Teig', 'Butter'], 'tags' => ['snack'], 'preparation' => 'Frisch aufgebacken', 'active' => true],
        ['id' => 'schoko-cookie', 'category_id' => 'snacks', 'name' => 'Schoko Cookie', 'price' => '1.90', 'ingredients' => ['Teig', 'Schoko'], 'tags' => ['snack'], 'preparation' => 'Serviert bei Raumtemperatur', 'active' => true],
        ['id' => 'kaese-toast', 'category_id' => 'snacks', 'name' => 'Kaese Toast', 'price' => '3.20', 'ingredients' => ['Toast', 'Kaese'], 'tags' => ['snack'], 'preparation' => 'Kurz getoastet', 'active' => true],
        ['id' => 'iced-tea', 'category_id' => 'getraenke', 'name' => 'Iced Tea', 'price' => '2.60', 'ingredients' => ['Tee', 'Zitrone', 'Eis'], 'tags' => ['tee'], 'preparation' => 'Gekuehlt serviert', 'active' => true],
        ['id' => 'mineralwasser', 'category_id' => 'getraenke', 'name' => 'Mineralwasser', 'price' => '1.50', 'ingredients' => ['Mineralwasser'], 'tags' => ['wasser'], 'preparation' => 'Gekuehlt serviert', 'active' => true],
        ['id' => 'apfelsaft', 'category_id' => 'getraenke', 'name' => 'Apfelsaft', 'price' => '2.10', 'ingredients' => ['Apfelsaft'], 'tags' => ['saft'], 'preparation' => 'Gekuehlt serviert', 'active' => true],
    ];
    if (!$has_categories) {
        menu_save_json_list($categories_path, $categories);
    }
    if (!$has_items) {
        menu_save_json_list($items_path, $items);
    }
}

/**
 * Load menu data (categories + items).
 */
function menu_get_menu(string $categories_path, string $items_path): array
{
    menu_ensure_seed($categories_path, $items_path);
    return [
        'categories' => menu_load_json_list($categories_path),
        'items' => menu_load_json_list($items_path),
    ];
}

/**
 * Add a category and persist.
 */
function menu_add_category(string $categories_path, string $items_path, string $name, bool $active): array
{
    menu_ensure_seed($categories_path, $items_path);
    $label = menu_normalize_label($name, 60);
    if ($label === '') {
        return ['ok' => false, 'error' => 'Name missing'];
    }
    $categories = menu_load_json_list($categories_path);
    $base_id = menu_slugify_id($label);
    if ($base_id === '') {
        $base_id = 'category';
    }
    $id = $base_id;
    $counter = 2;
    $ids = array_map(fn($c) => (string)($c['id'] ?? ''), $categories);
    while (in_array($id, $ids, true)) {
        $id = $base_id . '-' . $counter;
        $counter++;
    }
    $category = [
        'id' => $id,
        'name' => $label,
        'active' => $active,
    ];
    $categories[] = $category;
    if (!menu_save_json_list($categories_path, $categories)) {
        return ['ok' => false, 'error' => 'Save failed'];
    }
    return ['ok' => true, 'category' => $category];
}

/**
 * Add an item and persist.
 */
function menu_add_item(
    string $categories_path,
    string $items_path,
    string $category_id,
    string $name,
    string $price,
    $ingredients,
    $tags,
    string $preparation,
    bool $active
): array {
    menu_ensure_seed($categories_path, $items_path);
    $label = menu_normalize_label($name, 80);
    $price_value = menu_normalize_price($price);
    $ingredients_list = menu_normalize_list($ingredients, 40, 12);
    $tags_list = menu_normalize_tags($tags, 24, 8);
    $prep_value = menu_normalize_label($preparation, 160);
    if ($category_id === '' || $label === '') {
        return ['ok' => false, 'error' => 'Missing data'];
    }
    $categories = menu_load_json_list($categories_path);
    $items = menu_load_json_list($items_path);
    $category_ids = array_map(fn($c) => (string)($c['id'] ?? ''), $categories);
    if (!in_array($category_id, $category_ids, true)) {
        return ['ok' => false, 'error' => 'Category not found'];
    }
    $base_id = menu_slugify_id($label);
    if ($base_id === '') {
        $base_id = 'item';
    }
    $ids = array_map(fn($i) => (string)($i['id'] ?? ''), $items);
    $id = $base_id;
    $counter = 2;
    while (in_array($id, $ids, true)) {
        $id = $base_id . '-' . $counter;
        $counter++;
    }
    $item = [
        'id' => $id,
        'category_id' => $category_id,
        'name' => $label,
        'price' => $price_value,
        'ingredients' => $ingredients_list,
        'tags' => $tags_list,
        'preparation' => $prep_value,
        'active' => $active,
    ];
    $items[] = $item;
    if (!menu_save_json_list($items_path, $items)) {
        return ['ok' => false, 'error' => 'Save failed'];
    }
    return ['ok' => true, 'item' => $item];
}

/**
 * Update a category and persist.
 */
function menu_update_category(string $categories_path, string $items_path, string $id, string $name, bool $active): array
{
    menu_ensure_seed($categories_path, $items_path);
    $label = menu_normalize_label($name, 60);
    if ($id === '' || $label === '') {
        return ['ok' => false, 'error' => 'Missing data'];
    }
    $categories = menu_load_json_list($categories_path);
    $found = false;
    foreach ($categories as $index => $category) {
        if (($category['id'] ?? '') === $id) {
            $categories[$index]['name'] = $label;
            $categories[$index]['active'] = $active;
            $found = true;
            break;
        }
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'Category not found'];
    }
    if (!menu_save_json_list($categories_path, $categories)) {
        return ['ok' => false, 'error' => 'Save failed'];
    }
    return ['ok' => true];
}

/**
 * Update an item and persist.
 */
function menu_update_item(
    string $items_path,
    string $id,
    string $name,
    string $price,
    $ingredients,
    $tags,
    string $preparation,
    bool $active
): array
{
    $label = menu_normalize_label($name, 80);
    $price_value = menu_normalize_price($price);
    $ingredients_list = menu_normalize_list($ingredients, 40, 12);
    $tags_list = menu_normalize_tags($tags, 24, 8);
    $prep_value = menu_normalize_label($preparation, 160);
    if ($id === '' || $label === '') {
        return ['ok' => false, 'error' => 'Missing data'];
    }
    $items = menu_load_json_list($items_path);
    $found = false;
    foreach ($items as $index => $item) {
        if (($item['id'] ?? '') === $id) {
            $items[$index]['name'] = $label;
            $items[$index]['price'] = $price_value;
            $items[$index]['ingredients'] = $ingredients_list;
            $items[$index]['tags'] = $tags_list;
            $items[$index]['preparation'] = $prep_value;
            $items[$index]['active'] = $active;
            $found = true;
            break;
        }
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'Item not found'];
    }
    if (!menu_save_json_list($items_path, $items)) {
        return ['ok' => false, 'error' => 'Save failed'];
    }
    return ['ok' => true];
}

/**
 * Build display categories for the product list.
 */
function menu_build_display_categories(string $categories_path, string $items_path): array
{
    $menu = menu_get_menu($categories_path, $items_path);
    $items_by_category = [];
    foreach ($menu['items'] as $item) {
        if (!is_array($item) || empty($item['active'])) {
            continue;
        }
        $category_id = (string)($item['category_id'] ?? '');
        if ($category_id === '') {
            continue;
        }
        if (!isset($items_by_category[$category_id])) {
            $items_by_category[$category_id] = [];
        }
        $ingredients = [];
        if (isset($item['ingredients'])) {
            $ingredients = menu_normalize_list($item['ingredients'], 40, 12);
        }
        $tags = [];
        if (isset($item['tags'])) {
            $tags = menu_normalize_tags($item['tags'], 24, 8);
        }
        $items_by_category[$category_id][] = [
            'id' => (string)($item['id'] ?? ''),
            'category_id' => $category_id,
            'group' => '',
            'name' => (string)($item['name'] ?? ''),
            'price' => (string)($item['price'] ?? '0.00'),
            'ingredients' => $ingredients,
            'tags' => $tags,
            'preparation' => (string)($item['preparation'] ?? ''),
        ];
    }

    $categories = [];
    foreach ($menu['categories'] as $category) {
        if (!is_array($category) || empty($category['active'])) {
            continue;
        }
        $category_id = (string)($category['id'] ?? '');
        $items = $items_by_category[$category_id] ?? [];
        if (!$items) {
            continue;
        }
        $categories[] = [
            'id' => $category_id,
            'label' => (string)($category['name'] ?? ''),
            'items' => $items,
        ];
    }
    return $categories;
}
