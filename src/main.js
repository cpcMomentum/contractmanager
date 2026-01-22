import Vue from 'vue'
import App from './App.vue'
import store from './store/index.js'
import { translate, translatePlural } from '@nextcloud/l10n'

// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('contractmanager', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

// Get admin status from data attribute
const appElement = document.getElementById('content')
const isAdmin = appElement?.dataset?.isAdmin === 'true'

// Make it available globally
Vue.prototype.$isAdmin = isAdmin

new Vue({
	el: '#content',
	store,
	render: h => h(App),
})
