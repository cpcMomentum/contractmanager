# TechStack Preset: Nextcloud App

> **Preset-ID:** nextcloud-app
> **Beschreibung:** Nextcloud App Entwicklung — PHP Backend (OCP APIs) + Vue.js Frontend (@nextcloud/vue).
> **Erkennungssignal:** `appinfo/info.xml` im Projekt-Root.

---

## 1. Core Stack

### Backend
| Technologie | Version | Zweck |
|-------------|---------|-------|
| PHP | 8.1+ (Type Hints Pflicht) | Sprache |
| Nextcloud OCP APIs | NC 30+ | Framework (KEINE frei waehlbaren Frameworks) |
| Nextcloud IContainer | - | Dependency Injection |

### Frontend
| Technologie | Version | Zweck |
|-------------|---------|-------|
| Vue.js | 2 (LTS) oder 3 | Frontend Framework |
| @nextcloud/vue | latest | NC-native UI-Komponenten (NcAppContent, NcButton, etc.) |
| Vuex 3 / Pinia | - | State Management (Vuex fuer Vue 2, Pinia fuer Vue 3) |
| vue-router | 3 (Vue 2) / 4 (Vue 3) | Routing — **Hash-Mode Pflicht, NICHT History-Mode** |
| @nextcloud/axios | latest | HTTP Client |
| @nextcloud/router | latest | URL-Generierung (generateUrl()) |
| @nextcloud/l10n | latest | Uebersetzungen |

### Database
| Technologie | Zweck |
|-------------|-------|
| MySQL / MariaDB / PostgreSQL / SQLite | Vom Host vorgegeben — NICHT frei waehlbar |
| OCP\IDBConnection Query Builder | Datenbankzugriff — **raw SQL ist VERBOTEN** |
| OCP\Migration\IMigrationStep | Schema-Migrationen in lib/Migration/ |

## 2. Tooling

### PHP
| Tool | Zweck |
|------|-------|
| `php -l` | Syntax-Check (typisch im Docker-Container) |
| PHPUnit + Nextcloud TestCase | Unit Tests |
| psalm / phpstan | Statische Analyse (optional, app-spezifisch) |

### JavaScript
| Tool | Zweck |
|------|-------|
| webpack (@nextcloud/webpack-vue-config) | Build |
| eslint (@nextcloud/eslint-config) | Linting |
| jest | Tests (optional) |

## 3. Infrastructure

| Aspekt | Details |
|--------|---------|
| Container | **NEIN** — App laeuft IN Nextcloud, nicht daneben |
| Lokale Entwicklung | Nextcloud im Docker (OrbStack), Mount nach `/var/www/html/custom_apps/contractmanager` |
| Deploy: App Store | Tarball + Signatur + REST API Upload |
| Deploy: Inhouse | rsync auf eigene Instanz + `occ upgrade` |
| CI/CD | App Store Release-Pipeline (NICHT GitHub Actions fuer Deploy) |
| Hosting | Nextcloud-Server (self-hosted oder Managed) |

## 4. Auth & Security

| Aspekt | Loesung |
|--------|---------|
| Auth | Vom Host — `OCP\IUserSession` injizieren, `getUser()` nutzen |
| CSRF | NC-Framework erzwingt `requesttoken` Header bei allen Writes |
| XSS | Vue-Rendering safe by default; in PHP: `p()` / `print_unescaped()` |
| SQLi | Nur Query Builder — raw SQL **verboten** |
| Lizenz | **AGPL-3.0-or-later** (App Store Pflicht) |

## 5. Versionierung

- SemVer in `appinfo/info.xml` → `<version>X.Y.Z</version>`
- Synchron in `package.json` → `"version": "X.Y.Z"`
- NC-Kompatibilitaet: `<dependencies><nextcloud min-version="30" max-version="33"/></dependencies>`

## Projektstruktur

