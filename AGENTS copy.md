# AGENTS.md

Projekt: Kekcounter (PHP + statische Assets)

## Ziele
- Einheitliches Layout/Styling ueber alle Seiten.
- Kleine, wartbare PHP-Dateien ohne duplizierten HTML-Head.
- Keine schweren Frameworks oder Build-Pipelines.

## Architektur
- PHP-Seiten: `index.php`, `display.php`, `analysis.php`, `admin.php`.
- Gemeinsames Layout: `private/layout.php` (render_layout).
- Styling/Theme: `assets/styles.css`, `assets/theme.js`.
- I18n: `assets/i18n.js`.
- Settings-Modal: vom Layout geliefert (optional deaktivierbar).

## Konventionen
- ASCII only in source files (keine Umlaute, keine Unicode-Symbole).
- Verwende das Layout-Template fuer neue Seiten.
- Seitemeta (title/description/og) immer ueber `render_layout` setzen.
- App-spezifische Modals in `modals` einhaengen, nicht in den Layout-Head.
- Externe JS/CSS nur wenn unbedingt noetig.

## Layout-Template Nutzung
Siehe `LAYOUT_TEMPLATE.md`.

## Dateien, die Laufzeitdaten enthalten
- `private/visitors.csv`
- `private/request.log`
- `archives/*.csv`

Diese Dateien nicht manuell versionieren oder in Reviews als Code aendern.

## Typische Workflows
- Neue Seite: PHP-Datei anlegen, `private/layout.php` nutzen, Inhalte als `header`/`content`/`footer` bauen.
- Styling aendern: nur `assets/styles.css`.
- Theme/Language/Accessibility: `assets/theme.js`, `assets/i18n.js`, `assets/settings.js`.

## Tests
- Es gibt keine automatisierten Tests. Mindestens `php -l` auf geaenderte PHP-Dateien laufen lassen.
