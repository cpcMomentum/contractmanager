<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('contractmanager', 'Einstellungen') }}</h2>
		</div>

		<!-- User Settings -->
		<div class="settings-section">
			<h3>{{ t('contractmanager', 'Benachrichtigungen') }}</h3>

			<div class="settings-item">
				<NcCheckboxRadioSwitch :checked.sync="emailReminder" @update:checked="onEmailReminderChange">
					{{ t('contractmanager', 'E-Mail-Benachrichtigungen aktivieren') }}
				</NcCheckboxRadioSwitch>
				<p class="settings-description">
					{{ t('contractmanager', 'Sie erhalten E-Mails an Ihre in Nextcloud hinterlegte Adresse, wenn Verträge bald auslaufen.') }}
				</p>
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
						:filterable="false"
						:placeholder="t('contractmanager', 'Benutzer oder Gruppen suchen...')"
						:multiple="true"
						label="displayName"
						track-by="id"
						class="permission-select"
						@search="onSearch"
						@input="onEditorsChange">
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
						:filterable="false"
						:placeholder="t('contractmanager', 'Benutzer oder Gruppen suchen...')"
						:multiple="true"
						label="displayName"
						track-by="id"
						class="permission-select"
						@search="onSearch"
						@input="onViewersChange">
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
					<NcTextField :value.sync="adminSettings.talkChatToken"
						:placeholder="t('contractmanager', 'z.B. abc123xyz')"
						class="settings-input" />
				</div>

				<!-- Reminder Days -->
				<div class="settings-item reminder-days">
					<label class="settings-label">{{ t('contractmanager', 'Erinnerungszeitpunkte (Tage vor Kündigungsfrist)') }}</label>

					<div class="reminder-inputs">
						<div class="reminder-input-group">
							<label>{{ t('contractmanager', 'Erste Erinnerung') }}</label>
							<NcTextField :value.sync="adminSettings.reminderDays1"
								type="number"
								:min="1"
								class="number-input" />
							<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
						</div>

						<div class="reminder-input-group">
							<label>{{ t('contractmanager', 'Letzte Erinnerung') }}</label>
							<NcTextField :value.sync="adminSettings.reminderDays2"
								type="number"
								:min="1"
								class="number-input" />
							<span class="unit">{{ t('contractmanager', 'Tage') }}</span>
						</div>
					</div>
				</div>
			</div>

			<div class="settings-actions">
				<NcButton type="primary" :disabled="savingAdmin" @click="saveAdminSettings">
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
						<NcTextField :value.sync="newCategoryName"
							:placeholder="t('contractmanager', 'Neue Kategorie...')"
							class="category-input"
							@keyup.enter="addCategory" />
						<NcButton type="primary"
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
								<NcTextField :value.sync="editingCategoryName"
									class="category-input"
									@keyup.enter="saveCategory(category)"
									@keyup.esc="cancelEdit" />
								<NcButton type="primary" @click="saveCategory(category)">
									<template #icon>
										<CheckIcon :size="20" />
									</template>
								</NcButton>
								<NcButton type="tertiary" @click="cancelEdit">
									<template #icon>
										<CloseIcon :size="20" />
									</template>
								</NcButton>
							</template>
							<template v-else>
								<span class="category-name">{{ category.name }}</span>
								<div class="category-actions">
									<NcButton type="tertiary" @click="startEdit(category)">
										<template #icon>
											<PencilIcon :size="20" />
										</template>
									</NcButton>
									<NcButton type="tertiary"
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
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
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
import debounce from 'debounce'

export default {
	name: 'SettingsView',
	components: {
		NcButton,
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
			emailReminder: false,
			savingAdmin: false,
			adminSettings: {
				talkChatToken: '',
				reminderDays1: 14,
				reminderDays2: 3,
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
		...mapGetters('categories', {
			categories: 'allCategories',
		}),
	},
	async created() {
		this.fetchCategories()
		await this.loadUserSettings()
		if (this.$isAdmin) {
			await this.loadAdminSettings()
			await this.loadPermissionSettings()
		}
		// Initialize debounced search
		this.debouncedSearch = debounce(this.performSearch, 300)
	},
	methods: {
		...mapActions('categories', ['fetchCategories', 'createCategory', 'updateCategory', 'deleteCategory']),

		async loadUserSettings() {
			try {
				const settings = await SettingsService.getUserSettings()
				this.emailReminder = settings.emailReminder
			} catch (error) {
				console.error('Failed to load user settings:', error)
			}
		},

		async loadAdminSettings() {
			try {
				const settings = await SettingsService.getAdminSettings()
				this.adminSettings = {
					talkChatToken: settings.talkChatToken || '',
					reminderDays1: settings.reminderDays1 || 14,
					reminderDays2: settings.reminderDays2 || 3,
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

		onSearch(query, loading) {
			if (query.length < 1) {
				this.searchResults = []
				return
			}
			loading(true)
			this.debouncedSearch(query, loading)
		},

		async performSearch(query, loading) {
			try {
				this.searching = true
				const results = await SettingsService.searchUsersAndGroups(query)
				this.searchResults = results
			} catch (error) {
				console.error('Failed to search users/groups:', error)
				this.searchResults = []
			} finally {
				this.searching = false
				if (loading) loading(false)
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
				showSuccess(this.t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save permission settings:', error)
				showError(this.t('contractmanager', 'Fehler beim Speichern'))
			}
		},

		async onEmailReminderChange(value) {
			try {
				await SettingsService.updateUserSettings({ emailReminder: value })
				showSuccess(this.t('contractmanager', 'Einstellung gespeichert'))
			} catch (error) {
				console.error('Failed to save user settings:', error)
				showError(this.t('contractmanager', 'Fehler beim Speichern'))
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
				})
				this.adminSettings = {
					talkChatToken: result.talkChatToken || '',
					reminderDays1: result.reminderDays1 || 14,
					reminderDays2: result.reminderDays2 || 3,
				}
				showSuccess(this.t('contractmanager', 'Admin-Einstellungen gespeichert'))
			} catch (error) {
				console.error('Failed to save admin settings:', error)
				showError(this.t('contractmanager', 'Fehler beim Speichern der Admin-Einstellungen'))
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
				showSuccess(this.t('contractmanager', 'Kategorie hinzugefügt'))
			} catch (error) {
				console.error('Failed to add category:', error)
				showError(this.t('contractmanager', 'Fehler beim Hinzufügen der Kategorie'))
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
				showSuccess(this.t('contractmanager', 'Kategorie aktualisiert'))
			} catch (error) {
				console.error('Failed to update category:', error)
				showError(this.t('contractmanager', 'Fehler beim Aktualisieren der Kategorie'))
			}
		},

		async confirmDeleteCategory(category) {
			if (!confirm(this.t('contractmanager', 'Kategorie "{name}" wirklich löschen?', { name: category.name }))) {
				return
			}

			try {
				await this.deleteCategory(category.id)
				showSuccess(this.t('contractmanager', 'Kategorie gelöscht'))
			} catch (error) {
				console.error('Failed to delete category:', error)
				showError(this.t('contractmanager', 'Fehler beim Löschen der Kategorie'))
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
