# Berechtigungskonzept für ContractManager

## Ziel

Ein flexibles Berechtigungsmodell implementieren, das sowohl den Unternehmenskontext (alle sehen alle) als auch den privaten Kontext (private Verträge) unterstützt. Inklusive zweistufigem Lösch-Konzept mit Papierkorb.

---

## Zusammenfassung der Anforderungen

1. **Unternehmenskontext:** Alle berechtigten User sehen standardmäßig alle Verträge
2. **Admin-Rechte:** Nextcloud-Admins können IMMER alle Verträge sehen (auch private)
3. **Private Verträge:** User können Verträge als "privat" markieren → nur für sie sichtbar (außer Admin)
4. **Berechtigungssteuerung:** In den App-Einstellungen konfigurierbar (Gruppen UND Einzelpersonen)
5. **Alle Editoren können alle bearbeiten:** Kein Unterschied zwischen Ersteller und anderem Editor
6. **Zweistufiges Löschen:** Papierkorb für User, endgültiges Löschen nur durch Admin

---

## Berechtigungskonzept (3 Stufen)

### Stufe 1: App-Zugriff (in App-Einstellungen)

Konfiguration in den ContractManager-Einstellungen mit **kombinierten Auswahlfeldern** (Gruppen UND Einzelpersonen):

| Einstellung | Beschreibung |
|-------------|--------------|
| **Editor-Berechtigung** | Kann alle sichtbaren Verträge sehen, erstellen und bearbeiten |
| **Viewer-Berechtigung** | Kann alle (nicht-privaten) Verträge nur ansehen |

**Technische Umsetzung:**
- Ein kombiniertes Auswahlfeld pro Rolle (NcSelect mit Multi-Select)
- Akzeptiert sowohl Gruppen als auch Einzelpersonen
- Speicherung in App-Config als JSON: `["group:buchhaltung", "user:max.mustermann"]`
- Prefix `group:` oder `user:` unterscheidet die Typen

**Beispiel im UI:**
```
Editor-Berechtigung: [Gruppe: Buchhaltung] [User: Max Mustermann] [+]
Viewer-Berechtigung: [Gruppe: Externe] [User: Praktikant1] [+]
```

**Hierarchie:**
- Nextcloud-Admin → hat automatisch alle Rechte (kein Eintrag nötig)
- Editor → kann erstellen, ALLE sichtbaren bearbeiten, alle sehen
- Viewer → kann nur ansehen
- Kein Eintrag → kein Zugang zur App

---

### Stufe 2: Sichtbarkeit (is_private Feld)

**Neues Feld:** `is_private` (INTEGER, Default: 0)

| Vertrag-Typ | Editor/Viewer | Admin |
|-------------|---------------|-------|
| Standard (`is_private=0`) | Ja, sehen alle | Ja, sieht alle |
| Privat (`is_private=1`) | Nein, nur Ersteller | Ja, sieht alle |

**Wichtig:** Wer einen Vertrag sehen darf, sieht ALLE Felder (inkl. Beträge).
Keine granulare Feld-Sichtbarkeit → zu komplex, wenig Mehrwert.

---

### Stufe 3: Bearbeitungsrechte

| Aktion | Editor | Viewer | Admin |
|--------|--------|--------|-------|
| Anzeigen | Ja (wenn nicht privat oder Ersteller) | Ja (wenn nicht privat) | Ja |
| Erstellen | Ja | Nein | Ja |
| Bearbeiten | Ja (alle sichtbaren) | Nein | Ja |
| Archivieren | Ja (alle sichtbaren) | Nein | Ja |
| Wiederherstellen | Ja (alle sichtbaren) | Nein | Ja |
| In Papierkorb verschieben | Ja | Nein | Ja |
| Aus Papierkorb wiederherstellen | Ja (eigene) | Nein | Ja (alle) |
| Endgueltig loeschen | Nein | Nein | Ja |

**Wichtig:** Editoren können ALLE sichtbaren Verträge bearbeiten – nicht nur eigene!

---

## Zweistufiges Lösch-Konzept

### Übersicht

```
Editor klickt "Löschen"
        ↓
   Papierkorb (User)
        ↓
   30 Tage warten ──────────→ Automatisch gelöscht
        ↓
   ODER Admin sieht im Admin-Papierkorb
        ↓
   Admin klickt "Endgültig löschen"
        ↓
   Aus Datenbank entfernt
```

### Datenbank-Feld

```php
deleted_at  DATETIME  NULL  // NULL = aktiv, Datum = im Papierkorb seit
```

### Verhalten pro Rolle

