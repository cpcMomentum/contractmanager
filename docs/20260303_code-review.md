# Code Review: ContractManager v0.2.x

**Datum:** 2026-03-03
**Scope:** Gesamte Codebase (Backend + Frontend)
**Branch:** feat/28-fixed-contract-reminders (nach Commit 8099cf2)
**Ergebnis:** PASSED mit Empfehlungen

---

## Zusammenfassung

| Schwere | Anzahl | Beschreibung |
|---------|--------|-------------|
| Kritisch | 0 | - |
| Hoch | 4 | Defensive Validierung, Fehlerbehandlung |
| Mittel | 8 | DB-Konsistenz, Security-Hardening, l10n, UI |
| Niedrig | 5 | Code Quality, Cleanup |
| Info | 3 | Style, Naming |
| **Gesamt** | **20** | |

**Positiv:** OCP-Only APIs, Query Builder ueberall, sauberes MVC-Pattern, @nextcloud/* Pakete korrekt verwendet, Type Hints durchgehend, Background Jobs korrekt.

---

## Befunde

### Hoch (4)

#### H1: userId Null-Check in Controllern fehlt

- **Datei:** `lib/Controller/ContractController.php` (Zeile 35, 45, 54, 118, 186)
- **Kategorie:** Security
- **Problem:** `$this->userId` wird ohne Null-Check an Permission-Methoden uebergeben. Bei fehlgeschlagener Authentifizierung koennte userId null sein.
- **Fix:** Null-Check am Anfang jeder `@NoAdminRequired` Methode.

#### H2: DateTime-Parsing ohne try-catch in validate()

- **Datei:** `lib/Service/ContractService.php` (Zeile 48-55)
- **Kategorie:** Security
- **Problem:** `new DateTime($data['startDate'])` wirft Exception bei ungueltigen Strings wie "abc". Wird nicht gefangen.
- **Fix:** try-catch um DateTime-Konstruktor, Validierungsfehler zurueckgeben.

#### H3: json_decode() ohne Fehlerbehandlung in PermissionService

- **Datei:** `lib/Service/PermissionService.php` (Zeile 134-163)
- **Kategorie:** Security
- **Problem:** Korrupte Config-Werte koennten nach json_decode() unerwartete Typen ergeben.
- **Fix:** `if (!is_array($entries)) return false;` nach json_decode.

#### H4: json_decode() ohne Fehlerbehandlung in SettingsService

- **Datei:** `lib/Service/SettingsService.php` (Zeile 217, 251)
- **Kategorie:** Security
- **Problem:** getUserFilters()/setUserFilters() pruefen json_decode Ergebnis nicht.
- **Fix:** Null-Check + Fallback auf Default-Werte.

---

### Mittel (8)

#### M1: DB/Entity Mismatch bei cancellation_period

- **Datei:** `lib/Db/Contract.php` + `lib/Migration/Version010000Date20260116120000.php`
- **Kategorie:** Database
- **Problem:** Entity-Property ist `?string = null`, DB-Spalte ist `notnull => true` ohne DEFAULT. Funktioniert nur durch `?? ''` im Service.
- **Fix:** Neue Migration: `cancellation_period` auf `notnull => false` aendern oder `DEFAULT ''` setzen.

#### M2: TalkService Nachrichten nicht uebersetzt

- **Datei:** `lib/Service/TalkService.php` (Zeile 82-93)
- **Kategorie:** l10n
- **Problem:** Talk-Nachrichten sind hardcoded Deutsch. EmailService nutzt korrekt `$l->t()`.
- **Fix:** IFactory injecten, `$l->t()` verwenden.

#### M3: OC.currentUser statt @nextcloud/auth

- **Datei:** `src/components/ContractListItem.vue` (Zeile 217)
- **Kategorie:** OCP
- **Problem:** `OC.currentUser` ist private API.
- **Fix:** `import { getCurrentUser } from '@nextcloud/auth'`

#### M4: window.open() ohne Security-Attribute

- **Datei:** `src/components/ContractForm.vue` (646), `ContractListItem.vue` (204, 239)
- **Kategorie:** Security
- **Problem:** `window.open()` ohne `noopener,noreferrer`.
- **Fix:** `window.open(url, '_blank', 'noopener,noreferrer')`

#### M5: DAV-Response per Regex statt XML-Parser

- **Datei:** `src/components/ContractListItem.vue` (Zeile 218-231)
- **Kategorie:** Security
- **Problem:** XML aus PROPFIND-Response per Regex geparst. Fragil.
- **Fix:** DOMParser verwenden oder @nextcloud/files API.

#### M6: StatusBadge hardcoded Farben

- **Datei:** `src/components/StatusBadge.vue` (Zeile 42-54)
- **Kategorie:** Quality
- **Problem:** Hardcoded Hex-Farben statt NC CSS-Variablen. Bricht bei Dark Mode.
- **Fix:** `var(--color-success)`, `var(--color-warning)`, `var(--color-error)` verwenden.

#### M7: Keine Frontend-Validierung startDate < endDate

- **Datei:** `src/components/ContractForm.vue` (Zeile 499-511)
- **Kategorie:** Quality
- **Problem:** Backend prueft es, aber kein sofortiges Feedback im Formular.
- **Fix:** Computed Property oder Watcher fuer Datumsvergleich.

#### M8: TalkService nutzt interne OCA\Talk\* APIs

- **Datei:** `lib/Service/TalkService.php` (Zeile 109-113)
- **Kategorie:** OCP
- **Problem:** Korrekt als EXPERIMENTAL dokumentiert, aber Wartungsrisiko bei Talk-Updates.
- **Fix:** Langfristig HTTP-basierte OCS API. Kurzfristig: Akzeptieren, bei Updates pruefen.

---

### Niedrig (5)

#### N1: EmailService $appUrl ohne htmlspecialchars()

- **Datei:** `lib/Service/EmailService.php` (Zeile 162)
- **Fix:** `htmlspecialchars($appUrl, ENT_QUOTES)` im href-Attribut.

#### N2: Controller Error-Messages nicht uebersetzt

- **Datei:** `lib/Controller/ContractController.php` (Zeile 87, 119, 247, etc.)
- **Fix:** IFactory injecten, `$l->t()` fuer Fehlertexte.

#### N3: Unused CSS-Klasse .form-row--thirds

- **Datei:** `src/components/ContractForm.vue` (Zeile 687-697)
- **Fix:** Entfernen.

#### N4: Store error-State nicht genutzt

- **Datei:** `src/store/modules/contracts.js` (Zeile 8-9)
- **Fix:** Entweder Store-Error in Components nutzen oder entfernen.

#### N5: axios Import in ContractListItem nur fuer PROPFIND

- **Datei:** `src/components/ContractListItem.vue` (Zeile 100)
- **Fix:** Kommentar hinzufuegen warum direkter axios-Call.

---

### Info (3)

#### I1: Deprecated Methoden noch vorhanden

- **Datei:** `lib/Service/ContractService.php` (Zeile 135-144, 183-196)
- `checkAccess()`, `findAll()`, `findArchived()` - in naechster Major-Version entfernen.

#### I2: Inkonsistente Einrueckung (Tabs/Spaces)

- **Datei:** `lib/Service/ContractService.php`
- Bei Gelegenheit vereinheitlichen.

#### I3: Event-Handler-Naming inkonsistent

- **Dateien:** Mehrere Vue-Components
- `onEdit`/`onSearch` vs `handleDelete`/`handleSubmit` - Einheitlich waehlen.

---

## Empfehlung: Was umsetzen?

### Jetzt umsetzen (in diesem Branch)

| # | Aufwand | Begruendung |
|---|---------|-------------|
| **H1** | 5 min | Null-Check ist trivial, verhindert potenzielle NullPointer |
| **H2** | 5 min | try-catch um DateTime ist trivial, verhindert 500er |
| **M4** | 5 min | noopener/noreferrer ist ein String-Parameter |
| **N1** | 2 min | htmlspecialchars ist eine Zeile |
| **N3** | 1 min | CSS-Klasse loeschen |

**Hinweis:** H3 und H4 waren bei der Pruefung bereits korrekt implementiert (False Positives).

**Status:** Umgesetzt am 2026-03-03, Commit siehe unten.

### Eigenes Issue erstellen (spaeter)

| # | Begruendung |
|---|-------------|
| **M1** | DB-Migration braucht Planung und Testing (betrifft bestehende Installationen) |
| **M2** | TalkService l10n - Refactoring mit DI-Aenderung |
| **M3** | OC.currentUser Ersatz - klein aber benoetigt Frontend-Build + Test |
| **M5** | DAV XML-Parser - Refactoring der Datei-Oeffnen Logik |
| **M6** | StatusBadge Farben - Design-Entscheidung noetig (NC hat keine Status-Farben als Variablen) |
| **M7** | Frontend Datumsvalidierung - UX-Entscheidung noetig |
| **N2** | Controller l10n - betrifft viele Stellen, eigener Sweep |

### Nicht umsetzen / Akzeptieren

| # | Begruendung |
|---|-------------|
| **M8** | TalkService OCA\* - ist korrekt dokumentiert, Alternative waere HTTP-API Umbau |
| **I1-I3** | Kosmetik, bei Gelegenheit |
| **N4-N5** | Minimal Impact |

---

## Vorgehen

### Schritt 1: Quick-Fixes im aktuellen Branch (feat/28-fixed-contract-reminders)

Die 7 trivialen Fixes (H1-H4, M4, N1, N3) werden direkt im aktuellen Branch umgesetzt
und als eigener Commit hinzugefuegt. Alle sind Einzeiler-Aenderungen ohne funktionale
Auswirkungen auf bestehende Features.

**Betroffene Dateien:**
- `lib/Controller/ContractController.php` (H1: userId null-check)
- `lib/Service/ContractService.php` (H2: DateTime try-catch)
- `lib/Service/PermissionService.php` (H3: json_decode Pruefung)
- `lib/Service/SettingsService.php` (H4: json_decode Pruefung)
- `lib/Service/EmailService.php` (N1: htmlspecialchars)
- `src/components/ContractForm.vue` (M4: window.open + N3: CSS cleanup)
- `src/components/ContractListItem.vue` (M4: window.open)

**Commit-Message:** `fix(contractmanager): harden input validation and security (code review)`

### Schritt 2: GitHub Issue fuer Folge-Arbeiten

Fuer die 7 mittleren Punkte (M1-M3, M5-M7, N2) wird ein GitHub Issue erstellt.
Diese erfordern Design-Entscheidungen, DB-Migrationen oder groessere Refactorings
und sollen separat geplant und umgesetzt werden.

**Issue:** #31 (oder naechste freie Nummer)
**Label:** enhancement, code-quality

### Schritt 3: Akzeptierte Punkte

M8, I1-I3, N4-N5 werden bewusst nicht umgesetzt. Sie sind entweder dokumentierte
Trade-offs oder haben minimalen Impact. Bei zukuenftigen Refactorings koennen sie
mitgenommen werden.
