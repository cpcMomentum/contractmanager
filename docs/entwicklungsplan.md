# ContractManager - Detaillierter Entwicklungsplan

## Übersicht

**App:** ContractManager (Nextcloud 32+, PHP 8.2+, Vue.js 2.7)
**Ziel:** Vertragsverwaltung mit automatischen Kündigungserinnerungen
**Phasen:** 5 Entwicklungsphasen

---

## Phase 1: Basis-CRUD

### 1.1 Database Migration
**Datei:** `lib/Migration/Version010000Date20260116120000.php`
**Aufwand:** M

Erstellt Tabellen:
- `oc_contractmanager_contracts` (20 Felder)
- `oc_contractmanager_categories` (3 Felder + Default-Daten)

### 1.2 Entities
**Dateien:**
- `lib/Db/Contract.php` (S)
- `lib/Db/Category.php` (S)

### 1.3 Mapper
**Dateien:**
- `lib/Db/ContractMapper.php` (M) - findAll, find, findArchived
- `lib/Db/CategoryMapper.php` (S)

### 1.4 Services
**Dateien:**
- `lib/Service/ContractService.php` (M) - CRUD + Geschäftslogik
- `lib/Service/CategoryService.php` (S)
- `lib/Service/NotFoundException.php` (S)

### 1.5 Controller implementieren
**Dateien:** (bereits als Stubs vorhanden)
- `lib/Controller/ContractController.php` (M)
- `lib/Controller/CategoryController.php` (S)

### 1.6 Frontend API Layer
**Dateien:**
- `src/services/ContractService.js` (S)
- `src/services/CategoryService.js` (S)

### 1.7 Vuex Store
**Dateien:**
- `src/store/index.js` (S)
- `src/store/modules/contracts.js` (M)
- `src/store/modules/categories.js` (S)

### 1.8 Vue Components
**Dateien:**
- `src/views/ContractList.vue` (L)
- `src/components/ContractListItem.vue` (M)
- `src/components/ContractForm.vue` (L)
- `src/components/StatusBadge.vue` (S)

### 1.9 Integration
- `src/main.js` erweitern (Store)
- `src/App.vue` erweitern (Views)
- `package.json` erweitern (vuex)

**Test Phase 1:**
```bash
php occ migrations:execute contractmanager
npm run build
# Vertrag anlegen, anzeigen, bearbeiten im Browser
```

---

## Phase 2: Archiv & Status

### 2.1 Status-Workflow
**Datei:** `lib/Service/ContractService.php` erweitern
- cancel(), end(), archive(), restore()
- Status-Validierung

### 2.2 Database Migration
**Datei:** `lib/Migration/Version010001Date20260116130000.php` (S)
- Feld `previous_status` für Restore-Funktion

### 2.3 Neue Routes
**Datei:** `appinfo/routes.php` erweitern
- POST /api/contracts/{id}/cancel
- POST /api/contracts/{id}/end
- GET /api/contracts/archived

### 2.4 Controller erweitern
**Datei:** `lib/Controller/ContractController.php`
- cancel(), end(), archived() Methoden

### 2.5 Frontend
**Dateien:**
- `src/views/ArchiveView.vue` (M)
- `src/services/ContractService.js` erweitern
- `src/store/modules/contracts.js` erweitern

**Test Phase 2:**
```bash
# Status-Flow: active → cancelled → archived → restore
```

---

## Phase 3: Erinnerungen

### 3.1 Reminder Service
**Datei:** `lib/Service/ReminderService.php` (M)
- checkAndSendReminders()
- Nextcloud Notifications
- E-Mail-Versand

### 3.2 Notifier
**Datei:** `lib/Notification/Notifier.php` (M)
- Notification-Texte formatieren

### 3.3 Background Job
**Datei:** `lib/BackgroundJob/ReminderJob.php` (M)
- TimedJob (alle 6 Stunden)

### 3.4 Kündigungsdatum-Berechnung
**Datei:** `lib/Migration/Version010002Date20260116140000.php` (S)
- Feld `cancellation_deadline`

