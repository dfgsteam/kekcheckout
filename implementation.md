# implementation.md — Kek-Checkout (Kassenapp)

## 1. Ziel & Scope

**Kek-Checkout** ist eine touch-optimierte Web-Kassenapp (iPad, Querformat), die Verkäufe für **genau eine aktive Veranstaltung** erfasst und nach Abschluss **archiviert**.  
Persistenz: **Buchungslog als CSV** pro Veranstaltung; Stammdaten (Produkte, Kategorien, Benutzer, Settings) als **JSON**.

### Muss-Ziele (MVP)
- Kasse (Touch UI): Tabs je Kategorie + „Alle“
- Einzelbuchungen (kein Warenkorb, keine Mengen)
- Buchungsarten: **Verkauft | Gutschein | Freigetränk**
  - Gutschein/Freigetränk: Preis/Einzahlungen **0€**
- Storno: nur **letzte Buchung des aktuellen Users**, wird **nicht gelöscht**, sondern als **Status** markiert; optionaler Stornogrund
- Admin: Stammdaten & Keys verwalten, Veranstaltung schließen/archivieren, Exporte/Backups
- Ohne Login-Key: Home zeigt nur **Produktkarte** (Read-only)
- PWA (Caching) + Offline-Queue + Sync (mehrere Geräte möglich)

### Später (Nicht im MVP)
- Analyse-Seite mit Graphen für `/archives` (nur Platzhalter/Route + Datenbasis vorbereiten)

---

## 2. Architekturüberblick

### 2.1 Komponenten
- **PHP Webapp** (serverseitige Views + JSON-API Endpunkte)
- **Dateisystem-Storage**
  - JSON für Stammdaten/Settings
  - CSV für Buchungslog (pro Event)
- **PWA Layer**
  - `manifest.webmanifest`
  - `service-worker.js` (assets caching + offline page + queue handling clientseitig)
- **Offline Sync**
  - Client sammelt Buchungen lokal (IndexedDB/LocalStorage Queue)
  - Sync per JSON-POST an Server-Endpunkt, Server schreibt in CSV

### 2.2 Grundsatz: Event ist Kontext
Alle Buchungen laufen gegen **aktives Event**. Archivierte Events sind read-only.

---

## 3. Dateistruktur (Vorschlag)

/public
index.php
/assets
/css
/js
/icons
manifest.webmanifest
service-worker.js

/app
/controllers
HomeController.php
AuthController.php
AdminController.php
PosController.php
ApiController.php
ArchiveController.php
/services
Storage.php
EventService.php
AuthService.php
BookingService.php
CatalogService.php
LockService.php
/views
layout.php            (aus LAYOUT_TEMPLATE, per include)
home.php
pos.php
admin.php
archives.php
/domain
Booking.php
Product.php
Category.php
User.php

/storage
/active
event.json
catalog.json
users.json
settings.json
bookings.csv
/archives
2026-01-28_KekParty/
event.json
bookings.csv
export_bookings.csv

**Hinweis:** `LAYOUT_TEMPLATE` wird als `/app/views/layout.php` eingebunden. Navigation existiert, Tabs werden ergänzt.

---

## 4. Datenmodell

## 4.1 JSON-Dateien (Stammdaten)

4.1.2 /private/catalog.json

{
  "categories": [
    { "id": "cat_soft", "name": "Softdrinks", "sort": 10, "active": true }
  ],
  "products": [
    {
      "id": "prod_cola",
      "name": "Cola",
      "price_cents": 250,
      "category_id": "cat_soft",
      "sort": 10,
      "active": true
    }
  ]
}

4.1.3 /private/users.json

{
  "users": [
    { "id": "u1", "name": "Julius", "role": "admin", "key_plain": "ABC123", "active": true },
    { "id": "u2", "name": "Kasse 1", "role": "cashier", "key_plain": "XYZ999", "active": true }
  ]
}

4.1.4 /private/settings.json

{
  "ui": {
    "orientation": "landscape",
    "buttons_per_row": 4
  }
}


⸻

4.2 Buchungslog CSV (Pflicht)

4.2.1 Ort

/private/bookings.csv (nur aktives Event)
Beim Archivieren wird der gesamte Ordner nach /storage/archives/YYYY-MM-DD_Name/ verschoben.

4.2.2 CSV-Format (Komma, Deutsch)
	•	Delimiter: ,
	•	Encoding: UTF-8
	•	Dezimal: wird nicht im CSV gespeichert; Geld als Cent-Integer.

Header

timestamp_iso,user_id,user_name,role,action,booking_type,product_id,product_name,category_id,category_name,unit_price_cents,revenue_cents,status,storno_reason

Bedeutungen
	•	action: "book" (ein Kauf/Buchungsvorgang)
	•	booking_type: "Verkauft" | "Gutschein" | "Freigetränk"
	•	unit_price_cents: Preis des Produkts zum Zeitpunkt der Buchung (Snapshot)
	•	revenue_cents:
	•	Verkauft: unit_price_cents
	•	Gutschein/Freigetränk: 0
	•	status: "OK" oder "STORNO"
	•	storno_reason: optionaler Text (nur bei STORNO gesetzt)

Wichtig: Da globale Stammdaten später geändert werden können, werden product_name, category_name, unit_price_cents mitgeloggt.

