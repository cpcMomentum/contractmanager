<template>
	<div class="contract-list">
		<div class="contract-list__header">
			<h2>{{ t('contractmanager', 'Verträge') }}</h2>
			<div class="contract-list__header-actions">
				<NcActions :force-menu="true" type="secondary">
					<template #icon>
						<SortIcon :size="20" />
					</template>
					<NcActionButton v-for="option in sortOptions"
						:key="option.key"
						:close-after-click="true"
						@click="handleSortClick(option)">
						<template #icon>
							<component :is="getSortIcon(option)" :size="20" />
						</template>
						{{ option.label }}
					</NcActionButton>
				</NcActions>
				<NcButton :type="hasActiveFilters ? 'warning' : 'secondary'"
					@click="toggleFilters">
					<template #icon>
						<FilterOffIcon v-if="showFilters" :size="20" />
						<FilterIcon v-else :size="20" />
					</template>
					{{ t('contractmanager', 'Filter') }}
				</NcButton>
				<NcButton v-if="canEdit" type="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Neuer Vertrag') }}
				</NcButton>
			</div>
		</div>

		<div v-show="showFilters" class="contract-list__filters">
			<NcSelect v-model="filterVendor"
				:options="vendorOptions"
				:placeholder="t('contractmanager', 'Vertragspartner')"
				:clearable="true"
				input-id="filter-vendor"
				@input="persistFilters" />
			<NcSelect v-model="filterStatuses"
				:multiple="true"
				:options="statusOptions"
				:placeholder="t('contractmanager', 'Status')"
				:clearable="false"
				label="label"
				track-by="id"
				:reduce="option => option.id"
				input-id="filter-status"
				@input="persistFilters" />
			<NcSelect v-model="filterContractType"
				:options="contractTypeOptions"
				:placeholder="t('contractmanager', 'Vertragstyp')"
				:clearable="true"
				label="label"
				track-by="id"
				:reduce="option => option.id"
				input-id="filter-type"
				@input="persistFilters" />
			<NcButton v-if="hasActiveFilters"
				type="tertiary"
				@click="resetFilters">
				<template #icon>
					<CloseIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Zurücksetzen') }}
			</NcButton>
		</div>

		<div v-if="loading" class="contract-list__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="contracts.length === 0"
			:name="t('contractmanager', 'Keine Verträge')"
			:description="t('contractmanager', 'Erstellen Sie Ihren ersten Vertrag, um zu beginnen.')">
			<template #icon>
				<FileDocumentIcon :size="64" />
			</template>
			<template v-if="canEdit" #action>
				<NcButton type="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Neuer Vertrag') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else class="contract-list__items">
			<ContractListItem v-for="contract in contracts"
				:key="contract.id"
				:contract="contract"
				@edit="handleEdit"
				@duplicate="handleDuplicate"
				@view="handleView"
				@archive="handleArchive" />
		</div>

		<ContractForm :show="showCreateForm || showEditForm"
			:contract="editingContract"
			:loading="formLoading"
			@close="closeForm"
			@submit="handleFormSubmit" />

		<ContractForm :show="showViewForm"
			:contract="viewingContract"
			:read-only="true"
			@close="closeForm" />
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import { loadState } from '@nextcloud/initial-state'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import SortIcon from 'vue-material-design-icons/Sort.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortDescendingIcon from 'vue-material-design-icons/SortDescending.vue'
import CircleSmallIcon from 'vue-material-design-icons/CircleSmall.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FilterOffIcon from 'vue-material-design-icons/FilterOff.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContractListItem from '../components/ContractListItem.vue'
import ContractForm from '../components/ContractForm.vue'
import SettingsService from '../services/SettingsService.js'