| Rolle | Eigener Papierkorb | Auto-Loeschen nach 30 Tagen | Admin-Papierkorb | Endgueltig loeschen |
|-------|-------------------|---------------------------|------------------|-------------------|
| Editor | Ja, sieht eigene geloeschte | Ja | Nein | Nein |
| Viewer | Nein | Nein | Nein | Nein |
| Admin | Ja, sieht eigene geloeschte | Nein | Ja, sieht ALLE geloeschten | Ja |

### Ablauf im Detail

**1. Editor löscht Vertrag:**
```
- deleted_at = NOW()
- Vertrag verschwindet aus normaler Liste
- Vertrag erscheint im eigenen Papierkorb des Editors
```

**2. Editor-Papierkorb:**
```
- Zeigt nur eigene gelöschte Verträge
- Option: "Wiederherstellen" (deleted_at = NULL)
- KEIN endgültiges Löschen möglich
- Nach 30 Tagen: Automatisch aus Datenbank entfernt
```

**3. Admin-Papierkorb:**
```
- Zeigt ALLE gelöschten Verträge (aller User)
- Option: "Wiederherstellen" (deleted_at = NULL)
- Option: "Endgültig löschen" (DELETE FROM database)
- Option: "Papierkorb leeren" (alle endgültig löschen)
- KEIN automatisches Löschen – Admin hat volle Kontrolle
```

**4. Background Job (täglich):**
```
- Findet alle Verträge mit deleted_at > 30 Tage
- AUSNAHME: Verträge von Admins werden NICHT automatisch gelöscht
- Löscht qualifizierte Verträge endgültig
```

### UI-Konzept

**Navigation:**
```
[Verträge] [Archiv] [Papierkorb] [Einstellungen]
                         ↑
                    Neuer Tab
```

**Papierkorb-Ansicht (Editor):**
```
+-----------------------------------------------------+
| Papierkorb                                          |
+-----------------------------------------------------+
| Hinweis: Vertraege werden nach 30 Tagen automatisch |
| endgueltig geloescht.                               |
+-----------------------------------------------------+
| Vertrag A     Geloescht: 15.01.2026  [Wiederherstellen] |
| Vertrag B     Geloescht: 18.01.2026  [Wiederherstellen] |
+-----------------------------------------------------+
```

**Admin-Papierkorb (zusaetzliche Optionen):**
```
+-----------------------------------------------------+
| Papierkorb (Admin)               [Papierkorb leeren]|
+-----------------------------------------------------+
| Info: Als Admin werden Ihre geloeschten Vertraege   |
| NICHT automatisch geloescht.                        |
+-----------------------------------------------------+
| Vertrag A  (Max)   Geloescht: 15.01.2026  [W] [X]   |
| Vertrag B  (Lisa)  Geloescht: 18.01.2026  [W] [X]   |
| Vertrag C  (Admin) Geloescht: 10.01.2026  [W] [X]   |
+-----------------------------------------------------+
Legende: [W] = Wiederherstellen, [X] = Endgueltig loeschen
```

---

## Aktueller Bug (zu beheben)

`ContractMapper.findAll()` filtert aktuell nach `created_by`:
```php
// FALSCH - jeder sieht nur eigene Verträge
->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
```

Muss geändert werden zu: Alle sichtbaren Verträge (mit `is_private` Logik).

---

## Implementierungsschritte

### 1. Migration erstellen

**Datei:** `lib/Migration/Version010003Date20260121000000.php`

```php
// Neue Felder hinzufügen
$table->addColumn('is_private', Types::INTEGER, [
    'notnull' => true,
    'default' => 0,
]);

$table->addColumn('deleted_at', Types::DATETIME, [
    'notnull' => false,
    'default' => null,
]);
```

### 2. Contract Entity erweitern

**Datei:** `lib/Db/Contract.php`

```php
protected int $isPrivate = 0;
protected ?\DateTime $deletedAt = null;

public function getIsPrivate(): int {
    return $this->isPrivate;
}

public function setIsPrivate(int|bool $isPrivate): void {
    $this->isPrivate = (int)$isPrivate;
}

public function getDeletedAt(): ?\DateTime {
    return $this->deletedAt;
}

public function setDeletedAt(?\DateTime $deletedAt): void {
    $this->deletedAt = $deletedAt;
}

public function isDeleted(): bool {
    return $this->deletedAt !== null;
}
```

### 3. PermissionService erstellen (NEU)

**Datei:** `lib/Service/PermissionService.php`

