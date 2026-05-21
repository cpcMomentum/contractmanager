# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Datumsfelder (Start, Ende) nutzen jetzt einen Kalender-Datepicker statt manueller TT.MM.JJJJ-Eingabe — einheitlich mit der Kontakte-App (#137)
- Kündigungs-Automatik: Neue Felder „Gekündigt am" und „Gekündigt zum" (z. B. bei Sonderkündigung). Gekündigte Verträge werden am Laufzeitende bzw. zum „Gekündigt zum"-Datum automatisch beendet und archiviert (#136)

## [0.5.2] - 2026-05-21

### Fixed
- Vertragsdialog: Die Buttons „Abbrechen" und „Speichern" bleiben auf kleinen Fensterhöhen als fixierte Fußleiste sichtbar; der Dialog nutzt die verfügbare Höhe adaptiv statt eines festen Limits (#134)

## [0.5.1] - 2026-05-21

### Security
- AI-API-URL wird beim Speichern gegen Schema und Host validiert: nur `https` oder `http` auf localhost/127.0.0.1/[::1]/*.local/*.localhost (#123)
- Schutz gegen Prompt-Injection in der KI-Extraktion: PDF-Text wird in `<document>`-Tags gekapselt, eingebettete schliessende Tags werden neutralisiert (#124)
- Weniger personenbezogene Daten in Logs: E-Mail-Adressen werden maskiert, Vertragsnamen aus Reminder-Logs entfernt, Erfolgs-Logs auf Debug-Level (#125)
- API-Key-Maske als einheitliche Konstante (Single Source of Truth) statt hartkodierter Werte an mehreren Stellen (#126)

## [0.5.0] - 2026-05-19

### Added
- Vertragspartner-Feld jetzt als Autocomplete mit Vorschlaegen aus bestehenden Vertraegen (#107)
- URLs in Notizen werden in der Leseansicht als anklickbare Hyperlinks gerendert (#108)

### Fixed
- Doppelte Erinnerungs-Mails: wenn beide Reminder-Fenster gleichzeitig aktiv sind, wird nur noch die finale Erinnerung verschickt (#111)
- Per-Vertrag `reminderDays`-Override wirkt jetzt korrekt auf die finale Erinnerung — kein verfruehter "letzter Hinweis" mehr wenn ein eigener Wert gesetzt ist (#116)

### Changed
- Screenshots im App Store aktualisiert (saubere Demo-Daten, Autocomplete sichtbar)

## [0.4.4] - 2026-04-29

### Added
- Aktive Sortierung wird im Sort-Button angezeigt (Feldname + Richtungspfeil), nach Nextcloud Tasks Pattern (#82)

### Fixed
- Kuendigungsdatum bei auto_renewal-Vertraegen springt jetzt automatisch zur naechsten Periode, wenn die Frist bereits abgelaufen ist (#80)
- Vertraege ohne Kuendigungsfrist (z.B. befristete Vertraege) werden bei Sortierung "Kuendigen bis" ans Ende sortiert statt an den Anfang (#93)

## [0.4.3] - 2026-04-14

### Fixed
- **KRITISCH (#86)**: App-Update und `occ app:enable` crashten auf Nextcloud 33, weil der Repair-Step die seit NC 11 deprecated und in NC 33 entfernte `OC_App::getAppPath()` nutzte. Betroffene User mussten die App disablen und auf v0.4.0 ausweichen. Fix nutzt jetzt die OCP-API `IAppManager::getAppPath()`.

## [0.4.2] - 2026-04-11

### Fixed
- Automatische Bereinigung von Extra-Dateien aus frueheren Releases via RepairStep

## [0.4.1] - 2026-04-11

### Fixed
- Integritaetspruefung: `test-results/` aus Tarball entfernt

## [0.4.0] - 2026-04-08

### Added
- Suchfeld in der Sidebar: Client-seitige Volltextsuche ueber Name, Vertragspartner, Notizen und Zusatzfelder (#60)
- Globale Nextcloud-Suche: Vertraege erscheinen in der einheitlichen NC-Suche (#60)
- Vertragstyp "Unbefristet": Enddatum ist kein Pflichtfeld mehr (#49, #67)
- Filter "Ohne Kategorie" in der Sidebar mit Counter (#71)
- Sortieroption "Kuendigen bis" in der Vertragsliste (#70)
- Archiv-Counter in der Sidebar
- Editoren koennen Vertraege in den Papierkorb verschieben (Soft-Delete)

### Fixed
- Kuendigungsdatum-Berechnung bei Monatsende korrigiert (z.B. 31.03 minus 1 Monat = 28.02 statt 03.03) (#72)
- Browser-Confirm-Dialoge durch Nextcloud NcDialog ersetzt (Archivieren, Wiederherstellen, Kategorie loeschen)
- NcDialog-Buttons nutzen buttons-Prop statt actions-Slot (verhindert Re-Trigger beim Schliessen)
- Papierkorb nutzt ContractListItem (konsistentes Layout mit Vertragsliste und Archiv)
- Erinnerung wird automatisch deaktiviert wenn Enddatum entfernt wird
- Datumsvalidierung: Ungueltige Eingaben werden geleert mit Fehlermeldung

### Changed
- Loeschen-Dialog zeigt Hinweis auf 30-Tage Auto-Cleanup

## [0.3.0] - 2026-03-30

### Added
- Benutzerdefinierte Zusatzfelder: Bis zu 3 admin-konfigurierbare Felder pro Vertrag (#58)
- Zahlweise (Zahlungsintervall): Monatlich, Quartalsweise, Halbjaehrlich, Jaehrlich, Einmalig (#59)
- Externer Link Button: Externe URLs als Vertragsdokument hinterlegen

### Changed
- Formular-Redesign: Zusatzfelder in Grunddaten integriert, Kuendigen-bis in Laufzeit-Zeile, Kosten/Dokumente/Erinnerung als 3-Spalten-Layout
- Vertragsdokument oeffnet im Nextcloud Viewer Overlay statt in neuem Tab
- File Picker statt Smart Picker fuer Dokumentauswahl (weniger irrelevante Optionen)

### Fixed
- Vertragsordner "Oeffnen" Button funktioniert wieder (fehlende Methode)
- Rote Pflichtfeld-Markierung bei leerem Formular entfernt (HTML5 required Bug)

## [0.2.6] - 2026-03-25

### Added
- Smart Picker fuer Vertragsdokument: Unterstuetzt jetzt externe URLs zusaetzlich zu Nextcloud-Dateien (#48)
- Automatische Statusaenderung: Abgelaufene befristete Vertraege werden taeglich auf "Beendet" gesetzt (#53)

### Fixed
- Falsche Datumsberechnung bei monatlichen Vertraegen aelter als ~8 Jahre durch Iterations-Limit (#51)

## [0.2.5] - 2026-03-16

### Fixed
- Vertragsdokument öffnet jetzt im Nextcloud Viewer als Overlay statt nur den Ordner anzuzeigen (#43)
- Fallback bei fehlendem Viewer nutzt File-ID (`/f/{id}`) statt Parent-Ordner
- "In Nextcloud öffnen" im Formular nutzt ebenfalls File-ID für zuverlässiges Öffnen
- AI-Extraktionshinweise werden jetzt auf Deutsch angezeigt statt Englisch
- Validierungsfehler (Enddatum) nutzt NcNoteCard statt schwer lesbarem eigenen Styling

### Changed
- Nextcloud Viewer wird auf der Verträge-Seite geladen (LoadViewer Event)

## [0.2.4] - 2026-03-12

### Fixed
- Fix "Extracted app has more than 1 folder" installation error caused by macOS metadata in tarball (#41, #38)

## [0.2.3] - 2026-03-08

### Fixed
- Fix app update error by repackaging with correct code signature (#38)

## [0.2.2] - 2026-03-06

### Fixed
- Repackaged release tarball to fix "Extracted app has more than 1 folder" update error (#38)

## [0.2.1] - 2026-03-03

### Added
- Support for fixed-term contracts without cancellation period (#28)
- Dynamic reminders: cancellation deadline for auto_renewal, expiry date for fixed contracts (#27)
- Code review documentation (docs/20260303_code-review.md)

### Changed
- Settings link moved to navigation footer (#1)
- Improved privacy toggle UX (#30)
- Cancellation period field only shown for auto_renewal contracts
- Email and Talk reminder messages now differentiate by contract type

### Security
- Added userId null-check in ContractController (H1)
- Added try-catch for DateTime parsing in validation (H2)
- Added noopener/noreferrer to all window.open() calls (M4)
- Added htmlspecialchars for email URLs (N1)

### Removed
- Unused CSS class .form-row--thirds (N3)

## [0.2.0] - 2026-02-27

### Added
- Filterable contract list with vendor, status, and contract type filters (#22)
- Sortable contract list with persistent user preference (#21)
- Duplicate contract action (#18)
- Folder icon in contract list to open contract folder (#15)

### Changed
- Display name renamed to "Verträge" (#17)
- Filter and sort preferences persist per user across page reloads
- Updated screenshots for App Store listing

### Fixed
- Categories now sorted alphabetically (#8)
- Invalid JSON in l10n translation files (#16)

## [0.1.5] - 2026-02-23

### Fixed
- FilePicker not opening on certain hosting providers due to extremely long webpack chunk filenames
- Selected folder/file name not visible after FilePicker selection (only in tooltip)

### Changed
- Nextcloud 33 compatibility added (max-version raised to 33)
- Webpack chunk filenames shortened to hash-based naming

## [0.1.4] - 2026-01-23

### Added
- Permission system with Editor/Viewer roles
- Trash functionality with 30-day auto-cleanup
- Private contracts (only visible to creator)
- Read-only contract view for users with Viewer permission
- Nextcloud Initial State API for admin detection

### Changed
- Improved E-Mail reminder texts with personal greeting
- Viewer users can now view contract details (read-only)
- "New Contract" button hidden for Viewer users

### Fixed
- Mount point conflict between header height and admin detection
- Permission dropdown now loads all users/groups on open

## [0.1.3] - 2026-01-20

### Added
- Internationalization (i18n) with German and English translations
- Error handling improvements

### Fixed
- Date timezone bug in contract dates
- Access control and data isolation vulnerabilities
- Table name length issues for PostgreSQL compatibility

### Security
- Fixed data isolation between users

## [0.1.2] - 2026-01-19

### Added
- Admin and User settings UI
- Talk integration for reminders (via ChatManager API)
- E-Mail reminders with HTML and plain text
- Two reminder timepoints (configurable: default 14 and 3 days before deadline)

### Changed
- Use Nextcloud-native access control instead of custom middleware

### Removed
- Nextcloud Notification (bell) - Talk and E-Mail are sufficient

## [0.1.1] - 2026-01-18

### Added
- Archive functionality with restore option
- Validation with ValidationException and ForbiddenException
- Date utilities (dateUtils.js, periodUtils.js)

### Fixed
- PostgreSQL compatibility: Use PARAM_INT instead of PARAM_BOOL
- Shortened table names to avoid index length issues

## [0.1.0] - 2026-01-17

### Added
- Initial release
- Contract CRUD operations (create, read, update, delete)
- Category management with sidebar filter
- Contract list with status badges
- File picker integration for contract documents
- German date format (DD.MM.YYYY)
- Structured cancellation period input

[Unreleased]: https://github.com/cpcMomentum/contractmanager/compare/v0.5.2...HEAD
[0.5.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.5.0...v0.5.1
[0.2.4]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.5...v0.2.0
[0.1.5]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/cpcMomentum/contractmanager/compare/v0.1.0...v0.1.1
[0.5.0]: https://github.com/cpcMomentum/contractmanager/compare/v0.4.4...v0.5.0
[0.1.0]: https://github.com/cpcMomentum/contractmanager/releases/tag/v0.1.0