```
<app-name>/
├── appinfo/
│   ├── info.xml            # PFLICHT — App-Metadaten
│   ├── routes.php          # URL-Routing (Controller-Mapping)
│   └── signature.json      # Release-Prozess generiert (Integrity-Check)
├── lib/
│   ├── AppInfo/
│   │   └── Application.php # Bootstrapping, DI-Registration
│   ├── Controller/         # extends OCP\AppFramework\Controller
│   ├── Service/            # Business Logic
│   ├── Db/                 # Entities + Mapper (extends QBMapper)
│   ├── Migration/          # Schema-Migrationen + Repair Steps
│   ├── Notification/       # Notifier (optional)
│   ├── Listener/           # Event Listener
│   └── Settings/           # Admin/Personal Settings
├── src/                    # Vue Frontend Source
│   ├── main.js             # Entry
│   ├── App.vue
│   ├── components/
│   ├── views/
│   ├── store/              # Vuex / Pinia
│   └── router.js
├── js/                     # KOMPILIERTES JS (commit Pflicht!)
│   ├── contractmanager-main.js
│   └── contractmanager-main.js.map
├── css/                    # KOMPILIERTES CSS-Bundle — getrackt + ausgeliefert (#246)
│   └── contractmanager-main.css
├── vendor/                 # Composer RUNTIME-Deps (--no-dev) — getrackt + ausgeliefert (#249)
│   ├── autoload.php        #   smalot/pdfparser (PDF-Textextraktion) + composer/ + symfony/
│   └── ...                 #   Dev-Deps (phpunit) bleiben gitignored!
├── templates/              # PHP-Templates (main.php als Vue-Mount-Point)
├── l10n/                   # Uebersetzungen (Transifex)
├── tests/                  # PHPUnit
├── docs/
│   └── produktbeschreibung.md
├── CHANGELOG.md
├── CLAUDE.md
├── composer.json
├── package.json
├── webpack.config.js       # require('@nextcloud/webpack-vue-config')
└── README.md
```

## Command Mappings

| Concept | Command |
|---------|---------|
| `lint_frontend` | `npm run lint` |
| `lint_backend` | `docker exec -t nextcloud-dev php -l /var/www/html/custom_apps/contractmanager/lib/**/*.php` |
| `format_frontend` | `npx prettier --write src/` |
| `format_backend` | `N/A` |
| `typecheck` | `N/A` |
| `test_frontend` | `npm run test` |
| `test_backend` | `docker exec -t nextcloud-dev php /var/www/html/custom_apps/contractmanager/vendor/bin/phpunit -c /var/www/html/custom_apps/contractmanager/tests/phpunit.xml` |
| `build_frontend` | `npm run build` |
| `build_backend` | `N/A` |
| `dep_audit_fe` | `npm audit` |
| `dep_audit_be` | `N/A` |
| `install_deps_fe` | `npm install` |
| `install_deps_be` | `composer install` |

> **Hinweis:** `contractmanager` wird bei `/adopt` durch den tatsaechlichen App-Namen (aus `appinfo/info.xml`) ersetzt. PHP-Lint laeuft im Docker-Container weil PHP auf macOS typisch nicht installiert ist.

## Plugins

| Kategorie | Plugin | Benoetigt? |
|-----------|--------|------------|
| Recherche | `context7` | Ja |
| Security | `security-guidance` | Ja |
| Testing | `playwright` | Optional (NC-Apps nutzen eher manuelles Testing) |
| Type-Check Frontend | `typescript-lsp` | Wenn TypeScript im Vue-Setup |
| UI Design | `frontend-design` | Optional |

## Pflicht-Konventionen

> **Nicht optional** — App Store Ablehnung oder NC-Major-Update-Breakage bei Verstoss.

| Regel | Grund |
|-------|-------|
| Nur `OCP\*` APIs, niemals `OC\*` | `OC\*` ist privat, instabil — Breakage bei jedem Major-Update |
| Kein raw SQL, nur Query Builder | SQL-Injection + DB-Portabilitaet (MySQL/Postgres/SQLite) |
| AGPL-3.0-or-later ueberall | Harte App-Store-Pflicht |
| Kein "Nextcloud" im App-Namen | Marken-Policy |
| Type Hints in PHP | Projektstandard |
| Assets-Pfad: `js/contractmanager-main.js` | NC laedt JS aus `js/`, nicht aus `dist/` |
| `npm run build` vor Commit bei Vue-Aenderungen | Ohne kompiliertes JS laeuft die App nicht |
| Keine `.htaccess`/`.user.ini` im Release | NC FilenameValidator strippt diese → Signatur bricht |
| Hash-Routing im Vue Router | NC-Apps laufen unter `/apps/contractmanager/` ohne History-API |

