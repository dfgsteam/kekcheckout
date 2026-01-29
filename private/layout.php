<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KekCheckout\Layout;

/**
 * @deprecated Use KekCheckout\Layout::render()
 */
function render_layout(array $options): void
{
    $layout = new Layout();
    $layout->render($options);
}

/**
 * @deprecated Use KekCheckout\Layout::renderSettingsModal()
 */
function render_settings_modal(): string
{
    $layout = new Layout();
    return $layout->renderSettingsModal();
}

/**
 * @deprecated Use KekCheckout\Layout::renderErrorModal()
 */
function render_error_modal(): string
{
    $layout = new Layout();
    return $layout->renderErrorModal();
}
