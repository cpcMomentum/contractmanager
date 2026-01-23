import Vue from 'vue'
import App from './App.vue'
import store from './store/index.js'
import { translate, translatePlural } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('contractmanager', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

// Get admin status from Nextcloud Initial State API
const isAdmin = loadState('contractmanager', 'isAdmin', false)

// Make it available globally
Vue.prototype.$isAdmin = isAdmin

new Vue({
	store,
	render: h => h(App),
}).$mount('.app-contractmanager')
