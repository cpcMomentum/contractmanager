<template>
	<div class="contract-list">
		<div class="contract-list__header">
			<h2>{{ t('contractmanager', 'Verträge') }}</h2>
			<NcButton v-if="canEdit" type="primary" @click="showCreateForm = true">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Neuer Vertrag') }}
			</NcButton>
		</div>

		<div v-if="loading" class="contract-list__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="contracts.length === 0"
			:name="t('contractmanager', 'Keine Verträge')"
			:description="t('contractmanager', 'Erstellen Sie Ihren ersten Vertrag, um zu beginnen.')">
			<template #icon>
				<FileDocumentIcon :size="64" />
			</template>
			<template v-if="canEdit" #action>
				<NcButton type="primary" @click="showCreateForm = true">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Neuer Vertrag') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<div v-else class="contract-list__items">
			<ContractListItem v-for="contract in contracts"
				:key="contract.id"
				:contract="contract"
				@edit="handleEdit"
				@view="handleView"
				@archive="handleArchive" />
		</div>

		<ContractForm :show="showCreateForm || showEditForm"
			:contract="editingContract"
			:loading="formLoading"
			@close="closeForm"
			@submit="handleFormSubmit" />

		<ContractForm :show="showViewForm"
			:contract="viewingContract"
			:read-only="true"
			@close="closeForm" />
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import ContractListItem from '../components/ContractListItem.vue'
import ContractForm from '../components/ContractForm.vue'

export default {
	name: 'ContractList',
	components: {
		NcButton,
		NcLoadingIcon,
		NcEmptyContent,
		PlusIcon,
		FileDocumentIcon,
		ContractListItem,
		ContractForm,
	},
	props: {
		categoryFilter: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			showCreateForm: false,
			showEditForm: false,
			showViewForm: false,
			editingContract: null,
			viewingContract: null,
			formLoading: false,
		}
	},
	computed: {
		...mapGetters('contracts', {
			allContracts: 'allContracts',
			loading: 'isLoading',
			canEdit: 'canEdit',
		}),
		contracts() {
			// Show active, cancelled, and expired contracts (not archived)
			let filtered = this.allContracts.filter(c => c.status !== 'archived')
			if (this.categoryFilter !== null) {
				filtered = filtered.filter(c => c.categoryId === this.categoryFilter)
			}
			return filtered
		},
	},
	created() {
		this.fetchContracts()
		this.fetchCategories()
	},
	methods: {
		...mapActions('contracts', ['fetchContracts', 'createContract', 'updateContract', 'archiveContract']),
		...mapActions('categories', ['fetchCategories']),

		handleEdit(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		handleView(contract) {
			this.viewingContract = contract
			this.showViewForm = true
		},

		async handleArchive(contract) {
			if (confirm(t('contractmanager', 'Vertrag "{name}" wirklich archivieren?', { name: contract.name }))) {
				try {
					await this.archiveContract(contract.id)
				} catch (error) {
					console.error('Failed to archive contract:', error)
				}
			}
		},

		closeForm() {
			this.showCreateForm = false
			this.showEditForm = false
			this.showViewForm = false
			this.editingContract = null
			this.viewingContract = null
		},

		async handleFormSubmit(data) {
			this.formLoading = true
			try {
				if (this.editingContract) {
					await this.updateContract({
						id: this.editingContract.id,
						data,
					})
				} else {
					await this.createContract(data)
				}
				this.closeForm()
			} catch (error) {
				console.error('Failed to save contract:', error)
			} finally {
				this.formLoading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.contract-list {
	padding: 20px;
	padding-left: 50px;
	height: 100%;

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		align-items: center;
		height: 200px;
	}

	&__items {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
}
</style>
