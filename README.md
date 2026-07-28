# ContractManager

Nextcloud App zur Vertragsverwaltung mit automatischen Kündigungserinnerungen.

## Features

- Zentrale Vertragsverwaltung
- Automatische Erinnerungen vor Kündigungsfristen
- Kategorisierung von Verträgen
- Archiv für beendete Verträge
- Integration mit Nextcloud Files für Anhänge
- Export/Import der eigenen Vertragsdaten über Nextcloud [user_migration](https://github.com/nextcloud/user_migration)

## Datenexport und -import

Die Vertragsdaten eines Nutzers (inklusive Kategorien sowie Kündigungs- und
Erinnerungseinstellungen) lassen sich über den Standard-Mechanismus von Nextcloud
exportieren und wieder importieren – „Persönliche Einstellungen → Daten migrieren"
bzw. `occ user:export` / `occ user:import`. Dokument-Anhänge sind als Pfad-Referenzen
hinterlegt und werden vom Standard-Datei-Migrator mitgenommen.

**Voraussetzung:** Die offizielle App
[user_migration](https://github.com/nextcloud/user_migration) muss installiert sein.
Ohne sie ist der Migrator zwar registriert, wird aber nicht ausgelöst.

## Anforderungen

- Nextcloud 32+
- PHP 8.2+
- Node.js 20+

## Installation

### Entwicklung

```bash
# Dependencies installieren
npm install
composer install

# Frontend bauen (Development)
npm run watch

# Frontend bauen (Production)
npm run build
```

### Deployment

1. `npm run build` ausführen
2. Ordner `contractmanager/` nach `/var/www/nextcloud/apps/` kopieren
3. App aktivieren: `php occ app:enable contractmanager`

## Lizenz

AGPL-3.0-or-later

## Autor

cpcMomentum GmbH - https://cpcMomentum.com
