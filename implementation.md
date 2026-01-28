## Kasse (POS) — Abläufe & Logik

### 1) Zustände (State Machine)

- **Nicht eingeloggt**
  - Home zeigt nur **Produktkarte (Read-only)**: Kategorien/Tabs + Produktliste, keine Buchung möglich.
- **Eingeloggt (Kasse)**
  - Voller POS-Modus: Produkt-Buttons + Buchungsarten + Storno + Offline-Status.
- **Eingeloggt (Admin)**
  - Darf ebenfalls POS nutzen, zusätzlich Admin-Funktionen (falls du das so willst; ansonsten Admin → nur Admin-Seite).

---

### 2) Initialisierung beim Öffnen der Kasse

1. **Session/User prüfen**
   - Wenn keine Session: auf Home/Produktkarte bleiben.
2. **Aktive Veranstaltung laden**
   - Wenn keine aktive Veranstaltung vorhanden: POS sperren + Hinweis „Keine aktive Veranstaltung“.
3. **Stammdaten laden**
   - Kategorien + Produkte (nur `active=true`), Sortierung anwenden.
4. **UI aufbauen**
   - Tabs: `Alle` + je Kategorie (Kategorie-Tab = Produktfilter).
   - Produktbuttons: je Produkt ein Touch-Button (Name + Preis “beiseite”).
5. **Offline-Queue Status laden**
   - Anzahl ausstehender Buchungen anzeigen (z. B. Badge „Queue: 3“), falls vorhanden.

---

### 3) Produktdarstellung & Navigation

- **Tablogik**
  - Tab `Alle`: zeigt alle aktiven Produkte.
  - Kategorie-Tab: zeigt nur Produkte mit `category_id` der Kategorie.
- **Sortierung**
  - Produkte innerhalb eines Tabs nach `sort` (oder Name, falls nicht gesetzt).
- **Touch-Optimierung**
  - Große Buttons, keine Hover-Abhängigkeit, schnelle visuelle Rückmeldung beim Tap.

---

### 4) Buchung (Einzelbuchung pro Aktion)

#### 4.1 Ablauf (Happy Path)
1. User tappt **Produktbutton**
2. UI zeigt **Buchungsarten-Auswahl**:
   - `Verkauft`
   - `Gutschein`
   - `Freigetränk`
3. User wählt Buchungsart → App baut **Buchungsobjekt** (Snapshot)
   - `uhrzeit`
   - `user (id + name)`
   - `produkt (id + name)`
   - `kategorie (id + name)`
   - `preis` (produktpreis zum Zeitpunkt)
   - `buchungstyp` (Verkauft/Gutschein/Freigetränk)
   - `einnahmen`
4. **Einnahmenberechnung**
   - Wenn `Verkauft` → `einnahmen = preis`
   - Wenn `Gutschein` oder `Freigetränk` → `einnahmen = 0`
5. **Persistieren**
   - Online: direkt an Server senden → im Buchungslog speichern.
   - Offline: lokal in Queue speichern (als “ausstehend”).

#### 4.2 UI-Rückmeldung
- Online erfolgreich: kurzer “Erfolg”-Toast + optional akustisches Feedback.
- Offline: Toast “Offline – gespeichert (Queue)” + Queue-Zähler erhöhen.

---

### 5) Offline-Queue & Sync (Logik aus Sicht der Kasse)

#### 5.1 Queue-Regeln
- Jede Buchung wird **sofort** lokal als “queued” markiert, wenn kein Server erreichbar ist.
- Buchungen bleiben queued bis:
  - Sync erfolgreich, dann aus Queue entfernen
  - oder manuell gelöscht wird (falls du das erlauben willst; standard: **nicht** löschen, nur senden)

#### 5.2 Sync-Regeln
- Sobald wieder online:
  - queued Buchungen werden **in Reihenfolge** gesendet
  - bei Teilerfolg: erfolgreiche entfernen, rest bleibt queued
- Doppelte Zeiten sind erlaubt; keine harte ID/Order nötig.

---

### 6) Storno (nur letzte Buchung dieses Users)

#### 6.1 Sichtbarkeit/Verfügbarkeit
- POS zeigt Button **„Storno letzte Buchung“**
- Button ist aktiv, wenn es für den eingeloggten User mindestens eine **letzte Buchung mit Status OK** gibt.

#### 6.2 Ablauf
1. User tappt „Storno letzte Buchung“
2. Optional: Eingabefeld „Grund“ (nicht Pflicht)
3. Confirm-Dialog: „Letzte Buchung wirklich stornieren?“
4. Ausführung:
   - Online: Server markiert die **letzte OK-Buchung dieses Users** als `STORNO` und korrigiert `einnahmen=0`
   - Offline: **kein Storno am Server möglich**
     - empfohlenes Verhalten: Storno-Button deaktivieren oder Hinweis „Storno nur online möglich“
     - Alternative (wenn du willst): offline “Storno-Request” in Queue, aber das ist fehleranfälliger

#### 6.3 UI-Rückmeldung
- Erfolg: Toast “Storno durchgeführt”
- Fehler: Toast mit Grund (z. B. “Keine Buchung zum Stornieren”)

---

### 7) POS-Sperren & Validierungen

- **Archivierte Events**
  - POS darf **keine** Buchung anlegen, wenn Event nicht aktiv ist.
- **Produkt deaktiviert**
  - nicht anzeigen oder nicht buchbar
- **Preis fehlt**
  - Produkt nicht buchbar + Admin-Hinweis (falls nötig)

---

### 8) Summenanzeige (optional, aber üblich)
- Optional live Anzeige:
  - “Heute / Event: Einnahmen (nur Verkauft)”
  - “Queue: n ausstehend”
- Summenlogik:
  - Nur Buchungen `status=OK` zählen
  - Storno zählt nicht (oder zählt als 0)

---

### 9) Edge Cases (entscheidende Produktlogik)

- Produktname/Preis/Kategorie ändern sich später:
  - In der Kasse immer aktueller Katalog
  - Im Buchungslog werden **Snapshotwerte** geschrieben (Name, Kategorie, Preis, Einnahmen), damit Archive stabil bleiben.
- Mehrere Geräte:
  - Jede Buchung ist unabhängig, Reihenfolge nicht garantiert, aber fachlich ok.