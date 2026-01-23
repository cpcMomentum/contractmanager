# ContractManager – Produktbeschreibung (MVP)

---

## 0. Produktname

**ContractManager** – Nextcloud-App zur Verwaltung von Verträgen mit Kündigungserinnerungen.

---

## 1. Produktvision & Zweck

### Worum geht es?
Zentrale Verwaltung von laufenden Verträgen (Software-Abos, Telekommunikation, Versicherungen, etc.) innerhalb von Nextcloud mit automatischen Erinnerungen vor Ablauf der Kündigungsfristen.

### Welches Problem wird gelöst?
- Verträge sind verstreut und unübersichtlich
- Kündigungsfristen werden vergessen → ungewollte Verlängerungen und Kosten
- Kein Gesamtüberblick über laufende Verpflichtungen

### Für wen ist das Produkt gedacht?
- Kleine bis mittlere Unternehmen
- Selbstständige mit mehreren Verträgen
- Privatpersonen, die ihre Verträge zentral verwalten wollen

**Deployment-Ansatz:** Je Nextcloud-Instanz eine Installation. Keine Multi-Mandanten-Logik – Trennung erfolgt durch separate Nextcloud-Instanzen (z.B. privat vs. geschäftlich).

---

## 2. Zielgruppe & Berechtigungen

### Rollen

| Rolle | Beschreibung |
|-------|--------------|
| **Admin** | Nextcloud-Administrator, konfiguriert die App |
| **User** | Berechtigter Nutzer mit Zugriff auf ContractManager |

### Berechtigungsmodell (MVP)

- Admin legt in den App-Einstellungen fest, welche Nextcloud-User Zugriff haben
- Wer Zugriff hat, sieht **alle Verträge** (inkl. Archiv) und kann bearbeiten
- Keine granulare Einzelsteuerung ("nur eigene Verträge") im MVP

### Aktionen nach Rolle

| Aktion | User | Admin |
|--------|------|-------|
| Verträge ansehen | ✅ | ✅ |
| Verträge anlegen/bearbeiten | ✅ | ✅ |
| Archivieren | ✅ | ✅ |
| Archiv einsehen | ✅ | ✅ |
| Aus Archiv wiederherstellen | ✅ | ✅ |
| Endgültig löschen | ❌ | ✅ |
| Kategorien verwalten | ❌ | ✅ |
| App-Einstellungen | ❌ | ✅ |

---

## 3. Kernfunktionalitäten

### Was kann der Nutzer tun?

| Funktion | Beschreibung |
|----------|--------------|
| Vertrag anlegen | Neuen Vertrag mit allen relevanten Daten erfassen |
| Vertrag bearbeiten | Bestehende Daten ändern |
| Vertrag archivieren | Aus Hauptliste entfernen (nicht löschen) |
| Dokumente verknüpfen | Nextcloud-Ordner und/oder Hauptvertragsdatei verlinken |
| Liste ansehen | Alle Verträge mit Filter- und Suchfunktion |
| Erinnerung erhalten | Notification via Nextcloud Talk und/oder E-Mail |

### Was ist explizit NICHT Teil des MVP?

- Granulare Berechtigungen (nur eigene Verträge sehen)
- Genehmigungsworkflow
- Multi-Mandanten-Auswahl innerhalb der App
- Angebotsmanagement / Projektnummern
- Automatische Vertragserkennung aus PDFs

---

## 4. User Flows & Nutzungsszenarien

### Flow 1: Neuen Vertrag anlegen

1. User öffnet ContractManager
2. Klickt "Neuer Vertrag"
3. Füllt Formular aus (Pflichtfelder + optionale Felder)
4. Verknüpft optional Nextcloud-Ordner/Datei
5. Speichert → Vertrag erscheint in Liste mit Status "Aktiv"

### Flow 2: Erinnerung erhalten & handeln

1. System sendet Erinnerung (X Tage vor Kündigungsfrist)
2. User erhält Nachricht in Nextcloud Talk und/oder per E-Mail
3. User öffnet Vertrag im ContractManager
4. Entscheidet: Kündigen oder weiterlaufen lassen
5. Bei Kündigung: Setzt Status auf "Gekündigt"
6. Bei Verlängerung: Status bleibt "Aktiv", System berechnet nächstes Kündigungsdatum

### Flow 3: Vertrag archivieren

1. Vertrag ist beendet oder nicht mehr relevant
2. User öffnet Vertrag
3. Klickt "Archivieren"
4. Vertrag verschwindet aus Hauptliste, liegt im Archiv
5. Optional: Admin kann später endgültig löschen

### Flow 4: Vertrag wiederherstellen

1. User öffnet Archiv-Ansicht
2. Wählt archivierten Vertrag
3. Klickt "Wiederherstellen"
4. Vertrag erscheint wieder in Hauptliste

---

## 5. Geschäftslogik & Regeln

### Kündigungsdatum-Berechnung

