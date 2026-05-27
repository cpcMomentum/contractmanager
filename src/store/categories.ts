import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import CategoryService from '../services/CategoryService.js'

export interface Category {
	id: number
	name: string
	sortOrder?: number
}

export const useCategoriesStore = defineStore('categories', () => {
	const categories = ref<Category[]>([])
	const loading = ref(false)
	const error = ref<string | null>(null)

	const allCategories = computed(() => categories.value)
	const isLoading = computed(() => loading.value)
	const getCategoryById = computed(() => (id: number) => categories.value.find((c) => c.id === id))
	const getCategoryName = computed(() => (id: number) => {
		const category = categories.value.find((c) => c.id === id)
		return category ? category.name : ''
	})

	async function fetchCategories(): Promise<void> {
		loading.value = true
		error.value = null
		try {
			categories.value = await CategoryService.getAll()
		} catch (e) {
			error.value = (e as Error).message
		} finally {
			loading.value = false
		}
	}

	async function createCategory(name: string): Promise<Category> {
		loading.value = true
		error.value = null
		try {
			const category = await CategoryService.create(name)
			categories.value.push(category)
			return category
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function updateCategory({ id, name, sortOrder }: { id: number, name: string, sortOrder?: number }): Promise<Category> {
		loading.value = true
		error.value = null
		try {
			const category = await CategoryService.update(id, name, sortOrder)
			const index = categories.value.findIndex((c) => c.id === category.id)
			if (index !== -1) {
				categories.value.splice(index, 1, category)
			}
			return category
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	async function deleteCategory(id: number): Promise<void> {
		loading.value = true
		error.value = null
		try {
			await CategoryService.delete(id)
			categories.value = categories.value.filter((c) => c.id !== id)
		} catch (e) {
			error.value = (e as Error).message
			throw e
		} finally {
			loading.value = false
		}
	}

	return {
		categories,
		loading,
		error,
		allCategories,
		isLoading,
		getCategoryById,
		getCategoryName,
		fetchCategories,
		createCategory,
		updateCategory,
		deleteCategory,
	}
})