### 3.5 Reminder-Tracking
**Dateien:**
- `lib/Migration/Version010003Date20260116150000.php` (S)
- `lib/Db/ReminderSent.php` (S)
- `lib/Db/ReminderSentMapper.php` (S)

### 3.6 Talk Integration (optional)
**Datei:** `lib/Service/TalkService.php` (M)

### 3.7 Application.php erweitern
- Notifier registrieren

**Test Phase 3:**
```bash
php occ background-job:execute "OCA\ContractManager\BackgroundJob\ReminderJob"
# Notifications prüfen
```

---

## Phase 4: Berechtigungen & Settings

### 4.1 Settings Service
**Datei:** `lib/Service/SettingsService.php` (M)
- Admin: allowedUsers, allowedGroups, talkChatToken, reminderDays
- User: emailReminder
- canAccess() Prüfung

### 4.2 Access Middleware
**Datei:** `lib/Middleware/AccessCheckMiddleware.php` (M)

### 4.3 Controller
**Datei:** `lib/Controller/SettingsController.php` implementieren
- get(), getAdmin(), update(), updateAdmin()

### 4.4 Routes erweitern
- GET/PUT /api/settings/admin

### 4.5 Initial State
**Datei:** `lib/Controller/PageController.php` erweitern
- isAdmin an Frontend übergeben

### 4.6 Frontend
**Dateien:**
- `src/views/SettingsView.vue` (M)
- `src/components/CategoryManager.vue` (M)
- `src/services/SettingsService.js` (S)

### 4.7 Application.php erweitern
- Middleware registrieren

**Test Phase 4:**
```bash
# Als Admin: Settings konfigurieren
# Als User ohne Berechtigung: Access denied
```

---

## Phase 5: Polish & Testing

### 5.1 PHPUnit Setup
**Dateien:**
- `phpunit.xml` (S)
- `tests/bootstrap.php` (S)

### 5.2 Unit Tests
**Dateien:**
- `tests/Unit/Service/ContractServiceTest.php` (M)
- `tests/Unit/Service/ReminderServiceTest.php` (M)

### 5.3 Internationalisierung
**Datei:** `l10n/de.json` (M)

### 5.4 UX Verbesserungen
**Dateien:**
- `src/services/ErrorHandler.js` (S)
- `src/components/ContractListSkeleton.vue` (S)
- `src/components/FilePickerField.vue` (M)
- `css/main.scss` erweitern (Responsive)

### 5.5 Dokumentation
- `README.md` aktualisieren

**Test Phase 5:**
```bash
./vendor/bin/phpunit tests/Unit
npm run lint
php occ app:check-code contractmanager
```

---

## Security-Maßnahmen

> Basierend auf OWASP Top 10 und AI-First-Approach Security Standards

### Automatisch durch Nextcloud Framework
- **CSRF-Schutz:** Automatisch für alle POST/PUT/DELETE Requests
- **Session-Management:** Über Nextcloud IUserSession (httpOnly, secure)
- **Authentication:** Nextcloud Login erforderlich
- **HTTPS:** Via Nextcloud-Server-Konfiguration

### OWASP Top 10 Abdeckung

| OWASP | Risiko | Mitigation in ContractManager |
|-------|--------|-------------------------------|
| A01 | Broken Access Control | AccessCheckMiddleware, @NoAdminRequired |
| A02 | Cryptographic Failures | Nextcloud handles encryption |
| A03 | Injection | Query Builder (NIEMALS raw SQL) |
| A04 | Insecure Design | Security Review vor jeder Phase |
| A05 | Security Misconfiguration | `php occ app:check-code` |
| A06 | Vulnerable Components | composer/npm audit vor Deployment |
| A07 | Auth Failures | Nextcloud Session Management |
| A08 | Software Integrity | Code Signing für App Store |
| A09 | Logging Failures | Nextcloud Logger, keine PII in Logs |
| A10 | SSRF | Keine externen URLs verarbeitet |

### Implementiert in jeder Phase

#### Phase 1: Basis-CRUD
- **SQL Injection Prevention (A03):** NUR Query Builder verwenden
  ```php
  // RICHTIG
  $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))

  // FALSCH - NIE MACHEN
  $qb->where("id = $id")
  ```
