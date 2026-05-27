import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { translate, translatePlural } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import App from './App.vue'
import '../css/main.scss'

const isAdmin = loadState('contractmanager', 'isAdmin', false)

const app = createApp(App)
app.use(createPinia())

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural
app.config.globalProperties.$isAdmin = isAdmin

app.mount('.app-contractmanager')
