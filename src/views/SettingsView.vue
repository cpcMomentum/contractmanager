<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('contractmanager', 'Einstellungen') }}</h2>
		</div>

		<!-- User Settings -->
		<div class="settings-section">
			<h3>{{ t('contractmanager', 'Benachrichtigungen') }}</h3>

			<div class="settings-item">
				<NcCheckboxRadioSwitch v-model="emailReminder" @update:model-value="onEmailReminderChange">
					{{ t('contractmanager', 'E-Mail-Benachrichtigungen aktivieren') }}
				</NcCheckboxRadioSwitch>
				<p class="settings-description">
					{{ t('contractmanager', 'Sie erhalten E-Mails an Ihre in Nextcloud hinterlegte Adresse, wenn Verträge bald auslaufen.') }}
				</p>
			</div>
		</div>

		<div class="settings-section">
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

		<!-- Admin Settings -->
		<template v-if="$isAdmin">
			<!-- Permission Settings -->
			<div class="settings-section admin-section">
				<h3>
					<ShieldIcon :size="20" class="admin-icon" />
					{{ t('contractmanager', 'Berechtigungen') }}
				</h3>

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

			<div class="settings-section admin-section">
				<h3>
					<ShieldIcon :size="20" class="admin-icon" />
					{{ t('contractmanager', 'Administrator-Einstellungen') }}
				</h3>

				<!-- Talk Chat Token -->
				<div class="settings-item">
					<label class="settings-label">{{ t('contractmanager', 'Nextcloud Talk Chat-Token') }}</label>
					<p class="settings-description">
						{{ t('contractmanager', 'Token des Chats für Erinnerungen (aus der Chat-URL).') }}
					</p>
					<NcTextField v-model="adminSettings.talkChatToken"
						:placeholder="t('contractmanager', 'z.B. abc123xyz')"
						class="settings-input" />
				</div>

				<!-- Reminder Days -->
				<div class="settings-item reminder-days">
					<label class="settings-label">{{ t('contractmanager', 'Erinnerungszeitpunkte (Tage vor Kündigungsfrist)') }}</label>

					<div class="reminder-inputs">
						<div class="reminder-input-group">
							<label>{{ t('contractmanager', 'Erste Erinnerung') }}</label>
							<NcTextField v-model="adminSettings.reminderDays1"
								type="number"
								:min="1"
								class="number-input" />
							<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
						</div>

						<div class="reminder-input-group">
							<label>{{ t('contractmanager', 'Letzte Erinnerung') }}</label>
							<NcTextField v-model="adminSettings.reminderDays2"
								type="number"
								:min="1"
								class="number-input" />
							<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Custom Fields Settings -->
			<div class="settings-section admin-section">
				<h3>
					<ShieldIcon :size="20" class="admin-icon" />
					{{ t('contractmanager', 'Zusatzfelder') }}
				</h3>
				<p class="settings-description">
					{{ t('contractmanager', 'Aktivieren Sie bis zu 3 optionale Zusatzfelder für Verträge.') }}
				</p>

				<div v-for="n in 3" :key="'cf' + n" class="settings-item custom-field-item">
					<NcCheckboxRadioSwitch :model-value="customFieldEnabled(n)"
						@update:model-value="toggleCustomField(n, $event)">
						{{ t('contractmanager', 'Zusatzfeld {n}', { n }) }}
					</NcCheckboxRadioSwitch>
					<NcTextField v-if="customFieldEnabled(n)"
						v-model="adminSettings['customFieldLabel' + n]"
						:placeholder="customFieldPlaceholders[n - 1]"
						class="settings-input custom-field-label" />
				</div>
			</div>

			<!-- AI Extraction Settings -->
			<div class="settings-section admin-section">
				<h3>
					<ShieldIcon :size="20" class="admin-icon" />
					{{ t('contractmanager', 'KI-Vertragsanalyse') }}
				</h3>
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
			</div>

			<div class="settings-actions">
				<NcButton variant="primary" :disabled="savingAdmin" @click="saveAdminSettings">
					<template #icon>
						<NcLoadingIcon v-if="savingAdmin" :size="20" />
					</template>
					{{ t('contractmanager', 'Admin-Einstellungen speichern') }}
				</NcButton>
			</div>

			<!-- Category Management (Admin only) -->
			<div class="settings-section">
				<h3>{{ t('contractmanager', 'Kategorien verwalten') }}</h3>
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
		</template>

		<!-- Categories (read-only for non-admins) -->
		<div v-if="!$isAdmin" class="settings-section">
			<h3>{{ t('contractmanager', 'Kategorien') }}</h3>
			<p class="settings-description">
				{{ t('contractmanager', 'Kategorien können nur von Administratoren verwaltet werden.') }}
			</p>

			<div class="category-list">
				<div v-for="category in categories"
					:key="category.id"
					class="category-item">
					<span class="category-name">{{ category.name }}</span>
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
import { mapState, mapActions } from 'pinia'
import { useCategoriesStore } from '../store/categories'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import ShieldIcon from 'vue-material-design-icons/Shield.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import SettingsService from '../services/SettingsService.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

