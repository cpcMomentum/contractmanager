import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import ContractService from '../services/ContractService'

export type ContractStatus = 'active' | 'cancelled' | 'ended'
export type ContractType = 'fixed' | 'auto_renewal'
export type AmountType = 'netto' | 'brutto'

/**
 * Shape mirrors what the backend serializes in `Contract::jsonSerialize()`
 * (lib/Db/Contract.php). Dates come back as YYYY-MM-DD strings;
 * timestamps (`deletedAt`, `createdAt`, `updatedAt`) as ISO 8601.
 *
 * All fields except `id` are optional because partial payloads occur in
 * update flows and the create form.
 */
export interface Contract {
	id: number
	name?: string
	vendor?: string
	status?: ContractStatus
	categoryId?: number | null
	startDate?: string | null
	endDate?: string | null
	cancelledOn?: string | null
	cancelledTo?: string | null
	cancellationPeriod?: string | null
	contractType?: ContractType
	renewalPeriod?: string | null
	cost?: string | null
	amountType?: AmountType
	currency?: string | null
	costInterval?: string | null
	contractFolder?: string | null
	mainDocument?: string | null
	reminderEnabled?: boolean
	reminderDays?: number | null
	notes?: string | null
	customField1?: string | null
	customField2?: string | null
	customField3?: string | null
	archived?: boolean
	isPrivate?: boolean
	deletedAt?: string | null
	createdBy?: string
	createdAt?: string
	updatedAt?: string
}

export interface Permissions {
	isAdmin: boolean
	isEditor: boolean
	isViewer: boolean
	canEdit: boolean
	canDeletePermanently: boolean
}

export const useContractsStore = defineStore('contracts', () => {
	const contracts = ref<Contract[]>([])
	const archivedContracts = ref<Contract[]>([])
	const trashedContracts = ref<Contract[]>([])
	const currentContract = ref<Contract | null>(null)
	const loading = ref(false)
	const error = ref<string | null>(null)
	const permissions = ref<Permissions>({
		isAdmin: false,
		isEditor: false,
		isViewer: false,
		canEdit: false,
		canDeletePermanently: false,
	})

	const allContracts = computed(() => contracts.value)
	const isLoading = computed(() => loading.value)
	const getContractById = computed(() => (id: number) => contracts.value.find((c) => c.id === id))
	const isAdmin = computed(() => permissions.value.isAdmin)
	const isEditor = computed(() => permissions.value.isEditor)
	const isViewer = computed(() => permissions.value.isViewer)
	const canEdit = computed(() => permissions.value.canEdit)
	const canDeletePermanently = computed(() => permissions.value.canDeletePermanently)

	async function fetchContracts(): Promise<void> {
		loading.value = true
		error.value = null
		try {
			contracts.value = await ContractService.getAll()
		} catch (e) {
			error.value = (e as Error).message
		} finally {
			loading.value = false
		}
	}

	async function fetchArchivedContracts(): Promise<void> {
		loading.value = true
		error.value = null
		try {
			archivedContracts.value = await ContractService.getArchived()
		} catch (e) {
			error.value = (e as Error).message
		} finally {
			loading.value = false
		}
	}

	async function fetchTrashedContracts(): Promise<void> {
		loading.value = true
		error.value = null
		try {
			trashedContracts.value = await ContractService.getTrashed()
		} catch (e) {
			error.value = (e as Error).message
		} finally {
			loading.value = false
		}
	}

	async function fetchPermissions(): Promise<void> {
		error.value = null
		try {
			permissions.value = await ContractService.getPermissions()
		} catch (e) {
			error.value = (e as Error).message
		}
	}

	async function fetchContract(id: number): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.get(id)
			currentContract.value = contract
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function createContract(contractData: Partial<Contract>): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.create(contractData)
			contracts.value.push(contract)
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function updateContract({ id, data }: { id: number, data: Partial<Contract> }): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.update(id, data)
			const index = contracts.value.findIndex((c) => c.id === contract.id)
			if (index !== -1) {
				contracts.value.splice(index, 1, contract)
			}
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function deleteContract(id: number): Promise<void> {
		loading.value = true
		error.value = null
		try {
			await ContractService.delete(id)
			contracts.value = contracts.value.filter((c) => c.id !== id)
			archivedContracts.value = archivedContracts.value.filter((c) => c.id !== id)
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function archiveContract(id: number): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.archive(id)
			contracts.value = contracts.value.filter((c) => c.id !== contract.id)
			archivedContracts.value.push(contract)
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function restoreContract(id: number): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.restore(id)
			archivedContracts.value = archivedContracts.value.filter((c) => c.id !== contract.id)
			contracts.value.push(contract)
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function restoreFromTrash(id: number): Promise<Contract> {
		loading.value = true
		error.value = null
		try {
			const contract = await ContractService.restoreFromTrash(id)
			trashedContracts.value = trashedContracts.value.filter((c) => c.id !== contract.id)
			if (contract.archived) {
				archivedContracts.value.push(contract)
			} else {
				contracts.value.push(contract)
			}
			void fetchTrashedContracts()
			return contract
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function deletePermanently(id: number): Promise<void> {
		loading.value = true
		error.value = null
		try {
			await ContractService.deletePermanently(id)
			trashedContracts.value = trashedContracts.value.filter((c) => c.id !== id)
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function emptyTrash(): Promise<void> {
		loading.value = true
		error.value = null
		try {
			await ContractService.emptyTrash()
			trashedContracts.value = []
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	function clearCurrentContract(): void {
		currentContract.value = null
	}

	return {
		contracts,
		archivedContracts,
		trashedContracts,
		currentContract,
		loading,
		error,
		permissions,
		allContracts,
		isLoading,
		getContractById,
		isAdmin,
		isEditor,
		isViewer,
		canEdit,
		canDeletePermanently,
		fetchContracts,
		fetchArchivedContracts,
		fetchTrashedContracts,
		fetchPermissions,
		fetchContract,
		createContract,
		updateContract,
		deleteContract,
		archiveContract,
		restoreContract,
		restoreFromTrash,
		deletePermanently,
		emptyTrash,
		clearCurrentContract,
	}
})
