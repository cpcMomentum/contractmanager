<template>
	<div class="contract-list">
		<div class="contract-list__header">
			<h2>{{ t('contractmanager', 'Verträge') }}</h2>
			<NcButton type="primary" @click="showCreateForm = true">
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
			<template #action>
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
				@click="handleContractClick"
				@edit="handleEdit"
				@archive="handleArchive" />
		</div>

		<ContractForm :show="showCreateForm || showEditForm"
			:contract="editingContract"
			:loading="formLoading"
			@close="closeForm"
			@submit="handleFormSubmit" />
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
	data() {
		return {
			showCreateForm: false,
			showEditForm: false,
			editingContract: null,
			formLoading: false,
		}
	},
	computed: {
		...mapGetters('contracts', {
			contracts: 'allContracts',
			loading: 'isLoading',
		}),
	},
	created() {
		this.fetchContracts()
		this.fetchCategories()
	},
	methods: {
		...mapActions('contracts', ['fetchContracts', 'createContract', 'updateContract', 'archiveContract']),
		...mapActions('categories', ['fetchCategories']),

		handleContractClick(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		handleEdit(contract) {
			this.editingContract = contract
			this.showEditForm = true
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
			this.editingContract = null
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
