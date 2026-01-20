# ContractManager - Implementierungsplan

## Übersicht

**App:** ContractManager
**Zweck:** Nextcloud-App zur Verwaltung von Verträgen mit automatischen Kündigungserinnerungen
**Tech-Stack:** Nextcloud 32+, PHP 8.2+, Vue.js 2.7, @nextcloud/vue
**Source of Truth:** `docs/produktbeschreibung.md`

---

## Phasen-Status

| Phase | Beschreibung | Status |
|-------|--------------|--------|
| 1 | Basis-CRUD | ✅ ABGESCHLOSSEN |
| 2 | Archiv & Validierung | ✅ ABGESCHLOSSEN |
| 3 | Erinnerungen | ✅ ABGESCHLOSSEN |
| 4 | Berechtigungen & Settings | ✅ ABGESCHLOSSEN |
| 5 | Testing & Polish | ✅ ABGESCHLOSSEN |
| 6 | Themensammlung (Future) | 📋 GESAMMELT |

---

## Phase 1: Basis-CRUD ✅ ABGESCHLOSSEN

### Implementiert
- **Migration:** `Version010000Date20260116120000.php` - Tabellen contracts + categories
- **Entities:** Contract.php, Category.php
- **Mapper:** ContractMapper.php, CategoryMapper.php
- **Services:** ContractService.php, CategoryService.php
- **Controller:** ContractController.php, CategoryController.php, PageController.php
- **Frontend:** ContractList.vue, ContractForm.vue, ContractListItem.vue, StatusBadge.vue
- **Store:** Vuex mit contracts + categories Modulen
- **UI-Features:** Datumsformat DD.MM.YYYY, strukturierte Kündigungsfrist, FilePicker, Kategorien als Sidebar-Filter

---

## Phase 2: Archiv & Validierung ✅ ABGESCHLOSSEN

### Implementiert
- **Archived-Feld:** Separates Boolean (int 0/1), NICHT als Status
- **Validierung:** ValidationException, ForbiddenException
- **Utilities:** dateUtils.js, periodUtils.js
- **ArchiveView.vue** mit Restore-Funktion

### Lessons Learned (WICHTIG!)
| Erkenntnis | Lösung |
|------------|--------|
| PostgreSQL: PARAM_BOOL konvertiert false zu "f" | PARAM_INT mit 0/1 verwenden |
| Tabellen-Namen max ~25 Zeichen | contractmgr_ statt contractmanager_ |
| Archived als Status führt zu Statusverlust | Separates Boolean-Feld verwenden |

---

## Phase 3: Erinnerungen ✅ ABGESCHLOSSEN

### 3.1 Basis-Infrastruktur ✅ ABGESCHLOSSEN
- **Migration:** Version010002 - Tabelle contractmgr_reminders
- **ReminderSent Entity + Mapper** - Tracking gesendeter Erinnerungen
- **ReminderJob.php** - Background Job alle 6 Stunden

### 3.2 Zwei Erinnerungszeitpunkte ✅ ABGESCHLOSSEN

**Implementiert:**
- ReminderService mit `shouldSendFirstReminder()` und `shouldSendFinalReminder()`
- Reminder-Types: `cancellation_{endDate}_first` und `cancellation_{endDate}_final`
- Erste Erinnerung: X Tage vor Kündigungsfrist (Admin-Setting, Default: 14)
- Letzte Erinnerung: Y Tage vor Kündigungsfrist (Admin-Setting, Default: 3)

### 3.3 Talk-Integration ✅ ABGESCHLOSSEN

**Implementiert:** `lib/Service/TalkService.php`
- Nutzt interne Talk ChatManager API (nicht HTTP)
- Actor-Type: `guests` (triggert Unread-Counter)
- Sendet formatierte Markdown-Nachrichten mit Emoji

**Hinweis:** Bot-Nachrichten in Talk erzeugen keinen Unread-Counter. Daher wird als `guests` Actor-Type verwendet.

### 3.4 E-Mail-Integration ✅ ABGESCHLOSSEN

**Implementiert:** `lib/Service/EmailService.php`
- Nutzt Nextcloud IMailer
- HTML + Plain-Text E-Mails
- Nur wenn User `email_reminder` aktiviert hat

### Entscheidung: Nextcloud Notifications (Glocke) entfernt

