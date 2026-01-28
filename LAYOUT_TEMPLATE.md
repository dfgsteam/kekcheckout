# Layout-Template Anleitung

Diese Anleitung beschreibt, wie du das gemeinsame Layout-Template (`private/layout.php`) fuer weitere Apps/Seiten nutzt.

## Ziel
- Einheitliches Look & Feel fuer alle Apps.
- Zentraler HTML-Head (Fonts, Bootstrap, Icons, Styles).
- Einheitliches Settings-Modal (Theme/Accessibility/Language).
- Reduzierte Duplikate in den App-Dateien.

## Grundprinzip
Jede Seite erzeugt:
- `header` (Header-HTML)
- `content` (Seiteninhalt)
- optional `footer`, `modals`, `scripts`, `inline_scripts`

Dann wird `render_layout([...])` aufgerufen.

## Minimalbeispiel
```php
<?php
require_once __DIR__ . '/private/layout.php';

$header = <<<HTML
<header class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
  <div>
    <div class="text-uppercase text-primary small fw-semibold mb-2">My App</div>
    <h1 class="display-6 fw-semibold mb-2">Meine App</h1>
    <p class="text-secondary mb-0">Kurzbeschreibung.</p>
  </div>
</header>
HTML;

ob_start();
?>
<section class="card shadow-sm border-0">
  <div class="card-body">
    <h2 class="h5">Inhalt</h2>
    <p>Hallo Welt</p>
  </div>
</section>
<?php
$content = ob_get_clean();

render_layout([
    'title' => 'Meine App',
    'header' => $header,
    'content' => $content,
    'scripts' => ['assets/app.js'],
]);
```

## Optionen von render_layout
- `title`: Seitentitel (Text im `<title>`).
- `title_i18n`: data-i18n Key fuer den Titel (Standard: `title.index`).
- `description`: Meta-Description.
- `author`: Meta-Author.
- `app_name`: Meta application-name.
- `og_title`, `og_description`, `og_image`: OpenGraph Daten.
- `theme_color`: Theme-Color.
- `manifest`: Pfad zur Manifest-Datei. Leerer String deaktiviert die Ausgabe.
- `favicon`: Pfad zum Favicon. Leerer String deaktiviert die Ausgabe.
- `lang`: HTML-Lang (Standard: `de`).
- `body_class`: Body-Klasse.
- `main_class`: Main-Container-Klassen.
- `header`: HTML fuer Header.
- `content`: HTML fuer den Content.
- `footer`: HTML fuer Footer.
- `modals`: Zusatz-Modals (z. B. Access-Modal).
- `head_extra`: Zusaetzliche `<head>`-Tags (z. B. Robots-Tag).
- `include_settings`: `true|false` (Settings-Modal + settings.js).
- `inline_scripts`: Array mit Inline-JS (Strings, ohne `<script>` Tags).
- `scripts`: Array mit JS-Dateien (Strings, inkl. externen URLs).

## Typische Pattern

### 1) Inline-Variablen setzen
```php
$inline_scripts = [
  "window.MY_APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . ";",
];

render_layout([
  // ...
  'inline_scripts' => $inline_scripts,
]);
```

### 2) Settings-Modal deaktivieren
```php
render_layout([
  // ...
  'include_settings' => false,
]);
```

### 3) Fullscreen/Display-Layout
```php
render_layout([
  // ...
  'main_class' => 'container-fluid py-4 py-lg-5 px-3 px-lg-5',
]);
```

### 4) Robots noindex fuer Admin/Analyse
```php
render_layout([
  // ...
  'head_extra' => '<meta name="robots" content="noindex, nofollow">',
  'manifest' => '',
]);
```

## Wo das Template genutzt wird
- `index.php`
- `display.php`
- `analysis.php`
- `admin.php`

## Pflegehinweis
Wenn du das allgemeine Design aendern willst, aendere **nur**:
- `assets/styles.css`
- `assets/theme.js`
- `assets/i18n.js`
- optional `private/layout.php` (Layout-Struktur)

Damit sehen alle Apps direkt gleich aus.