export default {
	name: 'ContractList',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcLoadingIcon,
		NcEmptyContent,
		NcSelect,
		PlusIcon,
		FileDocumentIcon,
		SortIcon,
		SortAscendingIcon,
		SortDescendingIcon,
		CircleSmallIcon,
		FilterIcon,
		FilterOffIcon,
		CloseIcon,
		ContractListItem,
		ContractForm,
	},
	props: {
		categoryFilter: {
			type: Number,
			default: null,
		},
	},
	data() {
		const defaultPrefs = {
			sortBy: 'endDate',
			sortDirection: 'asc',
			filters: { vendor: '', statuses: ['active', 'cancelled', 'ended'], contractType: '' },
		}
		const prefs = loadState('contractmanager', 'userPreferences', defaultPrefs)
		const filters = prefs.filters || defaultPrefs.filters
		return {
			showCreateForm: false,
			showEditForm: false,
			showViewForm: false,
			editingContract: null,
			viewingContract: null,
			formLoading: false,
			sortBy: prefs.sortBy,
			sortDirection: prefs.sortDirection,
			sortOptions: [
				{ key: 'endDate', label: t('contractmanager', 'Enddatum'), defaultDirection: 'asc' },
				{ key: 'name', label: t('contractmanager', 'Name'), defaultDirection: 'asc' },
				{ key: 'updatedAt', label: t('contractmanager', 'Zuletzt geändert'), defaultDirection: 'desc' },
				{ key: 'cost', label: t('contractmanager', 'Kosten'), defaultDirection: 'desc' },
			],
			showFilters: false,
			filterVendor: filters.vendor || null,
			filterStatuses: filters.statuses || [],
			filterContractType: filters.contractType || null,
			statusOptions: [
				{ id: 'active', label: t('contractmanager', 'Aktiv') },
				{ id: 'cancelled', label: t('contractmanager', 'Gekündigt') },
				{ id: 'ended', label: t('contractmanager', 'Abgelaufen') },
			],
			contractTypeOptions: [
				{ id: 'fixed', label: t('contractmanager', 'Festlaufzeit') },
				{ id: 'auto_renewal', label: t('contractmanager', 'Automatische Verlängerung') },
			],
		}
	},
	computed: {
		...mapGetters('contracts', {
			allContracts: 'allContracts',
			loading: 'isLoading',
			canEdit: 'canEdit',
		}),
		vendorOptions() {
			const vendors = this.allContracts
				.map(c => c.vendor)
				.filter(v => v && v.trim() !== '')
			return [...new Set(vendors)].sort((a, b) => a.localeCompare(b))
		},
		hasActiveFilters() {
			if (this.filterVendor) return true
			if (this.filterStatuses.length > 0) return true
			if (this.filterContractType) return true
			return false
		},
		contracts() {
			let filtered = this.allContracts.filter(c => c.status !== 'archived')

			// Kategorie-Filter (Sidebar)
			if (this.categoryFilter !== null) {
				filtered = filtered.filter(c => c.categoryId === this.categoryFilter)
			}

			// Status-Filter (leer = kein Filter = alle anzeigen)
			if (this.filterStatuses.length > 0) {
				filtered = filtered.filter(c => this.filterStatuses.includes(c.status))
			}

			// Vertragspartner-Filter
			if (this.filterVendor) {
				filtered = filtered.filter(c => c.vendor === this.filterVendor)
			}

			// Vertragstyp-Filter
			if (this.filterContractType) {
				filtered = filtered.filter(c => c.contractType === this.filterContractType)
			}

			return this.sortContracts(filtered)
		},
	},
	created() {
		this.fetchContracts()
		this.fetchCategories()
		if (this.hasActiveFilters) {
			this.showFilters = true
		}
	},
	methods: {
		...mapActions('contracts', ['fetchContracts', 'createContract', 'updateContract', 'archiveContract']),
		...mapActions('categories', ['fetchCategories']),

		handleEdit(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		handleDuplicate(contract) {
			this.editingContract = {
				...contract,
				id: null,
				name: contract.name + ' (' + t('contractmanager', 'Kopie') + ')',
				status: 'active',
			}
			this.showCreateForm = true
		},

		handleView(contract) {
			this.viewingContract = contract
			this.showViewForm = true
		},

		async handleArchive(contract) {
			if (confirm(t('contractmanager', 'Vertrag "{name}" wirklich archivieren?', { name: contract.name }))) {
				try {
					await this.archiveContract(contract.id)
				} catch (error) {
					console.error('Failed to archive contract:', error)
				}
			}
		},

		closeForm() {
			this.showCreateForm = false
			this.showEditForm = false
			this.showViewForm = false
			this.editingContract = null
			this.viewingContract = null
		},

		async handleFormSubmit(data) {
			this.formLoading = true
			try {
				if (this.editingContract && this.editingContract.id) {
					await this.updateContract({
						id: this.editingContract.id,
						data,
					})
				} else {
					await this.createContract(data)
				}
				this.closeForm()
			} catch (error) {
				console.error('Failed to save contract:', error)
			} finally {
				this.formLoading = false
			}
		},

		sortContracts(contracts) {
			const sorted = [...contracts]
			const dir = this.sortDirection === 'asc' ? 1 : -1

			sorted.sort((a, b) => {
				let cmp = 0
				switch (this.sortBy) {
				case 'endDate': {
					const dateA = a.endDate ? new Date(a.endDate).getTime() : 0
					const dateB = b.endDate ? new Date(b.endDate).getTime() : 0
					cmp = dateA - dateB
					break
				}
				case 'name':
					cmp = (a.name || '').localeCompare(b.name || '')
					break
				case 'updatedAt': {
					const updA = a.updatedAt ? new Date(a.updatedAt).getTime() : 0
					const updB = b.updatedAt ? new Date(b.updatedAt).getTime() : 0
					cmp = updA - updB
					break
				}
				case 'cost':
					cmp = (parseFloat(a.cost) || 0) - (parseFloat(b.cost) || 0)
					break
				default:
					cmp = 0
				}
				return cmp * dir
			})
			return sorted
		},

		handleSortClick(option) {
			if (this.sortBy === option.key) {
				this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
			} else {
				this.sortBy = option.key
				this.sortDirection = option.defaultDirection
			}
			this.persistSortPreference()
		},

		getSortIcon(option) {
			if (this.sortBy !== option.key) {
				return 'CircleSmallIcon'
			}
			return this.sortDirection === 'asc' ? 'SortAscendingIcon' : 'SortDescendingIcon'
		},

		async persistSortPreference() {
			try {
				await SettingsService.updateUserSettings({
					sortBy: this.sortBy,
					sortDirection: this.sortDirection,
				})
			} catch (error) {
				console.error('Failed to persist sort preference:', error)
			}
		},

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		resetFilters() {
			this.filterVendor = null
			this.filterStatuses = []
			this.filterContractType = null
			this.persistFilters()
		},

		async persistFilters() {
			try {
				await SettingsService.updateUserSettings({
					filters: {
						vendor: this.filterVendor || '',
						statuses: this.filterStatuses,
						contractType: this.filterContractType || '',
					},
				})
			} catch (error) {
				console.error('Failed to persist filters:', error)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.contract-list {
	padding: 20px;
	padding-left: 50px;
	height: 100%;

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}

		&-actions {
			display: flex;
			align-items: center;
			gap: 8px;
		}
	}

	&__filters {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 16px;
		padding: 12px;
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius-large);

		.v-select {
			min-width: 180px;
			flex: 1;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		align-items: center;
		height: 200px;
	}

	&__items {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
}
</style>