Die Nextcloud-Notification (Glocke) wurde **nicht** implementiert, da Talk und E-Mail ausreichend sind und die Glocke als redundant empfunden wurde.

---

## Phase 4: Berechtigungen & Settings ✅ ABGESCHLOSSEN

### 4.1 SettingsService ✅ ABGESCHLOSSEN

**Implementiert:** `lib/Service/SettingsService.php`
- `getTalkChatToken()` / `setTalkChatToken()`
- `getReminderDays1()` / `setReminderDays1()` (Default: 14)
- `getReminderDays2()` / `setReminderDays2()` (Default: 3)
- `getUserEmailReminder()` / `setUserEmailReminder()`

**Entscheidung: Nextcloud-native Access Control**
Die Benutzerberechtigungen werden NICHT app-intern verwaltet, sondern über Nextcloud's Standard-Mechanismus:
- Admin → Apps → ContractManager → "Nur für bestimmte Gruppen aktivieren"
- Vorteile: Weniger Code, konsistente UX, keine Middleware nötig
- `allowed_users` und `canAccess()` wurden entfernt

### 4.2 Admin-Settings API & UI ✅ ABGESCHLOSSEN

**Implementiert:**
- Routes: `/api/settings/admin` (GET/PUT)
- SettingsController: `getAdmin()`, `updateAdmin()`
- SettingsView.vue: Admin-Bereich mit:
  - Talk-Chat-Token
  - Erinnerungstage 1 + 2

### 4.3 User-Settings API & UI ✅ ABGESCHLOSSEN

**Implementiert:**
- Routes: `/api/settings` (GET/PUT)
- SettingsController: `get()`, `update()`
- SettingsView.vue: E-Mail-Toggle für User

### 4.4 Kategorie-Verwaltung UI ✅ ABGESCHLOSSEN

**Implementiert in SettingsView.vue:**
- Kategorie hinzufügen
- Inline-Edit
- Löschen mit Bestätigung
- Nur für Admins sichtbar

### 4.5 Access-Control ✅ ABGESCHLOSSEN (Nextcloud-native)

**Entscheidung:** Keine eigene Middleware implementiert.

Stattdessen wird Nextcloud's nativer Mechanismus genutzt:
- Admin → Apps → ContractManager → "Nur für bestimmte Gruppen aktivieren"
- Nextcloud prüft automatisch bei jedem Request, ob der User die App nutzen darf
- Kein zusätzlicher Code erforderlich

---

## Phase 5: Testing & Polish ✅ ABGESCHLOSSEN

### 5.1 Internationalisierung ✅ ABGESCHLOSSEN
- l10n/de.json - Deutsche Übersetzungen
- l10n/en.json - Englische Übersetzungen

### 5.2 Repository-Bereinigung ✅ ABGESCHLOSSEN
- Sensible Daten entfernt (nicht-existente E-Mail-Adresse)
- Autor-Info korrigiert: cpcMomentum GmbH
- Homepage-URL korrigiert: https://cpcMomentum.com
- Interne Dokumentation aus Git entfernt (CLAUDE.md, docs/archive/)

### 5.3 App Store Vorbereitung ✅ ABGESCHLOSSEN
- info.xml vollständig mit Description
- README.md aktualisiert
- Keine sensiblen Daten im Repository

---

## Phase 6: Themensammlung (Future) 📋 GESAMMELT

Ideen für zukünftige Versionen, noch nicht priorisiert:

| # | Thema | Beschreibung | Priorität |
|---|-------|--------------|-----------|
| 1 | Berechtigungsmodell | Alle berechtigten User sehen alle Verträge (laut Produktbeschreibung) - aktuell filtert created_by | HOCH |
| 2 | E-Mail-Text überarbeiten | Übersetzung auf Deutsch, "Cancellation period" → "Kündigen bis" | MITTEL |
| 3 | Suchfunktion | Volltextsuche über alle Verträge | TBD |
| 4 | Vertrag duplizieren | Bestehenden Vertrag als Vorlage nutzen | TBD |
| 5 | Vertrag löschen | Admin-Only, mit Bestätigung | TBD |
| 6 | Export/Import | CSV/Excel Export, ggf. Import | TBD |
| 7 | UI-Redesign ContractListItem | Modernere Darstellung der Vertragsliste | TBD |
| 8 | Zugriffssteuerung | Vertrag-spezifische Berechtigungen (später evaluieren) | TBD |
| 9 | Nicht mehr erinnern | Bei erster Erinnerung Option "Nicht mehr erinnern" für diesen Vertrag (Link in E-Mail/Talk) | TBD |

