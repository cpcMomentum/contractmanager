import ContractService from '../../services/ContractService.js'

const state = {
	contracts: [],
	archivedContracts: [],
	trashedContracts: [],
	currentContract: null,
	loading: false,
	error: null,
	permissions: {
		isAdmin: false,
		isEditor: false,
		isViewer: false,
		canEdit: false,
		canDeletePermanently: false,
	},
}

const getters = {
	allContracts: (state) => state.contracts,
	archivedContracts: (state) => state.archivedContracts,
	trashedContracts: (state) => state.trashedContracts,
	currentContract: (state) => state.currentContract,
	isLoading: (state) => state.loading,
	error: (state) => state.error,
	getContractById: (state) => (id) => state.contracts.find((c) => c.id === id),
	isAdmin: (state) => state.permissions.isAdmin,
	isEditor: (state) => state.permissions.isEditor,
	isViewer: (state) => state.permissions.isViewer,
	canEdit: (state) => state.permissions.canEdit,
	canDeletePermanently: (state) => state.permissions.canDeletePermanently,
	permissions: (state) => state.permissions,
}

const mutations = {
	SET_CONTRACTS(state, contracts) {
		state.contracts = contracts
	},
	SET_ARCHIVED_CONTRACTS(state, contracts) {
		state.archivedContracts = contracts
	},
	SET_TRASHED_CONTRACTS(state, contracts) {
		state.trashedContracts = contracts
	},
	SET_CURRENT_CONTRACT(state, contract) {
		state.currentContract = contract
	},
	SET_PERMISSIONS(state, permissions) {
		state.permissions = permissions
	},
	ADD_CONTRACT(state, contract) {
		state.contracts.push(contract)
	},
	UPDATE_CONTRACT(state, updatedContract) {
		const index = state.contracts.findIndex((c) => c.id === updatedContract.id)
		if (index !== -1) {
			state.contracts.splice(index, 1, updatedContract)
		}
	},
	REMOVE_CONTRACT(state, id) {
		state.contracts = state.contracts.filter((c) => c.id !== id)
	},
	MOVE_TO_ARCHIVE(state, contract) {
		state.contracts = state.contracts.filter((c) => c.id !== contract.id)
		state.archivedContracts.push(contract)
	},
	RESTORE_FROM_ARCHIVE(state, contract) {
		state.archivedContracts = state.archivedContracts.filter((c) => c.id !== contract.id)
		state.contracts.push(contract)
	},
	MOVE_TO_TRASH(state, id) {
		state.contracts = state.contracts.filter((c) => c.id !== id)
		state.archivedContracts = state.archivedContracts.filter((c) => c.id !== id)
	},
	RESTORE_FROM_TRASH(state, contract) {
		state.trashedContracts = state.trashedContracts.filter((c) => c.id !== contract.id)
		if (contract.archived) {
			state.archivedContracts.push(contract)
		} else {
			state.contracts.push(contract)
		}
	},
	REMOVE_FROM_TRASH(state, id) {
		state.trashedContracts = state.trashedContracts.filter((c) => c.id !== id)
	},
	CLEAR_TRASH(state) {
		state.trashedContracts = []
	},
	SET_LOADING(state, loading) {
		state.loading = loading
	},
	SET_ERROR(state, error) {
		state.error = error
	},
}

const actions = {
	async fetchContracts({ commit }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contracts = await ContractService.getAll()
			commit('SET_CONTRACTS', contracts)
		} catch (error) {
			commit('SET_ERROR', error.message)
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async fetchArchivedContracts({ commit }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contracts = await ContractService.getArchived()
			commit('SET_ARCHIVED_CONTRACTS', contracts)
		} catch (error) {
			commit('SET_ERROR', error.message)
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async fetchTrashedContracts({ commit }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contracts = await ContractService.getTrashed()
			commit('SET_TRASHED_CONTRACTS', contracts)
		} catch (error) {
			commit('SET_ERROR', error.message)
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async fetchPermissions({ commit }) {
		try {
			const permissions = await ContractService.getPermissions()
			commit('SET_PERMISSIONS', permissions)
		} catch (error) {
			console.error('Failed to fetch permissions:', error)
		}
	},

	async fetchContract({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.get(id)
			commit('SET_CURRENT_CONTRACT', contract)
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async createContract({ commit }, contractData) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.create(contractData)
			commit('ADD_CONTRACT', contract)
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async updateContract({ commit }, { id, data }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.update(id, data)
			commit('UPDATE_CONTRACT', contract)
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async deleteContract({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			await ContractService.delete(id)
			commit('MOVE_TO_TRASH', id)
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async archiveContract({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.archive(id)
			commit('MOVE_TO_ARCHIVE', contract)
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async restoreContract({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.restore(id)
			commit('RESTORE_FROM_ARCHIVE', contract)
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async restoreFromTrash({ commit, dispatch }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			const contract = await ContractService.restoreFromTrash(id)
			commit('RESTORE_FROM_TRASH', contract)
			// Refresh lists to ensure consistency
			dispatch('fetchTrashedContracts')
			return contract
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async deletePermanently({ commit }, id) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			await ContractService.deletePermanently(id)
			commit('REMOVE_FROM_TRASH', id)
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	async emptyTrash({ commit }) {
		commit('SET_LOADING', true)
		commit('SET_ERROR', null)
		try {
			await ContractService.emptyTrash()
			commit('CLEAR_TRASH')
		} catch (error) {
			commit('SET_ERROR', error.message)
			throw error
		} finally {
			commit('SET_LOADING', false)
		}
	},

	clearCurrentContract({ commit }) {
		commit('SET_CURRENT_CONTRACT', null)
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
