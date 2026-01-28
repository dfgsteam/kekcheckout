<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$app_config = [];
$config_path = __DIR__ . '/config.php';
if (is_file($config_path)) {
    $loaded = require $config_path;
    if (is_array($loaded)) {
        $app_config = $loaded;
    }
}

/**
 * Shared layout template for Kekcounter-style apps.
 *
 * Usage:
 *   require_once __DIR__ . '/layout.php';
 *   ob_start();
 *   // echo page content here...
 *   $content = ob_get_clean();
 *   render_layout([
 *     'title' => 'My App',
 *     'header' => $header_html,
 *     'content' => $content,
 *     'scripts' => ['assets/app.js'],
 *   ]);
 */
function render_layout(array $options): void
{
    global $app_config;

    $lang = (string)($options['lang'] ?? 'de');
    $title = (string)($options['title'] ?? 'Kek-Counter');
    $description = (string)($options['description'] ?? ($app_config['description'] ?? ''));
    $author = (string)($options['author'] ?? ($app_config['author'] ?? ''));
    $app_name = (string)($options['app_name'] ?? ($app_config['app_name'] ?? ''));
    $og_title = (string)($options['og_title'] ?? $title);
    $og_description = (string)($options['og_description'] ?? $description);
    $og_image = (string)($options['og_image'] ?? ($app_config['og_image'] ?? 'assets/logo.png'));
    $theme_color = (string)($options['theme_color'] ?? ($app_config['theme_color'] ?? '#e6edf7'));
    $body_class = (string)($options['body_class'] ?? 'bg-body-tertiary');
    $main_class = (string)($options['main_class'] ?? 'container py-4 py-lg-5');
    $manifest = (string)($options['manifest'] ?? ($app_config['manifest'] ?? 'manifest.webmanifest'));
    $favicon = (string)($options['favicon'] ?? ($app_config['favicon'] ?? 'assets/logo.png'));
    $title_i18n = (string)($options['title_i18n'] ?? 'title.index');
    $header_html = (string)($options['header'] ?? '');
    $content_html = (string)($options['content'] ?? '');
    $footer_html = (string)($options['footer'] ?? '');
    $modals_html = (string)($options['modals'] ?? '');
    $head_extra = (string)($options['head_extra'] ?? '');
    $inline_scripts = $options['inline_scripts'] ?? [];
    $include_chart_js = (bool)($options['include_chart_js'] ?? false);
    $chart_js_src = (string)($options['chart_js_src'] ?? ($app_config['chart_js_src'] ?? ''));
    $scripts = $options['scripts'] ?? [];
    $include_settings = (bool)($options['include_settings'] ?? true);

    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safe_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $safe_author = htmlspecialchars($author, ENT_QUOTES, 'UTF-8');
    $safe_app_name = htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8');
    $safe_og_title = htmlspecialchars($og_title, ENT_QUOTES, 'UTF-8');
    $safe_og_description = htmlspecialchars($og_description, ENT_QUOTES, 'UTF-8');
    $safe_og_image = htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8');
    $safe_theme_color = htmlspecialchars($theme_color, ENT_QUOTES, 'UTF-8');
    $safe_manifest = htmlspecialchars($manifest, ENT_QUOTES, 'UTF-8');
    $safe_favicon = htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8');
    $safe_body_class = htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8');
    $safe_main_class = htmlspecialchars($main_class, ENT_QUOTES, 'UTF-8');
    $safe_lang = htmlspecialchars($lang, ENT_QUOTES, 'UTF-8');
    $safe_title_i18n = htmlspecialchars($title_i18n, ENT_QUOTES, 'UTF-8');

    $nonce = base64_encode(random_bytes(16));
    send_security_headers();
    send_csp_header($nonce);
    echo "<!doctype html>\n";
    echo "<html lang=\"{$safe_lang}\">\n";
    echo "  <head>\n";
    echo "    <meta charset=\"utf-8\">\n";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "    <title data-i18n=\"{$safe_title_i18n}\">{$safe_title}</title>\n";
    echo "    <meta name=\"description\" content=\"{$safe_description}\">\n";
    echo "    <meta name=\"author\" content=\"{$safe_author}\">\n";
    echo "    <meta name=\"application-name\" content=\"{$safe_app_name}\">\n";
    echo "    <meta property=\"og:title\" content=\"{$safe_og_title}\">\n";
    echo "    <meta property=\"og:description\" content=\"{$safe_og_description}\">\n";
    echo "    <meta property=\"og:type\" content=\"website\">\n";
    echo "    <meta property=\"og:locale\" content=\"de_DE\">\n";
    echo "    <meta property=\"og:image\" content=\"{$safe_og_image}\">\n";
    echo "    <meta name=\"twitter:card\" content=\"summary\">\n";
    echo "    <meta name=\"twitter:title\" content=\"{$safe_og_title}\">\n";
    echo "    <meta name=\"twitter:description\" content=\"{$safe_og_description}\">\n";
    echo "    <meta name=\"twitter:image\" content=\"{$safe_og_image}\">\n";
    echo "    <meta name=\"theme-color\" content=\"{$safe_theme_color}\">\n";
    echo "    <meta name=\"mobile-web-app-capable\" content=\"yes\">\n";
    echo "    <meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
    if ($manifest !== '') {
        echo "    <link rel=\"manifest\" href=\"{$safe_manifest}\">\n";
    }
    if ($favicon !== '') {
        echo "    <link rel=\"icon\" href=\"{$safe_favicon}\">\n";
        echo "    <link rel=\"apple-touch-icon\" href=\"{$safe_favicon}\">\n";
    }
    echo "    <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "    <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    echo "    <link href=\"https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap\" rel=\"stylesheet\">\n";
    echo "    <link\n";
    echo "      href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\"\n";
    echo "      rel=\"stylesheet\"\n";
    echo "      integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\"\n";
    echo "      crossorigin=\"anonymous\"\n";
    echo "    >\n";
    echo "    <link\n";
    echo "      href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\"\n";
    echo "      rel=\"stylesheet\"\n";
    echo "    >\n";
    echo "    <link rel=\"stylesheet\" href=\"assets/styles.css\">\n";
    if ($head_extra !== '') {
        echo "    {$head_extra}\n";
    }
    echo "  </head>\n";
    echo "  <body class=\"{$safe_body_class}\">\n";
    echo "    <main class=\"{$safe_main_class}\">\n";
    if ($header_html !== '') {
        echo $header_html . "\n";
    }
    echo $content_html . "\n";
    if ($footer_html !== '') {
        echo $footer_html . "\n";
    }
    echo "    </main>\n";
    if ($include_settings) {
        echo render_settings_modal();
    }
    if ($modals_html !== '') {
        echo $modals_html . "\n";
    }
    echo "    <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\" integrity=\"sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz\" crossorigin=\"anonymous\"></script>\n";
    if ($include_chart_js && $chart_js_src !== '') {
        $chart_src = htmlspecialchars($chart_js_src, ENT_QUOTES, 'UTF-8');
        echo "    <script src=\"{$chart_src}\"></script>\n";
    }
    echo "    <script src=\"assets/theme.js\"></script>\n";
    echo "    <script src=\"assets/i18n.js\"></script>\n";
    if ($include_settings) {
        echo "    <script src=\"assets/settings.js\"></script>\n";
    }
    if (is_string($inline_scripts)) {
        $inline_scripts = [$inline_scripts];
    }
    if (is_array($inline_scripts)) {
        foreach ($inline_scripts as $script) {
            $js = trim((string)$script);
            if ($js === '') {
                continue;
            }
            echo "    <script nonce=\"{$nonce}\">{$js}</script>\n";
        }
    }
    foreach ($scripts as $script) {
        $src = htmlspecialchars((string)$script, ENT_QUOTES, 'UTF-8');
        if ($src !== '') {
            echo "    <script src=\"{$src}\"></script>\n";
        }
    }
    echo "  </body>\n";
    echo "</html>\n";
}

