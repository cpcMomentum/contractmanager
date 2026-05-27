<template>
	<NcContent app-name="contractmanager">
		<NcAppNavigation>
			<div class="nav-search">
				<NcTextField v-model="searchQuery"
					:label="t('contractmanager', 'Verträge durchsuchen …')"
					:show-trailing-button="searchQuery !== ''"
					trailing-button-icon="close"
					@trailing-button-click="searchQuery = ''">
					<template #icon>
						<MagnifyIcon :size="20" />
					</template>
				</NcTextField>
			</div>

			<NcAppNavigationItem :name="t('contractmanager', 'Verträge')"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === null }"
				@click="showAllContracts">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="contractCount > 0" :count="contractCount" />
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
					<NcCounterBubble v-if="getCategoryContractCount(category.id) > 0" :count="getCategoryContractCount(category.id)" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem v-if="uncategorizedCount > 0"
				:name="t('contractmanager', 'Ohne Kategorie')"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === 'uncategorized' }"
				class="category-item"
				@click="filterByCategory('uncategorized')">
				<template #icon>
					<TagIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble :count="uncategorizedCount" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem :name="t('contractmanager', 'Archiv')"
				:class="{ active: currentView === 'archive' }"
				@click="currentView = 'archive'; selectedCategoryId = null">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="archivedCount > 0" :count="archivedCount" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem v-if="canEdit"
				:name="t('contractmanager', 'Papierkorb')"
				:class="{ active: currentView === 'trash' }"
				@click="currentView = 'trash'; selectedCategoryId = null">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="trashedCount > 0" :count="trashedCount" />
				</template>
			</NcAppNavigationItem>

			<template #footer>
				<NcAppNavigationItem :name="t('contractmanager', 'Einstellungen')"
					:class="{ active: currentView === 'settings' }"
					@click="currentView = 'settings'; selectedCategoryId = null">
					<template #icon>
						<CogIcon :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<ContractList v-if="currentView === 'contracts'" :category-filter="selectedCategoryId" :search-query="searchQuery" />
			<ArchiveView v-else-if="currentView === 'archive'" />
			<TrashView v-else-if="currentView === 'trash'" />
			<SettingsView v-else-if="currentView === 'settings'" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import ContractList from './views/ContractList.vue'
import ArchiveView from './views/ArchiveView.vue'
import TrashView from './views/TrashView.vue'
import SettingsView from './views/SettingsView.vue'
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from './store/contracts'
import { useCategoriesStore } from './store/categories'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppContent,
		NcCounterBubble,
		NcTextField,
		FileDocumentIcon,
		MagnifyIcon,
		ArchiveIcon,
		CogIcon,
		TagIcon,
		DeleteIcon,
		ContractList,
		ArchiveView,
		TrashView,
		SettingsView,
	},
	data() {
		return {
			currentView: 'contracts',
			selectedCategoryId: null,
			searchQuery: '',
		}
	},
	computed: {
		...mapState(useCategoriesStore, ['allCategories']),
		...mapState(useContractsStore, ['allContracts', 'archivedContracts', 'trashedContracts', 'canEdit']),
		contractCount() {
			return this.allContracts.filter(c => c.status !== 'archived').length
		},
		uncategorizedCount() {
			return this.allContracts.filter(c => c.status !== 'archived' && !c.categoryId).length
		},
		archivedCount() {
			return this.archivedContracts.length
		},
		trashedCount() {
			return this.trashedContracts.length
		},
	},
	created() {
		this.fetchCategories()
		this.fetchContracts()
		this.fetchArchivedContracts()
		this.fetchPermissions()
		this.fetchTrashedContracts()
	},
	methods: {
		...mapActions(useCategoriesStore, ['fetchCategories']),
		...mapActions(useContractsStore, ['fetchContracts', 'fetchArchivedContracts', 'fetchPermissions', 'fetchTrashedContracts']),
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

.nav-search {
	padding: 8px 10px;
}
</style>