```php
class PermissionService {
    public function __construct(
        private IConfig $config,
        private IGroupManager $groupManager,
    ) {}

    public function isEditor(string $userId): bool {
        return $this->hasPermission($userId, 'editors');
    }

    public function isViewer(string $userId): bool {
        return $this->hasPermission($userId, 'viewers');
    }

    public function isAdmin(string $userId): bool {
        return $this->groupManager->isAdmin($userId);
    }

    public function hasAccess(string $userId): bool {
        return $this->isAdmin($userId) || $this->isEditor($userId) || $this->isViewer($userId);
    }

    public function canEdit(string $userId): bool {
        return $this->isAdmin($userId) || $this->isEditor($userId);
    }

    public function canDeletePermanently(string $userId): bool {
        return $this->isAdmin($userId);
    }

    private function hasPermission(string $userId, string $configKey): bool {
        $entries = json_decode($this->config->getAppValue('contractmanager', $configKey, '[]'), true);

        foreach ($entries as $entry) {
            if (str_starts_with($entry, 'user:')) {
                if ($entry === 'user:' . $userId) {
                    return true;
                }
            } elseif (str_starts_with($entry, 'group:')) {
                $groupId = substr($entry, 6);
                if ($this->groupManager->isInGroup($userId, $groupId)) {
                    return true;
                }
            }
        }
        return false;
    }
}
```

### 4. ContractMapper anpassen

**Datei:** `lib/Db/ContractMapper.php`

```php
// Alle sichtbaren, aktiven Verträge (nicht archiviert, nicht gelöscht)
public function findAllVisible(string $userId, bool $isAdmin): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->getTableName())
       ->where($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
       ->andWhere($qb->expr()->isNull('deleted_at'));

    if (!$isAdmin) {
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->eq('is_private', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
                $qb->expr()->eq('created_by', $qb->createNamedParameter($userId))
            )
        );
    }

    $qb->orderBy('end_date', 'ASC');
    return $this->findEntities($qb);
}

// Papierkorb für User (nur eigene gelöschte)
public function findDeletedByUser(string $userId): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->getTableName())
       ->where($qb->expr()->isNotNull('deleted_at'))
       ->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
       ->orderBy('deleted_at', 'DESC');

    return $this->findEntities($qb);
}

// Admin-Papierkorb (ALLE gelöschten)
public function findAllDeleted(): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->getTableName())
       ->where($qb->expr()->isNotNull('deleted_at'))
       ->orderBy('deleted_at', 'DESC');

    return $this->findEntities($qb);
}

// Für Auto-Cleanup: Alte gelöschte Verträge (> 30 Tage, keine Admins)
public function findExpiredDeleted(\DateTime $cutoffDate, array $adminUserIds): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
       ->from($this->getTableName())
       ->where($qb->expr()->isNotNull('deleted_at'))
       ->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($cutoffDate, IQueryBuilder::PARAM_DATE)));

    // Admins ausschließen
    if (!empty($adminUserIds)) {
        $qb->andWhere($qb->expr()->notIn('created_by', $qb->createNamedParameter($adminUserIds, IQueryBuilder::PARAM_STR_ARRAY)));
    }

    return $this->findEntities($qb);
}
```

### 5. ContractService anpassen

**Datei:** `lib/Service/ContractService.php`

```php
// Soft-Delete: In Papierkorb verschieben
public function softDelete(Contract $contract): Contract {
    $contract->setDeletedAt(new \DateTime());
    return $this->mapper->update($contract);
}

// Wiederherstellen aus Papierkorb
public function restore(Contract $contract): Contract {
    $contract->setDeletedAt(null);
    return $this->mapper->update($contract);
}

// Endgültig löschen (nur Admin)
public function deletePermanently(Contract $contract): void {
    $this->mapper->delete($contract);
}

// Zugriffsprüfungen...
public function checkReadAccess(Contract $contract, string $userId, bool $isAdmin): void {
    if ($isAdmin) {
        return;
    }

    if ($contract->getIsPrivate() && $contract->getCreatedBy() !== $userId) {
        throw new ForbiddenException('Kein Zugriff auf diesen privaten Vertrag');
    }
}

public function checkWriteAccess(Contract $contract, string $userId, bool $isAdmin, bool $isEditor): void {
    $this->checkReadAccess($contract, $userId, $isAdmin);

    if (!$isAdmin && !$isEditor) {
        throw new ForbiddenException('Keine Berechtigung zum Bearbeiten');
    }
}
```

### 6. TrashCleanupJob erstellen (NEU)

**Datei:** `lib/BackgroundJob/TrashCleanupJob.php`