⸻

1. Kernabläufe

5.1 Auth (Key-Login)
	•	Eingabemaske: /login
	•	User gibt Key ein
	•	Wenn Key passt:
	•	Session user_id setzen
	•	Redirect:
	•	admin → /admin
	•	cashier → /pos

Ohne Session:
	•	/ zeigt Produktkarte (Read-only)

5.2 Kasse (POS)
	•	Seite: /pos
	•	UI:
	•	Tabs: „Alle“ + je Kategorie
	•	Produktbuttons: 4 pro Zeile (iPad Quer)
	•	Produktbutton zeigt: Name + Preis (beiseite)
	•	Klick auf Produkt öffnet Buchungsarten-Auswahl:
	•	Verkauft
	•	Gutschein
	•	Freigetränk
	•	Nach Auswahl:
	•	Client erstellt Buchungsobjekt
	•	Wenn online: POST zu /api/bookings
	•	Wenn offline: in Offline-Queue speichern, UI zeigt “queued”

5.3 Storno (nur letzte Buchung des Users)
	•	POS zeigt „Storno letzte Buchung“
	•	Optionales Feld “Grund” (nicht Pflicht)
	•	Client ruft /api/storno-last auf
	•	Server findet letzte Zeile (status OK) dieses Users im CSV und markiert sie als STORNO (siehe 6.2)
	•	Einnahmen werden korrigiert (revenue_cents auf 0 setzen)

5.4 Archivierung (Admin)
	•	Admin klickt „Veranstaltung archivieren“
	•	Server:
	1.	validiert: kein Archive läuft bereits
	2.	setzt event.json.status = archived, archived_at = now
	3.	verschiebt /private nach /storage/archives/YYYY-MM-DD_Name/
	4.	erstellt neuen /private Basiszustand:
	•	neues event.json (active)
	•	übernimmt catalog.json, users.json, settings.json (optional, konfigurierbar)

⸻

6. Implementationsdetails (entscheidend)

6.1 Concurrency / Mehrere Geräte

Da mehrere Geräte parallel buchen:
	•	Server nutzt File Locking beim Schreiben in CSV und JSON:
	•	flock() exklusiv für Schreiboperationen
	•	Leseoperationen optional shared lock
	•	Schreiblogik muss append-only sein (außer Storno: siehe 6.2)

6.2 Storno als Status (ohne Löschen)

Status-Änderung erfordert eine “Rewrite”-Operation, weil CSV Zeilen nicht in-place editierbar sind.

Vorgehen:
	1.	CSV komplett einlesen (streaming), letzte OK-Buchung des Users merken (Zeilenindex)
	2.	temp file schreiben:
	•	alle Zeilen kopieren
	•	bei Zielzeile: status=STORNO, storno_reason=<text>, revenue_cents=0
	3.	atomarer replace:
	•	bookings.csv.tmp → bookings.csv (rename)

Das bleibt konform mit „nicht löschen“, aber “als Storno dastehen” und “Einnahmen korrigieren”.

6.3 Offline Queue & Sync

Clientseitig:
	•	Queue in IndexedDB (empfohlen) oder LocalStorage (MVP-Notlösung)
	•	Ein queued Booking enthält:
	•	timestamp_client
	•	user_id
	•	booking_type
	•	product snapshot (id, name, category, price_cents)
	•	Sync-Worker:
	•	versucht periodisch zu senden (online events)
	•	Batch POST: /api/bookings/batch

Serverseitig:
	•	akzeptiert Batch, schreibt jede Buchung einzeln (mit lock)
	•	response: pro Eintrag success/fail

Konflikte:
	•	Doppelte Zeiten sind erlaubt, keine globale ID nötig.
	•	Reihenfolge: Server schreibt in Empfangsreihenfolge.

⸻

8. Admin-Funktionen (MVP)
	•	Veranstaltung:
	•	Name setzen
	•	Archivieren (B-Variante: schließen & verschieben)
	•	Kategorien:
	•	anlegen, umbenennen, sort, aktiv/inaktiv
	•	Produkte:
	•	anlegen, Kategorie zuordnen, Preis, sort, aktiv/inaktiv
	•	Benutzer/Keys:
	•	Name (vordefiniert), Rolle (admin/cashier), Key erstellen & plaintext anzeigen
	•	Export/Backup:
	•	CSV Export (Buchungslog)
	•	ZIP Backup (optional später, nicht initial notwendig)

⸻

9. UI/UX Anforderungen
	•	iPad Querformat, Touch-first
	•	Buttons groß, minimale Trefferfläche ~44px
	•	4 Buttons pro Zeile (konfigurierbar über settings)
	•	Tabs oben (Alle + Kategorien)
	•	Schnelle Rückmeldung:
	•	Toast “gebucht”
	•	Offline: “queued”
	•	„Storno letzte Buchung“ prominent, aber mit confirm

⸻

10. PWA (Caching)

10.1 Manifest
	•	Name: Kek-Checkout
	•	display: standalone
	•	orientation: landscape
	•	icons: 192/512

10.2 Service Worker
	•	precache: HTML shell, CSS, JS, icons
	•	runtime:
	•	/api/catalog network-first + fallback cache
	•	offline fallback page
	•	Queue: primär App-Logik (IndexedDB) + background sync (wenn verfügbar)