## Nicht-anwendbare ai-first-dev Skills

Diese Skills sind fuer NC-Apps **nicht relevant** und sollten uebersprungen werden:

| Skill | Grund |
|-------|-------|
| `setup-ci`, `setup-deploy` | CI/CD ist App Store Release, nicht GitHub Actions Deploy |
| `setup-logging` | Logging ueber NC-eigenes `OCP\ILogger` |
| `api-sync` | NC-Apps haben keine OpenAPI-Spec |
| `generate-legal-docs` | Lizenz ist fix AGPL |
| `qa` (Playwright) | Optional — manuelles Testing ueblicher bei NC-Apps |

---

## Skill-Konventionen

> **Diese Regeln werden von den bestehenden ai-first-dev Skills automatisch gelesen und angewendet.** Keine separaten Skills noetig — die Methodik bleibt gleich, nur die stack-spezifischen Regeln aendern sich.

### /release

#### Regeln

| Regel | Details |
|-------|---------|
| Branch-Pflicht | Release nur von `develop`, nicht von `main` oder Feature-Branches |
| Release-Branch | `release/vX.Y.Z` von `develop` abzweigen |
| Version synchron | `appinfo/info.xml` UND `package.json` muessen gleiche Version haben |
| Build vor Tarball | `npm install && npm run build` auf dem Release-Branch |
| Sign-Tree bereinigen | `.htaccess`, `.user.ini` aus Sign-Tree entfernen (NC FilenameValidator) |
| Signatur | `openssl dgst -sha512 -sign ~/.nextcloud/certificates/contractmanager.key` |
| Upgrade-Test PFLICHT | Vorversion installieren → neue Version drueberziehen → Integrity pruefen |
| Post-Release | Bei korrektem Workflow (Release von develop) ist main=develop → kein Sync noetig |
| Rollback | Defekte Version im App Store loeschen: `curl -X DELETE .../releases/<version>` |

#### Build-Artefakt-Asymmetrie (Lehre #245/#249 — WICHTIG)

Nicht alles, was Git ignoriert, darf aus dem Release fallen. Drei Klassen von Artefakten, die **ausgeliefert werden muessen**, aber unterschiedlich in Git liegen:

| Artefakt | In Git? | Muss in den Tarball? | Warum diese Regelung |
|----------|---------|----------------------|----------------------|
| `js/contractmanager-main.js` | **getrackt** | Ja | War schon immer getrackt |
| `css/contractmanager-main.css` | **getrackt** (`!css/…-main.css`) | Ja | #245: git-basiertes Packen liess die gitignorierte CSS fallen → ungestylte UI. Seit #246 getrackt |
| `vendor/` **Runtime** (smalot/pdfparser, symfony, composer/) | **getrackt** (Whitelist in `.gitignore`) | Ja | #245/#249: gitignoriertes vendor fiel aus dem Release → PDF-Textextraktion kaputt. Seit #249 Runtime-Pfade getrackt |
| `vendor/` **Dev** (phpunit, nextcloud/ocp) | **gitignored** | **Nein** | Blaeht signature.json + Tarball auf. Taucht nur bei `composer install` OHNE `--no-dev` auf |

**Kernregel:** `js/`, das kompilierte `css/`-Bundle und die `vendor/`-Runtime-Deps sind getrackt und liegen damit in `git archive HEAD` — der Release haengt nicht mehr an manuellen Worktree-Kopien. Der **Packaging-Guard (Check 15)** im geteilten Release-Skill bricht zusaetzlich hart ab, falls CSS oder `vendor/autoload.php` im finalen Tarball fehlen.

**Gefahr bei vendor:** Ein `composer install` ohne `--no-dev` zieht phpunit & Co. in `vendor/`. Die `.gitignore`-Whitelist (`!vendor/autoload.php`, `!vendor/composer`, `!vendor/smalot`, `!vendor/symfony`) faengt das ab — Dev-Deps landen in keinem dieser Pfade und bleiben ignoriert. Vor dem Committen von vendor-Aenderungen: `composer install --no-dev` sicherstellen.

