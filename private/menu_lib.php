<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KekCheckout\MenuManager;

/**
 * @deprecated Use KekCheckout\MenuManager::normalizeLabel()
 */
function menu_normalize_label(string $value, int $max): string
{
    $mgr = new MenuManager('', '');
    return $mgr->normalizeLabel($value, $max);
}

/**
 * @deprecated Use KekCheckout\MenuManager::slugifyId()
 */
function menu_slugify_id(string $value): string
{
    $mgr = new MenuManager('', '');
    return $mgr->slugifyId($value);
}

/**
 * @deprecated Use KekCheckout\MenuManager::normalizePrice()
 */
function menu_normalize_price(string $value): string
{
    $mgr = new MenuManager('', '');
    return $mgr->normalizePrice($value);
}

/**
 * @deprecated Use KekCheckout\MenuManager::normalizeList()
 */
function menu_normalize_list($value, int $max_item_len, int $max_items): array
{
    $mgr = new MenuManager('', '');
    return $mgr->normalizeList($value, $max_item_len, $max_items);
}

/**
 * @deprecated Use KekCheckout\MenuManager::normalizeTags()
 */
function menu_normalize_tags($value, int $max_item_len, int $max_items): array
{
    $mgr = new MenuManager('', '');
    return $mgr->normalizeTags($value, $max_item_len, $max_items);
}

/**
 * @deprecated Use KekCheckout\MenuManager::loadJsonList()
 */
function menu_load_json_list(string $path): array
{
    $mgr = new MenuManager('', '');
    return $mgr->loadJsonList($path);
}

/**
 * @deprecated Use KekCheckout\MenuManager::saveJsonList()
 */
function menu_save_json_list(string $path, array $data): bool
{
    $mgr = new MenuManager('', '');
    return $mgr->saveJsonList($path, $data);
}

/**
 * @deprecated Use KekCheckout\MenuManager::ensureSeed()
 */
function menu_ensure_seed(string $categories_path, string $items_path): void
{
    $mgr = new MenuManager($categories_path, $items_path);
    $mgr->ensureSeed();
}

/**
 * @deprecated Use KekCheckout\MenuManager::getMenu()
 */
function menu_get_menu(string $categories_path, string $items_path): array
{
    $mgr = new MenuManager($categories_path, $items_path);
    return $mgr->getMenu();
}

/**
 * @deprecated Use KekCheckout\MenuManager::addCategory()
 */
function menu_add_category(string $categories_path, string $items_path, string $name, bool $active): array
{
    $mgr = new MenuManager($categories_path, $items_path);
    return $mgr->addCategory($name, $active);
}

/**
 * @deprecated Use KekCheckout\MenuManager::addItem()
 */
function menu_add_item(string $categories_path, string $items_path, string $category_id, string $name, string $price, $ingredients, $tags, string $preparation, bool $active): array
{
    $mgr = new MenuManager($categories_path, $items_path);
    return $mgr->addItem($category_id, $name, $price, $ingredients, $tags, $preparation, $active);
}

/**
 * @deprecated Use KekCheckout\MenuManager::updateCategory()
 */
function menu_update_category(string $categories_path, string $items_path, string $id, string $name, bool $active): array
{
    $mgr = new MenuManager($categories_path, $items_path);
    return $mgr->updateCategory($id, $name, $active);
}

/**
 * @deprecated Use KekCheckout\MenuManager::updateItem()
 */
function menu_update_item(string $items_path, string $id, string $name, string $price, $ingredients, $tags, string $preparation, bool $active): array
{
    $mgr = new MenuManager('', $items_path);
    return $mgr->updateItem($id, $name, $price, $ingredients, $tags, $preparation, $active);
}

/**
 * @deprecated Use KekCheckout\MenuManager::buildDisplayCategories()
 */
function menu_build_display_categories(string $categories_path, string $items_path): array
{
    $mgr = new MenuManager($categories_path, $items_path);
    return $mgr->buildDisplayCategories();
}
