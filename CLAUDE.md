# CLAUDE.md – ContractManager

---

## App-Übersicht

**ContractManager** – Nextcloud-App zur Verwaltung von Verträgen mit Kündigungserinnerungen.

**Status:** Fertig (v1.0.0)

---

## Dokumentation & Pläne

### Source of Truth
- **Produktbeschreibung:** `docs/produktbeschreibung.md` - WAS gebaut wird
- **Implementierungsplan:** `docs/implementierungsplan.md` - WIE es gebaut wird

### Regeln
1. **Vor jeder Implementierung:** Plan gegen Produktbeschreibung prüfen
2. **Bei Abweichungen:** Plan aktualisieren BEVOR Code geschrieben wird
3. **Nach jeder Phase:** Plan-Status aktualisieren

### Referenzen
- **Nextcloud-Guides:** `../nextcloud-app-dev-guide.md` und `../nextcloud-app-summary.md`
- **AI-First-Workflow:** `../../AAA_Allgemeiner_Claude_Code_Chat/ai-first-approach/`

---

## Kernfunktionen (MVP)

1. Verträge anlegen, bearbeiten, archivieren
2. Kündigungsfristen verwalten (automatische Berechnung)
3. Erinnerungen via Nextcloud Talk + E-Mail
4. Dokumente aus Nextcloud verknüpfen (Ordner + Datei)
5. Admin-Berechtigungssteuerung (wer sieht die App)

---

## Aktueller Stand

| Phase | Beschreibung | Status |
|-------|--------------|--------|
| 1 | Basis-CRUD | ✅ |
| 2 | Archiv & Validierung | ✅ |
| 3 | Erinnerungen | ✅ |
| 4 | Settings & Berechtigungen | ✅ |
| 5 | Testing & Polish | ✅ |

**Status:** Alle Phasen abgeschlossen - Release v1.0.0

---

## Technische Lessons Learned

Diese Erkenntnisse aus der bisherigen Entwicklung MÜSSEN beachtet werden:

| Erkenntnis | Lösung |
|------------|--------|
| PostgreSQL: PARAM_BOOL konvertiert false zu "f" | `PARAM_INT` mit 0/1 verwenden |
| Tabellen-Namen max ~25 Zeichen | `contractmgr_` statt `contractmanager_` |
| Archived als Status führt zu Statusverlust | Separates Boolean-Feld verwenden |
| Nextcloud Entity: Boolean-Properties | Als `int` definieren, nicht `bool` |

---

## Tech-Stack

- **Nextcloud:** 32+
- **PHP:** 8.2+
- **Frontend:** Vue.js 2.7, @nextcloud/vue
- **Database:** Nextcloud Query Builder (PostgreSQL-kompatibel)
- **Build:** npm + webpack

---

## Projekt-Struktur

```
contractmanager/
├── docs/
│   ├── produktbeschreibung.md    ← Source of Truth
│   ├── implementierungsplan.md   ← Aktueller Plan
│   └── archive/                  ← Alte/überholte Pläne
├── lib/
│   ├── Controller/
│   ├── Service/
│   ├── Db/
│   └── Migration/
├── src/
│   ├── views/
│   ├── components/
│   ├── store/
│   └── services/
└── CLAUDE.md                     ← Diese Datei
```

---

*Erstellt: 2026-01-16*
*Aktualisiert: 2026-01-19*
