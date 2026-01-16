import Vue from 'vue'
import App from './App.vue'
import { translate, translatePlural } from '@nextcloud/l10n'

// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('contractmanager', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

new Vue({
    el: '#contractmanager',
    render: h => h(App),
})
