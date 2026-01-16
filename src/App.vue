<template>
	<NcContent app-name="contractmanager">
		<NcAppNavigation>
			<NcAppNavigationItem :name="t('contractmanager', 'Verträge')"
				:class="{ active: currentView === 'contracts' }"
				@click="currentView = 'contracts'">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :name="t('contractmanager', 'Archiv')"
				:class="{ active: currentView === 'archive' }"
				@click="currentView = 'archive'">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
			</NcAppNavigationItem>
			<template #footer>
				<NcAppNavigationItem :name="t('contractmanager', 'Einstellungen')"
					:class="{ active: currentView === 'settings' }"
					@click="currentView = 'settings'">
					<template #icon>
						<CogIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<ContractList v-if="currentView === 'contracts'" />
			<ArchiveView v-else-if="currentView === 'archive'" />
			<SettingsView v-else-if="currentView === 'settings'" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import ContractList from './views/ContractList.vue'
import ArchiveView from './views/ArchiveView.vue'
import SettingsView from './views/SettingsView.vue'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppContent,
		FileDocumentIcon,
		ArchiveIcon,
		CogIcon,
		ContractList,
		ArchiveView,
		SettingsView,
	},
	data() {
		return {
			currentView: 'contracts',
		}
	},
}
</script>

<style scoped>
.active {
	background-color: var(--color-primary-element-light);
}
</style>
