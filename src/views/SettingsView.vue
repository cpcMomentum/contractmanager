<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('contractmanager', 'Einstellungen') }}</h2>
		</div>

		<div class="settings-layout">
			<nav class="settings-nav" :aria-label="t('contractmanager', 'Einstellungs-Navigation')">
				<template v-for="group in navGroups" :key="group.label">
					<div class="settings-nav-group">
						{{ group.label }}
					</div>
					<button v-for="item in group.items"
						:key="item.key"
						type="button"
						class="settings-nav-item"
						:class="{ active: activeSection === item.key }"
						:aria-current="activeSection === item.key ? 'page' : undefined"
						@click="activeSection = item.key">
						<component :is="item.icon" :size="18" /> {{ item.label }}
					</button>
				</template>
			</nav>

			<div class="settings-content">
				<div v-show="activeSection === 'notifications'" class="settings-section">
					<h3>{{ t('contractmanager', 'Benachrichtigungen') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Lege fest, für welche Verträge du Erinnerungen erhalten möchtest, wie früh und auf welchem Weg.') }}
					</p>

					<!-- Reminder mode: which contracts -->
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Erinnerungen für') }}</label>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="all"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('contractmanager', 'Alle Verträge, die ich sehe') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="own"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('contractmanager', 'Nur meine eigenen Verträge') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="reminderMode"
							value="none"
							name="reminderMode"
							type="radio"
							@update:model-value="onReminderModeChange">
							{{ t('contractmanager', 'Keine Erinnerungen') }}
						</NcCheckboxRadioSwitch>
					</div>

					<template v-if="reminderMode !== 'none'">
						<!-- Channel: e-mail -->
						<div class="settings-item">
							<NcCheckboxRadioSwitch v-model="emailReminder" @update:model-value="onEmailReminderChange">
								{{ t('contractmanager', 'Per E-Mail benachrichtigen') }}
							</NcCheckboxRadioSwitch>
							<p class="settings-description">
								{{ t('contractmanager', 'E-Mails gehen an deine in Nextcloud hinterlegte Adresse.') }}
							</p>
						</div>

						<!-- Channel: personal Talk chat -->
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Eigener Nextcloud Talk Chat (optional)') }}</label>
							<p class="settings-description">
								{{ t('contractmanager', 'Token eines Chats, in dem du Erinnerungen erhalten möchtest (aus der Chat-URL). Leer lassen, um Talk nicht zu nutzen.') }}
							</p>
							<NcTextField v-model="userReminders.talkChatToken"
								:placeholder="t('contractmanager', 'z.B. abc123xyz')"
								class="settings-input" />
						</div>

						<!-- Personal lead time -->
						<div class="settings-item reminder-days">
							<label class="settings-label">{{ t('contractmanager', 'Eigene Vorlaufzeit (Tage vor der Frist)') }}</label>
							<p class="settings-description">
								{{ t('contractmanager', 'Leer lassen, um die Standardwerte des Administrators zu verwenden.') }}
							</p>
							<div class="reminder-inputs">
								<div class="reminder-input-group">
									<label>{{ t('contractmanager', 'Erste Erinnerung') }}</label>
									<NcTextField v-model="userReminders.reminderDays1Personal"
										type="number"
										:min="1"
										:placeholder="String(reminderDefaults.days1)"
										class="number-input" />
									<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
								</div>
								<div class="reminder-input-group">
									<label>{{ t('contractmanager', 'Letzte Erinnerung') }}</label>
									<NcTextField v-model="userReminders.reminderDays2Personal"
										type="number"
										:min="1"
										:placeholder="String(reminderDefaults.days2)"
										class="number-input" />
									<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
								</div>
							</div>
						</div>

						<div class="settings-actions">
							<NcButton variant="primary" :disabled="savingUserReminders" @click="saveUserReminderSettings">
								<template #icon>
									<NcLoadingIcon v-if="savingUserReminders" :size="20" />
								</template>
								{{ t('contractmanager', 'Speichern') }}
							</NcButton>
						</div>
					</template>
				</div>

				<div v-show="activeSection === 'amount'" class="settings-section">
					<h3>{{ t('contractmanager', 'Betragsangabe') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Standard für neue Verträge. Pro Vertrag kann davon abgewichen werden.') }}
					</p>
					<div class="settings-item">
						<NcCheckboxRadioSwitch v-model="defaultAmountType"
							value="netto"
							name="defaultAmountType"
							type="radio"
							@update:model-value="onDefaultAmountTypeChange">
							{{ t('contractmanager', 'Netto') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="defaultAmountType"
							value="brutto"
							name="defaultAmountType"
							type="radio"
							@update:model-value="onDefaultAmountTypeChange">
							{{ t('contractmanager', 'Brutto') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<!-- Auto-Backup (#296) -->
				<div v-show="activeSection === 'backup'" class="settings-section">
					<h3>{{ t('contractmanager', 'Automatisches Backup') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Sichert deine eigenen Vertragsdaten regelmäßig als JSON-Datei in einen Nextcloud-Ordner, damit der Stand in jedem normalen Nextcloud-Backup landet.') }}
					</p>
					<div class="settings-item">
						<NcCheckboxRadioSwitch :model-value="backupEnabled" @update:model-value="onBackupEnabledChange">
							{{ t('contractmanager', 'Automatisches Backup aktivieren') }}
						</NcCheckboxRadioSwitch>
					</div>
					<template v-if="backupEnabled">
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Zielordner') }}</label>
							<input v-model="backupFolder"
								class="settings-input"
								placeholder="/VertragsWerk-Backup"
								@blur="onBackupFolderChange">
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Intervall') }}</label>
							<select v-model="backupInterval" class="settings-input" @change="onBackupIntervalChange">
								<option value="daily">{{ t('contractmanager', 'Täglich') }}</option>
								<option value="weekly">{{ t('contractmanager', 'Wöchentlich') }}</option>
								<option value="monthly">{{ t('contractmanager', 'Monatlich') }}</option>
							</select>
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Uhrzeit') }}</label>
							<select v-model="backupHour" class="settings-input" @change="onBackupHourChange">
								<option value="">
									{{ t('contractmanager', 'Keine feste Uhrzeit') }}
								</option>
								<option v-for="hour in 24" :key="hour" :value="String(hour - 1)">
									{{ String(hour - 1).padStart(2, '0') }}:00
								</option>
							</select>
						</div>
						<p class="settings-description">
							{{ t('contractmanager', 'Es werden die letzten 30 Sicherungen behalten; ältere werden automatisch entfernt.') }}
						</p>
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Zuletzt gesichert') }}</label>
							<span class="settings-value">{{ lastBackupText }}</span>
						</div>
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Nächste Sicherung') }}</label>
							<span class="settings-value">{{ nextBackupText }}</span>
						</div>
						<div class="settings-item">
							<NcButton variant="secondary" :disabled="backingUpNow" @click="triggerBackupNow">
								<template #icon>
									<NcLoadingIcon v-if="backingUpNow" :size="20" />
									<BackupRestoreIcon v-else :size="20" />
								</template>
								{{ t('contractmanager', 'Jetzt sichern') }}
							</NcButton>
						</div>
					</template>

					<template v-if="canEdit">
						<h3 class="settings-subheading">
							{{ t('contractmanager', 'Backup einlesen') }}
						</h3>
						<p class="settings-description">
							{{ t('contractmanager', 'Liest eine Sicherungsdatei (JSON) aus deinen Nextcloud-Dateien ein, auch aus einer anderen Nextcloud. Die Verträge gehören danach dir. Verträge, die du schon hast (gleicher Name, Anbieter und Beginn), werden übersprungen.') }}
						</p>
						<div class="settings-item">
							<NcButton variant="secondary" :disabled="importBusy" @click="pickImportFile">
								<template #icon>
									<NcLoadingIcon v-if="importBusy" :size="20" />
									<UploadIcon v-else :size="20" />
								</template>
								{{ t('contractmanager', 'Sicherungsdatei wählen') }}
							</NcButton>
						</div>
					</template>
				</div>

				<!-- Kalender-Abo (#68) -->
				<div v-show="activeSection === 'calendar'" class="settings-section">
					<h3>{{ t('contractmanager', 'Kalender-Abo') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Abonniere deine Vertragsfristen als schreibgeschützten Kalender in der Nextcloud-Kalender-App. Pro Vertrag erscheint ein Ganztags-Termin an der Kündigungsfrist (bzw. dem Vertragsende). Enthalten sind nur die Verträge, für die du Erinnerungen erhältst – so läuft der Kalender nicht über.') }}
					</p>
					<template v-if="calendarFeedUrl">
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Abo-URL') }}</label>
							<div class="calendar-feed-row">
								<input ref="calendarFeedInput"
									class="settings-input calendar-feed-url"
									type="text"
									readonly
									:value="calendarFeedUrl">
								<NcButton variant="secondary" @click="copyCalendarFeedUrl">
									{{ t('contractmanager', 'Kopieren') }}
								</NcButton>
							</div>
							<p class="settings-description">
								{{ t('contractmanager', 'In der Kalender-App: „Neues Abonnement” bzw. „Abonnement aus Link” und diese URL einfügen.') }}
							</p>
						</div>
						<div class="settings-item">
							<NcButton variant="tertiary" @click="resetCalendarFeed">
								{{ t('contractmanager', 'URL neu erzeugen') }}
							</NcButton>
							<p class="settings-description">
								{{ t('contractmanager', 'Die bisherige URL wird dabei ungültig. Nutze das, falls die URL versehentlich geteilt wurde.') }}
							</p>
						</div>
					</template>
					<div v-else class="settings-item">
						<NcButton variant="primary" :disabled="generatingCalendarFeed" @click="resetCalendarFeed">
							<template #icon>
								<NcLoadingIcon v-if="generatingCalendarFeed" :size="20" />
							</template>
							{{ t('contractmanager', 'Abo-URL erzeugen') }}
						</NcButton>
						<p class="settings-description">
							{{ t('contractmanager', 'Die URL enthält einen geheimen Zugangs-Token. Gib sie nicht weiter.') }}
						</p>
					</div>
				</div>
			</div>
		</div>

		<NcDialog v-if="importPreview"
			:name="t('contractmanager', 'Backup einlesen?')"
			@close="closeImportDialog">
			<p class="import-file">
				{{ importPath }}
			</p>
			<ul class="import-summary">
				<li>{{ n('contractmanager', '%n Vertrag wird eingelesen', '%n Verträge werden eingelesen', importPreview.contracts) }}</li>
				<li v-if="importPreview.duplicates > 0">
					{{ n('contractmanager', '%n Vertrag ist schon vorhanden und wird übersprungen', '%n Verträge sind schon vorhanden und werden übersprungen', importPreview.duplicates) }}
				</li>
				<li v-if="importPreview.inTrash > 0">
					{{ n('contractmanager', '%n Vertrag liegt im Papierkorb und wird übersprungen. Stelle ihn dort wieder her.', '%n Verträge liegen im Papierkorb und werden übersprungen. Stelle sie dort wieder her.', importPreview.inTrash) }}
				</li>
				<li v-if="importPreview.invalid > 0">
					{{ n('contractmanager', '%n Eintrag ist unvollständig und wird übersprungen', '%n Einträge sind unvollständig und werden übersprungen', importPreview.invalid) }}
				</li>
				<li v-if="importPreview.categories > 0">
					{{ n('contractmanager', '%n Kategorie wird neu angelegt', '%n Kategorien werden neu angelegt', importPreview.categories) }}
				</li>
				<li v-if="importPreview.missingFiles > 0">
					{{ n('contractmanager', 'Bei %n Vertrag wurde das verknüpfte Dokument oder der Ordner hier nicht gefunden. Der Pfad bleibt erhalten, die Dateien musst du selbst nachkopieren.', 'Bei %n Verträgen wurden verknüpfte Dokumente oder Ordner hier nicht gefunden. Die Pfade bleiben erhalten, die Dateien musst du selbst nachkopieren.', importPreview.missingFiles) }}
				</li>
				<li v-if="importPreview.unknownUsers > 0">
					{{ n('contractmanager', 'Bei %n Vertrag gibt es den Zuständigen hier nicht, das Feld bleibt leer.', 'Bei %n Verträgen gibt es den Zuständigen hier nicht, das Feld bleibt leer.', importPreview.unknownUsers) }}
				</li>
			</ul>
			<template #actions>
				<NcButton @click="closeImportDialog">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary"
					:disabled="importBusy || importPreview.contracts === 0"
					@click="runImport">
					<template v-if="importBusy" #icon>
						<NcLoadingIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Einlesen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { markRaw } from 'vue'
import { mapState, mapActions } from 'pinia'
import { getCanonicalLocale, translatePlural as n } from '@nextcloud/l10n'
import { getFilePickerBuilder, showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import CashMultipleIcon from 'vue-material-design-icons/CashMultiple.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import { useCategoriesStore } from '../store/categories'
import { useContractsStore } from '../store/contracts'
import SettingsService from '../services/SettingsService'
import ImportService from '../services/ImportService'

// Persoenliche Einstellungen; App-weites steht in der Verwaltung (AdminView).
export default {
	name: 'SettingsView',
	components: {
		NcButton,
		NcDialog,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
		BackupRestoreIcon,
		UploadIcon,
	},
	data() {
		return {
			activeSection: 'notifications',
			emailReminder: false,
			reminderMode: 'own',
			userReminders: {
				talkChatToken: '',
				reminderDays1Personal: '',
				reminderDays2Personal: '',
			},
			reminderDefaults: {
				days1: 14,
				days2: 3,
			},
			savingUserReminders: false,
			defaultAmountType: 'netto',
			backupEnabled: false,
			backupFolder: '/VertragsWerk-Backup',
			backupInterval: 'weekly',
			// '' = no fixed hour; select values are strings.
			backupHour: '',
			savedBackupHour: '',
			backupLastRun: 0,
			backupNextRun: 0,
			backingUpNow: false,
			importBusy: false,
			importPath: '',
			importPreview: null,
			calendarFeedUrl: '',
			generatingCalendarFeed: false,
		}
	},
	computed: {
		...mapState(useContractsStore, ['canEdit']),
		navGroups() {
			return [{
				label: t('contractmanager', 'Persönlich'),
				items: [
					{ key: 'notifications', label: t('contractmanager', 'Benachrichtigungen'), icon: markRaw(BellIcon) },
					{ key: 'amount', label: t('contractmanager', 'Betragsangabe'), icon: markRaw(CashMultipleIcon) },
					{ key: 'backup', label: t('contractmanager', 'Automatisches Backup'), icon: markRaw(BackupRestoreIcon) },
					{ key: 'calendar', label: t('contractmanager', 'Kalender-Abo'), icon: markRaw(CalendarIcon) },
				],
			}]
		},
		// "Last / next backup" display (#397). Both are absolute Unix timestamps
		// computed by the backend, so the frontend only formats them.
		lastBackupText() {
			if (!this.backupLastRun) {
				return t('contractmanager', 'Noch nie')
			}
			return new Date(this.backupLastRun * 1000).toLocaleString(getCanonicalLocale())
		},
		nextBackupText() {
			if (!this.backupNextRun) {
				return t('contractmanager', 'Nach der ersten Sicherung')
			}
			return new Date(this.backupNextRun * 1000).toLocaleString(getCanonicalLocale())
		},
	},
	async created() {
		await this.loadUserSettings()
	},
	methods: {
		...mapActions(useCategoriesStore, ['fetchCategories']),
		...mapActions(useContractsStore, ['fetchContracts', 'fetchArchivedContracts']),

		async loadUserSettings() {
			try {
				const settings = await SettingsService.getUserSettings()
				this.emailReminder = settings.emailReminder
				this.reminderMode = settings.reminderMode || 'own'
				this.userReminders = {
					talkChatToken: settings.talkChatToken || '',
					reminderDays1Personal: settings.reminderDays1Personal ?? '',
					reminderDays2Personal: settings.reminderDays2Personal ?? '',
				}
				this.reminderDefaults = {
					days1: settings.reminderDays1 || 14,
					days2: settings.reminderDays2 || 3,
				}
				this.defaultAmountType = settings.defaultAmountType || 'netto'
				this.backupEnabled = settings.backupEnabled === true
				this.backupFolder = settings.backupFolder || '/VertragsWerk-Backup'
				this.backupInterval = settings.backupInterval || 'weekly'
				this.backupHour = typeof settings.backupHour === 'number' ? String(settings.backupHour) : ''
				this.savedBackupHour = this.backupHour
				this.backupLastRun = settings.backupLastRun || 0
				this.backupNextRun = settings.backupNextRun || 0
				this.calendarFeedUrl = settings.calendarFeedUrl || ''
			} catch (error) {
				console.error('Failed to load user settings:', error)
			}
		},

		async resetCalendarFeed() {
			this.generatingCalendarFeed = true
			try {
				const { calendarFeedUrl } = await SettingsService.resetCalendarFeedToken()
				this.calendarFeedUrl = calendarFeedUrl
				showSuccess(t('contractmanager', 'Abo-URL erzeugt'))
			} catch (error) {
				console.error('Failed to reset calendar feed token:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
			} finally {
				this.generatingCalendarFeed = false
			}
		},

		async copyCalendarFeedUrl() {
			try {
				await navigator.clipboard.writeText(this.calendarFeedUrl)
				showSuccess(t('contractmanager', 'In die Zwischenablage kopiert'))
			} catch (error) {
				// Clipboard API can be blocked (e.g. non-secure context) — fall back
				// to selecting the field so the user can copy manually.
				const input = this.$refs.calendarFeedInput
				if (input) {
					input.select()
				}
				showError(t('contractmanager', 'Kopieren nicht möglich – bitte manuell markieren'))
			}
		},

		async onBackupEnabledChange(value) {
			const previous = this.backupEnabled
			this.backupEnabled = value
			try {
				await SettingsService.updateUserSettings({ backupEnabled: value })
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup enabled:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.backupEnabled = previous
			}
		},

		async onBackupFolderChange() {
			const previous = this.backupFolder
			try {
				await SettingsService.updateUserSettings({ backupFolder: this.backupFolder })
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup folder:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.backupFolder = previous
			}
		},

		async onBackupIntervalChange() {
			const previous = this.backupInterval
			try {
				const updated = await SettingsService.updateUserSettings({ backupInterval: this.backupInterval })
				// "Nächste Sicherung" depends on the interval; take the server's
				// freshly computed value so the two never diverge.
				if (typeof updated.backupNextRun === 'number') {
					this.backupNextRun = updated.backupNextRun
				}
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup interval:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.backupInterval = previous
			}
		},

		async onBackupHourChange() {
			try {
				const updated = await SettingsService.updateUserSettings({
					backupHour: this.backupHour === '' ? -1 : Number(this.backupHour),
				})
				if (typeof updated.backupNextRun === 'number') {
					this.backupNextRun = updated.backupNextRun
				}
				this.savedBackupHour = this.backupHour
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save backup hour:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.backupHour = this.savedBackupHour
			}
		},

		async pickImportFile() {
			let path
			try {
				path = await getFilePickerBuilder(t('contractmanager', 'Sicherungsdatei wählen'))
					.setMultiSelect(false)
					.setMimeTypeFilter(['application/json'])
					.addButton({
						label: t('contractmanager', 'Auswählen'),
						variant: 'primary',
						callback: () => {},
					})
					.build()
					.pick()
			} catch (e) {
				console.debug('Import file picker cancelled', e)
				return
			}
			if (!path) {
				return
			}
			this.importBusy = true
			try {
				this.importPreview = await ImportService.preview(path)
				this.importPath = path
			} catch (error) {
				console.error('Import preview failed:', error)
				showError(error?.response?.data?.error || t('contractmanager', 'Die Datei konnte nicht gelesen werden.'))
			} finally {
				this.importBusy = false
			}
		},

		async runImport() {
			this.importBusy = true
			try {
				const summary = await ImportService.import(this.importPath)
				this.closeImportDialog()
				showSuccess(n('contractmanager', '%n Vertrag eingelesen', '%n Verträge eingelesen', summary.contracts))
				await Promise.all([this.fetchContracts(), this.fetchArchivedContracts(), this.fetchCategories()])
			} catch (error) {
				console.error('Import failed:', error)
				showError(error?.response?.data?.error || t('contractmanager', 'Einlesen fehlgeschlagen'))
			} finally {
				this.importBusy = false
			}
		},

		closeImportDialog() {
			this.importPreview = null
			this.importPath = ''
		},

		// Manually trigger a backup ("Jetzt sichern", #397) and refresh last/next.
		async triggerBackupNow() {
			this.backingUpNow = true
			try {
				const { backupLastRun, backupNextRun } = await SettingsService.triggerBackupNow()
				this.backupLastRun = backupLastRun
				this.backupNextRun = backupNextRun
				showSuccess(t('contractmanager', 'Sicherung erstellt'))
			} catch (error) {
				console.error('Failed to trigger backup:', error)
				showError(t('contractmanager', 'Sicherung fehlgeschlagen'))
			} finally {
				this.backingUpNow = false
			}
		},

		async onReminderModeChange(value) {
			const previous = this.reminderMode
			try {
				await SettingsService.updateUserSettings({ reminderMode: value })
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save reminder mode:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.reminderMode = previous
			}
		},

		async saveUserReminderSettings() {
			this.savingUserReminders = true
			try {
				// Empty string clears the personal value (0 → backend treats as "use default").
				const days1 = this.userReminders.reminderDays1Personal
				const days2 = this.userReminders.reminderDays2Personal
				await SettingsService.updateUserSettings({
					talkChatToken: this.userReminders.talkChatToken,
					reminderDays1Personal: days1 === '' ? 0 : parseInt(days1, 10),
					reminderDays2Personal: days2 === '' ? 0 : parseInt(days2, 10),
				})
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save reminder settings:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
			} finally {
				this.savingUserReminders = false
			}
		},

		async onDefaultAmountTypeChange() {
			const previous = this.defaultAmountType
			try {
				await SettingsService.updateUserSettings({ defaultAmountType: this.defaultAmountType })
			} catch (error) {
				console.error('Failed to save default amount type:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.defaultAmountType = previous
			}
		},

		async onEmailReminderChange(value) {
			try {
				await SettingsService.updateUserSettings({ emailReminder: value })
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save user settings:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
				this.emailReminder = !value
			}
		},
	},
}
</script>

<style scoped lang="scss" src="./settings-page.scss"></style>