#### Tarball-Whitelist (HART — alles andere = Abbruch)

**Erlaubte Top-Level-Eintraege:**
`appinfo/`, `lib/`, `js/`, `css/`, `vendor/` (nur Runtime-Deps), `img/`, `templates/`, `l10n/`, `CHANGELOG.md`, `README.md`, `LICENSE`

**appinfo/ darf nur enthalten:**
`info.xml`, `routes.php`, `signature.json`

**Verboten im Tarball (haeufige Fehler):**
`node_modules/`, `src/`, `tests/`, `.git/`, `.github/`, `.claude/`, `docs/`, `vendor/`-**Dev-Deps** (phpunit/ocp — Runtime-Deps sind dagegen PFLICHT), `package.json`, `package-lock.json`, `webpack.config.js`, `vite.config.js`, `composer.json`, `composer.lock`, `phpunit.xml`, `*.crt`, `*.csr`, `*.key`, `.htaccess`, `.user.ini`

#### Schritt-fuer-Schritt Release-Workflow

**Phase 1: Vorbereitung (auf develop)**

```bash
# 1. Sicherstellen: auf develop, aktuell
git checkout develop && git pull

# 2. CHANGELOG.md pruefen (muss schon geschrieben sein via /changelog)
head -20 CHANGELOG.md

# 3. Release-Branch erstellen
git checkout -b release/vX.Y.Z
```

**Phase 2: Version-Bump**

```bash
# 4. Version in info.xml aendern
#    <version>X.Y.Z</version>

# 5. Version in package.json aendern
#    "version": "X.Y.Z"

# 6. Pruefen: beide Versionen identisch
grep '<version>' appinfo/info.xml
grep '"version"' package.json
```

**Phase 3: Build + Commit**

```bash
# 7. Frontend bauen
npm install && npm run build

# 8. Alles committen
git add appinfo/info.xml package.json CHANGELOG.md js/
git commit -m "release: vX.Y.Z"
```

**Phase 4: Tarball erstellen + Whitelist-Check**

```bash
# 9. Tarball aus git archive (NICHT aus Worktree!)
#    Nur erlaubte Dateien via path-Filter
git archive HEAD \
  --prefix=contractmanager/ \
  -o ../contractmanager-vX.Y.Z.tar.gz \
  appinfo/info.xml appinfo/routes.php \
  lib/ js/ css/ img/ templates/ l10n/ \
  CHANGELOG.md README.md LICENSE

# 10. Whitelist-Check: Tarball-Inhalt pruefen
tar tzf ../contractmanager-vX.Y.Z.tar.gz | head -30
# Darf NUR die oben gelisteten Eintraege enthalten!

# 11. Auf .htaccess/.user.ini pruefen (MUSS leer sein)
tar tzf ../contractmanager-vX.Y.Z.tar.gz | grep -E '\.htaccess|\.user\.ini'
# Kein Output = OK. Output = ABBRUCH!
```

**Phase 5: Signierung**

```bash
# 12. Tarball signieren
SIGNATURE=$(openssl dgst -sha512 \
  -sign ~/.nextcloud/certificates/contractmanager.key \
  ../contractmanager-vX.Y.Z.tar.gz | openssl base64 -A)
echo "Signatur: ${SIGNATURE:0:20}..."
```

**Phase 6: PR + Merge + Tag**

```bash
# 13. Push + PR nach main
git push -u origin release/vX.Y.Z
gh pr create --base main --title "Release vX.Y.Z" --body "..."

# 14. PR mergen
gh pr merge --merge

# 15. Tag erstellen (auf main, NACH dem Merge)
git checkout main && git pull
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

**Phase 7: GitHub Release + App Store**

```bash
# 16. GitHub Release mit Tarball
gh release create vX.Y.Z ../contractmanager-vX.Y.Z.tar.gz \
  --title "vX.Y.Z" --notes "Siehe CHANGELOG.md"

# 17. App Store Upload
# Der Token kommt aus der Umgebungsvariable NC_APPSTORE_TOKEN, hinterlegt in
# .claude/settings.local.json (gitignored). Vorher pruefen, sonst 401.
test -n "$NC_APPSTORE_TOKEN" || { echo "FEHLER: NC_APPSTORE_TOKEN nicht gesetzt (siehe .claude/settings.local.json)"; exit 1; }