```php
class TrashCleanupJob extends TimedJob {

    public function __construct(
        ITimeFactory $time,
        private ContractMapper $mapper,
        private IGroupManager $groupManager,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(86400); // Täglich
    }

    protected function run($argument): void {
        $cutoffDate = new \DateTime('-30 days');

        // Admin-UserIds ermitteln (deren Verträge nicht auto-gelöscht werden)
        $adminGroup = $this->groupManager->get('admin');
        $adminUserIds = [];
        if ($adminGroup !== null) {
            foreach ($adminGroup->getUsers() as $user) {
                $adminUserIds[] = $user->getUID();
            }
        }

        $expiredContracts = $this->mapper->findExpiredDeleted($cutoffDate, $adminUserIds);

        foreach ($expiredContracts as $contract) {
            $this->mapper->delete($contract);
            $this->logger->info('Auto-deleted contract from trash', [
                'app' => Application::APP_ID,
                'contractId' => $contract->getId(),
                'contractName' => $contract->getName(),
            ]);
        }
    }
}
```

### 7. ContractController anpassen

**Datei:** `lib/Controller/ContractController.php`

```php
// Soft-Delete (Editor + Admin)
#[NoAdminRequired]
public function softDelete(int $id): JSONResponse {
    $contract = $this->contractService->find($id);
    $isAdmin = $this->permissionService->isAdmin($this->userId);
    $isEditor = $this->permissionService->isEditor($this->userId);

    $this->contractService->checkWriteAccess($contract, $this->userId, $isAdmin, $isEditor);
    $this->contractService->softDelete($contract);

    return new JSONResponse(['status' => 'ok']);
}

// Wiederherstellen (eigene oder Admin)
#[NoAdminRequired]
public function restore(int $id): JSONResponse {
    $contract = $this->contractService->find($id);
    $isAdmin = $this->permissionService->isAdmin($this->userId);

    if (!$isAdmin && $contract->getCreatedBy() !== $this->userId) {
        throw new ForbiddenException('Keine Berechtigung');
    }

    $this->contractService->restore($contract);
    return new JSONResponse(['status' => 'ok']);
}

// Endgültig löschen (NUR Admin - KEIN @NoAdminRequired)
public function deletePermanently(int $id): JSONResponse {
    $contract = $this->contractService->find($id);
    $this->contractService->deletePermanently($contract);
    return new JSONResponse(['status' => 'ok']);
}

// Papierkorb leeren (NUR Admin)
public function emptyTrash(): JSONResponse {
    $deletedContracts = $this->contractMapper->findAllDeleted();
    foreach ($deletedContracts as $contract) {
        $this->contractService->deletePermanently($contract);
    }
    return new JSONResponse(['status' => 'ok', 'deleted' => count($deletedContracts)]);
}

// Papierkorb anzeigen
#[NoAdminRequired]
public function trash(): JSONResponse {
    $isAdmin = $this->permissionService->isAdmin($this->userId);

    if ($isAdmin) {
        $contracts = $this->contractMapper->findAllDeleted();
    } else {
        $contracts = $this->contractMapper->findDeletedByUser($this->userId);
    }

    return new JSONResponse($contracts);
}
```

### 8-14. Frontend & weitere Schritte

(wie im vorherigen Plan, angepasst an Papierkorb-Logik)

---

## Zu ändernde Dateien

### Backend (NEU)
| Datei | Änderung |
|-------|----------|
| `lib/Migration/Version010003Date20260121000000.php` | is_private + deleted_at Felder |
| `lib/Service/PermissionService.php` | Berechtigungsprüfung |
| `lib/BackgroundJob/TrashCleanupJob.php` | Auto-Cleanup nach 30 Tagen |

### Backend (UPDATE)
| Datei | Änderung |
|-------|----------|
| `lib/Db/Contract.php` | isPrivate + deletedAt Properties |
| `lib/Db/ContractMapper.php` | findAllVisible(), findDeletedByUser(), findAllDeleted() |
| `lib/Service/ContractService.php` | softDelete(), restore(), deletePermanently() |
| `lib/Controller/ContractController.php` | Papierkorb-Endpunkte |
| `lib/Controller/SettingsController.php` | Permissions-Endpunkte |
| `appinfo/routes.php` | Neue Routes für Papierkorb |
| `lib/AppInfo/Application.php` | TrashCleanupJob registrieren |

