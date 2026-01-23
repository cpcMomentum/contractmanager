# Plan: Fix Mount-Point und Admin-Erkennung

## Problem

Die ContractManager App hat zwei sich gegenseitig ausschließende Probleme:

1. **Mit `id="content"`**: Header-Höhe ist korrekt, aber Admin-Erkennung funktioniert nicht (Element existiert nicht oder wird von Nextcloud überschrieben)
2. **Mit `id="contractmanager-app"`**: Admin-Erkennung funktioniert, aber Header-Höhe ist doppelt so hoch

## Ursache

Nach Recherche der offiziellen Nextcloud Apps (Tasks, Notes, Calendar, Contacts):

**Offizielle Apps machen es anders:**
- `templates/main.php`: Enthält **KEIN** HTML-Element, nur `script()` und `style()` Aufrufe
- `main.js`: Mountet auf eine **CSS-Klasse** wie `.app-tasks`
- `App.vue`: Das `<NcContent app-name="tasks">` Component erzeugt automatisch das Mount-Element mit der Klasse `.app-tasks`
- **isAdmin** wird über `IInitialStateService` (PHP) und `@nextcloud/initial-state` (JS) übergeben, nicht über data-Attribute

**Unser Problem:**
- Wir erstellen manuell ein `<div>` in `main.php`
- Wir mounten Vue darauf
- Das `<NcContent>` wird dann **innerhalb** dieses divs gerendert → doppelte Verschachtelung → kaputtes Layout

---

## Lösung: Nextcloud Initial State API verwenden

### Schritt 1: PageController - Initial State bereitstellen

**Datei:** `lib/Controller/PageController.php`

```php
<?php
declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IInitialState;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {

    public function __construct(
        IRequest $request,
        private ?string $userId,
        private IGroupManager $groupManager,
        private IInitialState $initialState,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'contractmanager-main');
        Util::addStyle(Application::APP_ID, 'main');

        // Admin-Status über Initial State API bereitstellen
        $isAdmin = $this->userId !== null && $this->groupManager->isAdmin($this->userId);
        $this->initialState->provideInitialState('isAdmin', $isAdmin);

        return new TemplateResponse(
            Application::APP_ID,
            'main'
        );
    }
}
```

### Schritt 2: templates/main.php - Kein eigenes HTML-Element

**Datei:** `templates/main.php`

```php
<?php
declare(strict_types=1);
// Leer - Scripts und Styles werden im PageController geladen
// NcContent erzeugt automatisch das Mount-Element
```

**Hinweis:** Die Datei kann komplett leer sein, weil:
- Scripts/Styles werden im PageController via `Util::addScript/addStyle` geladen
- Das Mount-Element wird von `<NcContent>` automatisch erzeugt

### Schritt 3: main.js - Auf NcContent-Klasse mounten und Initial State lesen

**Datei:** `src/main.js`

```javascript
import Vue from 'vue'
import App from './App.vue'
import store from './store/index.js'
import { translate, translatePlural } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('contractmanager', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

// Get admin status from Nextcloud Initial State API
const isAdmin = loadState('contractmanager', 'isAdmin', false)

// Make it available globally
Vue.prototype.$isAdmin = isAdmin

new Vue({
    store,
    render: h => h(App),
}).$mount('.app-contractmanager')
```

**Änderungen:**
1. `@nextcloud/initial-state` importieren und `loadState()` verwenden
2. Mount-Punkt von `#contractmanager-app` zu `.app-contractmanager` ändern
3. `$mount()` statt `el:` verwenden (für Klassen-Selektoren)

### Schritt 4: package.json - @nextcloud/initial-state

**Bereits installiert!** Keine Aktion nötig.

---

## Zu ändernde Dateien

| Datei | Änderung |
|-------|----------|
| `lib/Controller/PageController.php` | `IInitialState` injizieren, `provideInitialState()` aufrufen |
| `templates/main.php` | HTML-div entfernen, Datei kann leer sein |
| `src/main.js` | `loadState()` verwenden, auf `.app-contractmanager` mounten |

---

## Warum diese Lösung funktioniert

1. **Kein manuelles Mount-Element**: `<NcContent app-name="contractmanager">` erzeugt automatisch ein Element mit der Klasse `.app-contractmanager`
2. **Korrekte Layout-Hierarchie**: NcContent ist das Root-Element, nicht eingebettet in ein weiteres div
3. **Nextcloud-Standard**: So machen es alle offiziellen Apps (Tasks, Notes, Calendar, Contacts)
4. **Type-safe Admin-Status**: Über Initial State API wird ein Boolean übergeben, nicht ein String aus einem data-Attribut
5. **Kein ID-Konflikt**: Nextcloud hat bereits ein `#content` Element, wir verwenden eine Klasse statt einer ID

---

## Implementierungsreihenfolge

1. `PageController.php` anpassen (IInitialState injizieren)
2. `templates/main.php` leeren
3. `src/main.js` anpassen (loadState, neuer Mount-Punkt)
4. Frontend bauen (`npm run build`)
5. Auf Docker testen
6. Auf Managed Nextcloud deployen

**Hinweis:** `@nextcloud/initial-state` ist bereits installiert.

---

## Verifikation

### Tests nach Implementierung:

- [ ] Header-Höhe ist normal (nicht doppelt)
- [ ] Admin-Einstellungen (Berechtigungen) werden angezeigt wenn als Admin eingeloggt
- [ ] Papierkorb-Tab wird angezeigt (abhängig von canEdit Permission)
- [ ] Keine JavaScript-Fehler in der Konsole
- [ ] `document.querySelector('.app-contractmanager')` findet das Element
- [ ] In der Konsole: `loadState('contractmanager', 'isAdmin')` gibt korrekten Boolean zurück

### Browser-Konsole Test:
```javascript
// Sollte das NcContent-Element finden
document.querySelector('.app-contractmanager')

// Sollte true/false zurückgeben (nicht undefined)
// Nach Page-Load im Vue DevTools oder via window
```

---

*Plan erstellt: 2026-01-22*
*Löst das Problem: Mount-Point-Konflikt zwischen Header-Höhe und Admin-Erkennung*
