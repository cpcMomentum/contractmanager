# Session-Zusammenfassung 03.02.2026

## Thema: App-Signatur und Release-Vorbereitung

---

### Übersicht

Nextcloud hat den Certificate-Request für ContractManager genehmigt. Die App wurde signiert und ein Release-Tarball für den App Store erstellt.

---

### Erledigte Aufgaben

#### 1. Zertifikat abgeholt

- **PR #893** in `nextcloud/app-certificate-requests` wurde gemerged
- Zertifikat heruntergeladen und gespeichert unter:
  - `/Users/axel/.nextcloud/certificates/contractmanager.crt`
  - Gültig bis: 2036-05-10

#### 2. App signiert

- Private Key: `/Users/axel/.nextcloud/certificates/contractmanager.key`
- Signierung via `occ integrity:sign-app` im Docker-Container
- `appinfo/signature.json` erstellt und verifiziert

#### 3. Ko-fi Donation-Link hinzugefügt

- Account erstellt: `AxDe888`
- Link in `info.xml`: `<donation>https://ko-fi.com/AxDe888</donation>`
- Commit: `e04cb4b` via PR #2

#### 4. Release-Tarball erstellt

- **Datei:** `contractmanager-0.1.4.tar.gz` (3,6 MB)
- **Inhalt:** appinfo, css, img, js, lib, templates, LICENSE, README, CHANGELOG
- **Signatur:** Verifiziert mit `occ integrity:check-app`

---

### Wichtige Erkenntnisse

#### macOS tar und AppleDouble-Dateien

Problem: macOS tar erstellt automatisch `._*` Dateien für Extended Attributes, die die Signatur ungültig machen.

**Lösung:**
```bash
COPYFILE_DISABLE=1 tar -czvf app.tar.gz appfolder
```

#### Signatur-Workflow

1. Release-Ordner mit nur notwendigen Dateien erstellen
2. In Docker-Container kopieren
3. `occ integrity:sign-app` ausführen
4. `signature.json` zurückkopieren
5. Tarball erstellen mit `COPYFILE_DISABLE=1`

---

### Dateien

| Datei | Beschreibung |
|-------|--------------|
| `appinfo/info.xml` | Mit Donation-Link aktualisiert |
| `appinfo/signature.json` | Neu erstellt (gitignored) |
| `contractmanager-0.1.4.tar.gz` | Release-Paket für App Store |

---

### Nächster Schritt

- [ ] Tarball im Nextcloud App Store hochladen: https://apps.nextcloud.com

---

### Commits

| Hash | Beschreibung |
|------|--------------|
| `e04cb4b` | chore: Add Ko-fi donation link (#2) |

---

*Session: 03.02.2026, ca. 21:30 - 23:00*