DOWNLOAD_URL="https://github.com/cpcMomentum/contractmanager/releases/download/vX.Y.Z/contractmanager-vX.Y.Z.tar.gz"
curl -X POST https://apps.nextcloud.com/api/v1/apps/releases \
  -H "Authorization: Token $NC_APPSTORE_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"download\": \"$DOWNLOAD_URL\", \"signature\": \"$SIGNATURE\"}"
```

**Niemals einen Token-Wert in diese Datei zurueckschreiben** — sie ist im Git
getrackt und dieses Repo ist oeffentlich. Der frueher hier hartkodierte Token
war dadurch seit dem 29.04.2026 auf GitHub einsehbar und musste am 04.08.2026
im App Store neu erzeugt werden.

**Phase 8: Aufraemen**

```bash
# 18. Zurueck auf develop
git checkout develop && git pull

# 19. Pruefen: develop und main sind identisch (bei korrektem Workflow)
git log --oneline main..develop  # Sollte leer sein
git log --oneline develop..main  # Sollte leer sein
# Falls nicht leer: Workflow wurde nicht eingehalten → main nach develop mergen
```

### /prime

| Regel | Details |
|-------|---------|
| Pattern-Check PFLICHT | Vor Implementation IMMER bestehende Views/Komponenten durchsuchen — NC-Apps sind stark konventionsgetrieben. Patterns (z.B. NcAppNavigation-Nutzung) muessen wiederverwendet werden. |
| OCP-API Referenz | Pruefen welche OCP-APIs in der App bereits genutzt werden, um neue Features konsistent zu implementieren |
| API im Container verifizieren | `docker exec -t nextcloud-dev cat /var/www/html/lib/public/<Interface>.php` — nie aus dem Gedaechtnis coden |
| @nextcloud/* pruefen | Frontend-Pakete (@nextcloud/vue, @nextcloud/dialogs) vor Nutzung im `node_modules/` verifizieren |

### /design

| Regel | Details |
|-------|---------|
| Pattern-Check VOR Alternativen | Bestehende Views analysieren BEVOR Alternativen erwogen werden |
| NC-API-Recherche PFLICHT | Design darf nicht auf "irgendeine API" bauen — nur auf real existierende OCP-Interfaces |
| Hash-Routing beachten | Alle Navigations-Designs muessen Hash-Routing beruecksichtigen |
| @nextcloud/vue nutzen | UI-Komponenten aus @nextcloud/vue bevorzugen (NcButton, NcAppContent, NcModal, etc.) |

### /plan (create-plan)

| Regel | Details |
|-------|---------|
| OCP-Interface-Verifikation | **Blocker-Schritt** — Plan kann nicht finalisiert werden bevor alle referenzierten APIs im Docker-Container verifiziert wurden |
| Pattern-Check vorgezogen | Bestehende Patterns analysieren bevor neue Architektur geplant wird |
| Keine verbotenen APIs | Plan darf keine `OC\*` APIs, raw SQL oder History-Routing enthalten |
| Version Impact | Bei Schema-Aenderungen: Migration planen, bei API-Aenderungen: NC-Kompatibilitaetsbereich pruefen |

### /bugfix

| Regel | Details |
|-------|---------|
| Pattern-Check | Vor Fix bestehende Patterns analysieren — oft liegt der Bug in Abweichung vom Pattern |
| Deploy nach Build | Nach jedem Build automatisch in Docker-Container deployen: `docker exec -u www-data nextcloud-dev php occ maintenance:repair` |
| NC-API pruefen | Bei API-bezogenen Bugs: Interface-Signatur im Container verifizieren (APIs aendern sich zwischen NC-Majors) |
| Bool-Handling | Bei DB-Bugs: Bool als SmallInt pruefen (MySQL-Kompatibilitaet) |

### /validate

| Regel | Details |
|-------|---------|
| Build Output pruefen | `npm run build` muss `js/contractmanager-main.js` erzeugen |
| PHP-Lint im Container | `docker exec -t nextcloud-dev php -l ...` weil PHP auf macOS nicht installiert |
| info.xml validieren | Pflicht-Felder pruefen: id, name, summary, description, version, licence, author, namespace, category, dependencies |
| Version-Sync | info.xml Version == package.json Version |

### /scaffold

| Regel | Details |
|-------|---------|
| Controller | `extends OCP\AppFramework\Controller`, `#[NoAdminRequired]` Attribute, DI ueber Constructor |
| Service + Mapper Paar | Immer zusammen generieren — Controller → Service → Mapper Schichtung |
| Entity | `extends OCP\AppFramework\Db\Entity`, Getter/Setter automatisch, `addType()` im Constructor |
| Migration | `extends SimpleMigrationStep`, Naming: `VersionXXXYYYDateYYYYMMDDHHMMSS`, nur Schema Builder |
| Tabellennamen | Prefix mit App-ID: `contractmanager_<tablename>` |
| Route hinzufuegen | Neuen Controller in `appinfo/routes.php` registrieren |
| Vue Component | @nextcloud/vue Komponenten nutzen (NcAppContent, NcButton, etc.), Hash-Routing |

