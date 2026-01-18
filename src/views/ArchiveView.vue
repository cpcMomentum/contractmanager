<template>
	<div class="archive-view">
		<div class="archive-view__header">
			<h2>{{ t('contractmanager', 'Archiv') }}</h2>
		</div>

		<div v-if="loading" class="archive-view__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="archivedContracts.length === 0"
			:name="t('contractmanager', 'Archiv leer')"
			:description="t('contractmanager', 'Archivierte Verträge werden hier angezeigt.')">
			<template #icon>
				<ArchiveIcon :size="64" />
			</template>
		</NcEmptyContent>

		<div v-else class="archive-view__items">
			<ContractListItem v-for="contract in archivedContracts"
				:key="contract.id"
				:contract="contract"
				@click="handleContractClick"
				@restore="handleRestore" />
		</div>

		<ContractForm :show="showEditForm"
			:contract="editingContract"
			:loading="formLoading"
			@close="closeForm"
			@submit="handleFormSubmit" />
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ContractListItem from '../components/ContractListItem.vue'
import ContractForm from '../components/ContractForm.vue'

export default {
	name: 'ArchiveView',
	components: {
		NcLoadingIcon,
		NcEmptyContent,
		ArchiveIcon,
		ContractListItem,
		ContractForm,
	},
	data() {
		return {
			showEditForm: false,
			editingContract: null,
			formLoading: false,
		}
	},
	computed: {
		...mapGetters('contracts', {
			archivedContracts: 'archivedContracts',
			loading: 'isLoading',
		}),
	},
	created() {
		this.fetchArchivedContracts()
		this.fetchCategories()
	},
	methods: {
		...mapActions('contracts', ['fetchArchivedContracts', 'restoreContract', 'updateContract']),
		...mapActions('categories', ['fetchCategories']),

		handleContractClick(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		async handleRestore(contract) {
			if (confirm(t('contractmanager', 'Vertrag "{name}" wiederherstellen?', { name: contract.name }))) {
				try {
					await this.restoreContract(contract.id)
				} catch (error) {
					console.error('Failed to restore contract:', error)
				}
			}
		},

		closeForm() {
			this.showEditForm = false
			this.editingContract = null
		},

		async handleFormSubmit(data) {
			this.formLoading = true
			try {
				await this.updateContract({
					id: this.editingContract.id,
					data,
				})
				this.closeForm()
			} catch (error) {
				console.error('Failed to update contract:', error)
			} finally {
				this.formLoading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.archive-view {
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