function render_settings_modal(): string
{
    return <<<HTML
    <div class="modal fade" id="settingsDialog" tabindex="-1" aria-labelledby="settingsTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="settingsTitle" data-i18n="settings.title">Einstellungen</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" data-i18n-aria-label="common.close" aria-label="Schliessen"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label small text-secondary" data-i18n="settings.theme">
                <i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Theme
              </label>
              <div class="btn-group w-100" role="group" aria-label="Theme">
                <input type="radio" class="btn-check" name="settingsTheme" id="settingsThemeSystem" value="system" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsThemeSystem">
                  <i class="bi bi-circle-half me-1" aria-hidden="true"></i><span data-i18n="settings.theme.system">System</span>
                </label>
                <input type="radio" class="btn-check" name="settingsTheme" id="settingsThemeLight" value="light" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsThemeLight">
                  <i class="bi bi-brightness-high me-1" aria-hidden="true"></i><span data-i18n="settings.theme.light">Hell</span>
                </label>
                <input type="radio" class="btn-check" name="settingsTheme" id="settingsThemeDark" value="dark" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsThemeDark">
                  <i class="bi bi-moon-stars me-1" aria-hidden="true"></i><span data-i18n="settings.theme.dark">Dunkel</span>
                </label>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small text-secondary" data-i18n="settings.accessibility">
                <i class="bi bi-universal-access me-1" aria-hidden="true"></i>Barrierefreiheit
              </label>
              <div class="btn-group w-100" role="group" aria-label="Accessibility">
                <input type="radio" class="btn-check" name="settingsAccessibility" id="settingsAccessibilityStandard" value="standard" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsAccessibilityStandard">
                  <i class="bi bi-person me-1" aria-hidden="true"></i><span data-i18n="settings.accessibility.off">Standard</span>
                </label>
                <input type="radio" class="btn-check" name="settingsAccessibility" id="settingsAccessibilityHigh" value="high" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsAccessibilityHigh">
                  <i class="bi bi-universal-access me-1" aria-hidden="true"></i><span data-i18n="settings.accessibility.on">Barrierefrei</span>
                </label>
              </div>
            </div>
            <div>
              <label class="form-label small text-secondary" data-i18n="settings.language">
                <i class="bi bi-translate me-1" aria-hidden="true"></i>Sprache
              </label>
              <div class="btn-group w-100" role="group" aria-label="Sprache">
                <input type="radio" class="btn-check" name="settingsLanguage" id="settingsLanguageSystem" value="system" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsLanguageSystem">
                  <i class="bi bi-globe2 me-1" aria-hidden="true"></i><span data-i18n="language.system">System</span>
                </label>
                <input type="radio" class="btn-check" name="settingsLanguage" id="settingsLanguageDe" value="de" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsLanguageDe">
                  <i class="bi bi-flag me-1" aria-hidden="true"></i><span data-i18n="language.de">Deutsch</span>
                </label>
                <input type="radio" class="btn-check" name="settingsLanguage" id="settingsLanguageEn" value="en" autocomplete="off">
                <label class="btn btn-outline-secondary" for="settingsLanguageEn">
                  <i class="bi bi-flag-fill me-1" aria-hidden="true"></i><span data-i18n="language.en">Englisch</span>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
              <i class="bi bi-x-lg me-1" aria-hidden="true"></i><span data-i18n="common.close">Schliessen</span>
            </button>
          </div>
        </div>
      </div>
    </div>
HTML;
}
