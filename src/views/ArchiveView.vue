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

		<NcDialog v-if="showRestoreDialog"
			:name="t('contractmanager', 'Vertrag wiederherstellen')"
			@close="showRestoreDialog = false">
			<p>{{ t('contractmanager', 'Vertrag "{name}" wiederherstellen?', { name: restoringContract ? restoringContract.name : '' }) }}</p>
			<template #actions>
				<NcButton @click="showRestoreDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton variant="primary" @click="confirmRestore">
					{{ t('contractmanager', 'Wiederherstellen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from '../store/contracts'
import { useCategoriesStore } from '../store/categories'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ContractListItem from '../components/ContractListItem.vue'
import ContractForm from '../components/ContractForm.vue'

export default {
	name: 'ArchiveView',
	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcEmptyContent,
		ArchiveIcon,
		ContractListItem,
		ContractForm,
	},
	data() {
		return {
			showEditForm: false,
			showRestoreDialog: false,
			editingContract: null,
			restoringContract: null,
			formLoading: false,
		}
	},
	computed: {
		...mapState(useContractsStore, {
			archivedContracts: 'archivedContracts',
			loading: 'isLoading',
		}),
	},
	created() {
		this.fetchArchivedContracts()
		this.fetchCategories()
	},
	methods: {
		...mapActions(useContractsStore, ['fetchArchivedContracts', 'restoreContract', 'updateContract']),
		...mapActions(useCategoriesStore, ['fetchCategories']),

		handleContractClick(contract) {
			this.editingContract = contract
			this.showEditForm = true
		},

		handleRestore(contract) {
			this.restoringContract = contract
			this.showRestoreDialog = true
		},

		async confirmRestore() {
			if (!this.restoringContract) return
			try {
				await this.restoreContract(this.restoringContract.id)
			} catch (error) {
				console.error('Failed to restore contract:', error)
			} finally {
				this.showRestoreDialog = false
				this.restoringContract = null
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
