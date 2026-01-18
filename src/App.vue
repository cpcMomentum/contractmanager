<template>
	<NcContent app-name="contractmanager">
		<NcAppNavigation>
			<NcAppNavigationItem :name="t('contractmanager', 'Verträge')"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === null }"
				@click="showAllContracts">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="contractCount > 0">
						{{ contractCount }}
					</NcCounterBubble>
				</template>
			</NcAppNavigationItem>

			<!-- Category filters -->
			<NcAppNavigationItem v-for="category in allCategories"
				:key="category.id"
				:name="category.name"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === category.id }"
				class="category-item"
				@click="filterByCategory(category.id)">
				<template #icon>
					<TagIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="getCategoryContractCount(category.id) > 0">
						{{ getCategoryContractCount(category.id) }}
					</NcCounterBubble>
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem :name="t('contractmanager', 'Archiv')"
				:class="{ active: currentView === 'archive' }"
				@click="currentView = 'archive'; selectedCategoryId = null">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem :name="t('contractmanager', 'Einstellungen')"
				:class="{ active: currentView === 'settings' }"
				@click="currentView = 'settings'; selectedCategoryId = null">
				<template #icon>
					<CogIcon :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigation>

		<NcAppContent>
			<ContractList v-if="currentView === 'contracts'" :category-filter="selectedCategoryId" />
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
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import ContractList from './views/ContractList.vue'
import ArchiveView from './views/ArchiveView.vue'
import SettingsView from './views/SettingsView.vue'
import { mapGetters, mapActions } from 'vuex'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppContent,
		NcCounterBubble,
		FileDocumentIcon,
		ArchiveIcon,
		CogIcon,
		TagIcon,
		ContractList,
		ArchiveView,
		SettingsView,
	},
	data() {
		return {
			currentView: 'contracts',
			selectedCategoryId: null,
		}
	},
	computed: {
		...mapGetters('categories', ['allCategories']),
		...mapGetters('contracts', ['allContracts']),
		contractCount() {
			return this.allContracts.filter(c => c.status !== 'archived').length
		},
	},
	created() {
		this.fetchCategories()
		this.fetchContracts()
	},
	methods: {
		...mapActions('categories', ['fetchCategories']),
		...mapActions('contracts', ['fetchContracts']),
		showAllContracts() {
			this.currentView = 'contracts'
			this.selectedCategoryId = null
		},
		filterByCategory(categoryId) {
			this.currentView = 'contracts'
			this.selectedCategoryId = categoryId
		},
		getCategoryContractCount(categoryId) {
			return this.allContracts.filter(
				c => c.status !== 'archived' && c.categoryId === categoryId
			).length
		},
	},
}
</script>

<style scoped>
.active {
	background-color: var(--color-primary-element-light);
}

.category-item {
	padding-left: 16px;
}
</style>
