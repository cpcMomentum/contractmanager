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
| 3 | Erinnerungen | ⏳ TEILWEISE (3.1 fertig) |
| 4 | Berechtigungen & Settings | ⏳ TEILWEISE (4.1-4.2 fertig) |
| 5 | Testing & Polish | ❌ NICHT BEGONNEN |

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

## Phase 3: Erinnerungen ⏳ IN ARBEIT

### 3.1 Nextcloud Notifications ✅ ABGESCHLOSSEN
- **Migration:** Version010002 - Tabelle contractmgr_reminders
- **ReminderSent Entity + Mapper** - Tracking gesendeter Erinnerungen
- **Notifier.php** - Formatiert Notification-Texte
- **ReminderService.php** - Basis-Logik (checkAndSendReminders, calculateCancellationDeadline)
- **ReminderJob.php** - Background Job alle 6 Stunden

### 3.2 Zwei Erinnerungszeitpunkte ❌ FEHLT

**Anforderung (produktbeschreibung.md Zeile 138-140):**
> Zwei Erinnerungen: Erste Warnung (14 Tage) + letzte Warnung (3 Tage)
> Admin konfiguriert globale Default-Werte
> Pro Vertrag überschreibbar

**Zu implementieren:**
- ReminderService erweitern:
  - `shouldSendFirstReminder(Contract)` - Prüft reminder_days_1
  - `shouldSendFinalReminder(Contract)` - Prüft reminder_days_2
- Notifier erweitern:
  - Subject `cancellation_reminder_first`
  - Subject `cancellation_reminder_final`
- Reminder-Types:
  - `cancellation_{endDate}_first`
  - `cancellation_{endDate}_final`

**⚠️ ABHÄNGIGKEIT:** Braucht SettingsService für reminder_days_1/2!

### 3.3 Talk-Integration ❌ FEHLT

**Anforderung (produktbeschreibung.md Zeile 143):**
> Nextcloud Talk: Admin definiert einmalig den Ziel-Chat

**Zu implementieren:**
- **TalkService.php** erstellen:
  ```php
  class TalkService {
      public function isTalkAvailable(): bool;
      public function sendMessage(string $chatToken, string $message): bool;
  }
  ```
- Talk OCS API nutzen: `POST /ocs/v2.php/apps/spreed/api/v1/chat/{token}`
- In ReminderService integrieren

**⚠️ ABHÄNGIGKEIT:** Braucht SettingsService für talk_chat_token!

### 3.4 E-Mail-Integration ❌ FEHLT

**Anforderung (produktbeschreibung.md Zeile 144, 219):**
> E-Mail: Jeder User aktiviert/deaktiviert für sich selbst

**Zu implementieren:**
- **EmailService.php** erstellen:
  ```php
  class EmailService {
      public function sendReminder(Contract $contract, string $userId): void;
  }
  ```
- IMailer nutzen für SMTP-Versand
- Nur senden wenn User email_reminder aktiviert hat

**⚠️ ABHÄNGIGKEIT:** Braucht SettingsService für User-Setting email_reminder!

---

## Phase 4: Berechtigungen & Settings ⏳ IN ARBEIT

**WICHTIG: Phase 4.1 und 4.2 müssen VOR Phase 3.2-3.4 implementiert werden!**

### 4.1 SettingsService ✅ ABGESCHLOSSEN

**Implementiert:** `lib/Service/SettingsService.php`
- getAllowedUsers() / setAllowedUsers()
- getTalkChatToken() / setTalkChatToken()
- getReminderDays1() / setReminderDays1() (Default: 14)
- getReminderDays2() / setReminderDays2() (Default: 3)
- getUserEmailReminder() / setUserEmailReminder()
- canAccess() - Prüft Admin oder allowed_users

### 4.2 Admin-Settings API & UI ✅ ABGESCHLOSSEN

**Implementiert:**
- Routes: `/api/settings/admin` (GET/PUT)
- SettingsController: getAdmin(), updateAdmin()
- SettingsView.vue: Admin-Bereich mit Talk-Token, Erinnerungstage
- Frontend SettingsService.js

### 4.1 SettingsService (Referenz)

**Neue Datei:** `lib/Service/SettingsService.php`

