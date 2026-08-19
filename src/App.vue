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

			<!-- Category filters. Double as drop targets: dragging a contract row
			     onto one reassigns its category (#359). -->
			<NcAppNavigationItem v-for="category in allCategories"
				:key="category.id"
				:name="category.name"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === category.id, 'nav-drop-target': dropTargetKey === category.id }"
				class="category-item"
				@click="filterByCategory(category.id)"
				@dragover.prevent="dropTargetKey = category.id"
				@dragenter.prevent="dropTargetKey = category.id"
				@dragleave="onCategoryDragLeave(category.id, $event)"
				@drop.prevent="onCategoryDrop(category.id, $event)">
				<template #icon>
					<TagIcon :size="20" />
				</template>
				<template #counter>
					<NcCounterBubble v-if="getCategoryContractCount(category.id) > 0" :count="getCategoryContractCount(category.id)" />
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem v-if="uncategorizedCount > 0"
				:name="t('contractmanager', 'Ohne Kategorie')"
				:class="{ active: currentView === 'contracts' && selectedCategoryId === 'uncategorized', 'nav-drop-target': dropTargetKey === 'uncategorized' }"
				class="category-item"
				@click="filterByCategory('uncategorized')"
				@dragover.prevent="dropTargetKey = 'uncategorized'"
				@dragenter.prevent="dropTargetKey = 'uncategorized'"
				@dragleave="onCategoryDragLeave('uncategorized', $event)"
				@drop.prevent="onCategoryDrop('uncategorized', $event)">
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
import { parseContractId, resolveTargetCategoryId } from './utils/categoryDrop'
import { showSuccess, showError } from '@nextcloud/dialogs'

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
			// Key of the category entry currently hovered during a drag (#359).
			dropTargetKey: null,
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
		this.fetchArchivedContracts()
		this.fetchPermissions()
		this.fetchTrashedContracts()
	},
	methods: {
		...mapActions(useContractsStore, ['fetchArchivedContracts', 'fetchPermissions', 'fetchTrashedContracts', 'setContractCategory']),
		// dragleave fires when the cursor crosses onto a child element too; only
		// clear the highlight when the pointer actually leaves the whole entry.
		onCategoryDragLeave(key, event) {
			if (event.currentTarget.contains(event.relatedTarget)) {
				return
			}
			if (this.dropTargetKey === key) {
				this.dropTargetKey = null
			}
		},
		// A contract row was dropped on a category (#359): reassign its category.
		async onCategoryDrop(key, event) {
			this.dropTargetKey = null
			const id = parseContractId(event.dataTransfer)
			if (id === null) {
				return
			}
			const targetCategoryId = resolveTargetCategoryId(key)
			const contract = this.allContracts.find(c => c.id === id)
			// No-op when already in the target category — avoids a needless save.
			if (contract && (contract.categoryId ?? null) === targetCategoryId) {
				return
			}
			try {
				await this.setContractCategory(id, targetCategoryId)
				showSuccess(targetCategoryId === null
					? t('contractmanager', 'Kategorie entfernt')
					: t('contractmanager', 'In Kategorie verschoben'))
			} catch (error) {
				console.error('Failed to reassign category:', error)
				showError(t('contractmanager', 'Fehler beim Ändern der Kategorie'))
			}
		},
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
				c => c.status !== 'archived' && c.categoryId === categoryId,
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

/* Highlight the category entry a contract is being dragged onto (#359). The
   class sits on the <li> (child-component root gets the parent scope); the
   clickable link inside needs :deep to be reachable from scoped styles. */
.nav-drop-target :deep(.app-navigation-entry-link) {
	background-color: var(--color-primary-element-light);
	box-shadow: inset 3px 0 0 var(--color-primary-element);
	border-radius: var(--border-radius-large, 8px);
}

.nav-search {
	padding: 8px 10px;
}
</style>
