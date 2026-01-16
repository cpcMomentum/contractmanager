import ContractService from '../../services/ContractService.js'

const state = {
	contracts: [],
	archivedContracts: [],
	currentContract: null,
	loading: false,
	error: null,
}

const getters = {
	allContracts: (state) => state.contracts,
	archivedContracts: (state) => state.archivedContracts,
	currentContract: (state) => state.currentContract,
	isLoading: (state) => state.loading,
	error: (state) => state.error,
	getContractById: (state) => (id) => state.contracts.find((c) => c.id === id),
}

const mutations = {
	SET_CONTRACTS(state, contracts) {
		state.contracts = contracts
	},
	SET_ARCHIVED_CONTRACTS(state, contracts) {
		state.archivedContracts = contracts
	},
	SET_CURRENT_CONTRACT(state, contract) {
		state.currentContract = contract
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
			commit('REMOVE_CONTRACT', id)
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
