# ContractManager – Technischer Plan

---

## 1. Architektur-Übersicht

```
┌─────────────────────────────────────────────────────────────┐
│                     Nextcloud Server                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                ContractManager App                   │    │
│  │                                                      │    │
│  │  ┌──────────────┐    ┌──────────────────────────┐   │    │
│  │  │   Frontend   │    │        Backend           │   │    │
│  │  │   (Vue.js)   │◄──►│        (PHP)             │   │    │
│  │  │              │    │                          │   │    │
│  │  │  - App.vue   │    │  Controller/             │   │    │
│  │  │  - Views     │    │  ├── PageController      │   │    │
│  │  │  - Components│    │  ├── ContractController  │   │    │
│  │  │  - Store     │    │  ├── CategoryController  │   │    │
│  │  │              │    │  └── SettingsController  │   │    │
│  │  └──────────────┘    │                          │   │    │
│  │                      │  Service/                │   │    │
│  │                      │  ├── ContractService     │   │    │
│  │                      │  ├── CategoryService     │   │    │
│  │                      │  ├── ReminderService     │   │    │
│  │                      │  └── SettingsService     │   │    │
│  │                      │                          │   │    │
│  │                      │  Db/                     │   │    │
│  │                      │  ├── Contract (Entity)   │   │    │
│  │                      │  ├── ContractMapper      │   │    │
│  │                      │  ├── Category (Entity)   │   │    │
│  │                      │  └── CategoryMapper      │   │    │
│  │                      │                          │   │    │
│  │                      │  BackgroundJob/          │   │    │
│  │                      │  └── ReminderJob         │   │    │
│  │                      └──────────────────────────┘   │    │
│  └─────────────────────────────────────────────────────┘    │
│                              │                               │
│                              ▼                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Nextcloud Database                      │    │
│  │  - oc_contractmanager_contracts                      │    │
│  │  - oc_contractmanager_categories                     │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Nextcloud APIs (OCP)                    │    │
│  │  - IUserSession (Auth)                               │    │
│  │  - IConfig (Settings)                                │    │
│  │  - INotificationManager (Notifications)              │    │
│  │  - IMailer (E-Mail)                                  │    │
│  │  - Talk API (Chat-Nachrichten)                       │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Datenbank-Schema

### 2.1 Tabelle: `oc_contractmanager_contracts`

```sql
CREATE TABLE oc_contractmanager_contracts (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,

    -- Stammdaten
    name                    VARCHAR(255) NOT NULL,
    vendor                  VARCHAR(255) NOT NULL,
    status                  VARCHAR(20) NOT NULL DEFAULT 'active',
    category_id             INTEGER NULL,

    -- Laufzeit
    start_date              DATE NOT NULL,
    end_date                DATE NOT NULL,
    notice_period           VARCHAR(50) NOT NULL,
    contract_type           VARCHAR(20) NOT NULL DEFAULT 'fixed',
    renewal_period          VARCHAR(50) NULL,

    -- Kosten
    cost                    DECIMAL(10,2) NULL,
    currency                VARCHAR(3) DEFAULT 'EUR',
    cost_interval           VARCHAR(20) NULL,

    -- Nextcloud-Integration
    folder_path             VARCHAR(1024) NULL,
    document_path           VARCHAR(1024) NULL,

    -- Erinnerungen
    reminder_enabled        BOOLEAN DEFAULT TRUE,
    reminder_days           INTEGER NULL,

    -- Freitext
    notes                   TEXT NULL,

    -- Meta
    created_by              VARCHAR(64) NOT NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (category_id) REFERENCES oc_contractmanager_categories(id) ON DELETE SET NULL
);

-- Indizes
CREATE INDEX idx_contracts_user ON oc_contractmanager_contracts(created_by);
CREATE INDEX idx_contracts_status ON oc_contractmanager_contracts(status);
CREATE INDEX idx_contracts_end_date ON oc_contractmanager_contracts(end_date);
CREATE INDEX idx_contracts_category ON oc_contractmanager_contracts(category_id);
```

**Status-Enum:**
- `active` – Aktiv
- `cancelled` – Gekündigt
- `ended` – Beendet
- `archived` – Archiviert

**Contract-Type-Enum:**
- `fixed` – Befristet
- `auto_renewal` – Automatische Verlängerung

**Cost-Interval-Enum:**
- `monthly` – Monatlich
- `yearly` – Jährlich
- `once` – Einmalig

### 2.2 Tabelle: `oc_contractmanager_categories`

```sql
CREATE TABLE oc_contractmanager_categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(100) NOT NULL,
    sort_order  INTEGER DEFAULT 0
);