export default {
	name: 'SettingsView',
	components: {
		NcButton,
		NcDialog,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
		NcSelect,
		ShieldIcon,
		PlusIcon,
		PencilIcon,
		DeleteIcon,
		CheckIcon,
		CloseIcon,
		AccountIcon,
		AccountGroupIcon,
	},
	data() {
		return {
			showDeleteCategoryDialog: false,
			deletingCategory: null,
			emailReminder: false,
			defaultAmountType: 'netto',
			savingAdmin: false,
			adminSettings: {
				talkChatToken: '',
				reminderDays1: 14,
				reminderDays2: 3,
				customFieldLabel1: '',
				customFieldLabel2: '',
				customFieldLabel3: '',
				aiProvider: '',
				aiApiKey: '',
				aiApiUrl: '',
				aiModel: '',
			},
			permissionSettings: {
				editors: [],
				viewers: [],
			},
			searchResults: [],
			searching: false,
			newCategoryName: '',
			addingCategory: false,
			editingCategoryId: null,
			editingCategoryName: '',
		}
	},
	computed: {
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
			if (this.adminSettings.aiProvider === 'claude') return 'claude-sonnet-4-5-20250514'
			return 'gpt-4o'
		},
	},
	async created() {
		this.fetchCategories()
		await this.loadUserSettings()
		if (this.$isAdmin) {
			await this.loadAdminSettings()
			await this.loadPermissionSettings()
			await this.performSearch('')
		}
	},
	methods: {
		...mapActions(useCategoriesStore, ['fetchCategories', 'createCategory', 'updateCategory', 'deleteCategory']),

		customFieldEnabled(n) {
			return this.adminSettings['customFieldLabel' + n] !== ''
		},

		toggleCustomField(n, enabled) {
			if (enabled) {
				this.adminSettings['customFieldLabel' + n] = this.customFieldPlaceholders[n - 1].replace('z.B. ', '')
			} else {
				this.adminSettings['customFieldLabel' + n] = ''
			}
		},

		async loadUserSettings() {
			try {
				const settings = await SettingsService.getUserSettings()
				this.emailReminder = settings.emailReminder
				this.defaultAmountType = settings.defaultAmountType || 'netto'
			} catch (error) {
				console.error('Failed to load user settings:', error)
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

		async loadAdminSettings() {
			try {
				const settings = await SettingsService.getAdminSettings()
				this.adminSettings = {
					talkChatToken: settings.talkChatToken || '',
					reminderDays1: settings.reminderDays1 || 14,
					reminderDays2: settings.reminderDays2 || 3,
					customFieldLabel1: settings.customFieldLabel1 || '',
					customFieldLabel2: settings.customFieldLabel2 || '',
					customFieldLabel3: settings.customFieldLabel3 || '',
					aiProvider: settings.aiProvider || '',
					aiApiKey: settings.aiApiKey || '',
					aiApiUrl: settings.aiApiUrl || '',
					aiModel: settings.aiModel || '',
				}
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

		async saveAdminSettings() {
			this.savingAdmin = true
			try {
				const result = await SettingsService.updateAdminSettings({
					talkChatToken: this.adminSettings.talkChatToken,
					reminderDays1: parseInt(this.adminSettings.reminderDays1, 10),
					reminderDays2: parseInt(this.adminSettings.reminderDays2, 10),
					customFieldLabel1: this.adminSettings.customFieldLabel1,
					customFieldLabel2: this.adminSettings.customFieldLabel2,
					customFieldLabel3: this.adminSettings.customFieldLabel3,
					aiProvider: this.adminSettings.aiProvider || '',
					aiApiKey: this.adminSettings.aiApiKey,
					aiApiUrl: this.adminSettings.aiApiUrl,
					aiModel: this.adminSettings.aiModel,
				})
				this.adminSettings = {
					talkChatToken: result.talkChatToken || '',
					reminderDays1: result.reminderDays1 || 14,
					reminderDays2: result.reminderDays2 || 3,
					customFieldLabel1: result.customFieldLabel1 || '',
					customFieldLabel2: result.customFieldLabel2 || '',
					customFieldLabel3: result.customFieldLabel3 || '',
					aiProvider: result.aiProvider || '',
					aiApiKey: result.aiApiKey || '',
					aiApiUrl: result.aiApiUrl || '',
					aiModel: result.aiModel || '',
				}
				showSuccess(t('contractmanager', 'Admin-Einstellungen gespeichert'))
			} catch (error) {
				console.error('Failed to save admin settings:', error)
				showError(t('contractmanager', 'Fehler beim Speichern der Admin-Einstellungen'))
			} finally {
				this.savingAdmin = false
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

<style scoped lang="scss">
.settings-view {
	padding: 20px;
	padding-left: 50px;
	max-width: 800px;

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 24px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}
	}
}

.settings-section {
	margin-bottom: 32px;
	padding-bottom: 24px;
	border-bottom: 1px solid var(--color-border);

	h3 {
		margin: 0 0 12px;
		font-size: 16px;
		font-weight: 600;
		display: flex;
		align-items: center;
		gap: 8px;
	}
}

.admin-section {
	background: var(--color-background-dark);
	padding: 20px;
	border-radius: 8px;
	margin-top: 24px;

	h3 {
		color: var(--color-primary);
	}
}

.admin-icon {
	color: var(--color-primary);
}

.settings-item {
	margin-bottom: 20px;
}

.settings-label {
	display: block;
	font-weight: 600;
	margin-bottom: 4px;
}

.settings-description {
	margin: 4px 0 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.settings-input {
	max-width: 400px;
}

.reminder-days {
	.reminder-inputs {
		display: flex;
		gap: 24px;
		margin-top: 12px;
	}

	.reminder-input-group {
		display: flex;
		align-items: center;
		gap: 8px;

		label {
			font-size: 14px;
			min-width: 120px;
		}

		.number-input {
			width: 80px;
		}

		.unit {
			color: var(--color-text-maxcontrast);
		}
	}
}

.category-management {
	margin-top: 16px;
}

.category-add {
	display: flex;
	gap: 8px;
	margin-bottom: 16px;

	.category-input {
		max-width: 300px;
	}
}

.category-list-edit {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.category-edit-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: var(--color-background-dark);
	border-radius: 8px;

	.category-name {
		flex: 1;
		font-size: 14px;
	}

	.category-input {
		flex: 1;
		max-width: 300px;
	}

	.category-actions {
		display: flex;
		gap: 4px;
		opacity: 0.6;
		transition: opacity 0.2s;
	}

	&:hover .category-actions {
		opacity: 1;
	}
}

.category-list {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 12px;
}

.category-item {
	padding: 6px 12px;
	background: var(--color-background-dark);
	border-radius: 16px;
	font-size: 13px;
}

.custom-field-item {
	.custom-field-label {
		margin-top: 8px;
	}
}

.settings-actions {
	margin-top: 24px;
}

.permission-select {
	max-width: 500px;
}

.permission-option {
	display: flex;
	align-items: center;
	gap: 8px;

	.permission-option-type {
		margin-left: auto;
		color: var(--color-text-maxcontrast);
		font-size: 12px;
	}
}

.permission-tag {
	display: flex;
	align-items: center;
	gap: 4px;
}
</style>
