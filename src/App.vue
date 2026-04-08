<template>
	<NcContent app-name="contractmanager">
		<NcAppNavigation>
			<div class="nav-search">
				<NcTextField :value.sync="searchQuery"
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

			<NcAppNavigationItem v-if="uncategorizedCount > 0"
				:name="t('contractmanager', 'Ohne Kategorie')"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === 'uncategorized' }"
				class="category-item"
				@click="filterByCategory('uncategorized')">
				<template #icon>
					<TagIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble>
						{{ uncategorizedCount }}
					</NcCounterBubble>
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem :name="t('contractmanager', 'Archiv')"
				:class="{ active: currentView === 'archive' }"
				@click="currentView = 'archive'; selectedCategoryId = null">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="archivedCount > 0">
						{{ archivedCount }}
					</NcCounterBubble>
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
					<NcCounterBubble v-if="trashedCount > 0">
						{{ trashedCount }}
					</NcCounterBubble>
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
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
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
import { mapGetters, mapActions } from 'vuex'

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
		...mapGetters('categories', ['allCategories']),
		...mapGetters('contracts', ['allContracts', 'archivedContracts', 'trashedContracts', 'canEdit']),
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
		...mapActions('categories', ['fetchCategories']),
		...mapActions('contracts', ['fetchContracts', 'fetchArchivedContracts', 'fetchPermissions', 'fetchTrashedContracts']),
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
