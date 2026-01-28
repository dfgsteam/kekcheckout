# Kekcounter

Kekcounter ist eine schlanke Web-App zum Live-Zaehlen von Belegung bei Veranstaltungen.
Sie zeigt aktuelle Belegung, kritische Schwellenwerte und einen Verlauf an, inklusive Display- und Analyseansicht.

## Features
- Live Counter mit +1/-1 und kritischem Schwellwert
- Verlauf/Chart und Archivierung als CSV
- Display-Ansicht fuer grosse Screens
- Analyse-Ansicht fuer archivierte Veranstaltungen
- Token-basierter Zugriff (Access/Admin)
- Theme, Sprache und Accessibility via Settings-Modal

## Seiten
- `index.php` - Live Counter
- `display.php` - Display-Ansicht
- `analysis.php` - Archiv/Analyse
- `admin.php` - Verwaltung

## Setup
1. PHP 8+ (oder kompatibel) auf einem Webserver.
2. Schreibrechte fuer `private/` und `archives/`.
3. Optional Tokens setzen:
   - `private/.access_token`
   - `private/.admin_token`

## Layout-Template
Alle Seiten nutzen das gemeinsame Layout in `private/layout.php`.
Eine Anleitung findest du in `LAYOUT_TEMPLATE.md`.

## Wichtige Verzeichnisse
- `assets/` - Styles, JS, Theme, i18n
- `private/` - Laufzeitdaten und Konfiguration
- `archives/` - Archivierte CSVs

## Konventionen
- ASCII only in Source-Dateien (keine Umlaute, keine Unicode-Symbole).
- Layout-Template fuer neue Seiten verwenden.
- Seitemeta immer ueber `render_layout()` setzen.

## Entwicklung
- PHP-Syntaxcheck:
  ```sh
  php -l index.php
  php -l display.php
  php -l analysis.php
  php -l admin.php
  ```

## Lokal starten (PHP Built-in Server)
```sh
php -S localhost:8000
```
Dann im Browser oeffnen: `http://localhost:8000`

## PHP Installation (kurz)
1. macOS (Homebrew):
   ```sh
   brew install php
   ```
2. Ubuntu/Debian:
   ```sh
   sudo apt update
   sudo apt install php
   ```
3. Windows:
   - PHP ZIP von php.net laden, entpacken, Ordner zum PATH hinzufuegen.
   - Danach `php -v` im Terminal pruefen.

## Hinweise
- Laufzeitdaten sind in `private/visitors.csv` und `private/request.log`.
- Diese Dateien nicht versionieren oder manuell als Code aendern.

## Lizenz
TODO
