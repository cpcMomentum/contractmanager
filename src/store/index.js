import Vue from 'vue'
import Vuex from 'vuex'

import contracts from './modules/contracts.js'
import categories from './modules/categories.js'

Vue.use(Vuex)

export default new Vuex.Store({
	modules: {
		contracts,
		categories,
	},
})
