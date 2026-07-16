<template>
	<div class="contract-list-item" :class="'contract-list-item--' + statusChip.cls">
		<span class="contract-list-item__accent" aria-hidden="true" />
		<div class="contract-list-item__name">
			<a class="contract-name" href="#" @click.prevent="onEdit">
				{{ contract.name }}
			</a>
			<div class="contract-list-item__meta">
				<span>{{ contract.vendor }}</span>
				<template v-if="contract.renewalPeriod && contract.contractType === 'auto_renewal'">
					<span class="sep">·</span>
					<span>{{ t('contractmanager', 'Verlängerung:') }} {{ formatPeriod(contract.renewalPeriod) }}</span>
				</template>
				<template v-if="mode === 'trash' && contract.deletedAt">
					<span class="sep">·</span>
					<span>{{ t('contractmanager', 'Gelöscht:') }} {{ formatDate(contract.deletedAt) }}</span>
				</template>
				<template v-if="showCreator && contract.createdBy">
					<span class="sep">·</span>
					<span>{{ t('contractmanager', 'Erstellt von') }}: {{ contract.createdBy }}</span>
				</template>
				<template v-if="contract.responsibleUser">
					<span class="sep">·</span>
					<span>{{ t('contractmanager', 'Zuständig') }}: {{ contract.responsibleUser }}</span>
				</template>
			</div>
		</div>
		<div class="contract-list-item__status">
			<span class="cm-chip" :class="'cm-chip--' + statusChip.cls" :title="statusChip.title">
				{{ statusChip.label }}
			</span>
			<span v-if="contract.isPrivate" class="cm-chip cm-chip--lock" :title="t('contractmanager', 'Privater Vertrag (nur für mich sichtbar)')">
				<LockIcon :size="14" />
				{{ t('contractmanager', 'Privat') }}
			</span>
		</div>
		<div class="contract-list-item__cost">
			{{ contract.cost ? formatCost(contract.cost, contract.currency) : '—' }}
		</div>
		<div class="contract-list-item__deadline">
			<span v-if="deadlineLabel" class="contract-list-item__deadline-label">{{ deadlineLabel }}</span>
			<span>{{ deadlineDisplay }}</span>
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
import { generateUrl } from '@nextcloud/router'
import { formatDate } from '../utils/dateUtils.js'
import { formatPeriod, getDeadlineInfo } from '../utils/periodUtils.js'
import { isEndingSoon, isEndingSoonFixed, isExpiredFixed } from '../utils/contractStatus'
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
		// First-reminder window in days (admin setting). When omitted the
		// helper falls back to its built-in default.
		defaultReminderDays: {
			type: Number,
			default: undefined,
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
		endingSoon() {
			return isEndingSoon(this.contract, this.defaultReminderDays)
		},
		endingSoonFixed() {
			return isEndingSoonFixed(this.contract, this.defaultReminderDays)
		},
		expiredFixed() {
			return isExpiredFixed(this.contract)
		},
		// Single derived status chip — replaces the former stack of badges
		// (Status + „Kündigungsfrist endet" + „Abgelaufen") with one clear signal.
		statusChip() {
			if (this.expiredFixed) {
				return { cls: 'expired', label: t('contractmanager', 'Abgelaufen'), title: t('contractmanager', 'Das Enddatum ist überschritten.') }
			}
			if (this.endingSoon) {
				return { cls: 'soon', label: t('contractmanager', 'Kündigungsfrist endet'), title: t('contractmanager', 'Die Kündigungsfrist läuft in Kürze ab.') }
			}
			if (this.endingSoonFixed) {
				return { cls: 'soon', label: t('contractmanager', 'endet'), title: t('contractmanager', 'Der befristete Vertrag läuft in Kürze aus.') }
			}
			if (this.contract.status === 'cancelled') {
				return { cls: 'cancelled', label: t('contractmanager', 'Gekündigt'), title: '' }
			}
			if (this.contract.status === 'ended') {
				return { cls: 'ended', label: t('contractmanager', 'Beendet'), title: '' }
			}
			if (this.contract.contractType === 'fixed') {
				return { cls: 'active-fixed', label: t('contractmanager', 'Laufend'), title: t('contractmanager', 'Befristeter Vertrag, endet automatisch zum Enddatum.') }
			}
			return { cls: 'active', label: t('contractmanager', 'Laufend'), title: '' }
		},
		// Value + semantics of the "Frist / Ende" column (#252): the shown date
		// means different things per contract, so each row carries its own label.
		deadlineInfo() {
			return getDeadlineInfo(this.contract)
		},
		deadlineDisplay() {
			return this.deadlineInfo.date ? this.formatDate(this.deadlineInfo.date) : '—'
		},
		deadlineLabel() {
			const labels = {
				cancelBy: t('contractmanager', 'Kündbar bis'),
				cancelledTo: t('contractmanager', 'Gekündigt zum'),
				runsUntil: t('contractmanager', 'Läuft bis'),
			}
			return labels[this.deadlineInfo.labelKey] || null
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
/* Status-Farbpalette (aus WorkTime/Vinarium übernommen) */
$st: (
	active:    (#eaf5ee, #2f7d49),
	active-fixed: (#dcefe3, #1d5c33),
	soon:      (#fbf3e6, #9a6c25),
	cancelled: (#fef3c7, #92400e),
	ended:     (#efefef, #5a5a5a),
	expired:   (#fbecea, #b03b33),
);

/* Dark-Mode-Entsprechung (#204): gleiche Farbtöne, dunkler Grund + hellerer
   Text, damit die Chips auf dunklem Hintergrund lesbar bleiben. Bewusst eigene
   Werte statt NC-Variablen, um die kräftige Signalwirkung zu erhalten. */
$st-dark: (
	active:    (#17301f, #6fbf87),
	active-fixed: (#16281c, #63c081),
	soon:      (#33291a, #e0b15a),
	cancelled: (#3a2713, #e08a4a),
	ended:     (#2b2b2b, #a8a8a8),
	expired:   (#3a201d, #ef8177),
);

/* Chip-Hintergrund/Text im Dark-Mode. */
@mixin chip-dark {
	@each $name, $colors in $st-dark {
		&--#{$name} { background: nth($colors, 1); color: nth($colors, 2); }
	}
}

/* Akzentbalken der Listenzeile im Dark-Mode (heller Textton). */
@mixin accent-dark {
	@each $name, $colors in $st-dark {
		&--#{$name} .contract-list-item__accent { background: nth($colors, 2); }
	}
}

/* Shared column layout — MUST match .contract-list__thead in ContractList.vue */
.contract-list-item {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 150px 110px 120px 116px;
	align-items: center;
	gap: 14px;
	position: relative;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border-light, var(--color-border));
	min-height: 58px;
	transition: background-color 0.15s ease;

	&:last-child { border-bottom: none; }

	&:hover { background: var(--color-background-hover); }

	&__accent {
		position: absolute;
		left: 0;
		top: 0;
		bottom: 0;
		width: 3px;
		background: transparent;
	}

	@each $name, $colors in $st {
		&--#{$name} .contract-list-item__accent { background: nth($colors, 2); }
	}

	// Dark-Mode: explizit gewähltes NC-Theme oder System-Präferenz (#204).
	body[data-theme-dark] &,
	body[data-theme-dark-highcontrast] & {
		@include accent-dark;
	}
	@media (prefers-color-scheme: dark) {
		body[data-theme-default] & {
			@include accent-dark;
		}
	}

	&__name {
		min-width: 0;
		padding: 9px 0 9px 18px;
	}

	&__meta {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
		margin-top: 2px;
		font-size: 12.5px;
		color: var(--color-text-maxcontrast);

		.sep { opacity: 0.5; }
	}

	&__status {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}

	&__cost {
		text-align: right;
		font-size: 15px;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
	}

	&__deadline {
		display: flex;
		flex-direction: column;
		font-variant-numeric: tabular-nums;
		white-space: nowrap;
		color: var(--color-main-text);
	}

	&__deadline-label {
		font-size: 11px;
		line-height: 1.3;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		gap: 2px;
		padding-right: 8px;
	}
}

.contract-name {
	font-size: 16px;
	font-weight: 600;
	color: var(--color-primary-element);
	text-decoration: none;
	cursor: pointer;

	&:hover { text-decoration: underline; }
}

.cm-chip {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: var(--border-radius, 8px);
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;

	@each $name, $colors in $st {
		&--#{$name} { background: nth($colors, 1); color: nth($colors, 2); }
	}

	&--lock { background: #fbf3e6; color: #9a6c25; }

	// Dark-Mode: explizit gewähltes NC-Theme oder System-Präferenz (#204).
	body[data-theme-dark] &,
	body[data-theme-dark-highcontrast] & {
		@include chip-dark;
		&--lock { background: #33291a; color: #e0b15a; }
	}
	@media (prefers-color-scheme: dark) {
		body[data-theme-default] & {
			@include chip-dark;
			&--lock { background: #33291a; color: #e0b15a; }
		}
	}
}

.delete-action {
	color: var(--color-error) !important;
}
</style>
