# AI Development Guidelines

## 1. Technology Stack

**Siehe `techstack.md` für den vollständigen, standardmäßig zu verwendenden TechStack.**

Wichtigste Prinzipien:
- Containerisierung (Docker) ist EMPFOHLEN, aber nicht fuer alle Projekte zwingend (siehe techstack.md Sektion "Infrastruktur")
- Dependency Versions muessen immer gepinned sein (NO `:latest` tags)
- State-of-the-art Libraries fuer Auth verwenden (NO custom auth logic)

## 2. Development Guidelines

### AI Agent Behavior
- **Research First**: Never guess. Perform research for up-to-date knowledge before coding.
- **Context7**: Use https://context7.com/ for current library documentation and versions.
- **Subagents**: Utilize subagents for specialized tasks.
- **Context**: Always read `dev.md` first as the source of truth.
- **Plugins nutzen**: Alle 6 Pflicht-Plugins aktiv einsetzen (siehe unten).

### Plugins (Anthropic Marketplace)

Diese Plugins werden bei `/ai-first-dev:setup` installiert. Welche Plugins benoetigt werden, haengt vom **Tech-Stack** ab (siehe `techstack.md` Sektion "Plugins").

**Core-Plugins (immer):**

| Plugin | Kategorie | Wirkung |
|--------|-----------|---------|
| `context7` | Recherche | Aktuelle Library-Dokumentation |
| `security-guidance` | Sicherheit | Automatische Warnungen bei Security-Problemen |

**Stack-spezifische Plugins (bedingt):**

| Plugin | Kategorie | Wann benoetigt |
|--------|-----------|----------------|
| `typescript-lsp` | Code Intelligence | TypeScript im Stack |
| `pyright-lsp` | Code Intelligence | Python im Stack |
| `playwright` | Testing | Frontend vorhanden |
| `frontend-design` | Frontend | Frontend vorhanden |

**Pruefung:** `/setup-plugins` prueft ob die fuer den Stack relevanten Plugins installiert sind.

### Recherche-Ressourcen

| Ressource | Plugin/Tool | Verwendung |
|-----------|-------------|------------|
| **Context7** | `context7` Plugin | Aktuelle Library-Dokumentation, Versionen, Best Practices |
| WebSearch | Built-in | Fallback, allgemeine Recherche |
| .claude/techstack.md | lokal | Standard Tech-Stack Definition |
| .claude/standards/ | lokal | Projekt-spezifische Standards |

### Version Control (Git)

#### GRUNDREGEL: Niemals direkt auf `main` oder `develop` arbeiten!

Alle Änderungen entstehen auf Feature-Branches und gelangen **ausschließlich via Pull Request** in protected Branches.

#### Branches

| Branch | Zweck | Schutz |
|--------|-------|--------|
| `main` | Produktion, immer stabil | Protected: PR + 1 Review + CI |
| `develop` | Integration für Features | Protected: PR + CI |
| `feature/*` | Neue Funktionalität | Frei |
| `bugfix/*` | Fehlerbehebung | Frei |
| `hotfix/*` | Kritische Fixes (direkt → main) | Frei |
| `release/*` | Release-Branches (`release/vX.Y.Z`) | Frei |
| `chore/*` | Maintenance, Dependencies | Frei |
| `docs/*` | Dokumentation | Frei |
| `refactor/*` | Code-Refactoring | Frei |

#### Branch-Naming

```
<type>/<beschreibung>
<type>/<issue-id>-<beschreibung>
```

**Regeln:**
- Alles lowercase, Wörter mit `-` getrennt
- Issue-ID optional (empfohlen wenn Issue existiert)
- Kurz und beschreibend

**Beispiele:**
- `feature/new-ui-design`
- `feature/42-user-authentication`
- `bugfix/87-login-token-expiry`
- `hotfix/critical-security-patch`
- `release/v1.2.0`
- `chore/update-dependencies`
- `refactor/optimize-db-queries`

**Validierung:** GitHub Action prüft automatisch: `^(feature|bugfix|hotfix|release|chore|docs|refactor)\/[a-z0-9.-]+$`

#### Merge-Flow

```
feature/*  ──PR──▶ develop ──PR+Review──▶ main
bugfix/*   ──PR──▶ develop ──PR+Review──▶ main
release/*  ──PR+Review──────────────────────▶ main
hotfix/*   ──PR+Review──────────────────────▶ main
```