- **Automatisch:** Enddatum minus Kündigungsfrist = spätestes Kündigungsdatum
- **Bei automatischer Verlängerung:** Nach Ablauf des Enddatums wird automatisch neues Enddatum + Kündigungsdatum berechnet (basierend auf Verlängerungszeitraum)
- **Manuelles Überschreiben:** Optional möglich

### Erinnerungslogik

**Voraussetzungen für Erinnerung:**
- Status = "Aktiv"
- Erinnerung aktiv = Ja

**Keine Erinnerung bei:**
- Status = "Gekündigt", "Beendet" oder "Archiviert"
- Erinnerung aktiv = Nein

**Zeitpunkte:**
- Zwei Erinnerungen: Erste Warnung (z.B. 14 Tage) + letzte Warnung (z.B. 3 Tage)
- Admin konfiguriert globale Default-Werte
- Pro Vertrag überschreibbar

**Kanäle:**
- Nextcloud Talk: Admin definiert einmalig den Ziel-Chat
- E-Mail: Jeder User aktiviert/deaktiviert für sich selbst

### Status-Übergänge

```
Aktiv → Gekündigt → Beendet → Archiviert
                              ↓
                    Wiederhergestellt (zurück zu vorherigem Status)
```

### Weitere Regeln

- Normale User können nicht direkt löschen – nur archivieren
- Endgültiges Löschen nur durch Admin
- Kategorien werden zentral durch Admin verwaltet

---

## 6. Daten & Objekte

### Vertrag (Hauptobjekt)

| Feld | Pflicht | Typ | Beschreibung |
|------|---------|-----|--------------|
| id | Auto | Integer | Eindeutige ID |
| name | Ja | Text | Vertragsbezeichnung |
| vertragspartner | Ja | Text (Autocomplete) | Anbieter/Dienstleister |
| status | Ja | Enum | Aktiv / Gekündigt / Beendet / Archiviert |
| kategorie_id | Nein | FK → Kategorie | Vertragsart |
| startdatum | Ja | Datum | Vertragsbeginn |
| enddatum | Ja | Datum | Vertragsende |
| kuendigungsfrist | Ja | Text | z.B. "3 Monate" |
| vertragstyp | Ja | Enum | Befristet / Automatische Verlängerung |
| verlaengerungszeitraum | Bedingt | Text | Nur bei auto. Verlängerung, z.B. "12 Monate" |
| kosten | Nein | Decimal | Netto-Betrag |
| waehrung | Nein | Enum | EUR / USD |
| kostenintervall | Nein | Enum | monatlich / jährlich / einmalig |
| vertragsordner | Nein | Text | Nextcloud-Pfad zum Ordner |
| hauptvertrag | Nein | Text | Nextcloud-Pfad zur Datei |
| erinnerung_aktiv | Nein | Boolean | Erinnerung aktiviert? |
| erinnerung_tage | Nein | Integer | Überschreibt globalen Default |
| notizen | Nein | Text | Freitext |
| erstellt_von | Auto | FK → User | Nextcloud User-ID |
| erstellt_am | Auto | Timestamp | Erstellungsdatum |
| geaendert_am | Auto | Timestamp | Letzte Änderung |

### Kategorie

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | Integer | Eindeutige ID |
| name | Text | Kategoriename |
| sortierung | Integer | Reihenfolge im Dropdown |

**Default-Kategorien:**
- Software
- Telekommunikation
- Versicherung
- Miete/Leasing
- Dienstleistung
- Sonstige

### App-Einstellungen (Admin)

| Einstellung | Typ | Beschreibung |
|-------------|-----|--------------|
| berechtigte_user | Array | Liste der User mit Zugriff |
| talk_chat_id | Text | Nextcloud Talk Chat für Erinnerungen |
| erinnerung_tage_1 | Integer | Erste Erinnerung (Default: 14 Tage) |
| erinnerung_tage_2 | Integer | Zweite Erinnerung (Default: 3 Tage) |

### User-Einstellungen (pro User)

| Einstellung | Typ | Beschreibung |
|-------------|-----|--------------|
| email_erinnerung | Boolean | E-Mail-Erinnerungen aktiviert? |

---

## 7. Ausblick

### Potenzielle Erweiterungen (nicht MVP)

| Feature | Beschreibung | Priorität |
|---------|--------------|-----------|
| Erweitertes Berechtigungssystem | Lese-Rolle, nur eigene Verträge sehen | Mittel |
| Genehmigungsworkflow | Vertrag anlegen → Freigabe durch Vorgesetzten | Mittel |
| Kostenübersicht | Dashboard mit monatlichen/jährlichen Gesamtkosten | Niedrig |
| Multi-Mandanten | Mehrere Firmen in einer Instanz verwalten | Niedrig |
| PDF-Erkennung | Automatische Datenextraktion aus Vertragsdokumenten | Niedrig |

### Explizit separates Projekt

- **Angebotsmanager:** Eigener Workflow für Angebotserstellung, Genehmigung, Projektnummern-Vergabe – gehört nicht in ContractManager

---

*Erstellt: 2026-01-16*
*Version: 1.0 (MVP)*
