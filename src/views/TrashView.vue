<template>
	<div class="trash-view">
		<div class="trash-view__header">
			<h2>{{ t('contractmanager', 'Papierkorb') }}</h2>
			<NcButton v-if="isAdmin && trashedContracts.length > 0"
				type="error"
				@click="confirmEmptyTrash">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Papierkorb leeren') }}
			</NcButton>
		</div>

		<NcNoteCard v-if="!isAdmin && trashedContracts.length > 0" type="info">
			{{ t('contractmanager', 'Verträge werden nach 30 Tagen automatisch endgültig gelöscht.') }}
		</NcNoteCard>

		<NcNoteCard v-if="isAdmin && trashedContracts.length > 0" type="info">
			{{ t('contractmanager', 'Als Admin werden Ihre gelöschten Verträge nicht automatisch gelöscht.') }}
		</NcNoteCard>

		<div v-if="loading" class="trash-view__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<NcEmptyContent v-else-if="trashedContracts.length === 0"
			:name="t('contractmanager', 'Papierkorb leer')"
			:description="t('contractmanager', 'Gelöschte Verträge werden hier angezeigt.')">
			<template #icon>
				<DeleteIcon :size="64" />
			</template>
		</NcEmptyContent>

		<div v-else class="trash-view__items">
			<div v-for="contract in trashedContracts"
				:key="contract.id"
				class="trash-item">
				<div class="trash-item__info">
					<span class="trash-item__name">{{ contract.name }}</span>
					<span class="trash-item__vendor">{{ contract.vendor }}</span>
					<span v-if="isAdmin" class="trash-item__creator">
						({{ contract.createdBy }})
					</span>
					<span class="trash-item__date">
						{{ t('contractmanager', 'Gelöscht:') }} {{ formatDate(contract.deletedAt) }}
					</span>
				</div>
				<div class="trash-item__actions">
					<NcButton type="secondary"
						@click="handleRestore(contract)">
						<template #icon>
							<RestoreIcon :size="20" />
						</template>
						{{ t('contractmanager', 'Wiederherstellen') }}
					</NcButton>
					<NcButton v-if="isAdmin"
						type="error"
						@click="confirmPermanentDelete(contract)">
						<template #icon>
							<DeleteForeverIcon :size="20" />
						</template>
						{{ t('contractmanager', 'Endgültig löschen') }}
					</NcButton>
				</div>
			</div>
		</div>

		<NcDialog v-if="showDeleteDialog"
			:name="t('contractmanager', 'Vertrag endgültig löschen?')"
			@close="showDeleteDialog = false">
			<p>{{ t('contractmanager', 'Der Vertrag wird unwiderruflich gelöscht.') }}</p>
			<template #actions>
				<NcButton @click="showDeleteDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton type="error" @click="handlePermanentDelete">
					{{ t('contractmanager', 'Endgültig löschen') }}
				</NcButton>
			</template>
		</NcDialog>

		<NcDialog v-if="showEmptyTrashDialog"
			:name="t('contractmanager', 'Papierkorb wirklich leeren?')"
			@close="showEmptyTrashDialog = false">
			<p>{{ t('contractmanager', 'Alle Verträge werden unwiderruflich gelöscht.') }}</p>
			<template #actions>
				<NcButton @click="showEmptyTrashDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton type="error" @click="handleEmptyTrash">
					{{ t('contractmanager', 'Papierkorb leeren') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DeleteForeverIcon from 'vue-material-design-icons/DeleteForever.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'TrashView',
	components: {
		NcButton,
		NcLoadingIcon,
		NcEmptyContent,
		NcNoteCard,
		NcDialog,
		DeleteIcon,
		DeleteForeverIcon,
		RestoreIcon,
	},
	data() {
		return {
			showDeleteDialog: false,
			showEmptyTrashDialog: false,
			contractToDelete: null,
		}
	},
	computed: {
		...mapGetters('contracts', {
			trashedContracts: 'trashedContracts',
			loading: 'isLoading',
			isAdmin: 'isAdmin',
		}),
	},
	created() {
		this.fetchTrashedContracts()
		this.fetchPermissions()
	},
	methods: {
		...mapActions('contracts', [
			'fetchTrashedContracts',
			'fetchPermissions',
			'restoreFromTrash',
			'deletePermanently',
			'emptyTrash',
		]),

		formatDate(dateString) {
			if (!dateString) return ''
			const date = new Date(dateString)
			return date.toLocaleDateString('de-DE', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
			})
		},

		async handleRestore(contract) {
			try {
				await this.restoreFromTrash(contract.id)
				showSuccess(t('contractmanager', 'Vertrag wiederhergestellt'))
			} catch (error) {
				console.error('Failed to restore contract:', error)
				showError(t('contractmanager', 'Fehler beim Wiederherstellen'))
			}
		},

		confirmPermanentDelete(contract) {
			this.contractToDelete = contract
			this.showDeleteDialog = true
		},

		async handlePermanentDelete() {
			if (!this.contractToDelete) return

			try {
				await this.deletePermanently(this.contractToDelete.id)
				showSuccess(t('contractmanager', 'Vertrag endgültig gelöscht'))
			} catch (error) {
				console.error('Failed to delete contract:', error)
				showError(t('contractmanager', 'Fehler beim Löschen'))
			} finally {
				this.showDeleteDialog = false
				this.contractToDelete = null
			}
		},

		confirmEmptyTrash() {
			this.showEmptyTrashDialog = true
		},

		async handleEmptyTrash() {
			try {
				await this.emptyTrash()
				showSuccess(t('contractmanager', 'Papierkorb geleert'))
			} catch (error) {
				console.error('Failed to empty trash:', error)
				showError(t('contractmanager', 'Fehler beim Leeren des Papierkorbs'))
			} finally {
				this.showEmptyTrashDialog = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.trash-view {
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
		gap: 8px;
		margin-top: 16px;
	}
}

.trash-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	&__info {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__name {
		font-weight: 600;
	}

	&__vendor {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
	}

	&__creator {
		color: var(--color-text-maxcontrast);
		font-size: 0.85em;
	}

	&__date {
		color: var(--color-text-maxcontrast);
		font-size: 0.85em;
	}

	&__actions {
		display: flex;
		gap: 8px;
	}
}
</style>
