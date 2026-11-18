> Extends: node_modules/nc-app-tooling/docs/design-system.md

# Design-System VertragsWerk

Hier steht nur, was VertragsWerk über das gemeinsame Design-System hinaus regelt.

## Navigation

- Fuß: Neuerungen · Einstellungen · Verwaltung.
- **Einstellungen**: persönlich, Gruppe „Persönlich“ mit Benachrichtigungen, Betragsangabe,
  Automatisches Backup und Kalender-Abo.
- **Verwaltung**: app-weit, nur für Nextcloud-Admins. Gruppen „Zugriff“ (Berechtigungen,
  Verträge übertragen), „Verträge“ (Allgemein, Zusatzfelder, KI-Vertragsanalyse, Kategorien)
  und „Lizenz“ (WerkPlus).
- Kategorien sind app-weit und stehen deshalb nur in der Verwaltung. Alle anderen sehen sie
  als Filter in der App-Navigation.
- Der aktive Punkt steht noch nicht in der URL (Soll-Regel), VertragsWerk hat keinen Router.

## WerkPlus

- Nur Nextcloud-Admins sehen die Verwaltung und damit den WerkPlus-Abschnitt. Der Hinweis
  für Nicht-Admins (Abschnitt 5, Punkt 5) erscheint deshalb nie.