- **Input Validation:** Type Hints in Controller-Methoden
  ```php
  public function create(string $name, int $categoryId): JSONResponse
  ```
- **XSS Prevention:** Vue.js escaped automatisch, bei PHP-Templates `p()` verwenden

#### Phase 2: Archiv & Status
- **Status-Validierung:** Nur erlaubte Übergänge (Business Logic Security)
- **Ownership-Check:** User kann nur eigene Verträge bearbeiten (oder alle bei MVP)

#### Phase 3: Erinnerungen
- **Rate Limiting:** Reminder-Job läuft nur alle 6 Stunden
- **Spam Prevention:** Tracking welche Erinnerungen bereits gesendet wurden
- **E-Mail-Validierung:** Nur an verifizierte Nextcloud-E-Mail-Adressen
- **Keine PII in Logs:** Vertragsnamen/Details nicht loggen

#### Phase 4: Berechtigungen
- **Access Control Middleware (A01):** Prüft bei JEDEM Request ob User berechtigt ist
- **Admin-Only Endpoints:** Ohne `@NoAdminRequired` Annotation
- **User-Isolation:** Settings pro User getrennt gespeichert
- **Least Privilege:** User sehen nur was sie brauchen

#### Phase 5: Testing & Hardening
- **Security Tests:** Unberechtigte Zugriffe testen
- **Input Fuzzing:** Ungültige Eingaben testen
- **Dependency Audit:** `composer audit` + `npm audit`

### Pre-Deployment Security Checklist

```markdown
## Vor jedem Deployment ausführen

### Code Security
- [ ] `php occ app:check-code contractmanager` ohne Errors
- [ ] `composer audit` - keine High/Critical
- [ ] `npm audit --audit-level=high` - keine High/Critical
- [ ] Keine `OC\*` APIs verwendet (nur `OCP\*`)
- [ ] Alle DB-Queries über Query Builder
- [ ] Keine hardcodierten Credentials
- [ ] Keine sensiblen Daten in Logs

### Access Control
- [ ] Alle Endpoints mit korrekten Annotations
- [ ] Admin-Endpoints ohne @NoAdminRequired
- [ ] AccessCheckMiddleware aktiv

### Input/Output
- [ ] Alle User-Inputs validiert (Type Hints)
- [ ] PHP-Templates nutzen p() für Output
- [ ] JSON-Responses sind escaped
```

### Lokale Security-Scans (vor Commit)

```bash
# PHP Dependencies prüfen
composer audit

# JavaScript Dependencies prüfen
npm audit --audit-level=high

# Nextcloud Code-Check
php occ app:check-code contractmanager

# Optional: Secrets im Code suchen
grep -r "password\|secret\|api.key" lib/ src/ --include="*.php" --include="*.js"
```

---

## Kritische Dateien (Reihenfolge)

1. `lib/Migration/Version010000Date20260116120000.php` - DB-Schema
2. `lib/Db/Contract.php` + `lib/Db/ContractMapper.php` - Datenzugriff
3. `lib/Service/ContractService.php` - Geschäftslogik
4. `lib/Controller/ContractController.php` - API
5. `src/views/ContractList.vue` + `src/components/ContractForm.vue` - UI

---

## Neue Dateien gesamt: ~40

| Phase | Neue Dateien | Geänderte Dateien |
|-------|--------------|-------------------|
| 1 | 17 | 4 |
| 2 | 2 | 5 |
| 3 | 8 | 3 |
| 4 | 5 | 4 |
| 5 | 9 | 2 |

---

## Verifikation

Nach jeder Phase:
1. `php occ app:enable contractmanager`
2. `npm run build`
3. Browser-Test der neuen Funktionen
4. API-Test mit curl

Finale Verifikation:
```bash
# Tests
./vendor/bin/phpunit
npm run lint

# Code-Check
php occ app:check-code contractmanager

# Manueller Test
# - Vertrag anlegen mit allen Feldern
# - Status-Workflow durchspielen
# - Erinnerung manuell triggern
# - Settings als Admin ändern
```

---

*Erstellt: 2026-01-16*
*Version: 1.0*
