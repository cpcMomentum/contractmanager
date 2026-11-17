<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('contractmanager', 'Verwaltung') }}</h2>
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
				<!-- Permission Settings -->
				<div v-show="activeSection === 'permissions'" class="settings-section">
					<h3>{{ t('contractmanager', 'Berechtigungen') }}</h3>

					<!-- Editor Permission -->
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Editor-Berechtigung') }}</label>
						<p class="settings-description">
							{{ t('contractmanager', 'Benutzer und Gruppen mit Editor-Rechten können alle sichtbaren Verträge erstellen und bearbeiten.') }}
						</p>
						<NcSelect v-model="permissionSettings.editors"
							:options="searchResults"
							:loading="searching"
							:placeholder="t('contractmanager', 'Benutzer oder Gruppen suchen...')"
							:multiple="true"
							label="displayName"
							track-by="id"
							class="permission-select"
							@update:model-value="onEditorsChange">
							<template #option="option">
								<div class="permission-option">
									<AccountGroupIcon v-if="option.type === 'group'" :size="20" />
									<AccountIcon v-else :size="20" />
									<span>{{ option.displayName }}</span>
									<span class="permission-option-type">
										{{ option.type === 'group' ? t('contractmanager', 'Gruppe') : t('contractmanager', 'Benutzer') }}
									</span>
								</div>
							</template>
							<template #selected-option="option">
								<div class="permission-tag">
									<AccountGroupIcon v-if="option.type === 'group'" :size="16" />
									<AccountIcon v-else :size="16" />
									<span>{{ option.displayName }}</span>
								</div>
							</template>
						</NcSelect>
					</div>

					<!-- Viewer Permission -->
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Viewer-Berechtigung') }}</label>
						<p class="settings-description">
							{{ t('contractmanager', 'Benutzer und Gruppen mit Viewer-Rechten können alle Verträge nur ansehen.') }}
						</p>
						<NcSelect v-model="permissionSettings.viewers"
							:options="searchResults"
							:loading="searching"
							:placeholder="t('contractmanager', 'Benutzer oder Gruppen suchen...')"
							:multiple="true"
							label="displayName"
							track-by="id"
							class="permission-select"
							@update:model-value="onViewersChange">
							<template #option="option">
								<div class="permission-option">
									<AccountGroupIcon v-if="option.type === 'group'" :size="20" />
									<AccountIcon v-else :size="20" />
									<span>{{ option.displayName }}</span>
									<span class="permission-option-type">
										{{ option.type === 'group' ? t('contractmanager', 'Gruppe') : t('contractmanager', 'Benutzer') }}
									</span>
								</div>
							</template>
							<template #selected-option="option">
								<div class="permission-tag">
									<AccountGroupIcon v-if="option.type === 'group'" :size="16" />
									<AccountIcon v-else :size="16" />
									<span>{{ option.displayName }}</span>
								</div>
							</template>
						</NcSelect>
					</div>
				</div>

				<!-- Verträge übertragen -->
				<div v-show="activeSection === 'transfer'" class="settings-section">
					<h3>{{ t('contractmanager', 'Verträge übertragen') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Überträgt die Zuständigkeit für alle Verträge einer Person auf eine andere, zum Beispiel bei einem Mitarbeiterwechsel. „Erstellt von" bleibt unverändert.') }}
					</p>
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Von') }}</label>
						<NcSelect v-model="transferFrom"
							:options="transferUserResults"
							:loading="transferSearching"
							:placeholder="t('contractmanager', 'Benutzer suchen...')"
							label="displayName"
							track-by="id"
							:clearable="true"
							@open="onTransferOpen"
							@search="onTransferSearch"
							@update:model-value="onTransferFromChange" />
						<p v-if="transferCount !== null" class="settings-description">
							{{ t('contractmanager', 'Betrifft {count} Verträge', { count: transferCount }) }}
						</p>
					</div>
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Auf') }}</label>
						<NcSelect v-model="transferTo"
							:options="transferUserResults"
							:loading="transferSearching"
							:placeholder="t('contractmanager', 'Benutzer suchen...')"
							label="displayName"
							track-by="id"
							:clearable="true"
							@open="onTransferOpen"
							@search="onTransferSearch" />
					</div>
					<div class="settings-actions">
						<NcButton variant="primary" :disabled="!canTransfer || transferring" @click="showTransferDialog = true">
							<template #icon>
								<NcLoadingIcon v-if="transferring" :size="20" />
							</template>
							{{ t('contractmanager', 'Übertragen') }}
						</NcButton>
					</div>
					<NcDialog v-if="showTransferDialog"
						:name="t('contractmanager', 'Verträge übertragen')"
						:message="t('contractmanager', '{count} Verträge von {from} auf {to} übertragen?', { count: transferCount || 0, from: transferFrom ? transferFrom.displayName : '', to: transferTo ? transferTo.displayName : '' })"
						@update:open="showTransferDialog = $event">
						<template #actions>
							<NcButton @click="showTransferDialog = false">
								{{ t('contractmanager', 'Abbrechen') }}
							</NcButton>
							<NcButton variant="primary" @click="doTransfer">
								{{ t('contractmanager', 'Übertragen') }}
							</NcButton>
						</template>
					</NcDialog>
				</div>

				<div v-show="activeSection === 'general'" class="settings-section">
					<h3>{{ t('contractmanager', 'Allgemein') }}</h3>

					<!-- Reminder link health (overwrite.cli.url) -->
					<div v-if="showReminderLinkWarning" class="reminder-link-note">
						<button type="button"
							class="reminder-link-summary"
							:aria-expanded="reminderLinkExpanded ? 'true' : 'false'"
							@click="reminderLinkExpanded = !reminderLinkExpanded">
							<InformationOutlineIcon :size="18" class="reminder-link-summary__icon" />
							<span>{{ t('contractmanager', 'Hinweis') }}</span>
							<ChevronUpIcon v-if="reminderLinkExpanded" :size="18" />
							<ChevronDownIcon v-else :size="18" />
						</button>
						<div v-if="reminderLinkExpanded" class="reminder-link-details">
							<p class="reminder-link-intro">
								{{ t('contractmanager', 'Links in Erinnerungs-E-Mails führen eventuell zur falschen Adresse:') }}
							</p>
							<dl class="reminder-link-values">
								<div>
									<dt>{{ t('contractmanager', 'Hinterlegt') }}</dt>
									<dd>{{ reminderLink.cliUrl || '—' }}</dd>
								</div>
								<div>
									<dt>{{ t('contractmanager', 'Du nutzt') }}</dt>
									<dd>{{ safeAccessHost }}</dd>
								</div>
							</dl>
							<p class="reminder-link-fix">
								{{ t('contractmanager', 'So korrigierst du das: bei verwaltetem Hosting im Verwaltungs-Panel deines Anbieters, auf einem eigenen Server per Kommandozeile:') }}
							</p>
							<code class="reminder-link-command">{{ reminderLinkCommand }}</code>
						</div>
					</div>

					<!-- Default reminder lead time -->
					<div class="settings-item reminder-days">
						<label class="settings-label">{{ t('contractmanager', 'Standard-Vorlaufzeit (Tage vor Kündigungsfrist)') }}</label>
						<p class="settings-description">
							{{ t('contractmanager', 'Gilt für alle Benutzer, die keine eigene Vorlaufzeit eingestellt haben.') }}
						</p>

						<div class="reminder-inputs">
							<div class="reminder-input-group">
								<label>{{ t('contractmanager', 'Erste Erinnerung') }}</label>
								<NcTextField v-model="adminSettings.reminderDays1"
									type="number"
									:min="1"
									class="number-input"
									@blur="saveReminderDays('reminderDays1')" />
								<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
							</div>

							<div class="reminder-input-group">
								<label>{{ t('contractmanager', 'Letzte Erinnerung') }}</label>
								<NcTextField v-model="adminSettings.reminderDays2"
									type="number"
									:min="1"
									class="number-input"
									@blur="saveReminderDays('reminderDays2')" />
								<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
							</div>
						</div>
					</div>

					<!-- Successor for contracts of deleted accounts (#299) -->
					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'Nachfolger bei gelöschtem Konto') }}</label>
						<p class="settings-description">
							{{ t('contractmanager', 'Wird ein Konto gelöscht, geht die Zuständigkeit für dessen Verträge an diese Person über. Ohne Angabe bleiben die Verträge unverändert. Private Verträge werden nie automatisch übertragen, sie bleiben für Administratoren sichtbar. Verträge werden dabei nie gelöscht.') }}
						</p>
						<NcSelect v-model="deletionSuccessorOption"
							:options="transferUserResults"
							:loading="transferSearching"
							:placeholder="t('contractmanager', 'Benutzer suchen...')"
							label="displayName"
							track-by="id"
							:clearable="true"
							input-id="deletion-successor"
							@open="onTransferOpen"
							@search="onTransferSearch"
							@update:model-value="onDeletionSuccessorChange" />
						<p class="settings-description">
							{{ t('contractmanager', 'Denk daran, den Eintrag anzupassen, wenn diese Person selbst das Unternehmen verlässt.') }}
						</p>
					</div>
				</div>

				<!-- Custom Fields Settings -->
				<div v-show="activeSection === 'customFields'" class="settings-section">
					<h3>{{ t('contractmanager', 'Zusatzfelder') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Aktiviere bis zu 3 optionale Zusatzfelder für Verträge.') }}
					</p>

					<div v-for="n in 3" :key="'cf' + n" class="settings-item custom-field-item">
						<NcCheckboxRadioSwitch :model-value="customFieldEnabled(n)"
							@update:model-value="toggleCustomField(n, $event)">
							{{ t('contractmanager', 'Zusatzfeld {n}', { n }) }}
						</NcCheckboxRadioSwitch>
						<NcTextField v-if="customFieldEnabled(n)"
							v-model="adminSettings['customFieldLabel' + n]"
							:placeholder="customFieldPlaceholders[n - 1]"
							class="settings-input custom-field-label"
							@blur="saveAdminField('customFieldLabel' + n, adminSettings['customFieldLabel' + n])" />
					</div>
				</div>

				<!-- AI Extraction Settings -->
				<div v-show="activeSection === 'ai'" class="settings-section">
					<h3>{{ t('contractmanager', 'KI-Vertragsanalyse') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Automatische Erkennung von Vertragsdaten aus PDF-Dokumenten.') }}
					</p>

					<div class="settings-item">
						<label class="settings-label">{{ t('contractmanager', 'KI-Provider') }}</label>
						<NcSelect v-model="adminSettings.aiProvider"
							:options="aiProviderOptions"
							:placeholder="t('contractmanager', 'Deaktiviert')"
							label="label"
							track-by="value"
							:reduce="option => option.value"
							:clearable="true"
							class="settings-input" />
						<p class="ai-status" :class="aiActive ? 'ai-status--active' : 'ai-status--inactive'">
							{{ aiActive
								? t('contractmanager', 'Aktiv – Analyse verfügbar')
								: (adminSettings.aiProvider
									? t('contractmanager', 'Inaktiv – kein API-Key gespeichert')
									: t('contractmanager', 'Deaktiviert – kein Provider gewählt')) }}
						</p>
					</div>

					<template v-if="adminSettings.aiProvider">
						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'API Key') }}</label>
							<NcTextField v-model="adminSettings.aiApiKey"
								type="password"
								:placeholder="t('contractmanager', 'API Key eingeben')"
								class="settings-input" />
						</div>

						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'API URL') }}</label>
							<p class="settings-description">
								{{ t('contractmanager', 'Standard-URL wird automatisch gesetzt. Für Ollama z.B. http://localhost:11434/v1') }}
							</p>
							<NcTextField v-model="adminSettings.aiApiUrl"
								:placeholder="aiDefaultUrl"
								class="settings-input" />
						</div>

						<div class="settings-item">
							<label class="settings-label">{{ t('contractmanager', 'Modell') }}</label>
							<NcTextField v-model="adminSettings.aiModel"
								:placeholder="aiDefaultModel"
								class="settings-input" />
						</div>
					</template>

					<div class="settings-actions settings-actions--inline">
						<NcButton variant="primary" :disabled="savingAi" @click="saveAiSettings">
							<template #icon>
								<NcLoadingIcon v-if="savingAi" :size="20" />
							</template>
							{{ t('contractmanager', 'KI-Einstellungen speichern') }}
						</NcButton>
						<p class="settings-description settings-description--hint">
							{{ t('contractmanager', 'Provider, API-Key, URL und Modell werden zusammen gespeichert.') }}
						</p>
					</div>
				</div>

				<!-- Kategorien -->
				<div v-show="activeSection === 'categories'" class="settings-section">
					<h3>{{ t('contractmanager', 'Kategorien') }}</h3>
					<p class="settings-description">
						{{ t('contractmanager', 'Kategorien für die Vertragsorganisation hinzufügen, bearbeiten oder löschen.') }}
					</p>

					<div class="category-management">
						<!-- Add new category -->
						<div class="category-add">
							<NcTextField v-model="newCategoryName"
								:placeholder="t('contractmanager', 'Neue Kategorie...')"
								class="category-input"
								@keyup.enter="addCategory" />
							<NcButton variant="primary"
								:disabled="!newCategoryName.trim() || addingCategory"
								@click="addCategory">
								<template #icon>
									<PlusIcon :size="20" />
								</template>
								{{ t('contractmanager', 'Hinzufügen') }}
							</NcButton>
						</div>

						<!-- Category list -->
						<div class="category-list-edit">
							<div v-for="category in categories"
								:key="category.id"
								class="category-edit-item">
								<template v-if="editingCategoryId === category.id">
									<NcTextField v-model="editingCategoryName"
										class="category-input"
										@keyup.enter="saveCategory(category)"
										@keyup.esc="cancelEdit" />
									<NcButton variant="primary" @click="saveCategory(category)">
										<template #icon>
											<CheckIcon :size="20" />
										</template>
									</NcButton>
									<NcButton variant="tertiary" @click="cancelEdit">
										<template #icon>
											<CloseIcon :size="20" />
										</template>
									</NcButton>
								</template>
								<template v-else>
									<span class="category-name">{{ category.name }}</span>
									<div class="category-actions">
										<NcButton variant="tertiary" @click="startEdit(category)">
											<template #icon>
												<PencilIcon :size="20" />
											</template>
										</NcButton>
										<NcButton variant="tertiary"
											@click="confirmDeleteCategory(category)">
											<template #icon>
												<DeleteIcon :size="20" />
											</template>
										</NcButton>
									</div>
								</template>
							</div>
						</div>
					</div>
				</div>

				<!-- WerkPlus: eigene Knoepfe, deshalb ohne gemeinsamen Speichern-Knopf -->
				<div v-show="activeSection === 'werkplus'" class="settings-section">
					<h3>{{ t('contractmanager', 'WerkPlus') }}</h3>
					<WerkPlusSettings />
				</div>
			</div>
		</div>

		<NcDialog v-if="showDeleteCategoryDialog"
			:name="t('contractmanager', 'Kategorie löschen')"
			@close="showDeleteCategoryDialog = false">
			<p>{{ t('contractmanager', 'Kategorie "{name}" wirklich löschen?', { name: deletingCategory ? deletingCategory.name : '' }) }}</p>
			<template #actions>
				<NcButton @click="showDeleteCategoryDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="error" @click="executeDeleteCategory">
					{{ t('contractmanager', 'Löschen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { markRaw } from 'vue'
import { mapState, mapActions } from 'pinia'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUpIcon from 'vue-material-design-icons/ChevronUp.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FormTextboxIcon from 'vue-material-design-icons/FormTextbox.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import RobotOutlineIcon from 'vue-material-design-icons/RobotOutline.vue'
import ShieldIcon from 'vue-material-design-icons/Shield.vue'
import SwapHorizontalIcon from 'vue-material-design-icons/SwapHorizontal.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import { useCategoriesStore } from '../store/categories'
import SettingsService from '../services/SettingsService'
import ContractService from '../services/ContractService'
import WerkPlusSettings from '../components/WerkPlusSettings.vue'
import { isAiActive } from '../utils/aiSettings'
import { resolveCustomFieldEnabled, customFieldEnabledKey } from '../utils/customFields'

// App-weite Verwaltung, nur fuer Nextcloud-Admins (App.vue blendet den Punkt sonst aus).
export default {
	name: 'AdminView',
	components: {
		NcButton,
		NcDialog,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
		NcSelect,
		AccountGroupIcon,
		AccountIcon,
		CheckIcon,
		ChevronDownIcon,
		ChevronUpIcon,
		CloseIcon,
		DeleteIcon,
		InformationOutlineIcon,
		PencilIcon,
		PlusIcon,
		WerkPlusSettings,
	},
	data() {
		return {
			activeSection: 'permissions',
			showDeleteCategoryDialog: false,
			deletingCategory: null,
			savingAi: false,
			adminSettings: {
				reminderDays1: 14,
				reminderDays2: 3,
				customFieldLabel1: '',
				customFieldLabel2: '',
				customFieldLabel3: '',
				customField1Enabled: false,
				customField2Enabled: false,
				customField3Enabled: false,
				aiProvider: '',
				aiApiKey: '',
				aiApiUrl: '',
				aiModel: '',
				deletionSuccessor: '',
			},
			// Object form of adminSettings.deletionSuccessor for the NcSelect.
			deletionSuccessorOption: null,
			reminderLink: null,
			reminderLinkExpanded: false,
			permissionSettings: {
				editors: [],
				viewers: [],
			},
			searchResults: [],
			searching: false,
			transferFrom: null,
			transferTo: null,
			transferCount: null,
			transferUserResults: [],
			transferSearching: false,
			transferInitialLoaded: false,
			transferring: false,
			showTransferDialog: false,
			newCategoryName: '',
			addingCategory: false,
			editingCategoryId: null,
			editingCategoryName: '',
		}
	},
	computed: {
		navGroups() {
			return [
				{
					label: t('contractmanager', 'Zugriff'),
					items: [
						{ key: 'permissions', label: t('contractmanager', 'Berechtigungen'), icon: markRaw(ShieldIcon) },
						{ key: 'transfer', label: t('contractmanager', 'Verträge übertragen'), icon: markRaw(SwapHorizontalIcon) },
					],
				},
				{
					label: t('contractmanager', 'Verträge'),
					items: [
						{ key: 'general', label: t('contractmanager', 'Allgemein'), icon: markRaw(CogIcon) },
						{ key: 'customFields', label: t('contractmanager', 'Zusatzfelder'), icon: markRaw(FormTextboxIcon) },
						{ key: 'ai', label: t('contractmanager', 'KI-Vertragsanalyse'), icon: markRaw(RobotOutlineIcon) },
						{ key: 'categories', label: t('contractmanager', 'Kategorien'), icon: markRaw(TagIcon) },
					],
				},
				{
					label: t('contractmanager', 'Lizenz'),
					items: [
						{ key: 'werkplus', label: t('contractmanager', 'WerkPlus'), icon: markRaw(KeyOutlineIcon) },
					],
				},
			]
		},
		...mapState(useCategoriesStore, {
			categories: 'allCategories',
		}),
		customFieldPlaceholders() {
			return [
				t('contractmanager', 'z.B. Versicherungsnummer'),
				t('contractmanager', 'z.B. Zugeordnet an'),
				t('contractmanager', 'z.B. Kostenstelle'),
			]
		},
		aiProviderOptions() {
			return [
				{ value: 'claude', label: 'Claude (Anthropic)' },
				{ value: 'openai_compatible', label: t('contractmanager', 'OpenAI-kompatibel (Mistral, Ollama, OpenAI, ...)') },
			]
		},
		aiDefaultUrl() {
			if (this.adminSettings.aiProvider === 'claude') return 'https://api.anthropic.com'
			return 'https://api.openai.com/v1'
		},
		aiDefaultModel() {
			if (this.adminSettings.aiProvider === 'claude') return 'claude-sonnet-4-6'
			return 'gpt-4o'
		},
		// KI ist erst "aktiv", wenn ein Provider gewählt UND ein API-Key
		// gespeichert ist. Der Key kommt vom Server maskiert zurück (nicht-leer),
		// ein frisch eingegebener Key ist der Klartext — beides zählt als gesetzt.
		aiActive() {
			return isAiActive(this.adminSettings)
		},
		canTransfer() {
			return !!this.transferFrom && !!this.transferTo
				&& this.principalUid(this.transferFrom) !== this.principalUid(this.transferTo)
		},
		// True when reminder mail links may point at the wrong host.
		showReminderLinkWarning() {
			return !!this.reminderLink && this.reminderLink.status !== 'ok'
		},
		// accessHost stammt aus dem HTTP-Host-Header. Vor der Anzeige defensiv auf
		// gültige Hostname-/Port-Zeichen reduzieren, damit ein manipulierter Header
		// keinen präparierten Text in den angezeigten occ-Befehl schmuggeln kann.
		safeAccessHost() {
			const raw = (this.reminderLink && this.reminderLink.accessHost) || ''
			return raw.replace(/[^a-zA-Z0-9.:-]/g, '')
		},
		reminderLinkCommand() {
			const host = this.safeAccessHost || 'deine-domain.tld'
			return `sudo -u www-data php occ config:system:set overwrite.cli.url --value="https://${host}"`
		},
	},
	async created() {
		this.fetchCategories()
		await this.loadAdminSettings()
		await this.loadPermissionSettings()
		await this.performSearch('')
	},
	methods: {
		...mapActions(useCategoriesStore, ['fetchCategories', 'createCategory', 'updateCategory', 'deleteCategory']),

		customFieldEnabled(n) {
			return this.adminSettings[customFieldEnabledKey(n)]
		},

		// Toggle only flips the active flag; the label is left untouched. Enabling
		// no longer prefills a hardcoded value, and disabling keeps the name so it
		// survives being switched off and on again (#368). A deactivated field is
		// gated out of the contract form server-side (empty label there).
		toggleCustomField(n, enabled) {
			const key = customFieldEnabledKey(n)
			this.adminSettings[key] = enabled
			this.saveAdminField(key, enabled)
		},

		// Autosave a single independent admin field (reminder days, custom-field
		// labels, deletion successor). The backend PUT /admin applies only the
		// fields that are non-null, so sending one field leaves the rest — and
		// especially the AI credentials — untouched. The AI block keeps its own
		// deliberate save (saveAiSettings) because it only makes sense as a
		// complete, validated set. (#151)
		async saveAdminField(field, value) {
			try {
				await SettingsService.updateAdminSettings({ [field]: value })
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save admin setting ' + field + ':', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
			}
		},

		// A cleared reminder-days field parses to NaN, which JSON-serializes to
		// null and is silently ignored by the backend (see saveAdminField) — do
		// not report a false "gespeichert" for a value that was never persisted.
		saveReminderDays(field) {
			const parsed = parseInt(this.adminSettings[field], 10)
			if (Number.isNaN(parsed)) {
				return
			}
			this.saveAdminField(field, parsed)
		},

		async loadAdminSettings() {
			try {
				const settings = await SettingsService.getAdminSettings()
				this.adminSettings = {
					reminderDays1: settings.reminderDays1 || 14,
					reminderDays2: settings.reminderDays2 || 3,
					customFieldLabel1: settings.customFieldLabel1 || '',
					customFieldLabel2: settings.customFieldLabel2 || '',
					customFieldLabel3: settings.customFieldLabel3 || '',
					customField1Enabled: resolveCustomFieldEnabled(settings, 1),
					customField2Enabled: resolveCustomFieldEnabled(settings, 2),
					customField3Enabled: resolveCustomFieldEnabled(settings, 3),
					aiProvider: settings.aiProvider || '',
					aiApiKey: settings.aiApiKey || '',
					aiApiUrl: settings.aiApiUrl || '',
					aiModel: settings.aiModel || '',
					deletionSuccessor: settings.deletionSuccessor || '',
				}
				this.deletionSuccessorOption = settings.deletionSuccessor
					? {
						id: 'user:' + settings.deletionSuccessor,
						uid: settings.deletionSuccessor,
						displayName: settings.deletionSuccessorDisplayName || settings.deletionSuccessor,
					}
					: null
				this.reminderLink = settings.reminderLink || null
			} catch (error) {
				console.error('Failed to load admin settings:', error)
			}
		},

		async loadPermissionSettings() {
			try {
				const settings = await SettingsService.getPermissionSettings()
				// Convert string IDs to objects for NcSelect
				this.permissionSettings.editors = await this.convertIdsToObjects(settings.editors || [])
				this.permissionSettings.viewers = await this.convertIdsToObjects(settings.viewers || [])
			} catch (error) {
				console.error('Failed to load permission settings:', error)
			}
		},

		async convertIdsToObjects(ids) {
			// Convert stored IDs like "group:admin" or "user:john" to display objects
			const objects = []
			for (const id of ids) {
				const [type, identifier] = id.split(':')
				objects.push({
					id,
					type,
					displayName: identifier, // Will be updated by search if user searches
					...(type === 'group' ? { gid: identifier } : { uid: identifier }),
				})
			}
			return objects
		},

		async performSearch(query) {
			try {
				this.searching = true
				this.searchResults = await SettingsService.searchUsersAndGroups(query)
			} catch (error) {
				console.error('Failed to search users/groups:', error)
				this.searchResults = []
			} finally {
				this.searching = false
			}
		},

		principalUid(principal) {
			if (!principal) return ''
			return principal.uid || String(principal.id || '').replace('user:', '')
		},
		// Clearing the field is a valid choice: no successor means contracts of
		// a deleted account are left untouched.
		onDeletionSuccessorChange(option) {
			this.adminSettings.deletionSuccessor = this.principalUid(option)
			this.saveAdminField('deletionSuccessor', this.adminSettings.deletionSuccessor || '')
		},

		// Lazy-load a first batch of users when a transfer picker is first opened,
		// so the dropdown is not empty before typing (same behaviour as the
		// "responsible" picker in the contract form).
		async onTransferOpen() {
			if (this.transferInitialLoaded) return
			this.transferInitialLoaded = true
			await this.fetchTransferUsers('')
		},
		async onTransferSearch(query) {
			await this.fetchTransferUsers(query)
		},
		async fetchTransferUsers(query) {
			try {
				this.transferSearching = true
				this.transferUserResults = await ContractService.searchUsers(query || '')
			} catch (e) {
				this.transferUserResults = []
			} finally {
				this.transferSearching = false
			}
		},

		async onTransferFromChange() {
			this.transferCount = null
			if (!this.transferFrom) return
			try {
				this.transferCount = await ContractService.transferPreview(this.principalUid(this.transferFrom))
			} catch (e) {
				console.error('Failed to load transfer preview:', e)
			}
		},

		async doTransfer() {
			this.showTransferDialog = false
			if (!this.canTransfer) return
			this.transferring = true
			try {
				const count = await ContractService.transfer(this.principalUid(this.transferFrom), this.principalUid(this.transferTo))
				showSuccess(t('contractmanager', '{count} Verträge übertragen', { count }))
				this.transferFrom = null
				this.transferTo = null
				this.transferCount = null
			} catch (e) {
				console.error('Transfer failed:', e)
				showError(t('contractmanager', 'Übertragung fehlgeschlagen'))
			} finally {
				this.transferring = false
			}
		},

		async onEditorsChange(value) {
			await this.savePermissionSettings('editors', value)
		},

		async onViewersChange(value) {
			await this.savePermissionSettings('viewers', value)
		},

		async savePermissionSettings(field, value) {
			try {
				const ids = value.map(item => item.id)
				await SettingsService.updatePermissionSettings({
					[field]: ids,
				})
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save permission settings:', error)
				showError(t('contractmanager', 'Fehler beim Speichern'))
			}
		},

		// The AI block is saved as one deliberate, complete set (provider + key +
		// URL + model), not autosaved per field: a half-entered credential set
		// should not persist. Only the AI fields are sent and only the AI fields
		// of the response are taken back — the masked key in particular — so this
		// never clobbers the independently autosaved fields. (#151)
		async saveAiSettings() {
			this.savingAi = true
			try {
				const result = await SettingsService.updateAdminSettings({
					aiProvider: this.adminSettings.aiProvider || '',
					aiApiKey: this.adminSettings.aiApiKey,
					aiApiUrl: this.adminSettings.aiApiUrl,
					aiModel: this.adminSettings.aiModel,
				})
				this.adminSettings.aiProvider = result.aiProvider || ''
				this.adminSettings.aiApiKey = result.aiApiKey || ''
				this.adminSettings.aiApiUrl = result.aiApiUrl || ''
				this.adminSettings.aiModel = result.aiModel || ''
				showSuccess(t('contractmanager', 'KI-Einstellungen gespeichert'))
			} catch (error) {
				console.error('Failed to save AI settings:', error)
				showError(t('contractmanager', 'Fehler beim Speichern der KI-Einstellungen'))
			} finally {
				this.savingAi = false
			}
		},

		async addCategory() {
			if (!this.newCategoryName.trim()) return

			this.addingCategory = true
			try {
				await this.createCategory(this.newCategoryName.trim())
				this.newCategoryName = ''
				showSuccess(t('contractmanager', 'Kategorie hinzugefügt'))
			} catch (error) {
				console.error('Failed to add category:', error)
				showError(t('contractmanager', 'Fehler beim Hinzufügen der Kategorie'))
			} finally {
				this.addingCategory = false
			}
		},

		startEdit(category) {
			this.editingCategoryId = category.id
			this.editingCategoryName = category.name
		},

		cancelEdit() {
			this.editingCategoryId = null
			this.editingCategoryName = ''
		},

		async saveCategory(category) {
			if (!this.editingCategoryName.trim()) return

			try {
				await this.updateCategory({
					id: category.id,
					name: this.editingCategoryName.trim(),
				})
				this.cancelEdit()
				showSuccess(t('contractmanager', 'Kategorie aktualisiert'))
			} catch (error) {
				console.error('Failed to update category:', error)
				showError(t('contractmanager', 'Fehler beim Aktualisieren der Kategorie'))
			}
		},

		confirmDeleteCategory(category) {
			this.deletingCategory = category
			this.showDeleteCategoryDialog = true
		},

		async executeDeleteCategory() {
			if (!this.deletingCategory) return
			try {
				await this.deleteCategory(this.deletingCategory.id)
				showSuccess(t('contractmanager', 'Kategorie gelöscht'))
			} catch (error) {
				console.error('Failed to delete category:', error)
				showError(t('contractmanager', 'Fehler beim Löschen der Kategorie'))
			} finally {
				this.showDeleteCategoryDialog = false
				this.deletingCategory = null
			}
		},
	},
}
</script>

<style scoped lang="scss" src="./settings-page.scss"></style>
