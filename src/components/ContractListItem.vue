<template>
	<div class="contract-list-item">
		<div class="contract-list-item__main">
			<div class="contract-list-item__header">
				<a class="contract-name" href="#" @click.prevent="onEdit">
					{{ contract.name }}
				</a>
				<StatusBadge :status="contract.status" />
				<span v-if="contract.isPrivate" class="private-badge" :title="t('contractmanager', 'Privater Vertrag (nur für mich sichtbar)')">
					<LockIcon :size="16" />
					{{ t('contractmanager', 'Privat') }}
				</span>
			</div>
			<div class="contract-list-item__details">
				<span>{{ contract.vendor }}</span>
				<span v-if="contract.cost">{{ formatCost(contract.cost, contract.currency) }}</span>
				<span>|</span>
				<span>{{ t('contractmanager', 'Endet:') }} {{ formatDate(contract.endDate) }}</span>
				<span v-if="cancellationDeadline">| {{ t('contractmanager', 'Kündigen bis:') }} {{ formatDate(cancellationDeadline) }}</span>
				<span v-if="contract.renewalPeriod">| {{ t('contractmanager', 'Verlängerung:') }} {{ formatPeriod(contract.renewalPeriod) }}</span>
				<span v-if="showCreator && contract.createdBy">| {{ t('contractmanager', 'Erstellt von') }}: {{ contract.createdBy }}</span>
			</div>
		</div>
		<div class="contract-list-item__actions">
			<NcButton v-if="contract.mainDocument"
				type="tertiary"
				:title="t('contractmanager', 'Vertragsdokument öffnen')"
				@click.stop="openDocument">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
			</NcButton>
			<NcActions :force-menu="true">
				<NcActionButton v-if="!contract.archived && canEdit"
					@click.stop="$emit('archive', contract)">
					<template #icon>
						<ArchiveIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Archivieren') }}
				</NcActionButton>
				<NcActionButton v-if="contract.archived && canEdit"
					@click.stop="$emit('restore', contract)">
					<template #icon>
						<RestoreIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Wiederherstellen') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit"
					@click.stop="$emit('duplicate', contract)">
					<template #icon>
						<ContentDuplicate :size="20" />
					</template>
					{{ t('contractmanager', 'Duplizieren') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit"
					@click.stop="$emit('edit', contract)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Bearbeiten') }}
				</NcActionButton>
				<NcActionButton v-if="isAdmin"
					class="delete-action"
					@click.stop="confirmDelete">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Löschen') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Delete confirmation dialog -->
		<NcDialog v-if="showDeleteDialog"
			:name="t('contractmanager', 'Vertrag endgültig löschen?')"
			@close="showDeleteDialog = false">
			<p>{{ t('contractmanager', 'Der Vertrag wird unwiderruflich gelöscht.') }}</p>
			<template #actions>
				<NcButton @click="showDeleteDialog = false">
					{{ t('contractmanager', 'Abbrechen') }}
				</NcButton>
				<NcButton type="error" @click="handleDelete">
					{{ t('contractmanager', 'Löschen') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import ContentDuplicate from 'vue-material-design-icons/ContentDuplicate.vue'
import StatusBadge from './StatusBadge.vue'
import { generateUrl } from '@nextcloud/router'
import { formatDate } from '../utils/dateUtils.js'
import { formatPeriod, calculateCancellationDeadline } from '../utils/periodUtils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'ContractListItem',
	components: {
		NcActions,
		NcActionButton,
		NcButton,
		NcDialog,
		ArchiveIcon,
		RestoreIcon,
		PencilIcon,
		DeleteIcon,
		LockIcon,
		FileDocumentIcon,
		ContentDuplicate,
		StatusBadge,
	},
	props: {
		contract: {
			type: Object,
			required: true,
		},
		showCreator: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['edit', 'duplicate', 'archive', 'restore', 'delete'],
	data() {
		return {
			showDeleteDialog: false,
		}
	},
	computed: {
		...mapGetters('contracts', ['isAdmin', 'canEdit']),
		cancellationDeadline() {
			if (this.contract.status !== 'active') {
				return null
			}
			return calculateCancellationDeadline(this.contract.endDate, this.contract.cancellationPeriod)
		},
	},
	methods: {
		...mapActions('contracts', ['deleteContract']),
		formatDate,
		formatPeriod,
		onEdit() {
			if (this.canEdit) {
				this.$emit('edit', this.contract)
			} else {
				this.$emit('view', this.contract)
			}
		},
		confirmDelete() {
			this.showDeleteDialog = true
		},
		async handleDelete() {
			try {
				await this.deleteContract(this.contract.id)
				showSuccess(t('contractmanager', 'Vertrag in Papierkorb verschoben'))
				this.$emit('delete', this.contract)
			} catch (error) {
				console.error('Failed to delete contract:', error)
				showError(t('contractmanager', 'Fehler beim Löschen'))
			} finally {
				this.showDeleteDialog = false
			}
		},
		formatCost(cost, currency) {
			if (!cost) return ''
			const amount = parseFloat(cost)
			return new Intl.NumberFormat('de-DE', {
				style: 'currency',
				currency: currency || 'EUR',
			}).format(amount)
		},
		openDocument() {
			if (!this.contract.mainDocument) return
			const path = this.contract.mainDocument
			const parentDir = path.substring(0, path.lastIndexOf('/')) || '/'
			const fileName = path.substring(path.lastIndexOf('/') + 1)
			const filesUrl = generateUrl('/apps/files/?dir={dir}&scrollto={file}&openfile={file}', {
				dir: parentDir,
				file: fileName,
			})
			window.open(filesUrl, '_blank')
		},
	},
}
</script>

<style scoped lang="scss">
.contract-list-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 16px;
	background: var(--color-main-background);
	border-radius: 8px;
	transition: background-color 0.15s ease;

	&:hover {
		background: var(--color-background-hover);
	}

	&__main {
		flex: 1;
		min-width: 0;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 4px;
	}

	&__details {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 8px;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: 4px;
		flex-shrink: 0;
		margin-left: 16px;
	}
}

.contract-name {
	font-size: 16px;
	font-weight: 600;
	color: var(--color-main-text);
	text-decoration: none;
	cursor: pointer;

	&:hover {
		text-decoration: underline;
		color: var(--color-primary-element);
	}
}

.private-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 8px;
	background-color: var(--color-warning-hover);
	color: var(--color-warning-text);
	border-radius: 12px;
	font-size: 12px;
	font-weight: 500;
}

.delete-action {
	color: var(--color-error) !important;
}
</style>
