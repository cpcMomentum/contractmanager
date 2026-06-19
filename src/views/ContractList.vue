<template>
	<div class="contract-list">
		<div class="contract-list__header">
			<h2>{{ t('contractmanager', 'Verträge') }}</h2>
			<div class="contract-list__header-actions">
				<NcActions :force-menu="true" variant="secondary" :menu-name="activeSortLabel">
					<template #icon>
						<SortAscendingIcon v-if="sortDirection === 'asc'" :size="20" />
						<SortDescendingIcon v-else :size="20" />
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
				<NcButton :variant="hasActiveFilters ? 'warning' : 'secondary'"
					@click="toggleFilters">
					<template #icon>
						<FilterOffIcon v-if="showFilters" :size="20" />
						<FilterIcon v-else :size="20" />
					</template>
					{{ t('contractmanager', 'Filter') }}
				</NcButton>
				<NcButton v-if="canEdit" variant="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Neuer Vertrag') }}
				</NcButton>
			</div>
		</div>

		<div v-if="!loading && allContracts.length && kpiScopeActive" class="kpi-scope">
			<FilterIcon :size="14" /> {{ kpiScopeLabel }}
		</div>
		<div v-if="!loading && allContracts.length" class="contract-kpis">
			<div class="kpi">
				<div class="kpi__lab">
					<FileDocumentIcon :size="15" /> {{ t('contractmanager', 'Aktive Verträge') }}
				</div>
				<div class="kpi__num">
					{{ kpiActiveCount }}
				</div>
				<div class="kpi__sub">
					{{ kpiTypeSub }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi__lab">
					<CashMultipleIcon :size="15" /> {{ t('contractmanager', 'Monatliche Kosten') }}
				</div>
				<div class="kpi__num">
					{{ kpiMonthlyLabel }}
				</div>
				<div class="kpi__sub">
					{{ kpiMonthlySub }}
				</div>
			</div>
			<div class="kpi" :class="{ 'kpi--warn': kpiEndingSoon > 0 }">
				<div class="kpi__lab">
					<BellRingIcon :size="15" /> {{ t('contractmanager', 'Kündigung fällig') }}
				</div>
				<div class="kpi__num">
					{{ kpiEndingSoon }}
				</div>
				<div class="kpi__sub">
					{{ t('contractmanager', 'innerhalb der Vorlaufzeit') }}
				</div>
			</div>
		</div>

		<div v-show="showFilters" class="contract-list__filters">
			<NcSelect v-model="filterVendor"
				:options="vendorOptions"
				:placeholder="t('contractmanager', 'Vertragspartner')"
				:clearable="true"
				input-id="filter-vendor"
				@update:model-value="persistFilters" />
			<NcSelect v-model="filterStatuses"
				:multiple="true"
				:options="statusOptions"
				:placeholder="t('contractmanager', 'Status')"
				:clearable="false"
				label="label"
				track-by="id"
				:reduce="option => option.id"
				input-id="filter-status"
				@update:model-value="persistFilters" />
			<NcSelect v-model="filterContractType"
				:options="contractTypeOptions"
				:placeholder="t('contractmanager', 'Vertragstyp')"
				:clearable="true"
				label="label"
				track-by="id"
				:reduce="option => option.id"
				input-id="filter-type"
				@update:model-value="persistFilters" />
			<NcSelect v-model="filterResponsible"
				:options="responsibleOptions"
				:placeholder="t('contractmanager', 'Zuständig')"
				:clearable="true"
				input-id="filter-responsible"
				@update:model-value="persistFilters" />
			<NcButton v-if="hasActiveFilters"
				variant="tertiary"
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
				<NcButton variant="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Neuer Vertrag') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else class="contract-list__table">
			<div class="contract-list__thead">
				<span class="col-name">{{ t('contractmanager', 'Vertrag') }}</span>
				<span>{{ t('contractmanager', 'Status') }}</span>
				<span class="col-cost">{{ t('contractmanager', 'Kosten') }}</span>
				<span>{{ t('contractmanager', 'Kündigen bis') }}</span>
				<span aria-hidden="true" />
			</div>
			<div class="contract-list__items">
				<ContractListItem v-for="contract in contracts"
					:key="contract.id"
					:contract="contract"
					:default-reminder-days="defaultReminderDays"
					@edit="handleEdit"
					@duplicate="handleDuplicate"
					@view="handleView"
					@archive="handleArchive" />
			</div>
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

		<NcDialog v-if="showArchiveDialog"
			:name="t('contractmanager', 'Vertrag archivieren')"
			@close="showArchiveDialog = false">
			<p>{{ t('contractmanager', 'Vertrag "{name}" wirklich archivieren?', { name: archivingContract ? archivingContract.name : '' }) }}</p>
			<template #actions>
				<NcButton @click="showArchiveDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="warning" @click="confirmArchive">
					{{ t('contractmanager', 'Archivieren') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from '../store/contracts'
import { useCategoriesStore } from '../store/categories'
import { loadState } from '@nextcloud/initial-state'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortDescendingIcon from 'vue-material-design-icons/SortDescending.vue'
import CircleSmallIcon from 'vue-material-design-icons/CircleSmall.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FilterOffIcon from 'vue-material-design-icons/FilterOff.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CashMultipleIcon from 'vue-material-design-icons/CashMultiple.vue'
import BellRingIcon from 'vue-material-design-icons/BellRing.vue'
import ContractListItem from '../components/ContractListItem.vue'
import { calculateCancellationDeadline } from '../utils/periodUtils.js'
import { parseLocalDate } from '../utils/dateUtils.js'
import { DEFAULT_REMINDER_DAYS_1, isEndingSoon } from '../utils/contractStatus'
import ContractForm from '../components/ContractForm.vue'
import SettingsService from '../services/SettingsService'
import { showInfo, showError } from '@nextcloud/dialogs'

export default {
	name: 'ContractList',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcEmptyContent,
		NcSelect,
		PlusIcon,
		FileDocumentIcon,
		SortAscendingIcon,
		SortDescendingIcon,
		CircleSmallIcon,
		FilterIcon,
		FilterOffIcon,
		CloseIcon,
		CashMultipleIcon,
		BellRingIcon,
		ContractListItem,
		ContractForm,
	},
	props: {
		categoryFilter: {
			type: [Number, String],
			default: null,
		},
		searchQuery: {
			type: String,
			default: '',
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
			showArchiveDialog: false,
			editingContract: null,
			viewingContract: null,
			archivingContract: null,
			formLoading: false,
			sortBy: prefs.sortBy,
			sortDirection: prefs.sortDirection,
			sortOptions: [
				{ key: 'endDate', label: t('contractmanager', 'Enddatum'), defaultDirection: 'asc' },
				{ key: 'name', label: t('contractmanager', 'Name'), defaultDirection: 'asc' },
				{ key: 'updatedAt', label: t('contractmanager', 'Zuletzt geändert'), defaultDirection: 'desc' },
				{ key: 'cost', label: t('contractmanager', 'Kosten'), defaultDirection: 'desc' },
				{ key: 'cancellationDeadline', label: t('contractmanager', 'Kündigen bis'), defaultDirection: 'asc' },
			],
			showFilters: false,
			filterVendor: filters.vendor || null,
			filterStatuses: filters.statuses || [],
			filterContractType: filters.contractType || null,
			filterResponsible: filters.responsible || null,
			// Window the badge / filter uses for "Kündigungsfrist endet". Defaults to the
			// constant in utils/contractStatus; gets overridden once the admin
			// setting comes back from the user-settings endpoint.
			defaultReminderDays: DEFAULT_REMINDER_DAYS_1,
			statusOptions: [
				{ id: 'active', label: t('contractmanager', 'Aktiv') },
				{ id: 'ending_soon', label: t('contractmanager', 'Kündigungsfrist endet') },
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
		...mapState(useContractsStore, {
			allContracts: 'allContracts',
			loading: 'isLoading',
			canEdit: 'canEdit',
		}),
		...mapState(useCategoriesStore, ['allCategories']),
		vendorOptions() {
			const vendors = this.allContracts
				.map(c => c.vendor)
				.filter(v => v && v.trim() !== '')
			return [...new Set(vendors)].sort((a, b) => a.localeCompare(b))
		},
		responsibleOptions() {
			const users = this.allContracts
				.map(c => c.responsibleUser)
				.filter(u => u && u.trim() !== '')
			return [...new Set(users)].sort((a, b) => a.localeCompare(b))
		},
		activeSortLabel() {
			const option = this.sortOptions.find(o => o.key === this.sortBy)
			return option ? option.label : ''
		},
		hasActiveFilters() {
			if (this.filterVendor) return true
			if (this.filterStatuses.length > 0) return true
			if (this.filterContractType) return true
			if (this.filterResponsible) return true
			return false
		},
		// --- KPI-Kennzahlen: beziehen sich auf die aktuell ANGEZEIGTE (gefilterte)
		// Menge, damit Kategorie-Auswahl, Filter und Suche durchschlagen. ---
		kpiBaseContracts() {
			return this.contracts
		},
		kpiScopeActive() {
			return this.hasActiveFilters
				|| this.categoryFilter !== null
				|| this.searchQuery.trim() !== ''
		},
		kpiScopeLabel() {
			if (this.categoryFilter === 'uncategorized') {
				return t('contractmanager', 'Kennzahlen für Verträge ohne Kategorie')
			}
			if (this.categoryFilter !== null) {
				const cat = this.allCategories.find(c => c.id === this.categoryFilter)
				if (cat) {
					return t('contractmanager', 'Kennzahlen für Kategorie „{name}“', { name: cat.name })
				}
			}
			return t('contractmanager', 'Kennzahlen für die gefilterte Ansicht')
		},
		kpiActiveCount() {
			return this.kpiBaseContracts.filter(c => c.status === 'active').length
		},
		kpiTypeSub() {
			const auto = this.kpiBaseContracts.filter(c => c.status === 'active' && c.contractType === 'auto_renewal').length
			const rest = this.kpiActiveCount - auto
			return t('contractmanager', '{auto} mit autom. Verlängerung · {rest} weitere', { auto, rest })
		},
		kpiEndingSoon() {
			return this.kpiBaseContracts.filter(c => isEndingSoon(c, this.defaultReminderDays)).length
		},
		// Verträge der Ansicht, die noch laufende Kosten verursachen: aktive UND
		// gekündigte, deren Laufzeit noch nicht abgelaufen ist. Einmalzahlungen,
		// beendete und archivierte zählen nicht.
		kpiCostContracts() {
			const divisor = { monthly: 1, quarterly: 3, semi_annual: 6, yearly: 12 }
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			return this.kpiBaseContracts.filter(c => {
				if (c.status === 'ended' || c.status === 'archived') return false
				if (!divisor[c.costInterval]) return false
				if (!Number.isFinite(parseFloat(c.cost))) return false
				const effectiveEnd = c.cancelledTo || c.endDate
				if (effectiveEnd) {
					const end = parseLocalDate(effectiveEnd)
					if (end && end < today) return false
				}
				return true
			})
		},
		kpiLeadCurrency() {
			return 'EUR'
		},
		kpiMonthlyTotal() {
			const divisor = { monthly: 1, quarterly: 3, semi_annual: 6, yearly: 12 }
			let sum = 0
			this.kpiCostContracts.forEach(c => {
				if ((c.currency || 'EUR') !== this.kpiLeadCurrency) return
				sum += parseFloat(c.cost) / divisor[c.costInterval]
			})
			return sum
		},
		kpiForeignCurrencyCount() {
			return this.kpiCostContracts.filter(c => (c.currency || 'EUR') !== this.kpiLeadCurrency).length
		},
		kpiAmountTypeMixed() {
			const inLeadCurrency = this.kpiCostContracts.filter(c => (c.currency || 'EUR') === this.kpiLeadCurrency)
			const hasNetto = inLeadCurrency.some(c => (c.amountType || 'netto') === 'netto')
			const hasBrutto = inLeadCurrency.some(c => (c.amountType || 'netto') === 'brutto')
			return hasNetto && hasBrutto
		},
		kpiMonthlyLabel() {
			return new Intl.NumberFormat('de-DE', { style: 'currency', currency: this.kpiLeadCurrency }).format(this.kpiMonthlyTotal)
		},
		kpiMonthlySub() {
			const parts = [t('contractmanager', 'Laufende Verträge, auf den Monat')]
			if (this.kpiAmountTypeMixed) {
				parts.push(t('contractmanager', 'netto und brutto gemischt'))
			}
			if (this.kpiForeignCurrencyCount > 0) {
				parts.push(t('contractmanager', '+ {n} in anderer Währung', { n: this.kpiForeignCurrencyCount }))
			}
			return parts.join(' · ')
		},
		contracts() {
			let filtered = this.allContracts.filter(c => c.status !== 'archived')

			// Volltextsuche (Sidebar-Suchfeld)
			if (this.searchQuery.trim()) {
				const query = this.searchQuery.trim().toLowerCase()
				filtered = filtered.filter(c => {
					return (c.name || '').toLowerCase().includes(query)
						|| (c.vendor || '').toLowerCase().includes(query)
						|| (c.notes || '').toLowerCase().includes(query)
						|| (c.customField1 || '').toLowerCase().includes(query)
						|| (c.customField2 || '').toLowerCase().includes(query)
						|| (c.customField3 || '').toLowerCase().includes(query)
						|| (c.responsibleUser || '').toLowerCase().includes(query)
				})
			}

			// Kategorie-Filter (Sidebar)
			if (this.categoryFilter === 'uncategorized') {
				filtered = filtered.filter(c => !c.categoryId)
			} else if (this.categoryFilter !== null) {
				filtered = filtered.filter(c => c.categoryId === this.categoryFilter)
			}

			// Status-Filter (leer = kein Filter = alle anzeigen).
			// "ending_soon" is a virtual option: it matches active contracts whose
			// cancellation deadline is inside the first-reminder window — see
			// utils/contractStatus.isEndingSoon.
			if (this.filterStatuses.length > 0) {
				const wantsEndingSoon = this.filterStatuses.includes('ending_soon')
				const realStatuses = this.filterStatuses.filter(id => id !== 'ending_soon')
				const reminderDays = this.defaultReminderDays
				filtered = filtered.filter(c => {
					if (wantsEndingSoon && isEndingSoon(c, reminderDays)) return true
					return realStatuses.includes(c.status)
				})
			}

			// Vertragspartner-Filter
			if (this.filterVendor) {
				filtered = filtered.filter(c => c.vendor === this.filterVendor)
			}

			// Vertragstyp-Filter
			if (this.filterContractType) {
				filtered = filtered.filter(c => c.contractType === this.filterContractType)
			}

			// Zuständig-Filter
			if (this.filterResponsible) {
				filtered = filtered.filter(c => c.responsibleUser === this.filterResponsible)
			}

			return this.sortContracts(filtered)
		},
	},
	created() {
		// Wait for contracts AND permissions before resolving a deep link, so the
		// edit-vs-view decision sees the real canEdit value (not the default false).
		Promise.all([this.fetchContracts(), this.fetchPermissions()]).then(() => this.handleDeepLink())
		this.loadReminderWindow()
		this.fetchCategories()
		if (this.hasActiveFilters) {
			this.showFilters = true
		}
	},
	methods: {
		...mapActions(useContractsStore, ['fetchContracts', 'fetchPermissions', 'createContract', 'updateContract', 'archiveContract']),
		...mapActions(useCategoriesStore, ['fetchCategories']),

		async loadReminderWindow() {
			try {
				const settings = await SettingsService.getUserSettings()
				const days = Number(settings.reminderDays1)
				if (Number.isFinite(days) && days > 0) {
					this.defaultReminderDays = days
				}
			} catch (e) {
				// Falls die Settings nicht erreichbar sind, bleibt der Default
				// (DEFAULT_REMINDER_DAYS_1) bestehen — kein Blocker für die Liste.
				console.debug('Failed to load reminder window setting:', e)
			}
		},

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

		// Open a specific contract when arriving from a reminder email
		// (…/apps/contractmanager/?contract=ID). Read once on load; the param is
		// stripped afterwards so a reload doesn't reopen the contract.
		handleDeepLink() {
			const params = new URLSearchParams(window.location.search)
			const idParam = params.get('contract')
			if (!idParam) {
				return
			}
			params.delete('contract')
			const query = params.toString()
			window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''))

			const id = parseInt(idParam, 10)
			const contract = Number.isNaN(id) ? null : this.allContracts.find(c => c.id === id)
			if (!contract) {
				showInfo(t('contractmanager', 'Der Vertrag aus der Erinnerung ist nicht mehr vorhanden.'))
				return
			}
			// Mirror the normal list-click behaviour: editors land in the editable
			// form (so they can act on the reminder right away), viewers get the
			// read-only details.
			if (this.canEdit) {
				this.handleEdit(contract)
			} else {
				this.handleView(contract)
			}
		},

		handleArchive(contract) {
			this.archivingContract = contract
			this.showArchiveDialog = true
		},

		async confirmArchive() {
			if (!this.archivingContract) return
			try {
				await this.archiveContract(this.archivingContract.id)
			} catch (error) {
				console.error('Failed to archive contract:', error)
				showError(t('contractmanager', 'Fehler beim Archivieren'))
			} finally {
				this.showArchiveDialog = false
				this.archivingContract = null
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
				showError(t('contractmanager', 'Fehler beim Speichern'))
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
					if (!a.endDate && !b.endDate) { cmp = 0; break }
					if (!a.endDate) { cmp = 1; break }
					if (!b.endDate) { cmp = -1; break }
					cmp = new Date(a.endDate).getTime() - new Date(b.endDate).getTime()
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
				case 'cancellationDeadline': {
					const deadlineA = calculateCancellationDeadline(a.endDate, a.cancellationPeriod, a.contractType, a.renewalPeriod, { status: a.status, cancelledTo: a.cancelledTo, deadlineType: a.cancellationDeadlineType })
					const deadlineB = calculateCancellationDeadline(b.endDate, b.cancellationPeriod, b.contractType, b.renewalPeriod, { status: b.status, cancelledTo: b.cancelledTo, deadlineType: b.cancellationDeadlineType })
					if (!deadlineA && !deadlineB) { cmp = 0; break }
					if (!deadlineA) { cmp = 1; break }
					if (!deadlineB) { cmp = -1; break }
					cmp = deadlineA.getTime() - deadlineB.getTime()
					break
				}
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
			this.filterResponsible = null
			this.persistFilters()
		},

		async persistFilters() {
			try {
				await SettingsService.updateUserSettings({
					filters: {
						vendor: this.filterVendor || '',
						statuses: this.filterStatuses,
						contractType: this.filterContractType || '',
						responsible: this.filterResponsible || '',
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

	&__table {
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large, 12px);
		overflow: hidden;
	}

	&__thead {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 150px 110px 120px 116px;
		gap: 14px;
		align-items: center;
		padding: 11px 0;
		border-bottom: 1px solid var(--color-border);
		font-size: 12px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);

		.col-name { padding-left: 18px; }
		.col-cost { text-align: right; }
	}

	&__items {
		display: block;
	}
}

.kpi-scope {
	display: flex;
	align-items: center;
	gap: 5px;
	max-width: 920px;
	margin: 0 0 8px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.contract-kpis {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;
	margin-bottom: 18px;
	max-width: 920px;

	.kpi {
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large, 12px);
		padding: 14px 16px;
	}

	.kpi__lab {
		display: flex;
		align-items: center;
		gap: 7px;
		font-size: 13px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
	}

	.kpi__num {
		font-size: 26px;
		font-weight: 700;
		line-height: 1;
		margin-top: 8px;
		font-variant-numeric: tabular-nums;
	}

	.kpi--warn .kpi__num { color: #9a6c25; }

	.kpi__sub {
		font-size: 12.5px;
		color: var(--color-text-maxcontrast);
		margin-top: 5px;
	}
}

@media (max-width: 768px) {
	.contract-kpis { grid-template-columns: 1fr; }
}
</style>