### Frontend (UPDATE)
| Datei | Änderung |
|-------|----------|
| `src/App.vue` | Papierkorb-Tab in Navigation |
| `src/views/Trash.vue` | NEU - Papierkorb-Ansicht |
| `src/views/Settings.vue` | Editor/Viewer-Konfiguration |
| `src/components/ContractForm.vue` | Privat-Checkbox |
| `src/components/ContractListItem.vue` | Löschen-Button, Ersteller-Anzeige, Privat-Badge |
| `src/store/contracts.js` | Permissions + Trash-Actions |

---

## Neue Übersetzungsstrings

```json
{
  "Berechtigungen": "Permissions",
  "Editor-Berechtigung": "Editor permission",
  "Viewer-Berechtigung": "Viewer permission",
  "Privater Vertrag (nur für mich sichtbar)": "Private contract (only visible to me)",
  "Privat": "Private",
  "Erstellt von": "Created by",
  "Papierkorb": "Trash",
  "Löschen": "Delete",
  "Endgültig löschen": "Delete permanently",
  "Wiederherstellen": "Restore",
  "Papierkorb leeren": "Empty trash",
  "Verträge werden nach 30 Tagen automatisch endgültig gelöscht.": "Contracts are automatically permanently deleted after 30 days.",
  "Als Admin werden Ihre gelöschten Verträge nicht automatisch gelöscht.": "As admin, your deleted contracts are not automatically deleted.",
  "Vertrag endgültig löschen?": "Permanently delete contract?",
  "Der Vertrag wird unwiderruflich gelöscht.": "The contract will be permanently deleted.",
  "Papierkorb wirklich leeren?": "Really empty trash?",
  "Alle Verträge werden unwiderruflich gelöscht.": "All contracts will be permanently deleted.",
  "Vertrag in Papierkorb verschoben": "Contract moved to trash",
  "Vertrag wiederhergestellt": "Contract restored",
  "Vertrag endgültig gelöscht": "Contract permanently deleted",
  "Papierkorb geleert": "Trash emptied"
}
```

---

## Implementierungsreihenfolge

1. Migration erstellen (is_private + deleted_at)
2. Contract Entity erweitern
3. PermissionService erstellen
4. ContractMapper anpassen
5. ContractService erweitern (softDelete, restore, deletePermanently)
6. TrashCleanupJob erstellen
7. ContractController Papierkorb-Endpunkte
8. Routes anpassen
9. SettingsController Permissions-Endpunkte
10. Frontend: Papierkorb-Tab + View
11. Frontend: Settings mit Editor/Viewer-Picker
12. Frontend: Privat-Checkbox
13. Frontend: Löschen-Buttons + Dialoge
14. Store: Permissions + Trash-Actions
15. Übersetzungen
16. Testen

---

## Verifikation

### Als Viewer testen:
- [ ] Alle nicht-privaten Verträge sehen
- [ ] Private Verträge anderer User NICHT sehen
- [ ] KEINE Bearbeitung möglich
- [ ] KEIN Löschen möglich
- [ ] Papierkorb nicht sichtbar

### Als Editor testen:
- [ ] Alle sichtbaren Verträge bearbeiten können
- [ ] Vertrag löschen → erscheint im eigenen Papierkorb
- [ ] Eigene gelöschte Verträge wiederherstellen
- [ ] KEIN endgültiges Löschen möglich
- [ ] Private Verträge anderer User NICHT sehen

### Als Admin testen:
- [ ] Alle Verträge sehen (auch private)
- [ ] Admin-Papierkorb sieht ALLE gelöschten Verträge
- [ ] Einzelne Verträge endgültig löschen
- [ ] "Papierkorb leeren" funktioniert
- [ ] Eigene gelöschte Verträge werden NICHT auto-gelöscht

### Auto-Cleanup testen:
- [ ] Editor-Verträge nach 30 Tagen automatisch gelöscht
- [ ] Admin-Verträge werden NICHT automatisch gelöscht

### API-Tests:
- [ ] DELETE /contracts/{id} als Viewer → 403
- [ ] DELETE /contracts/{id}/permanent als Editor → 403
- [ ] DELETE /contracts/{id}/permanent als Admin → 200
- [ ] POST /trash/empty als Editor → 403
- [ ] POST /trash/empty als Admin → 200

---

## Bewusst NICHT implementiert

| Feature | Grund |
|---------|-------|
| Granulare Feld-Sichtbarkeit | Zu komplex |
| Sharing einzelner Verträge | Overengineering |
| Nextcloud-native "Für Gruppen aktivieren" | Unterscheidet nicht Editor/Viewer |
| Papierkorb für Viewer | Viewer kann nicht löschen |

---

*Erstellt: 2026-01-21*
*Status: Bereit zur Implementierung*
