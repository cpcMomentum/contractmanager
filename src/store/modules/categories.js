import CategoryService from '../../services/CategoryService.js'

const state = {
	categories: [],
	loading: false,
	error: null,
}

const getters = {
	allCategories: (state) => state.categories,
	isLoading: (state) => state.loading,
	error: (state) => state.error,
	getCategoryById: (state) => (id) => state.categories.find((c) => c.id === id),
	getCategoryName: (state) => (id) => {
		const category = state.categories.find((c) => c.id === id)
		return category ? category.name : ''
	},
}

const mutations = {
	SET_CATEGORIES(state, categories) {
		state.categories = categories
	},
	ADD_CATEGORY(state, category) {
		state.categories.push(category)
	},
	UPDATE_CATEGORY(state, updatedCategory) {
		const index = state.categories.findIndex((c) => c.id === updatedCategory.id)
		if (index !== -1) {
			state.categories.splice(index, 1, updatedCategory)
		}
	},
	REMOVE_CATEGORY(state, id) {
		state.categories = state.categories.filter((c) => c.id !== id)
	},
	SET_LOADING(state, loading) {
		state.loading = loading
	},
	SET_ERROR(state, error) {
		state.error = error
	},
}

const actions = {
	async fetchCategories({ commit }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const categories = await CategoryService.getAll()
			commit('SET_CATEGORIES', categories)
		} catch (error) {
			commit('SET_ERROR', error.message)
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async createCategory({ commit }, name) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const category = await CategoryService.create(name)
			commit('ADD_CATEGORY', category)
			return category
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async updateCategory({ commit }, { id, name, sortOrder }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const category = await CategoryService.update(id, name, sortOrder)
			commit('UPDATE_CATEGORY', category)
			return category
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async deleteCategory({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			await CategoryService.delete(id)
			commit('REMOVE_CATEGORY', id)
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
