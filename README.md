# ContractManager

Nextcloud App zur Vertragsverwaltung mit automatischen Kündigungserinnerungen.

## Features

- Zentrale Vertragsverwaltung
- Automatische Erinnerungen vor Kündigungsfristen
- Kategorisierung von Verträgen
- Archiv für beendete Verträge
- Integration mit Nextcloud Files für Anhänge

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

cpc Momentum - https://cpc-momentum.de