-- Default-Kategorien
INSERT INTO oc_contractmanager_categories (name, sort_order) VALUES
    ('Software', 1),
    ('Telekommunikation', 2),
    ('Versicherung', 3),
    ('Miete/Leasing', 4),
    ('Dienstleistung', 5),
    ('Sonstige', 99);
```

---

## 3. Backend-Komponenten

### 3.1 Entities

#### `lib/Db/Contract.php`
```php
class Contract extends Entity {
    protected string $name;
    protected string $vendor;
    protected string $status = 'active';
    protected ?int $categoryId = null;
    protected string $startDate;
    protected string $endDate;
    protected string $noticePeriod;
    protected string $contractType = 'fixed';
    protected ?string $renewalPeriod = null;
    protected ?string $cost = null;
    protected string $currency = 'EUR';
    protected ?string $costInterval = null;
    protected ?string $folderPath = null;
    protected ?string $documentPath = null;
    protected bool $reminderEnabled = true;
    protected ?int $reminderDays = null;
    protected ?string $notes = null;
    protected string $createdBy;
    protected string $createdAt;
    protected string $updatedAt;
}
```

#### `lib/Db/Category.php`
```php
class Category extends Entity {
    protected string $name;
    protected int $sortOrder = 0;
}
```

### 3.2 Mapper

#### `lib/Db/ContractMapper.php`
- `findAll(string $userId): array` – Alle aktiven Verträge
- `findArchived(string $userId): array` – Archivierte Verträge
- `findById(int $id): Contract` – Einzelner Vertrag
- `findDueForReminder(int $days): array` – Verträge mit fälliger Erinnerung
- `insert(Contract $contract): Contract`
- `update(Contract $contract): Contract`
- `delete(Contract $contract): void`

#### `lib/Db/CategoryMapper.php`
- `findAll(): array`
- `findById(int $id): Category`
- `insert(Category $category): Category`
- `update(Category $category): Category`
- `delete(Category $category): void`

### 3.3 Services

#### `lib/Service/ContractService.php`
- `findAll(): array`
- `findArchived(): array`
- `find(int $id): Contract`
- `create(array $data): Contract`
- `update(int $id, array $data): Contract`
- `archive(int $id): Contract`
- `restore(int $id): Contract`
- `delete(int $id): void` (nur Admin)
- `calculateCancellationDate(Contract $contract): \DateTime`

#### `lib/Service/CategoryService.php`
- `findAll(): array`
- `create(string $name): Category`
- `update(int $id, string $name, int $sortOrder): Category`
- `delete(int $id): void`

#### `lib/Service/ReminderService.php`
- `checkAndSendReminders(): void`
- `sendTalkNotification(Contract $contract): void`
- `sendEmailNotification(Contract $contract, string $userId): void`

#### `lib/Service/SettingsService.php`
- `getAdminSettings(): array`
- `setAdminSettings(array $settings): void`
- `getUserSettings(string $userId): array`
- `setUserSettings(string $userId, array $settings): void`
- `isUserAuthorized(string $userId): bool`

### 3.4 Controller

#### `lib/Controller/ContractController.php`
| Method | Route | Beschreibung |
|--------|-------|--------------|
| `index()` | `GET /api/contracts` | Alle Verträge (aktiv) |
| `show(id)` | `GET /api/contracts/{id}` | Einzelner Vertrag |
| `create()` | `POST /api/contracts` | Neuer Vertrag |
| `update(id)` | `PUT /api/contracts/{id}` | Vertrag bearbeiten |
| `destroy(id)` | `DELETE /api/contracts/{id}` | Löschen (nur Admin) |
| `archive(id)` | `POST /api/contracts/{id}/archive` | Archivieren |
| `restore(id)` | `POST /api/contracts/{id}/restore` | Wiederherstellen |

#### `lib/Controller/CategoryController.php`
| Method | Route | Beschreibung |
|--------|-------|--------------|
| `index()` | `GET /api/categories` | Alle Kategorien |
| `create()` | `POST /api/categories` | Neue Kategorie (Admin) |
| `update(id)` | `PUT /api/categories/{id}` | Bearbeiten (Admin) |
| `destroy(id)` | `DELETE /api/categories/{id}` | Löschen (Admin) |

#### `lib/Controller/SettingsController.php`
| Method | Route | Beschreibung |
|--------|-------|--------------|
| `get()` | `GET /api/settings` | Einstellungen laden |
| `update()` | `PUT /api/settings` | Einstellungen speichern |

### 3.5 Background Job

#### `lib/BackgroundJob/ReminderJob.php`
- Läuft täglich via Nextcloud Cron
- Prüft alle aktiven Verträge auf fällige Erinnerungen
- Sendet Notifications via Talk und/oder E-Mail

---

## 4. Frontend-Komponenten

### 4.1 Struktur

```
src/
├── main.js                 # Vue App Initialisierung
├── App.vue                 # Haupt-Komponente mit Navigation
├── store/
│   └── index.js           # Vuex Store (State Management)
├── services/
│   ├── api.js             # Axios API Client
│   ├── contractApi.js     # Contract API Calls
│   ├── categoryApi.js     # Category API Calls
│   └── settingsApi.js     # Settings API Calls
├── views/
│   ├── ContractList.vue   # Hauptliste der Verträge
│   ├── ContractDetail.vue # Vertragsdetails (Anzeige)
│   ├── ContractForm.vue   # Formular (Neu/Bearbeiten)
│   ├── ArchiveView.vue    # Archiv-Ansicht
│   └── SettingsView.vue   # Einstellungen
└── components/
    ├── ContractCard.vue   # Einzelne Vertragskarte in Liste
    ├── ContractTable.vue  # Tabellenansicht
    ├── StatusBadge.vue    # Status-Anzeige
    ├── CategorySelect.vue # Kategorie-Dropdown
    ├── DatePicker.vue     # Datum-Auswahl (Wrapper)
    ├── CostInput.vue      # Kosten mit Währung
    ├── FilePicker.vue     # Nextcloud File Picker
    └── ReminderSettings.vue # Erinnerungs-Einstellungen
