# App Store Release - Anleitung

## Status

| Schritt | Status |
|---------|--------|
| info.xml vollständig | ✅ |
| CHANGELOG.md | ✅ |
| Lizenz SPDX-Format | ✅ |
| Screenshots | ✅ |
| Zertifikat beantragt | ⏳ PR offen |
| App registriert | ⬜ |
| Release hochgeladen | ⬜ |

---

## 1. Zertifikat

### Private Key (lokal gespeichert)
```
~/.nextcloud/certificates/contractmanager.key
```
**WICHTIG:** Sicher aufbewahren! Ohne Key keine Updates möglich.

### Certificate Signing Request
- PR erstellt: https://github.com/nextcloud/app-certificate-requests/pulls
- Suche nach "contractmanager"

### Nach PR-Merge
Das Zertifikat wird hier verfügbar sein:
```
https://github.com/nextcloud/app-certificate-requests/blob/master/contractmanager/contractmanager.crt
```

Herunterladen und speichern als:
```
~/.nextcloud/certificates/contractmanager.crt
```

---

## 2. App im App Store registrieren

Nach Erhalt des Zertifikats:

1. **Account erstellen** auf https://apps.nextcloud.com (falls nicht vorhanden)

2. **App registrieren:**
   - Zertifikat-Inhalt (`contractmanager.crt`) bereitstellen
   - Signatur erstellen:
   ```bash
   echo -n "contractmanager" | openssl dgst -sha512 \
     -sign ~/.nextcloud/certificates/contractmanager.key | openssl base64
   ```

---

## 3. Release erstellen

### 3.1 Version in info.xml prüfen
```xml
<version>0.1.4</version>
```

### 3.2 Frontend bauen
```bash
cd /Users/axel/nextcloud_cpcMomentum/AAB_Coding_Projekte/contractmanager
npm run build
```

### 3.3 Tarball erstellen
```bash
# Ins übergeordnete Verzeichnis wechseln
cd /Users/axel/nextcloud_cpcMomentum/AAB_Coding_Projekte

# Tarball erstellen (ohne unnötige Dateien)
tar --exclude='contractmanager/node_modules' \
    --exclude='contractmanager/.git' \
    --exclude='contractmanager/src' \
    --exclude='contractmanager/docs' \
    --exclude='contractmanager/.github' \
    --exclude='contractmanager/CLAUDE.md' \
    -czf contractmanager-0.1.4.tar.gz contractmanager
```

### 3.4 Tarball signieren
```bash
openssl dgst -sha512 \
  -sign ~/.nextcloud/certificates/contractmanager.key \
  contractmanager-0.1.4.tar.gz | openssl base64
```

### 3.5 GitHub Release erstellen
1. Gehe zu https://github.com/cpcMomentum/contractmanager/releases
2. "Draft a new release"
3. Tag: `v0.1.4`
4. Titel: `v0.1.4`
5. Tarball als Asset anhängen
6. Release Notes aus CHANGELOG.md kopieren

### 3.6 Im App Store hochladen
- Download-URL des Tarballs (von GitHub Release)
- Signatur aus Schritt 3.4

---

## Checkliste für zukünftige Releases

- [ ] Version in `appinfo/info.xml` erhöhen
- [ ] CHANGELOG.md aktualisieren
- [ ] `npm run build`
- [ ] Git commit & push
- [ ] Tarball erstellen
- [ ] Tarball signieren
- [ ] GitHub Release erstellen
- [ ] Im App Store hochladen

---

*Erstellt: 2026-01-23*