### /changelog

| Regel | Details |
|-------|---------|
| Version aus info.xml | Version aus `appinfo/info.xml` lesen, nicht aus package.json |

---

## Agent-Kontext

> **Diese Informationen werden von allen Agenten (code-reviewer, debugger, research-analyst, etc.) gelesen wenn sie an diesem Projekt arbeiten.** Keine separaten NC-Agenten noetig.

### Kern-Wissen fuer alle Agenten

- **Runtime:** Die App laeuft INNERHALB von Nextcloud (PHP), nicht als eigenstaendiger Service
- **Kein Docker fuer die App:** Docker wird nur als Entwicklungsumgebung genutzt (Nextcloud-Instanz im Container)
- **API-Einschraenkung:** Nur `OCP\*` APIs erlaubt, niemals `OC\*` (privat, instabil)
- **DB-Zugriff:** Nur ueber OCP Query Builder, kein raw SQL, kein eigenes ORM
- **Frontend:** Vue 2/3 mit @nextcloud/vue Komponenten, Hash-Routing, webpack
- **Auth:** Bereitgestellt vom Host — `OCP\IUserSession`, kein eigenes Auth-System
- **Lizenz:** AGPL-3.0-or-later ist Pflicht (App Store)
- **Build-Output committen:** `js/` Verzeichnis mit kompiliertem JS muss committed werden

### Fuer code-reviewer

- Pruefe ob nur `OCP\*` APIs genutzt werden (keine `OC\*`)
- Pruefe ob DB-Zugriff nur ueber Query Builder laeuft
- Pruefe ob Type Hints in PHP vorhanden sind
- Pruefe ob neue Routes in `appinfo/routes.php` registriert sind
- Pruefe ob `#[NoAdminRequired]` korrekt gesetzt ist
- Pruefe ob AGPL-Header in neuen Dateien vorhanden ist

### Fuer debugger

- PHP-Fehler im Docker-Container pruefen: `docker exec -t nextcloud-dev tail -f /var/www/html/data/nextcloud.log`
- OCP-API Signaturen im Container verifizieren bevor Fixes vorgeschlagen werden
- Bool-Handling (MySQL SmallInt vs. Boolean) bei DB-Problemen pruefen
- Bei Integrity-Fehlern: `FILE_MISSING` = Datei im Tarball aber nicht auf Disk, `EXTRA_FILE` = Datei auf Disk aber nicht im Tarball

### Fuer security-auditor

- CSRF wird vom NC-Framework erzwungen (`requesttoken` Header)
- XSS: Vue safe by default, in PHP `p()` / `print_unescaped()` pruefen
- SQL Injection: Nur Query Builder, raw SQL ist verboten
- Keine Secrets in `info.xml` oder committed JS

### Fuer research-analyst

- NC-Dokumentation: https://docs.nextcloud.com/server/latest/developer_manual/
- OCP-APIs verifizieren via: `docker exec -t nextcloud-dev cat /var/www/html/lib/public/<Interface>.php`
- @nextcloud/* Pakete: `node_modules/@nextcloud/<pkg>/` einsehen
- Context7 fuer aktuelle @nextcloud/vue Dokumentation nutzen
