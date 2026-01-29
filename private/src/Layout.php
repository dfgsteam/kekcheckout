<?php
declare(strict_types=1);

namespace KekCheckout;

class Layout
{
    public function render(array $options): void
    {
        $title = $options['title'] ?? 'Kek-Counter';
        $app_name = $options['app_name'] ?? 'Kek-Counter';
        $description = $options['description'] ?? '';
        $og_image = $options['og_image'] ?? 'assets/logo.png';
        $theme_color = $options['theme_color'] ?? '#e6edf7';
        $manifest = $options['manifest'] ?? 'manifest.webmanifest';
        $favicon = $options['favicon'] ?? 'assets/logo.png';
        $chart_js_src = $options['chart_js_src'] ?? 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        $content = $options['content'] ?? '';
        $header = $options['header'] ?? '';
        $modals = $options['modals'] ?? '';
        $inline_scripts = $options['inline_scripts'] ?? [];
        $scripts = $options['scripts'] ?? [];
        $styles = $options['styles'] ?? [];
        $body_class = $options['body_class'] ?? 'bg-light';
        $header_extra = $options['header_extra'] ?? '';
        $footer_extra = $options['footer_extra'] ?? '';

        ?>
        <!DOCTYPE html>
        <html lang="de" data-bs-theme="auto">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
            <title><?= htmlspecialchars($title) ?></title>
            <meta name="description" content="<?= htmlspecialchars($description) ?>">
            <meta name="theme-color" content="<?= htmlspecialchars($theme_color) ?>">
            <link rel="manifest" href="<?= htmlspecialchars($manifest) ?>">
            <link rel="icon" href="<?= htmlspecialchars($favicon) ?>" type="image/png">
            <link rel="apple-touch-icon" href="<?= htmlspecialchars($favicon) ?>">
            <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
            <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
            <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
            <meta property="og:type" content="website">

            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
            <link href="assets/styles.css" rel="stylesheet">
            <?php foreach ($styles as $style): ?>
                <link href="<?= htmlspecialchars($style) ?>" rel="stylesheet">
            <?php endforeach; ?>
            <?= $header_extra ?>
        </head>
        <body class="<?= htmlspecialchars($body_class) ?>">
            <main class="container py-4">
                <?= $header ?>
                <?= $content ?>
            </main>
            <?= $modals ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            <script src="<?= htmlspecialchars($chart_js_src) ?>"></script>
            <script src="assets/theme.js"></script>
            <script src="assets/i18n.js"></script>
            <?php foreach ($scripts as $script): ?>
                <script src="<?= htmlspecialchars($script) ?>"></script>
            <?php endforeach; ?>
            <?php foreach ($inline_scripts as $code): ?>
                <script><?= $code ?></script>
            <?php endforeach; ?>
            <?= $footer_extra ?>
        </body>
        </html>
        <?php
    }

    public function renderSettingsModal(): string
    {
        ob_start();
        ?>
        <div class="modal fade" id="settingsDialog" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="settingsTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary" id="labelThreshold"></label>
                            <input type="number" id="settingThreshold" class="form-control form-control-lg border-2" min="1">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary" id="labelMaxPoints"></label>
                            <input type="number" id="settingMaxPoints" class="form-control form-control-lg border-2" min="1">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-secondary" id="labelChartMax"></label>
                            <input type="number" id="settingChartMax" class="form-control form-control-lg border-2" min="1">
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelWindowHours"></label>
                                <input type="number" id="settingWindowHours" class="form-control form-control-lg border-2" min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelTickMinutes"></label>
                                <input type="number" id="settingTickMinutes" class="form-control form-control-lg border-2" min="1">
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelStornoMinutes"></label>
                                <input type="number" id="settingStornoMinutes" class="form-control form-control-lg border-2" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-uppercase text-secondary" id="labelStornoBack"></label>
                                <input type="number" id="settingStornoBack" class="form-control form-control-lg border-2" min="0">
                            </div>
                        </div>
                        <div id="settingsStatus" class="alert d-none py-2 px-3 small"></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal" id="btnSettingsCancel"></button>
                        <button type="button" class="btn btn-primary fw-bold px-4" id="btnSettingsSave"></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderErrorModal(): string
    {
        ob_start();
        ?>
        <div class="modal fade" id="errorDialog" tabindex="-1" aria-hidden="true" style="z-index: 2000;">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center p-4">
                        <div class="mb-3 text-danger">
                            <i class="bi bi-exclamation-triangle-fill display-4"></i>
                        </div>
                        <h5 class="fw-bold mb-2" id="errorTitle"></h5>
                        <p class="text-secondary small mb-4" id="errorMessage"></p>
                        <button type="button" class="btn btn-primary w-100 fw-bold" data-bs-dismiss="modal" id="btnErrorOk">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
