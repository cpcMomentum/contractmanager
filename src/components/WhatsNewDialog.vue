<template>
	<!-- v-if: NcModal erst beim Oeffnen mounten, sonst aktiviert sein Focus-Trap
	     schon beim Seitenladen und verschluckt Tab app-weit (#266). -->
	<NcModal v-if="open"
		label-id="whatsnew-title"
		@keydown.esc="dismiss"
		@close="dismiss">
		<div class="whatsnew">
			<h2 id="whatsnew-title">
				{{ title }}
			</h2>
			<p v-if="archive && groups.length === 0" class="whatsnew__empty">
				{{ t('contractmanager', 'Noch keine Neuerungen.') }}
			</p>

			<div v-for="group in groups" :key="group.version" class="whatsnew__group">
				<p class="whatsnew__version">
					{{ t('contractmanager', 'Version {version}', { version: group.version }) }}
				</p>

				<div v-for="(entry, index) in group.entries" :key="group.version + '-' + index" class="whatsnew__entry">
					<div class="whatsnew__icon">
						<component :is="iconFor(entry.icon)" :size="22" />
					</div>
					<div class="whatsnew__body">
						<h3 class="whatsnew__entry-title">
							{{ entry.title }}
							<span v-if="entry.plus" class="whatsnew__badge">WerkPlus</span>
						</h3>
						<p class="whatsnew__entry-text">
							{{ entry.text }}
						</p>
						<p v-if="entry.where" class="whatsnew__where">
							{{ t('contractmanager', 'Zu finden unter') }}
							<b>{{ entry.where }}</b><span v-if="entry.adminOnly">{{ ' ' + t('contractmanager', '(nur für Administratoren)') }}</span>
						</p>
						<a v-if="entry.plus"
							class="whatsnew__link"
							:href="WERKPLUS_URL"
							target="_blank"
							rel="noreferrer noopener">
							{{ t('contractmanager', 'Mehr zu WerkPlus') }}
						</a>
					</div>
				</div>
			</div>

			<div class="actions">
				<NcButton variant="primary" @click="dismiss">
					{{ archive ? t('contractmanager', 'Schließen') : t('contractmanager', 'Alles klar') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
/**
 * „Was ist neu?"-Fenster (#415, Rollout aus dem Pilot RechnungsWerk#308). Zeigt
 * einmal je Nutzer und Version die Neuerungen aus `whatsnew/whatsnew.json`, ist
 * wegklickbar und blockiert nie. Faellt der Abruf aus, bleibt das Fenster aus.
 */
import { onMounted, ref, type Component } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import BellRingIcon from 'vue-material-design-icons/BellRing.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import EmailIcon from 'vue-material-design-icons/Email.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import TranslateIcon from 'vue-material-design-icons/Translate.vue'
import WhatsNewService, { type WhatsNewGroup } from '../services/WhatsNewService'

/** Zielseite der WerkPlus-Eintraege (Konzept v1.1, Abschnitt 2). */
const WERKPLUS_URL = 'https://werkwolke.de'

/**
 * Erlaubte Symbole. Bewusst eine feste Liste statt dynamischer Importe: das
 * haelt das Bundle klein und macht einen Tippfehler in der JSON harmlos. Ein
 * Symbol kostet ein Wort in der Datei, ist sprachneutral und veraltet nicht
 * mit der naechsten Oberflaechenaenderung — anders als ein Screenshot.
 * Die Liste haelt `tests/Unit/Service/WhatsNewServiceTest.php` gegen die
 * ausgelieferte Datei.
 */
const ICONS: Record<string, Component> = {
	'account-group': AccountGroupIcon,
	archive: ArchiveIcon,
	'bell-ring': BellRingIcon,
	calendar: CalendarIcon,
	'chart-bar': ChartBarIcon,
	cog: CogIcon,
	email: EmailIcon,
	'file-document': FileDocumentIcon,
	folder: FolderIcon,
	magnify: MagnifyIcon,
	star: StarIcon,
	tag: TagIcon,
	translate: TranslateIcon,
}

const open = ref(false)
/**
 * Die anzuzeigenden Versionsgruppen. Im Popup genau eine (die neueste
 * ungesehene), im Archiv alle. Ein gemeinsames Format haelt Vorlage und
 * Anzeige einfach.
 */
const groups = ref<WhatsNewGroup[]>([])
/** Archiv-Modus (#427): ueber das Menue aufgerufen, nicht das Auto-Popup. */
const archive = ref(false)
// Anzeigename aus info.xml, nicht die App-ID: die App heisst VertragsWerk.
const title = t('contractmanager', 'Was ist neu in VertragsWerk')

/** Unbekannter oder fehlender Name faellt auf den Stern zurueck. */
function iconFor(name: string): Component {
	return ICONS[name] ?? StarIcon
}

// Auto-Popup: einmal je Nutzer und Version die neueste ungesehene Version.
onMounted(async () => {
	try {
		const payload = await WhatsNewService.getWhatsNew()
		// Hat der Nutzer waehrend des Abrufs schon das Archiv geoeffnet, nicht
		// ueberschreiben — sonst quittierte „Schliessen" ungefragt das Popup.
		if (payload.entries.length > 0 && !open.value) {
			groups.value = [{ version: payload.version, entries: payload.entries }]
			archive.value = false
			open.value = true
		}
	} catch {
		// Kein Fenster ist besser als eine Fehlermeldung ueber Neuerungen.
	}
})

/**
 * Das Archiv oeffnen (#427) — alle bisherigen Neuerungen, ueber den Menueeintrag.
 * Beruehrt keine Marke; Nachlesen ist kein Quittieren.
 */
async function openArchive(): Promise<void> {
	try {
		const payload = await WhatsNewService.getWhatsNewArchive()
		groups.value = payload.versions
		archive.value = true
		open.value = true
	} catch {
		// Kein Fenster ist besser als eine Fehlermeldung ueber Neuerungen.
	}
}

/**
 * Knopf, X und Escape laufen hier zusammen. Escape meldet NcModal zusaetzlich
 * als `close`, deshalb der Riegel: sonst ginge dieselbe Quittung zweimal raus.
 * Im Popup-Modus wird die laufende Version quittiert, im Archiv-Modus nicht —
 * es war nur Nachlesen.
 */
async function dismiss(): Promise<void> {
	if (!open.value) {
		return
	}
	open.value = false
	if (archive.value) {
		return
	}
	try {
		await WhatsNewService.markWhatsNewSeen()
	} catch {
		// Quittung verloren: das Fenster kommt beim naechsten Start noch einmal.
		// Das ist die harmlosere Seite des Fehlers.
	}
}

// Der Menueeintrag „Neuerungen" in App.vue ruft dies ueber eine Template-Referenz.
defineExpose({ openArchive })
</script>

<style scoped>
.whatsnew {
	padding: 24px;
	display: flex;
	flex-direction: column;
	min-width: 0;
}
.whatsnew h2 {
	margin: 0;
}
.whatsnew__version {
	margin: 2px 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
.whatsnew__empty {
	margin: 8px 0;
	color: var(--color-text-maxcontrast);
}
/* Im Archiv trennt eine Linie die Versionsbloecke; im Popup gibt es nur einen. */
.whatsnew__group + .whatsnew__group {
	margin-top: 16px;
	padding-top: 10px;
	border-top: 1px solid var(--color-border);
}
.whatsnew__entry {
	display: flex;
	gap: 14px;
	align-items: flex-start;
	padding: 14px 0;
	border-top: 1px solid var(--color-border);
}
.whatsnew__entry:first-of-type {
	border-top: none;
}
.whatsnew__icon {
	flex: 0 0 auto;
	width: 40px;
	height: 40px;
	margin-top: 2px;
	border-radius: 20px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-primary-element-light, #e5f2fa);
	color: var(--color-primary-element, #0082c9);
}
.whatsnew__body {
	flex: 1;
	min-width: 0;
}
.whatsnew__entry-title {
	margin: 0 0 4px;
	font-size: 1.05em;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
.whatsnew__entry-text {
	margin: 0;
}
.whatsnew__where {
	margin: 6px 0 0;
	font-size: 0.92em;
	color: var(--color-text-maxcontrast);
}
.whatsnew__where b {
	font-weight: 600;
	color: var(--color-main-text);
}
.whatsnew__badge {
	display: inline-flex;
	align-items: center;
	height: 20px;
	padding: 0 9px;
	border-radius: 10px;
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #fff);
	font-size: 0.75em;
	font-weight: 600;
}
.whatsnew__link {
	display: inline-block;
	margin-top: 8px;
	font-weight: 600;
}
.actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 12px;
}
</style>