**Hinweis:** Diese Themen sind gesammelt, aber noch nicht für die Implementierung freigegeben. Priorisierung erfolgt bei Bedarf.

---

## Datenmodell

### Contract Entity
| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | int | Primary Key |
| name | string | Vertragsbezeichnung |
| vendor | string | Vertragspartner |
| status | string | active/cancelled/ended |
| categoryId | int? | FK zu Category |
| startDate | DateTime | Vertragsbeginn |
| endDate | DateTime | Vertragsende |
| cancellationPeriod | string | z.B. "3 months" |
| contractType | string | fixed/auto_renewal |
| renewalPeriod | string? | z.B. "12 months" |
| cost | string? | Betrag |
| currency | string | EUR/USD/CHF/GBP |
| contractFolder | string? | Nextcloud-Pfad |
| mainDocument | string? | Nextcloud-Pfad |
| reminderEnabled | int | 0/1 |
| reminderDays | int? | Überschreibt Global-Default |
| notes | string? | Freitext |
| archived | int | 0/1 |
| createdBy | string | User ID |
| createdAt | DateTime | Erstellt |
| updatedAt | DateTime | Geändert |

### ReminderSent Entity
| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | int | Primary Key |
| contractId | int | FK zu Contract |
| reminderType | string | z.B. "cancellation_2026-06-30_first" |
| sentAt | DateTime | Zeitpunkt |
| sentTo | string | User ID |

### Settings (über IConfig)
**Admin (App-weit):**
- `contractmanager.talk_chat_token` - string
- `contractmanager.reminder_days_1` - int (Default: 14)
- `contractmanager.reminder_days_2` - int (Default: 3)

**User (pro User):**
- `contractmanager.email_reminder` - bool (Default: false)

**Access Control:** Über Nextcloud-native Gruppensteuerung (Admin → Apps)

---

## API-Endpunkte

### Implementiert
| Method | Endpoint | Auth |
|--------|----------|------|
| GET | /api/contracts | User |
| GET | /api/contracts/archived | User |
| GET | /api/contracts/{id} | User |
| POST | /api/contracts | User |
| PUT | /api/contracts/{id} | User |
| DELETE | /api/contracts/{id} | Admin |
| POST | /api/contracts/{id}/archive | User |
| POST | /api/contracts/{id}/restore | User |
| GET | /api/categories | User |
| POST | /api/categories | Admin |
| PUT | /api/categories/{id} | Admin |
| DELETE | /api/categories/{id} | Admin |
| GET | /api/settings | User |
| PUT | /api/settings | User |
| GET | /api/settings/admin | Admin |
| PUT | /api/settings/admin | Admin |

---

## Services-Übersicht

| Service | Datei | Zweck |
|---------|-------|-------|
| ContractService | lib/Service/ContractService.php | CRUD für Verträge |
| CategoryService | lib/Service/CategoryService.php | CRUD für Kategorien |
| SettingsService | lib/Service/SettingsService.php | Admin- & User-Settings |
| ReminderService | lib/Service/ReminderService.php | Erinnerungslogik |
| TalkService | lib/Service/TalkService.php | Talk-Nachrichten |
| EmailService | lib/Service/EmailService.php | E-Mail-Versand |

---

## Offene Punkte

Keine offenen Punkte. Alle Phasen 1-5 sind abgeschlossen.

Siehe **Phase 6 - Themensammlung** für zukünftige Features.

---

## Security-Checkliste

- [x] Alle DB-Queries über Query Builder
- [x] Keine OC\* APIs (nur OCP\*)
- [x] Admin-Endpoints ohne @NoAdminRequired
- [x] PARAM_INT statt PARAM_BOOL
- [x] Keine Secrets in Logs
- [ ] php occ app:check-code vor Release

---

*Erstellt: 2026-01-18*
*Aktualisiert: 2026-01-20*
*Basis: produktbeschreibung.md v1.0*