#### Commit-Messages (Conventional Commits)

```
<type>(<scope>): <beschreibung>

[optionaler body]

Co-Authored-By: Claude <noreply@anthropic.com>
```

| Type | Beschreibung | Beispiel |
|------|-------------|---------|
| `feat` | Neues Feature | `feat(auth): add OAuth2 login` |
| `fix` | Bugfix | `fix(api): correct pagination offset` |
| `docs` | Dokumentation | `docs: update API reference` |
| `refactor` | Refactoring | `refactor(db): optimize query structure` |
| `chore` | Maintenance | `chore: update dependencies` |
| `test` | Tests | `test: add auth unit tests` |
| `perf` | Performance | `perf(queries): add database indexes` |
| `ci` | CI/CD | `ci: add security scan step` |
| `style` | Formatting | `style: fix linting errors` |

#### PR-Titel (PFLICHT: Conventional Commits Format)

```
<type>(<scope>): <beschreibung>
```

**Regeln:**
- Selbes Format wie Commit-Messages
- Max. 72 Zeichen
- Beschreibung im Imperativ ("add", nicht "added")
- Scope optional aber empfohlen

**Beispiele:**
- `feat(auth): add user registration with email verification`
- `fix(api): resolve pagination bug on contracts endpoint`
- `refactor(database): migrate from raw SQL to SQLAlchemy ORM`
- `chore: update Next.js to v15.1`

**Validierung:** GitHub Action prüft automatisch: `^(feat|fix|docs|style|refactor|test|chore|perf|ci|build|revert)(\(.+\))?: .{1,72}$`

**Nicht erlaubt:**
- `Added some stuff` (kein Typ-Prefix)
- `feat: this is a very long title that exceeds the character limit and is hard to read` (zu lang)
- `Feature/user auth` (Branch-Name statt PR-Titel)

#### CHANGELOG

- `CHANGELOG.md` dokumentiert alle Änderungen (Keep a Changelog Format)
- Muss **vor jedem PR nach `main`** aktualisiert werden
- Wird automatisch aus Conventional Commits generiert (`/changelog`)

#### Versionierung

- **Semantic Versioning** (X.Y.Z): Major.Minor.Patch
- Versionsnummer immer inkrementieren
- Version im Docker Container-Namen verwenden

#### Stages

- **DEV-Stage**: Entwickler kann außerhalb der lokalen Maschine testen
- **PROD-Stage**: Nur nach bestandener QA

> **Vollständige Dokumentation:** `.claude/standards/github-branch-protection.md`

### Security
- **Secrets**:
    - NEVER commit credentials/secrets (`.env`).
    - Keep secrets server-side only.
    - Maintain strict `.gitignore`.
- **Auth**: Use state-of-the-art middleware/libraries. No custom auth logic.
- **CORS**: Configure strictly (allowlist only) for Frontend/Backend communication.
- **HTTPS**: Ensure all connections are secure.
- **Security**: Follow OWASP Top 10.
- **Rate Limiting**: Implement rate limiting.
- **Logging**: Implement structured logging.
- **Monitoring**: Implement monitoring.
- **Backup**: Implement backup. 

### Testing & Quality
- **MVP Strategy**: Unit tests ONLY.
    - Use unit tests to validate success.
    - **User Confirmation**: User must approve the choice of unit tests.
- **Code Quality**:
    - Dependency auditing & linting required (siehe `techstack.md` für Tools).
    - **Dependencies**: Pin versions. NO `:latest` tags in `package.json`.
    - **Logs**: Minimal, no PII data.

### Mandatory Project Structure & Documentation
- **Source of Truth**:
    - `dev.md` (This file): HOW to develop
    - `techstack.md`: WHAT technologies to use
- **Required Files**:
    - `project.md`: Natural language description & guardrails.
    - `plan.md`: Top-level roadmap & critical decisions.
    - `phase X.md`: Detailed specs & mandatory tests for current phase.
    - `claude.md`: Minimal, project-specific instructions (deployment, how-to) derived from `dev.md` and `techstack.md`.
    - `README.md`: What, How, Setup, API docs.
    - `LICENSE.md`: Included in setup.
