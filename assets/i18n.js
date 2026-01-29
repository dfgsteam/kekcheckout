(() => {
  const STORAGE_KEY = "kekcounter.lang";
  const AVAILABLE_MODES = ["system", "de", "en"];

  const translations = {
    de: {
      "title.index": "Kek - Checkout",
      "title.admin": "Kek - Checkout Admin",
      "title.analysis": "Kek - Checkout Analyse",
      "title.display": "Kek - Checkout Display",
      "title.indexCount": "{base} - {count}",
      "title.indexCritical": "{base} - {count} kritisch",
      "title.eventCount": "{event} - {count}",
      "app.updated": "Aktualisiert",
      "nav.back": "Zurueck",
      "nav.menu": "Menue",
      "nav.logout": "Abmelden",
      "nav.access": "Zugang",
      "nav.csv": "CSV Export",
      "nav.csvDownload": "CSV Export",
      "nav.tablet": "Tablet",
      "nav.display": "Display",
      "nav.analysis": "Analyse",
      "nav.admin": "Admin",
      "footer.privacy": "Datenschutz",
      "footer.imprint": "Impressum",
      "footer.builtBy": "Erstellt von",
      "settings.title": "Einstellungen",
      "settings.button": "Einstellungen",
      "settings.theme": "Theme",
      "settings.theme.system": "System",
      "settings.theme.light": "Hell",
      "settings.theme.dark": "Dunkel",
      "settings.accessibility": "Barrierefreiheit",
      "settings.accessibility.off": "Standard",
      "settings.accessibility.on": "Barrierefrei",
      "settings.language": "Sprache",
      "language.system": "System",
      "language.de": "Deutsch",
      "language.en": "Englisch",
      "language.toggle": "Sprache wechseln",
      "theme.system": "System",
      "theme.dark": "Dunkel",
      "theme.light": "Hell",
      "theme.toggle": "Theme wechseln",
      "accessibility.on": "Standard",
      "accessibility.off": "Barrierefrei",
      "accessibility.toggle": "Accessibility wechseln",
      "event.unnamed": "Unbenannt",
      "event.active": "Aktiv",
      "common.save": "Speichern",
      "common.delete": "Loeschen",
      "common.close": "Schliessen",
      "common.refresh": "Aktualisieren",
      "common.refreshing": "Aktualisiere",
      "common.download": "Download",
      "common.search": "Suchen",
      "common.loading": "Lade...",
      "common.loadingShort": "Lade",
      "common.saving": "Speichere...",
      "common.savingShort": "Speichere",
      "common.saveFailed": "Speichern fehlgeschlagen",
      "common.noData": "Keine Daten.",
      "common.noMatches": "Keine Treffer",
      "common.filterReset": "Filter zuruecksetzen",
      "common.listRefresh": "Liste aktualisieren",
      "common.wait": "Bitte kurz warten",
      "common.forget": "Vergessen",
      "index.tagline": "Checkout",
      "index.subtitle": "Schneller Zugriff auf Produkte und Buchungen.",
      "index.counter.present": "Buchungen",
      "index.counter.critical": "Kritisch ab",
      "index.chart.title": "Buchungsverlauf",
      "index.chart.subtitle": "Umsatz und Buchungen im Blick.",
      "index.chart.series": "Buchungen",
      "index.modal.title": "Zugriffstoken",
      "index.modal.note": "Token wird lokal im Browser gespeichert.",
      "index.modal.placeholder": "Zugriffstoken",
      "index.modal.status.none": "Kein Token gespeichert",
      "chart.visitors": "Buchungen",
      "chart.visitorsAria": "Buchungen Verlauf",
      "chart.occupancyLabel": "Buchungen: {count}",
      "chart.visitorsLabel": "Buchungen: {count}",
      "chart.retentionLabel": "Retention: {value}%",
      "token.saved": "Token gespeichert",
      "token.adminSaved": "Admin-Token gespeichert",
      "token.noneSaved": "Kein Token gespeichert",
      "token.checking": "Token wird geprueft...",
      "token.valid": "Token gueltig",
      "token.invalid": "Token ungueltig",
      "token.adminInUse": "Admin-Token wird genutzt",
      "token.requiredToChange": "Token noetig, um zu aendern",
      "token.missingServer": "Token fehlt auf dem Server",
      "token.missing": "Token fehlt",
      "token.promptAccess": "Access-Token eingeben",
      "token.adminRequired": "Admin-Token erforderlich",
      "token.adminMissing": "Admin-Token fehlt",
      "token.adminChecking": "Admin-Token wird geprueft...",
      "token.adminValid": "Admin-Token gueltig",
      "token.adminInvalid": "Admin-Token ungueltig",
      "token.adminVerified": "Admin-Token verifiziert",
      "token.adminMissingServer": "Admin-Token fehlt auf dem Server",
      "download.starting": "Download startet...",
      "download.done": "Download fertig",
      "download.started": "Download gestartet",
      "download.failed": "Download fehlgeschlagen",
      "archive.none": "Keine Archive vorhanden",
      "archive.noneMessage": "Noch keine Archive vorhanden.",
      "archive.noneLoaded": "Keine Archive geladen",
      "archive.noMatches": "Keine Treffer",
      "archive.noMatchesQuery": "Keine Treffer fuer \"{query}\".",
      "archive.noMatchesGeneric": "Keine Treffer.",
      "archive.countFiltered": "{count} von {total} Archiv(e)",
      "archive.countAll": "{count} Archiv(e) gefunden",
      "archive.loading": "Lade Archive...",
      "archive.loadFailed": "Archive konnten nicht geladen werden",
      "archive.rename": "Umbenennen",
      "archive.renaming": "Benenne um...",
      "archive.renamePrompt": "Neuer Name (ohne .csv)",
      "archive.renameFailed": "Umbenennen fehlgeschlagen",
      "archive.renamed": "Umbenannt in {name}",
      "archive.renamedToast": "Archiv umbenannt",
      "archive.deleteConfirm": "Archiv {name} wirklich loeschen?",
      "archive.deleting": "Loesche Archiv...",
      "archive.deleted": "Archiv geloescht",
      "archive.deleteFailed": "Loeschen fehlgeschlagen",
      "log.none": "Keine Log-Eintraege gefunden.",
      "log.noneMessage": "Noch keine Log-Eintraege.",
      "log.noMatches": "Keine Treffer im Log.",
      "log.loading": "Log wird geladen...",
      "log.loadFailed": "Log konnte nicht geladen werden",
      "log.entries.none": "Keine Eintraege",
      "log.entries.count": "{count} Eintraege",
      "log.entries.countFiltered": "{count} von {total} Eintraege",
      "log.download.started": "Log-Download gestartet",
      "log.download.failed": "Log-Download fehlgeschlagen",
      "log.status.notLoaded": "Noch nicht geladen",
      "log.status.noneLoaded": "Keine Logs geladen",
      "log.meta.error": "fehler",
      "log.meta.ok": "ok",
      "log.meta.delta": "delta",
      "log.meta.count": "anzahl",
      "log.meta.name": "name",
      "log.meta.archive": "archiv",
      "event.restart.confirm": "Veranstaltung wirklich neustarten?",
      "event.restart.running": "Neustart laeuft...",
      "event.restart.archived": "Archiviert: {name}",
      "event.restart.done": "Neustart abgeschlossen",
      "event.restart.toast": "Veranstaltung neu gestartet",
      "event.restart.failed": "Neustart fehlgeschlagen",
      "event.name.loading": "Lade Veranstaltungsname...",
      "event.name.loaded": "Name geladen",
      "event.name.none": "Kein Name gesetzt",
      "event.name.loadFailed": "Name konnte nicht geladen werden",
      "event.name.saved": "Name gespeichert",
      "event.name.removed": "Name entfernt",
      "event.name.noneLoaded": "Kein Name geladen",
      "settings.loading": "Lade Einstellungen...",
      "settings.loaded": "Einstellungen geladen",
      "settings.loadFailed": "Einstellungen konnten nicht geladen werden",
      "settings.fillAll": "Bitte alle Werte ausfuellen",
      "settings.saving": "Speichere Einstellungen...",
      "settings.saved": "Einstellungen gespeichert",
      "settings.noneLoaded": "Keine Einstellungen geladen",
      "analysis.loading": "Lade Analyse...",
      "analysis.loaded": "Analyse geladen",
      "analysis.failed": "Analyse fehlgeschlagen",
      "analysis.noDataArchive": "Keine Daten im Archiv",
      "analysis.loadedNoDataToast": "Archiv geladen (keine Daten)",
      "analysis.current.none": "Kein Archiv ausgewaehlt",
      "analysis.placeholder.selectArchive": "Bitte ein Archiv auswaehlen.",
      "analysis.stat.start": "Start",
      "analysis.stat.end": "Ende",
      "analysis.stat.duration": "Dauer",
      "analysis.stat.peak": "Peak",
      "analysis.stat.peakValue": "{count} um {time}",
      "analysis.stat.minimum": "Minimum",
      "analysis.stat.average": "Durchschnitt",
      "analysis.stat.fluctuation": "Fluktuation/h",
      "analysis.stat.highPhase": "Hochphase",
      "analysis.stat.highPhaseLongest": "Hochphase (Laengste)",
      "analysis.stat.highPhaseStability": "Hochphase Stabilitaet",
      "analysis.stat.capacityPeak": "Kapazitaet (Peak)",
      "analysis.stat.capacityAvg": "Kapazitaet (Avg)",
      "analysis.stay.average": "Durchschnittliche Verweildauer",
      "analysis.stay.median": "Median Verweildauer",
      "analysis.stay.duration": "Verweildauer",
      "analysis.stay.none": "Keine Abgaenge",
      "analysis.stay.short": "Kurz (<30m)",
      "analysis.stay.medium": "Mittel (30-90m)",
      "analysis.stay.long": "Lang (>90m)",
      "analysis.flow.arrival": "Einlass {index}",
      "analysis.flow.arrivalLabel": "Einlass",
      "analysis.flow.departure": "Abgang {index}",
      "analysis.flow.departureLabel": "Abgang",
      "analysis.flow.none": "Keine Daten",
      "analysis.retention.item30": "Retention 30m",
      "analysis.retention.item60": "Retention 60m",
      "analysis.retention.item120": "Retention 120m",
      "analysis.retention.label": "Retention",
      "analysis.suggest.capacity.high":
        "Kapazitaet nahezu erreicht. Einlasskontrolle oder groessere Location einplanen.",
      "analysis.suggest.capacity.medium":
        "Peak nahe Kapazitaet. Puffer fuer Spitzenzeiten einplanen.",
      "analysis.suggest.capacity.ok":
        "Kapazitaet ausreichend. Fokus auf Komfort und Wege.",
      "analysis.suggest.stay.long":
        "Lange Verweildauer. Mehr Angebote und Ruhebereiche planen.",
      "analysis.suggest.stay.short":
        "Kurze Verweildauer. Programm und Bindung verbessern.",
      "analysis.suggest.highPhase":
        "Laengere Hochphase. Personal und Versorgung skalieren.",
      "analysis.suggest.fluctuation":
        "Hohe Fluktuation. Einlass und Abgang besser staffen.",
      "analysis.suggest.none": "Keine klare Auffaelligkeit. Weiter beobachten.",
      "display.fullscreen": "Vollbild",
      "display.fullscreenExit": "Beenden",
      "display.fullscreenAria": "Vollbild",
      "display.fullscreenExitAria": "Vollbild beenden",
      "display.subtitle": "Live-Ansicht fuer Anzeigedisplays.",
      "admin.tagline": "Admin",
      "admin.title": "Kek-Counter Admin",
      "admin.subtitle": "Kasse, Buchungen und Zugriffe verwalten.",
      "admin.auth.title": "Admin-Token",
      "admin.auth.note": "Token wird nur lokal im Browser gespeichert.",
      "admin.auth.placeholder": "Admin-Token",
      "admin.auth.status.none": "Kein Token gespeichert",
      "admin.event.title": "Schicht",
      "admin.event.note": "Startet eine neue CSV-Schicht und archiviert die alte.",
      "admin.event.restart": "Neustarten",
      "admin.event.nameLabel": "Schichtname",
      "admin.event.namePlaceholder": "z.B. Abendverkauf",
      "admin.settings.title": "Einstellungen",
      "admin.settings.note": "Defaults fuer Kasse und Auswertungen.",
      "admin.settings.threshold": "Kritisch-Grenze",
      "admin.settings.maxPoints": "Max. Datenpunkte",
      "admin.settings.chartMaxPoints": "Chart-Max. Punkte",
      "admin.settings.windowHours": "Zeitfenster (Stunden)",
      "admin.settings.tickMinutes": "Tick-Abstand (Min)",
      "admin.settings.capacityDefault": "Kapazitaet (Default)",
      "admin.settings.stornoMinutes": "Storno Max Minuten",
      "admin.settings.stornoBack": "Storno Max Rueckwaerts",
      "admin.accessToken.title": "Zugriffstoken",
      "admin.accessToken.note": "Setzt das Zugriffstoken fuer Buchungen.",
      "admin.accessToken.placeholder": "Neues Zugriffstoken",
      "admin.accessKeys.title": "Kassen-Keys",
      "admin.accessKeys.note": "Mehrere Kassen-Keys mit Namen anlegen.",
      "admin.accessKeys.nameLabel": "Name",
      "admin.accessKeys.namePlaceholder": "z.B. Marvin",
      "admin.accessKeys.keyLabel": "Key",
      "admin.accessKeys.keyPlaceholder": "Neues Zugriffstoken",
      "admin.accessKeys.add": "Anlegen",
      "admin.accessKeys.existing": "Vorhandene Keys",
      "admin.accessKeys.none": "Keine Keys vorhanden.",
      "admin.accessKeys.loading": "Lade Keys...",
      "admin.accessKeys.loaded": "Keys geladen.",
      "admin.accessKeys.loadFailed": "Keys konnten nicht geladen werden",
      "admin.accessKeys.missing": "Name und Key benoetigt.",
      "admin.accessKeys.saved": "Key gespeichert.",
      "admin.accessKeys.deleted": "Key geloescht.",
      "admin.accessKeys.deleteConfirm": "Key wirklich loeschen?",
      "admin.adminToken.title": "Admin-Token",
      "admin.adminToken.note": "Setzt den Admin-Token fuer Admin- und Analysezugriff.",
      "admin.adminToken.placeholder": "Neues Admin-Token",
      "admin.archive.title": "Buchungsarchiv",
      "admin.archive.note": "CSV-Archive verwalten und umbenennen.",
      "admin.archive.downloadLatest": "Letztes CSV herunterladen",
      "admin.archive.refresh": "Liste aktualisieren",
      "archive.searchPlaceholder": "Archiv suchen",
      "archive.sort.label": "Archiv sortieren",
      "archive.sort.modifiedDesc": "Neueste zuerst",
      "archive.sort.modifiedAsc": "Aelteste zuerst",
      "archive.sort.nameAsc": "Name A-Z",
      "archive.sort.nameDesc": "Name Z-A",
      "archive.sort.sizeDesc": "Groesse absteigend",
      "archive.sort.sizeAsc": "Groesse aufsteigend",
      "admin.log.title": "Request-Log",
      "admin.log.note": "Letzte API-Aktionen und Fehler.",
      "admin.log.searchPlaceholder": "Log suchen",
      "admin.log.filterLabel": "Log filtern",
      "admin.log.filter.all": "Alle Status",
      "admin.log.filter.2xx": "2xx Erfolg",
      "admin.log.filter.4xx": "4xx Fehler",
      "admin.log.filter.5xx": "5xx Fehler",
      "admin.log.filter.error": "Nur Fehlerfelder",
      "admin.log.limitLabel": "Log Limit",
      "admin.log.table.time": "Zeit",
      "admin.log.table.action": "Aktion",
      "admin.log.table.status": "Status",
      "admin.log.table.ip": "IP",
      "admin.log.table.info": "Info",
      "analysis.tagline": "Analyse",
      "analysis.title": "Kek-Counter Analyse",
      "analysis.subtitle": "Archivierte Veranstaltungen durchsuchen.",
      "analysis.auth.title": "Zugriff",
      "analysis.auth.note": "Admin-Token eingeben.",
      "analysis.auth.placeholder": "Admin-Token",
      "analysis.auth.status.none": "Kein Admin-Token gespeichert",
      "analysis.archive.title": "Archivierte Veranstaltungen",
      "analysis.archive.table.event": "Veranstaltung",
      "analysis.archive.table.modified": "Geaendert",
      "analysis.archive.table.size": "Groesse",
      "analysis.archive.table.actions": "Aktionen",
      "analysis.current.note": "Aktueller Datensatz.",
      "analysis.metrics.title": "Kennzahlen & Verlauf",
      "analysis.metrics.note": "Ausgewaehltes Archiv wird ausgewertet.",
      "analysis.capacity.label": "Kapazitaet",
      "analysis.capacity.apply": "Aktualisieren",
      "analysis.interpretation.title": "Interpretation",
      "analysis.interpretation.note":
        "Hinweis: Verweildauer und Retention sind aus Zaehlerdifferenzen abgeleitet und daher Naeherungen.",
      "analysis.duration.title": "Verweildauer",
      "analysis.flows.title": "Stosszeiten",
      "analysis.retention.title": "Retention",
      "analysis.chart.aria": "Verlauf Analyse",
      "analysis.retention.aria": "Retention",
    },
    en: {
      "title.index": "Kek - Checkout",
      "title.admin": "Kek - Checkout Admin",
      "title.analysis": "Kek - Checkout Analysis",
      "title.display": "Kek - Checkout Display",
      "title.indexCount": "{base} - {count}",
      "title.indexCritical": "{base} - {count} critical",
      "title.eventCount": "{event} - {count}",
      "app.updated": "Updated",
      "nav.back": "Back",
      "nav.menu": "Menu",
      "nav.logout": "Sign out",
      "nav.access": "Access",
      "nav.csv": "CSV export",
      "nav.csvDownload": "CSV export",
      "nav.tablet": "Tablet",
      "nav.display": "Display",
      "nav.analysis": "Analysis",
      "nav.admin": "Admin",
      "footer.privacy": "Privacy",
      "footer.imprint": "Imprint",
      "footer.builtBy": "Built by",
      "settings.title": "Settings",
      "settings.button": "Settings",
      "settings.theme": "Theme",
      "settings.theme.system": "System",
      "settings.theme.light": "Light",
      "settings.theme.dark": "Dark",
      "settings.accessibility": "Accessibility",
      "settings.accessibility.off": "Standard",
      "settings.accessibility.on": "High contrast",
      "settings.language": "Language",
      "language.system": "System",
      "language.de": "German",
      "language.en": "English",
      "language.toggle": "Change language",
      "theme.system": "System",
      "theme.dark": "Dark",
      "theme.light": "Light",
      "theme.toggle": "Switch theme",
      "accessibility.on": "Standard",
      "accessibility.off": "High contrast",
      "accessibility.toggle": "Toggle accessibility",
      "event.unnamed": "Untitled",
      "event.active": "Active",
      "common.save": "Save",
      "common.delete": "Delete",
      "common.close": "Close",
      "common.refresh": "Refresh",
      "common.refreshing": "Refreshing",
      "common.download": "Download",
      "common.search": "Search",
      "common.loading": "Loading...",
      "common.loadingShort": "Loading",
      "common.saving": "Saving...",
      "common.savingShort": "Saving",
      "common.saveFailed": "Save failed",
      "common.noData": "No data.",
      "common.noMatches": "No matches",
      "common.filterReset": "Reset filters",
      "common.listRefresh": "Refresh list",
      "common.wait": "Please wait",
      "common.forget": "Forget",
      "index.tagline": "Checkout",
      "index.subtitle": "Fast access to products and bookings.",
      "index.counter.present": "Bookings",
      "index.counter.critical": "Critical at",
      "index.chart.title": "Booking history",
      "index.chart.subtitle": "Revenue and bookings at a glance.",
      "index.chart.series": "Bookings",
      "index.modal.title": "Access token",
      "index.modal.note": "Token is stored locally in this browser only.",
      "index.modal.placeholder": "Access token",
      "index.modal.status.none": "No token saved",
      "chart.visitors": "Bookings",
      "chart.visitorsAria": "Booking history",
      "chart.occupancyLabel": "Bookings: {count}",
      "chart.visitorsLabel": "Bookings: {count}",
      "chart.retentionLabel": "Retention: {value}%",
      "token.saved": "Token saved",
      "token.adminSaved": "Admin token saved",
      "token.noneSaved": "No token saved",
      "token.checking": "Checking token...",
      "token.valid": "Token valid",
      "token.invalid": "Token invalid",
      "token.adminInUse": "Using admin token",
      "token.requiredToChange": "Token required to change",
      "token.missingServer": "Token missing on server",
      "token.missing": "Token missing",
      "token.promptAccess": "Enter access token",
      "token.adminRequired": "Admin token required",
      "token.adminMissing": "Admin token missing",
      "token.adminChecking": "Checking admin token...",
      "token.adminValid": "Admin token valid",
      "token.adminInvalid": "Admin token invalid",
      "token.adminVerified": "Admin token verified",
      "token.adminMissingServer": "Admin token missing on server",
      "download.starting": "Download starting...",
      "download.done": "Download complete",
      "download.started": "Download started",
      "download.failed": "Download failed",
      "archive.none": "No archives",
      "archive.noneMessage": "No archives yet.",
      "archive.noneLoaded": "No archives loaded",
      "archive.noMatches": "No matches",
      "archive.noMatchesQuery": "No matches for \"{query}\".",
      "archive.noMatchesGeneric": "No matches.",
      "archive.countFiltered": "{count} of {total} archive(s)",
      "archive.countAll": "{count} archive(s) found",
      "archive.loading": "Loading archives...",
      "archive.loadFailed": "Archives could not be loaded",
      "archive.rename": "Rename",
      "archive.renaming": "Renaming...",
      "archive.renamePrompt": "New name (without .csv)",
      "archive.renameFailed": "Rename failed",
      "archive.renamed": "Renamed to {name}",
      "archive.renamedToast": "Archive renamed",
      "archive.deleteConfirm": "Delete archive {name}?",
      "archive.deleting": "Deleting archive...",
      "archive.deleted": "Archive deleted",
      "archive.deleteFailed": "Delete failed",
      "log.none": "No log entries found.",
      "log.noneMessage": "No log entries yet.",
      "log.noMatches": "No matches in log.",
      "log.loading": "Loading log...",
      "log.loadFailed": "Log could not be loaded",
      "log.entries.none": "No entries",
      "log.entries.count": "{count} entries",
      "log.entries.countFiltered": "{count} of {total} entries",
      "log.download.started": "Log download started",
      "log.download.failed": "Log download failed",
      "log.status.notLoaded": "Not loaded yet",
      "log.status.noneLoaded": "No logs loaded",
      "log.meta.error": "error",
      "log.meta.ok": "ok",
      "log.meta.delta": "delta",
      "log.meta.count": "count",
      "log.meta.name": "name",
      "log.meta.archive": "archive",
      "event.restart.confirm": "Restart event?",
      "event.restart.running": "Restart in progress...",
      "event.restart.archived": "Archived: {name}",
      "event.restart.done": "Restart complete",
      "event.restart.toast": "Event restarted",
      "event.restart.failed": "Restart failed",
      "event.name.loading": "Loading event name...",
      "event.name.loaded": "Name loaded",
      "event.name.none": "No name set",
      "event.name.loadFailed": "Name could not be loaded",
      "event.name.saved": "Name saved",
      "event.name.removed": "Name removed",
      "event.name.noneLoaded": "No name loaded",
      "settings.loading": "Loading settings...",
      "settings.loaded": "Settings loaded",
      "settings.loadFailed": "Settings could not be loaded",
      "settings.fillAll": "Please fill all values",
      "settings.saving": "Saving settings...",
      "settings.saved": "Settings saved",
      "settings.noneLoaded": "No settings loaded",
      "analysis.loading": "Loading analysis...",
      "analysis.loaded": "Analysis loaded",
      "analysis.failed": "Analysis failed",
      "analysis.noDataArchive": "No data in archive",
      "analysis.loadedNoDataToast": "Archive loaded (no data)",
      "analysis.current.none": "No archive selected",
      "analysis.placeholder.selectArchive": "Please select an archive.",
      "analysis.stat.start": "Start",
      "analysis.stat.end": "End",
      "analysis.stat.duration": "Duration",
      "analysis.stat.peak": "Peak",
      "analysis.stat.peakValue": "{count} at {time}",
      "analysis.stat.minimum": "Minimum",
      "analysis.stat.average": "Average",
      "analysis.stat.fluctuation": "Fluctuation/h",
      "analysis.stat.highPhase": "High phase",
      "analysis.stat.highPhaseLongest": "High phase (longest)",
      "analysis.stat.highPhaseStability": "High phase stability",
      "analysis.stat.capacityPeak": "Capacity (peak)",
      "analysis.stat.capacityAvg": "Capacity (avg)",
      "analysis.stay.average": "Average stay",
      "analysis.stay.median": "Median stay",
      "analysis.stay.duration": "Stay duration",
      "analysis.stay.none": "No departures",
      "analysis.stay.short": "Short (<30m)",
      "analysis.stay.medium": "Medium (30-90m)",
      "analysis.stay.long": "Long (>90m)",
      "analysis.flow.arrival": "Entry {index}",
      "analysis.flow.arrivalLabel": "Entry",
      "analysis.flow.departure": "Exit {index}",
      "analysis.flow.departureLabel": "Exit",
      "analysis.flow.none": "No data",
      "analysis.retention.item30": "Retention 30m",
      "analysis.retention.item60": "Retention 60m",
      "analysis.retention.item120": "Retention 120m",
      "analysis.retention.label": "Retention",
      "analysis.suggest.capacity.high":
        "Capacity nearly reached. Plan entry control or larger venue.",
      "analysis.suggest.capacity.medium":
        "Peak near capacity. Plan buffer for peak times.",
      "analysis.suggest.capacity.ok":
        "Capacity sufficient. Focus on comfort and flow.",
      "analysis.suggest.stay.long":
        "Long stay duration. Plan more offers and quiet areas.",
      "analysis.suggest.stay.short":
        "Short stay duration. Improve program and retention.",
      "analysis.suggest.highPhase":
        "Longer high phase. Scale staff and supply.",
      "analysis.suggest.fluctuation":
        "High fluctuation. Stagger entry and exit.",
      "analysis.suggest.none": "No clear pattern. Keep monitoring.",
      "display.fullscreen": "Fullscreen",
      "display.fullscreenExit": "Exit",
      "display.fullscreenAria": "Fullscreen",
      "display.fullscreenExitAria": "Exit fullscreen",
      "display.subtitle": "Live view for display screens.",
      "admin.tagline": "Admin",
      "admin.title": "Kek-Counter Admin",
      "admin.subtitle": "Manage event, CSV, and access.",
      "admin.auth.title": "Admin token",
      "admin.auth.note": "Token is stored locally in this browser only.",
      "admin.auth.placeholder": "Admin token",
      "admin.auth.status.none": "No token saved",
      "admin.event.title": "Event",
      "admin.event.note": "Starts a new CSV and archives the old one.",
      "admin.event.restart": "Restart",
      "admin.event.nameLabel": "Event name",
      "admin.event.namePlaceholder": "e.g. Summer fest",
      "admin.settings.title": "Settings",
      "admin.settings.note": "Default values for app and analysis.",
      "admin.settings.threshold": "Critical threshold",
      "admin.settings.maxPoints": "Max data points",
      "admin.settings.chartMaxPoints": "Chart max points",
      "admin.settings.windowHours": "Window (hours)",
      "admin.settings.tickMinutes": "Tick interval (min)",
      "admin.settings.capacityDefault": "Capacity (default)",
      "admin.settings.stornoMinutes": "Storno max minutes",
      "admin.settings.stornoBack": "Storno max back",
      "admin.accessToken.title": "Access token",
      "admin.accessToken.note": "Sets the access token for +1/-1.",
      "admin.accessToken.placeholder": "New access token",
      "admin.accessKeys.title": "Access keys",
      "admin.accessKeys.note": "Create multiple POS keys with names.",
      "admin.accessKeys.nameLabel": "Name",
      "admin.accessKeys.namePlaceholder": "e.g. Marvin",
      "admin.accessKeys.keyLabel": "Key",
      "admin.accessKeys.keyPlaceholder": "New access token",
      "admin.accessKeys.add": "Add",
      "admin.accessKeys.existing": "Existing keys",
      "admin.accessKeys.none": "No keys yet.",
      "admin.accessKeys.loading": "Loading keys...",
      "admin.accessKeys.loaded": "Keys loaded.",
      "admin.accessKeys.loadFailed": "Failed to load keys",
      "admin.accessKeys.missing": "Name and key required.",
      "admin.accessKeys.saved": "Key saved.",
      "admin.accessKeys.deleted": "Key deleted.",
      "admin.accessKeys.deleteConfirm": "Delete this key?",
      "admin.adminToken.title": "Admin token",
      "admin.adminToken.note": "Sets the admin token for admin and analysis access.",
      "admin.adminToken.placeholder": "New admin token",
      "admin.archive.title": "CSV archive",
      "admin.archive.downloadLatest": "Download latest CSV",
      "admin.archive.refresh": "Refresh list",
      "archive.searchPlaceholder": "Search archive",
      "archive.sort.label": "Sort archive",
      "archive.sort.modifiedDesc": "Newest first",
      "archive.sort.modifiedAsc": "Oldest first",
      "archive.sort.nameAsc": "Name A-Z",
      "archive.sort.nameDesc": "Name Z-A",
      "archive.sort.sizeDesc": "Size desc",
      "archive.sort.sizeAsc": "Size asc",
      "admin.log.title": "Request log",
      "admin.log.note": "Latest API actions and errors.",
      "admin.log.searchPlaceholder": "Search log",
      "admin.log.filterLabel": "Filter log",
      "admin.log.filter.all": "All status",
      "admin.log.filter.2xx": "2xx success",
      "admin.log.filter.4xx": "4xx errors",
      "admin.log.filter.5xx": "5xx errors",
      "admin.log.filter.error": "Errors only",
      "admin.log.limitLabel": "Log limit",
      "admin.log.table.time": "Time",
      "admin.log.table.action": "Action",
      "admin.log.table.status": "Status",
      "admin.log.table.ip": "IP",
      "admin.log.table.info": "Info",
      "analysis.tagline": "Analysis",
      "analysis.title": "Kek-Counter Analysis",
      "analysis.subtitle": "Browse archived events.",
      "analysis.auth.title": "Access",
      "analysis.auth.note": "Enter admin token.",
      "analysis.auth.placeholder": "Admin token",
      "analysis.auth.status.none": "No admin token saved",
      "analysis.archive.title": "Archived events",
      "analysis.archive.table.event": "Event",
      "analysis.archive.table.modified": "Updated",
      "analysis.archive.table.size": "Size",
      "analysis.archive.table.actions": "Actions",
      "analysis.current.note": "Current dataset.",
      "analysis.metrics.title": "Metrics & history",
      "analysis.metrics.note": "Selected archive is being analyzed.",
      "analysis.capacity.label": "Capacity",
      "analysis.capacity.apply": "Update",
      "analysis.interpretation.title": "Interpretation",
      "analysis.interpretation.note":
        "Note: stay duration and retention are derived from counter deltas and are approximations.",
      "analysis.duration.title": "Stay duration",
      "analysis.flows.title": "Peak times",
      "analysis.retention.title": "Retention",
      "analysis.chart.aria": "Analysis history",
      "analysis.retention.aria": "Retention",
    },
  };

  let currentMode = "system";
  let currentLang = "de";

  function resolveLanguage(mode) {
    if (mode === "de" || mode === "en") {
      return mode;
    }
    const browser = (navigator.language || "").toLowerCase();
    return browser.startsWith("de") ? "de" : "en";
  }

  function getStoredMode() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY) || "";
      return AVAILABLE_MODES.includes(stored) ? stored : "";
    } catch (error) {
      return "";
    }
  }

  function setStoredMode(mode) {
    try {
      if (AVAILABLE_MODES.includes(mode)) {
        localStorage.setItem(STORAGE_KEY, mode);
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch (error) {
      return;
    }
  }

  function interpolate(text, vars) {
    if (!vars) {
      return text;
    }
    let result = text;
    Object.keys(vars).forEach((key) => {
      const value = String(vars[key]);
      result = result.replace(new RegExp(`\\{${key}\\}`, "g"), value);
    });
    return result;
  }

  function t(key, vars) {
    const table = translations[currentLang] || translations.en || {};
    const fallback = translations.en || {};
    const base = table[key] ?? fallback[key] ?? key;
    if (typeof base !== "string") {
      return String(base ?? key);
    }
    return interpolate(base, vars);
  }

  function updateLanguageButtons() {
    const buttons = document.querySelectorAll("[data-language-toggle]");
    const labelKey =
      currentMode === "system"
        ? "language.system"
        : currentMode === "de"
          ? "language.de"
          : "language.en";
    const labelText = t(labelKey);
    buttons.forEach((button) => {
      const label = button.querySelector("[data-language-label]");
      if (label) {
        label.textContent = labelText;
      } else {
        button.textContent = labelText;
      }
      button.setAttribute("aria-label", t("language.toggle"));
      button.setAttribute("title", t("language.toggle"));
    });
  }

  function applyTranslations(scope = document) {
    const elements = scope.querySelectorAll(
      "[data-i18n], [data-i18n-placeholder], [data-i18n-title], [data-i18n-aria-label]"
    );
    elements.forEach((el) => {
      const key = el.getAttribute("data-i18n");
      if (key) {
        el.textContent = t(key);
      }
      const placeholderKey = el.getAttribute("data-i18n-placeholder");
      if (placeholderKey) {
        el.setAttribute("placeholder", t(placeholderKey));
      }
      const titleKey = el.getAttribute("data-i18n-title");
      if (titleKey) {
        el.setAttribute("title", t(titleKey));
      }
      const ariaKey = el.getAttribute("data-i18n-aria-label");
      if (ariaKey) {
        el.setAttribute("aria-label", t(ariaKey));
      }
    });
    updateLanguageButtons();
    document.documentElement.lang = currentLang;
    document.documentElement.setAttribute("data-lang", currentLang);
    document.documentElement.setAttribute("data-lang-mode", currentMode);
  }

  function setLanguageMode(mode, persist) {
    const next = AVAILABLE_MODES.includes(mode) ? mode : "system";
    currentMode = next;
    currentLang = resolveLanguage(next);
    if (persist) {
      setStoredMode(next);
    }
    applyTranslations();
    document.dispatchEvent(
      new CustomEvent("languagechange", { detail: { lang: currentLang, mode: currentMode } })
    );
  }

  function toggleLanguage() {
    const index = AVAILABLE_MODES.indexOf(currentMode);
    const next = AVAILABLE_MODES[(index + 1) % AVAILABLE_MODES.length];
    setLanguageMode(next, true);
  }

  function init() {
    const stored = getStoredMode();
    currentMode = stored || "system";
    currentLang = resolveLanguage(currentMode);
    applyTranslations();
    const buttons = document.querySelectorAll("[data-language-toggle]");
    buttons.forEach((button) => {
      button.addEventListener("click", toggleLanguage);
    });
    if ("onlanguagechange" in window) {
      window.addEventListener("languagechange", () => {
        if (currentMode !== "system") {
          return;
        }
        const nextLang = resolveLanguage("system");
        if (nextLang === currentLang) {
          return;
        }
        currentLang = nextLang;
        applyTranslations();
        document.dispatchEvent(
          new CustomEvent("languagechange", { detail: { lang: currentLang, mode: currentMode } })
        );
      });
    }
  }

  window.t = t;
  window.i18n = {
    t,
    getLanguage: () => currentLang,
    getMode: () => currentMode,
    setLanguage: (mode) => setLanguageMode(mode, true),
    applyTranslations,
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
