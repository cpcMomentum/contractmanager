import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import ContractService from '../services/ContractService.js'
import { useContractsStore, type Contract } from './contracts'

vi.mock('../services/ContractService.js', () => ({
	default: {
		getAll: vi.fn(),
		getArchived: vi.fn(),
		getTrashed: vi.fn(),
		get: vi.fn(),
		create: vi.fn(),
		update: vi.fn(),
		delete: vi.fn(),
		archive: vi.fn(),
		restore: vi.fn(),
		restoreFromTrash: vi.fn(),
		deletePermanently: vi.fn(),
		emptyTrash: vi.fn(),
		getPermissions: vi.fn(),
	},
}))

const mockedService = vi.mocked(ContractService)

const contract = (id: number, overrides: Partial<Contract> = {}): Contract => ({
	id,
	status: 'active',
	archived: false,
	...overrides,
})

describe('contracts store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('starts with empty lists and default permissions', () => {
		const store = useContractsStore()
		expect(store.allContracts).toEqual([])
		expect(store.archivedContracts).toEqual([])
		expect(store.trashedContracts).toEqual([])
		expect(store.isAdmin).toBe(false)
		expect(store.canEdit).toBe(false)
	})

	it('getContractById finds the matching contract', async () => {
		mockedService.getAll.mockResolvedValue([contract(1), contract(2), contract(3)])
		const store = useContractsStore()
		await store.fetchContracts()

		expect(store.getContractById(2)?.id).toBe(2)
		expect(store.getContractById(99)).toBeUndefined()
	})

	it('archiveContract moves the contract from active to archived', async () => {
		mockedService.getAll.mockResolvedValue([contract(1), contract(2)])
		mockedService.archive.mockResolvedValue(contract(1, { archived: true }))
		const store = useContractsStore()
		await store.fetchContracts()

		await store.archiveContract(1)

		expect(store.allContracts.map((c) => c.id)).toEqual([2])
		expect(store.archivedContracts.map((c) => c.id)).toEqual([1])
	})

	it('deleteContract removes the contract from active and archived lists', async () => {
		mockedService.getAll.mockResolvedValue([contract(1), contract(2)])
		mockedService.delete.mockResolvedValue(undefined)
		const store = useContractsStore()
		await store.fetchContracts()
		store.archivedContracts.push(contract(3))

		await store.deleteContract(1)

		expect(store.allContracts.map((c) => c.id)).toEqual([2])
		expect(store.archivedContracts.map((c) => c.id)).toEqual([3])
	})

	it('fetchPermissions reflects backend permissions in computed getters', async () => {
		mockedService.getPermissions.mockResolvedValue({
			isAdmin: true,
			isEditor: true,
			isViewer: true,
			canEdit: true,
			canDeletePermanently: true,
		})
		const store = useContractsStore()

		await store.fetchPermissions()

		expect(store.isAdmin).toBe(true)
		expect(store.canEdit).toBe(true)
		expect(store.canDeletePermanently).toBe(true)
	})
})
