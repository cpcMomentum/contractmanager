<template>
	<div class="contract-list-item">
		<div class="contract-list-item__main">
			<div class="contract-list-item__header">
				<a class="contract-name" href="#" @click.prevent="onEdit">
					{{ contract.name }}
				</a>
				<StatusBadge v-if="contract.status" :status="contract.status" />
				<span v-if="endingSoon"
					class="status-indicator status-ending-soon"
					:title="t('contractmanager', 'Die Kündigungsfrist läuft in Kürze ab.')">
					{{ t('contractmanager', 'Kündigung naht') }}
				</span>
				<span v-if="expiredFixed"
					class="status-indicator status-expired"
					:title="t('contractmanager', 'Das Enddatum ist überschritten.')">
					{{ t('contractmanager', 'Abgelaufen') }}
				</span>
				<span v-if="contract.isPrivate" class="private-badge" :title="t('contractmanager', 'Privater Vertrag (nur für mich sichtbar)')">
					<LockIcon :size="16" />
					{{ t('contractmanager', 'Privat') }}
				</span>
			</div>
			<div class="contract-list-item__details">
				<span>{{ contract.vendor }}</span>
				<span v-if="contract.cost">{{ formatCost(contract.cost, contract.currency) }}</span>
				<span>|</span>
				<span v-if="contract.contractType === 'auto_renewal'">{{ t('contractmanager', 'Endet:') }} {{ formatDate(effectiveEndDate || contract.endDate) }}</span>
				<span v-else>{{ t('contractmanager', 'Läuft aus am:') }} {{ formatDate(effectiveEndDate || contract.endDate) }}</span>
				<span v-if="cancellationDeadline && contract.contractType === 'auto_renewal'">| {{ t('contractmanager', 'Kündigen bis:') }} {{ formatDate(cancellationDeadline) }}</span>
				<span v-if="contract.renewalPeriod && contract.contractType === 'auto_renewal'">| {{ t('contractmanager', 'Verlängerung:') }} {{ formatPeriod(contract.renewalPeriod) }}</span>
				<span v-if="mode === 'trash' && contract.deletedAt">| {{ t('contractmanager', 'Gelöscht:') }} {{ formatDate(contract.deletedAt) }}</span>
				<span v-if="showCreator && contract.createdBy">| {{ t('contractmanager', 'Erstellt von') }}: {{ contract.createdBy }}</span>
			</div>
		</div>
		<div class="contract-list-item__actions">
			<NcButton v-if="contract.contractFolder"
				variant="tertiary"
				:title="t('contractmanager', 'Vertragsordner öffnen')"
				@click.stop="openFolder">
				<template #icon>
					<FolderOpenIcon :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="contract.mainDocument"
				variant="tertiary"
				:title="t('contractmanager', 'Vertragsdokument öffnen')"
				@click.stop="openDocument">
				<template #icon>
					<FileDocumentIcon :size="20" />
				</template>
			</NcButton>
			<NcActions :force-menu="true">
				<NcActionButton v-if="!contract.archived && canEdit && mode !== 'trash'"
					@click.stop="$emit('archive', contract)">
					<template #icon>
						<ArchiveIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Archivieren') }}
				</NcActionButton>
				<NcActionButton v-if="(contract.archived || mode === 'trash') && canEdit"
					:close-after-click="true"
					@click="$emit('restore', contract)">
					<template #icon>
						<RestoreIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Wiederherstellen') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit && mode !== 'trash'"
					@click.stop="$emit('duplicate', contract)">
					<template #icon>
						<ContentDuplicate :size="20" />
					</template>
					{{ t('contractmanager', 'Duplizieren') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit && mode !== 'trash'"
					@click.stop="$emit('edit', contract)">
					<template #icon>
						<PencilIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Bearbeiten') }}
				</NcActionButton>
				<NcActionButton v-if="canEdit && mode !== 'trash'"
					class="delete-action"
					:close-after-click="true"
					@click="showDeleteDialog = true">
					<template #icon>
						<DeleteIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Löschen') }}
				</NcActionButton>
				<NcActionButton v-if="mode === 'trash' && isAdmin"
					class="delete-action"
					:close-after-click="true"
					@click="$emit('deletePermanently', contract)">
					<template #icon>
						<DeleteForeverIcon :size="20" />
					</template>
					{{ t('contractmanager', 'Endgültig löschen') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Delete confirmation dialog -->
		<NcDialog v-if="showDeleteDialog"
			:name="t('contractmanager', 'Vertrag löschen')"
			:message="t('contractmanager', 'Der Vertrag wird in den Papierkorb verschoben und nach 30 Tagen automatisch gelöscht. Bis dahin kann er wiederhergestellt werden.')"
			:buttons="deleteDialogButtons"
			@update:open="showDeleteDialog = $event" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { mapState, mapActions } from 'pinia'
import { useContractsStore } from '../store/contracts'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DeleteForeverIcon from 'vue-material-design-icons/DeleteForever.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import FolderOpenIcon from 'vue-material-design-icons/FolderOpen.vue'
import ContentDuplicate from 'vue-material-design-icons/ContentDuplicate.vue'
import StatusBadge from './StatusBadge.vue'
import { generateUrl } from '@nextcloud/router'
import { formatDate } from '../utils/dateUtils.js'
import { formatPeriod, calculateCancellationDeadline, getEffectiveEndDate } from '../utils/periodUtils.js'
import { isEndingSoon, isExpiredFixed } from '../utils/contractStatus'
import { isUrl } from '../utils/documentUtils.js'
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
		DeleteForeverIcon,
		LockIcon,
		FileDocumentIcon,
		FolderOpenIcon,
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
		mode: {
			type: String,
			default: 'default',
		},
	},
	emits: ['edit', 'view', 'duplicate', 'archive', 'restore', 'delete', 'deletePermanently'],
	data() {
		return {
			showDeleteDialog: false,
		}
	},
	computed: {
		...mapState(useContractsStore, ['isAdmin', 'canEdit']),
		cancellationDeadline() {
			if (this.contract.status !== 'active') {
				return null
			}
			return calculateCancellationDeadline(this.contract.endDate, this.contract.cancellationPeriod, this.contract.contractType, this.contract.renewalPeriod)
		},
		effectiveEndDate() {
			return getEffectiveEndDate(this.contract.endDate, this.contract.contractType, this.contract.renewalPeriod, {
				status: this.contract.status,
				cancelledTo: this.contract.cancelledTo,
			})
		},
		endingSoon() {
			return isEndingSoon(this.contract)
		},
		expiredFixed() {
			return isExpiredFixed(this.contract)
		},
		deleteDialogButtons() {
			return [
				{
					label: t('contractmanager', 'Abbrechen'),
					callback: () => { this.showDeleteDialog = false },
				},
				{
					label: t('contractmanager', 'In Papierkorb'),
					variant: 'warning',
					callback: () => { this.handleDelete() },
				},
			]
		},
	},
	methods: {
		...mapActions(useContractsStore, ['deleteContract']),
		formatDate,
		formatPeriod,
		onEdit() {
			if (this.canEdit) {
				this.$emit('edit', this.contract)
			} else {
				this.$emit('view', this.contract)
			}
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
		openFolder() {
			if (!this.contract.contractFolder) return
			const filesUrl = generateUrl('/apps/files/?dir={dir}', {
				dir: this.contract.contractFolder,
			})
			window.open(filesUrl, '_blank', 'noopener,noreferrer')
		},
		async openDocument() {
			if (!this.contract.mainDocument) return
			// URL (intern oder extern): direkt oeffnen
			if (isUrl(this.contract.mainDocument)) {
				window.open(this.contract.mainDocument, '_blank', 'noopener,noreferrer')
				return
			}
			// Legacy-Pfad: Versuch 1 - Nextcloud Viewer Overlay (kein neuer Tab)
			if (window.OCA?.Viewer?.open) {
				OCA.Viewer.open({ path: this.contract.mainDocument })
				return
			}
			// Legacy-Pfad: Versuch 2 - File-ID per WebDAV holen und /f/{id} oeffnen
			try {
				const user = getCurrentUser()?.uid
				const davPath = `/remote.php/dav/files/${user}${this.contract.mainDocument}`
				const response = await axios({
					method: 'PROPFIND',
					url: davPath,
					headers: { Depth: '0' },
					data: `<?xml version="1.0"?>
						<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
							<d:prop><oc:fileid/></d:prop>
						</d:propfind>`,
				})
				const parser = new DOMParser()
				const xml = parser.parseFromString(response.data, 'application/xml')
				const fileidNode = xml.getElementsByTagNameNS('http://owncloud.org/ns', 'fileid')[0]
				if (fileidNode?.textContent) {
					window.open(generateUrl('/f/{fileId}', { fileId: fileidNode.textContent }), '_blank', 'noopener,noreferrer')
					return
				}
			} catch (e) {
				console.warn('[ContractManager] Could not resolve file ID:', e)
			}
			// Versuch 3: Datei in Files-App anzeigen (scrollto)
			const path = this.contract.mainDocument
			const parentDir = path.substring(0, path.lastIndexOf('/')) || '/'
			const fileName = path.substring(path.lastIndexOf('/') + 1)
			window.open(generateUrl('/apps/files/?dir={dir}&scrollto={file}', { dir: parentDir, file: fileName }), '_blank', 'noopener,noreferrer')
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

.status-indicator {
	display: inline-flex;
	align-items: center;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.2px;
	white-space: nowrap;
	cursor: help;

	&.status-ending-soon {
		background-color: #fff4e0;
		color: #92400e;
	}

	&.status-expired {
		background-color: #fee2e2;
		color: #991b1b;
	}
}

.delete-action {
	color: var(--color-error) !important;
}
</style>
