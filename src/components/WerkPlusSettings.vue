<!--
	SPDX-FileCopyrightText: 2026 cpcMomentum
	SPDX-License-Identifier: AGPL-3.0-or-later

	WerkPlus-Abschnitt der Verwaltung, Aufbau und feste Texte nach dem
	Design-System der App-Flotte (Abschnitt 5).
-->
<template>
	<div class="werkplus">
		<p class="werkplus__hint">
			{{ t('contractmanager', 'Mit einem WerkPlus-Schlüssel schaltet VertragsWerk zusätzliche Funktionen frei. Ohne Schlüssel bleibt alles, wie es ist.') }}
			{{ t('contractmanager', 'Der Schlüssel gilt für alle Werk-Apps dieser Nextcloud und muss nur in einer davon eingetragen werden.') }}
		</p>

		<NcNoteCard v-if="loadError" type="error" :text="loadError" />

		<template v-if="status">
			<NcNoteCard v-if="status.storeTooNew" type="warning" :text="t('contractmanager', 'Eine andere Werk-App hat die Lizenz in einem neueren Format gespeichert. Bitte aktualisiere VertragsWerk.')" />
			<NcNoteCard v-if="stateNote" :type="stateNote.type" :text="stateNote.text" />
			<NcNoteCard v-if="trialKey" type="success" class="werkplus__trial-key">
				<p v-if="licenseActive">
					{{ t('contractmanager', 'Der Testzeitraum läuft. Das ist dein WerkPlus-Schlüssel:') }}
				</p>
				<p v-else>
					{{ t('contractmanager', 'Der Testzeitraum ist gestartet, die Lizenz konnte aber noch nicht geprüft werden. Mit „Jetzt prüfen“ versuchst du es erneut. Das ist dein WerkPlus-Schlüssel:') }}
				</p>
				<p><code>{{ trialKey }}</code></p>
				<p>{{ t('contractmanager', 'Bitte bewahre ihn sicher auf. Er ist hinterlegt, wird aber nur jetzt vollständig angezeigt.') }}</p>
			</NcNoteCard>
			<NcNoteCard v-if="actionInfo" type="info" :text="actionInfo" />
			<NcNoteCard v-if="actionError" type="error" :text="actionError" />
			<NcNoteCard v-else-if="status.lastError" type="warning" :text="status.lastError.message" />

			<dl v-if="status.keySet" class="werkplus__facts">
				<dt>{{ t('contractmanager', 'Tarif') }}</dt>
				<dd>{{ tierLabel }}</dd>
				<template v-if="status.licensedEmployees">
					<dt>{{ t('contractmanager', 'Lizenzierte Mitarbeitende') }}</dt>
					<dd>{{ status.licensedEmployees }}</dd>
				</template>
				<template v-if="status.expiresAt">
					<dt>{{ t('contractmanager', 'Gültig bis') }}</dt>
					<dd>{{ formatDate(status.expiresAt) }}</dd>
				</template>
				<dt>{{ t('contractmanager', 'Letzte erfolgreiche Prüfung') }}</dt>
				<dd>{{ status.lastCheckAt ? formatDateTime(status.lastCheckAt) : t('contractmanager', 'noch keine') }}</dd>
				<dt>{{ t('contractmanager', 'Schlüssel') }}</dt>
				<dd>{{ status.keyHint }}</dd>
			</dl>

			<p v-if="!status.canManage && !status.storeTooNew" class="werkplus__hint">
				{{ t('contractmanager', 'Den Schlüssel können nur Nextcloud-Admins eintragen oder entfernen, weil er für alle Werk-Apps dieser Nextcloud gilt.') }}
			</p>

			<label v-if="canEdit" class="werkplus__field">
				<span>{{ t('contractmanager', 'WerkPlus-Schlüssel') }}</span>
				<input v-model="keyInput"
					class="werkplus__input"
					type="text"
					autocomplete="off"
					spellcheck="false"
					:placeholder="status.keySet ? t('contractmanager', 'gespeichert, leer lassen') : 'wp_…'">
			</label>

			<div class="werkplus__actions">
				<NcButton v-if="canEdit"
					variant="primary"
					:disabled="busy || (!keyInput.trim() && !status.keySet)"
					@click="onActivate">
					{{ status.keySet ? t('contractmanager', 'Speichern und prüfen') : t('contractmanager', 'Schlüssel prüfen und speichern') }}
				</NcButton>
				<NcButton v-if="canEdit && !status.keySet" :disabled="busy" @click="confirmTrial = true">
					{{ t('contractmanager', '30 Tage testen') }}
				</NcButton>
				<NcButton v-if="status.keySet && !status.storeTooNew" :disabled="busy" @click="onRefresh">
					{{ t('contractmanager', 'Jetzt prüfen') }}
				</NcButton>
				<NcButton v-if="canEdit && status.keySet"
					variant="tertiary"
					:disabled="busy"
					@click="confirmRemove = true">
					{{ t('contractmanager', 'Schlüssel entfernen') }}
				</NcButton>
			</div>

			<div class="werkplus__privacy">
				<span class="werkplus__label">{{ t('contractmanager', 'Was an WerkPlus übertragen wird') }}</span>
				<p class="werkplus__hint">
					{{ t('contractmanager', 'Eine aus dieser Nextcloud abgeleitete Kennung, die Versionen der aktivierten Werk-Apps und, sobald vorhanden, der Schlüssel. Nicht übertragen werden Verträge, Vertragspartner oder andere Vertragsdaten, Personendaten, Nextcloud-Benutzer- oder Datei-IDs.') }}
				</p>
				<p class="werkplus__hint">
					{{ t('contractmanager', 'Die Lizenz wird auf diesem Server geprüft. Ist WerkPlus nicht erreichbar, arbeitet VertragsWerk normal weiter.') }}
				</p>
				<p class="werkplus__hint">
					{{ t('contractmanager', 'Kennung dieser Nextcloud:') }} <code>{{ status.instanceUuid }}</code>
				</p>
			</div>
		</template>

		<NcDialog v-if="confirmTrial"
			:name="t('contractmanager', 'WerkPlus 30 Tage testen')"
			@close="confirmTrial = false">
			<p>{{ t('contractmanager', 'Der Testzeitraum läuft 30 Tage, braucht keine Zahlungsdaten, gilt für alle Werk-Apps dieser Nextcloud und ist je Nextcloud nur einmal möglich. Jetzt starten?') }}</p>
			<template #actions>
				<NcButton @click="confirmTrial = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary" @click="onTrial">
					{{ t('contractmanager', 'Testzeitraum starten') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog v-if="confirmRemove"
			:name="t('contractmanager', 'WerkPlus-Schlüssel entfernen')"
			@close="confirmRemove = false">
			<p>{{ t('contractmanager', 'Der Schlüssel wird für alle Werk-Apps dieser Nextcloud entfernt, sie arbeiten danach im freien Umfang. Den Schlüssel kannst du jederzeit wieder eintragen. Fortfahren?') }}</p>
			<template #actions>
				<NcButton @click="confirmRemove = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="error" @click="onRemove">
					{{ t('contractmanager', 'Entfernen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getCanonicalLocale, translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import LicenseService, { type LicenseStatus } from '../services/LicenseService'

const status = ref<LicenseStatus | null>(null)
const keyInput = ref('')
const busy = ref(false)
const loadError = ref('')
const actionError = ref('')
const actionInfo = ref('')
const trialKey = ref('')
const confirmRemove = ref(false)
const confirmTrial = ref(false)

// Kein Fehler im eigentlichen Sinn, der Vertrag verlangt eine ruhige Anzeige.
const CALM_CODES = ['TRIAL_ALREADY_USED']

const canEdit = computed(() => status.value?.canManage === true && !status.value.storeTooNew)

const licenseActive = computed(() => status.value?.state === 'valid' || status.value?.state === 'grace')

const tierLabel = computed(() => (status.value?.tier === 'werkplus' ? 'WerkPlus' : t('contractmanager', 'Frei')))

const stateNote = computed<{ type: 'success' | 'warning' | 'error', text: string } | null>(() => {
	const s = status.value
	if (!s) {
		return null
	}
	switch (s.state) {
	case 'valid':
		return { type: 'success', text: t('contractmanager', 'WerkPlus ist aktiv.') }
	case 'grace':
		return {
			type: 'warning',
			text: t('contractmanager', 'Die Lizenz ist abgelaufen und konnte noch nicht erneuert werden. Die Funktionen bleiben bis {date} erhalten.', { date: s.graceUntil ? formatDate(s.graceUntil) : '' }),
		}
	case 'expired':
		return { type: 'error', text: t('contractmanager', 'Die Lizenz ist abgelaufen. VertragsWerk arbeitet im freien Umfang weiter.') }
	case 'invalid':
		return { type: 'error', text: t('contractmanager', 'Der gespeicherte Lizenznachweis ist ungültig. Bitte prüfe die Lizenz erneut.') }
	default:
		return null
	}
})

function formatDate(iso: string): string {
	return new Date(iso).toLocaleDateString(getCanonicalLocale())
}

function formatDateTime(iso: string): string {
	return new Date(iso).toLocaleString(getCanonicalLocale())
}

async function load(): Promise<void> {
	try {
		status.value = await LicenseService.getLicense()
		loadError.value = ''
	} catch (e) {
		loadError.value = (e as Error).message || t('contractmanager', 'Lizenzstatus konnte nicht geladen werden.')
	}
}

// Nach einem Fehler den Status neu laden, die Eingabe aber stehen lassen.
async function run(action: () => Promise<LicenseStatus>): Promise<void> {
	busy.value = true
	actionError.value = ''
	actionInfo.value = ''
	try {
		status.value = await action()
		keyInput.value = ''
	} catch (e) {
		const { message, code } = e as { message?: string, code?: string }
		const text = message || t('contractmanager', 'WerkPlus hat einen Fehler gemeldet. Bitte versuche es später erneut.')
		if (code && CALM_CODES.includes(code)) {
			actionInfo.value = text
		} else {
			actionError.value = text
		}
		await load()
	} finally {
		busy.value = false
	}
}

function onActivate(): Promise<void> {
	trialKey.value = ''
	return run(() => LicenseService.activateLicense(keyInput.value.trim()))
}

function onTrial(): Promise<void> {
	confirmTrial.value = false
	trialKey.value = ''
	return run(async () => {
		const result = await LicenseService.startTrial()
		trialKey.value = result.key
		return result.status
	})
}

function onRefresh(): Promise<void> {
	return run(() => LicenseService.refreshLicense())
}

function onRemove(): Promise<void> {
	confirmRemove.value = false
	trialKey.value = ''
	return run(() => LicenseService.removeLicense())
}

onMounted(load)
</script>

<style scoped>
.werkplus__hint {
	color: var(--color-text-maxcontrast);
	margin: 0 0 12px;
}

.werkplus__facts {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 16px;
	margin: 12px 0 16px;
}

/* Nextcloud gibt dt/dd global eine feste Breite, Rechtsbuendigkeit und Innenabstand mit. */
.werkplus__facts dt,
.werkplus__facts dd {
	width: auto;
	margin: 0;
	padding: 0;
	text-align: start;
}

.werkplus__facts dt {
	color: var(--color-text-maxcontrast);
}

.werkplus__field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	max-width: 480px;
	margin-bottom: 16px;
}

.werkplus__input {
	width: 100%;
}

.werkplus__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 12px 0 20px;
}

.werkplus__label {
	font-weight: 600;
}

.werkplus__privacy code,
.werkplus__trial-key code {
	user-select: all;
}
</style>