```

### 4.2 Views

#### `ContractList.vue`
- Toolbar: Suche, Filter (Status, Kategorie), Sortierung
- Toggle: Karten-/Tabellenansicht
- Button "Neuer Vertrag"
- Liste der aktiven Verträge

#### `ContractDetail.vue`
- Alle Vertragsdetails (read-only)
- Buttons: Bearbeiten, Archivieren, Löschen (Admin)
- Link zu Nextcloud-Dateien

#### `ContractForm.vue`
- Formular für Neu/Bearbeiten
- Validierung (Pflichtfelder)
- Nextcloud FilePicker Integration
- Kündigungsdatum-Vorschau (berechnet)

#### `ArchiveView.vue`
- Liste archivierter Verträge
- Button "Wiederherstellen"
- Button "Endgültig löschen" (nur Admin)

#### `SettingsView.vue`
- Admin: Berechtigte User, Talk-Chat, Erinnerungs-Defaults
- User: E-Mail-Benachrichtigung aktivieren

### 4.3 State Management (Vuex)

```javascript
const store = {
    state: {
        contracts: [],
        archivedContracts: [],
        categories: [],
        settings: {},
        currentContract: null,
        loading: false,
        error: null,
    },
    mutations: {
        SET_CONTRACTS,
        SET_ARCHIVED_CONTRACTS,
        SET_CATEGORIES,
        SET_SETTINGS,
        SET_CURRENT_CONTRACT,
        ADD_CONTRACT,
        UPDATE_CONTRACT,
        REMOVE_CONTRACT,
        SET_LOADING,
        SET_ERROR,
    },
    actions: {
        fetchContracts,
        fetchArchivedContracts,
        fetchCategories,
        fetchSettings,
        createContract,
        updateContract,
        archiveContract,
        restoreContract,
        deleteContract,
        saveSettings,
    },
    getters: {
        activeContracts,
        contractsByCategory,
        contractsDueSoon,
        totalMonthlyCost,
    }
}
```

---

## 5. API-Endpoints (Zusammenfassung)

| Method | Endpoint | Beschreibung | Auth |
|--------|----------|--------------|------|
| `GET` | `/api/contracts` | Alle aktiven Verträge | User |
| `GET` | `/api/contracts?archived=true` | Archivierte Verträge | User |
| `GET` | `/api/contracts/{id}` | Einzelner Vertrag | User |
| `POST` | `/api/contracts` | Neuer Vertrag | User |
| `PUT` | `/api/contracts/{id}` | Bearbeiten | User |
| `DELETE` | `/api/contracts/{id}` | Löschen | Admin |
| `POST` | `/api/contracts/{id}/archive` | Archivieren | User |
| `POST` | `/api/contracts/{id}/restore` | Wiederherstellen | User |
| `GET` | `/api/categories` | Alle Kategorien | User |
| `POST` | `/api/categories` | Neue Kategorie | Admin |
| `PUT` | `/api/categories/{id}` | Bearbeiten | Admin |
| `DELETE` | `/api/categories/{id}` | Löschen | Admin |
| `GET` | `/api/settings` | Einstellungen laden | User |
| `PUT` | `/api/settings` | Einstellungen speichern | User/Admin |

---

## 6. Nextcloud-Integrationen

### 6.1 Dateien-Integration
- Nextcloud FilePicker für Ordner/Dokument-Auswahl
- Links zu Dateien öffnen im Files-App

### 6.2 Notifications
- `OCP\Notification\INotificationManager` für In-App Notifications
- `OCP\Mail\IMailer` für E-Mail-Versand

### 6.3 Talk-Integration
- Talk API für Chat-Nachrichten
- Bot-User oder Webhook für automatische Nachrichten

### 6.4 User-Management
- `OCP\IUserSession` für aktuellen User
- `OCP\IGroupManager` für Admin-Check
- `OCP\IConfig` für App-Settings

---

## 7. Entwicklungsphasen

### Phase 1: Basis-CRUD (Foundation)
**Ziel:** Verträge anlegen, anzeigen, bearbeiten, löschen

- [ ] Database Migration erstellen
- [ ] Entity & Mapper (Contract, Category)
- [ ] ContractService mit CRUD
- [ ] ContractController mit API-Endpoints
- [ ] Frontend: ContractList, ContractForm
- [ ] Kategorie-Auswahl (read-only, Default-Kategorien)

**Ergebnis:** Funktionierende Vertragsverwaltung ohne Erinnerungen

### Phase 2: Archiv & Status
**Ziel:** Verträge archivieren, wiederherstellen, Status-Workflow

- [ ] Status-Übergänge implementieren
- [ ] Archiv-Funktionalität (archive/restore)
- [ ] ArchiveView im Frontend
- [ ] StatusBadge-Komponente
- [ ] Filter nach Status

**Ergebnis:** Vollständiger Vertragslebenszyklus

### Phase 3: Erinnerungen
**Ziel:** Automatische Erinnerungen vor Kündigungsfrist

- [ ] ReminderService implementieren
- [ ] BackgroundJob für tägliche Prüfung
- [ ] Nextcloud Notifications
- [ ] E-Mail-Versand
- [ ] Talk-Integration (optional)
- [ ] Erinnerungs-Einstellungen (Admin + User)

**Ergebnis:** Proaktive Kündigungserinnerungen

### Phase 4: Berechtigungen & Settings
**Ziel:** Admin-Einstellungen, Berechtigungssteuerung

- [ ] SettingsService
- [ ] Admin-Bereich: Berechtigte User
- [ ] Kategorie-Verwaltung (Admin)
- [ ] User-Einstellungen (E-Mail on/off)

**Ergebnis:** Vollständige MVP-Funktionalität

### Phase 5: Polish & Testing
**Ziel:** Qualitätssicherung, UX-Verbesserungen

- [ ] PHPUnit Tests (Services, Controller)
- [ ] Frontend-Tests (optional)
- [ ] Error Handling verbessern
- [ ] Loading States
- [ ] Responsive Design prüfen
- [ ] Übersetzungen (de, en)

**Ergebnis:** Production-ready MVP

---

## 8. Technische Entscheidungen

| Entscheidung | Wahl | Begründung |
|--------------|------|------------|
| Vue Version | Vue 2.7 | @nextcloud/vue 8.x basiert auf Vue 2 |
| State Management | Vuex | Standard für Vue 2, gut dokumentiert |
| Styling | SCSS + @nextcloud/vue | Konsistentes NC-Look&Feel |
| Date Library | Native JS Date | Einfach, keine Extra-Dependency |
| Kündigungsfrist-Format | String ("3 Monate") | Flexibel, einfache Eingabe |

---

## 9. Offene Punkte / Risiken

| Punkt | Status | Kommentar |
|-------|--------|-----------|
| Talk-Integration | Zu prüfen | API-Verfügbarkeit für Bot-Nachrichten |
| FilePicker | Zu prüfen | @nextcloud/dialogs Version |
| Cron-Intervall | Festgelegt | Täglich reicht für Erinnerungen |
| Multi-User-Berechtigung | MVP: Alle sehen alles | Später erweiterbar |

---

*Erstellt: 2026-01-16*
*Version: 1.0*
