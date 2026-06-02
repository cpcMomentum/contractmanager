import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import CategoryService from '../services/CategoryService'
import { useCategoriesStore } from './categories'

vi.mock('../services/CategoryService', () => ({
	default: {
		getAll: vi.fn(),
		create: vi.fn(),
		update: vi.fn(),
		delete: vi.fn(),
	},
}))

const mockedService = vi.mocked(CategoryService)

describe('categories store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('starts empty and not loading', () => {
		const store = useCategoriesStore()
		expect(store.allCategories).toEqual([])
		expect(store.isLoading).toBe(false)
		expect(store.error).toBeNull()
	})

	it('fetchCategories populates state from the service', async () => {
		mockedService.getAll.mockResolvedValue([
			{ id: 1, name: 'Versicherung' },
			{ id: 2, name: 'Telekommunikation' },
		])
		const store = useCategoriesStore()

		await store.fetchCategories()

		expect(store.allCategories).toHaveLength(2)
		expect(store.allCategories[0].name).toBe('Versicherung')
		expect(store.isLoading).toBe(false)
	})

	it('getCategoryName returns the matching name or empty string', async () => {
		mockedService.getAll.mockResolvedValue([{ id: 7, name: 'Sonstige' }])
		const store = useCategoriesStore()
		await store.fetchCategories()

		expect(store.getCategoryName(7)).toBe('Sonstige')
		expect(store.getCategoryName(999)).toBe('')
	})

	it('createCategory appends to state on success', async () => {
		mockedService.create.mockResolvedValue({ id: 42, name: 'Neu' })
		const store = useCategoriesStore()

		const created = await store.createCategory('Neu')

		expect(created).toEqual({ id: 42, name: 'Neu' })
		expect(store.allCategories).toContainEqual({ id: 42, name: 'Neu' })
	})

	it('createCategory records the error and rethrows on failure', async () => {
		mockedService.create.mockRejectedValue(new Error('boom'))
		const store = useCategoriesStore()

		await expect(store.createCategory('x')).rejects.toThrow('boom')
		expect(store.error).toBe('boom')
		expect(store.isLoading).toBe(false)
	})
})