```php
class SettingsService {
    // === Admin-Settings (produktbeschreibung.md Zeile 206-213) ===

    // berechtigte_user - Array der User mit Zugriff
    public function getAllowedUsers(): array;
    public function setAllowedUsers(array $userIds): void;

    // talk_chat_id - Nextcloud Talk Chat Token
    public function getTalkChatToken(): ?string;
    public function setTalkChatToken(?string $token): void;

    // erinnerung_tage_1 - Erste Erinnerung (Default: 14)
    public function getReminderDays1(): int;
    public function setReminderDays1(int $days): void;

    // erinnerung_tage_2 - Zweite Erinnerung (Default: 3)
    public function getReminderDays2(): int;
    public function setReminderDays2(int $days): void;

    // === User-Settings (produktbeschreibung.md Zeile 219) ===

    // email_erinnerung - Boolean pro User
    public function getUserEmailReminder(string $userId): bool;
    public function setUserEmailReminder(string $userId, bool $enabled): void;

    // === Access Control ===
    public function canAccess(string $userId): bool;
}
```

**Storage:** IConfig::setAppValue / setUserValue

### 4.2 Admin-Settings API & UI

**Routes hinzufügen:**
```php
['name' => 'settings#getAdmin', 'url' => '/api/settings/admin', 'verb' => 'GET'],
['name' => 'settings#updateAdmin', 'url' => '/api/settings/admin', 'verb' => 'PUT'],
```

**SettingsController erweitern:**
```php
// Ohne @NoAdminRequired - nur Admins
public function getAdmin(): JSONResponse {
    return new JSONResponse([
        'allowedUsers' => $this->settingsService->getAllowedUsers(),
        'talkChatToken' => $this->settingsService->getTalkChatToken(),
        'reminderDays1' => $this->settingsService->getReminderDays1(),
        'reminderDays2' => $this->settingsService->getReminderDays2(),
    ]);
}
```

**Frontend - AdminSettingsSection.vue:**
- User-Auswahl (NcMultiSelect)
- Talk-Chat-Token (NcInputField)
- Erinnerungstage 1 + 2 (NcInputField type="number")

### 4.3 User-Settings API & UI

**SettingsController erweitern:**
```php
/** @NoAdminRequired */
public function get(): JSONResponse;
public function update(bool $emailReminder): JSONResponse;
```

**SettingsView.vue erweitern:**
- E-Mail-Toggle mit Backend verbinden (aktuell nur UI)

### 4.4 Kategorie-Verwaltung UI

**Neue Komponente:** `CategoryManager.vue`
- Kategorie-Liste mit Drag&Drop
- Inline-Edit, Löschen mit Bestätigung
- Nur für Admins sichtbar

### 4.5 Access-Control Middleware

**Neue Datei:** `lib/Middleware/AccessCheckMiddleware.php`
```php
class AccessCheckMiddleware extends Middleware {
    public function beforeController($controller, $methodName): void {
        if (!$this->settingsService->canAccess($this->userId)) {
            throw new NotPermittedException();
        }
    }
}
```

---

## Phase 5: Testing & Polish ❌ NICHT BEGONNEN

### 5.1 PHPUnit Tests
- ContractServiceTest.php
- ReminderServiceTest.php
- SettingsServiceTest.php

### 5.2 Internationalisierung
- l10n/de.json
- l10n/en.json

### 5.3 Error Handling
- ErrorHandler.js für Frontend
- Vuex Actions mit Error-Wrapper

### 5.4 UX
- Responsive CSS
- Loading Skeletons

---

## Datenmodell

### Contract Entity (aktuell)
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
- `contractmanager.allowed_users` - JSON array
- `contractmanager.talk_chat_token` - string
- `contractmanager.reminder_days_1` - int (Default: 14)
- `contractmanager.reminder_days_2` - int (Default: 3)

**User (pro User):**
- `contractmanager.email_reminder` - bool (Default: false)

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

### Zu implementieren (Phase 4)
| Method | Endpoint | Auth |
|--------|----------|------|
| GET | /api/settings/admin | Admin |
| PUT | /api/settings/admin | Admin |

---

## Implementierungsreihenfolge

```
1. Phase 4.1 - SettingsService        ← ZUERST!
2. Phase 4.2 - Admin-Settings API/UI
3. Phase 3.2 - Zwei Zeitpunkte
4. Phase 4.3 - User-Settings API/UI
5. Phase 3.3 - Talk-Integration
6. Phase 3.4 - E-Mail-Integration
7. Phase 4.4 - Kategorie-Verwaltung
8. Phase 4.5 - Access Middleware
9. Phase 5   - Testing & Polish
```

---

## Security-Checkliste

- [ ] Alle DB-Queries über Query Builder
- [ ] Keine OC\* APIs (nur OCP\*)
- [ ] Admin-Endpoints ohne @NoAdminRequired
- [ ] PARAM_INT statt PARAM_BOOL
- [ ] Keine Secrets in Logs
- [ ] php occ app:check-code vor Release

---

*Erstellt: 2026-01-18*
*Basis: produktbeschreibung.md v1.0*
