<template>
	<NcListItem :name="contract.name"
		:bold="false"
		:force-display-actions="true"
		@click="$emit('click', contract)">
		<template #subname>
			<span class="contract-vendor">{{ contract.vendor }}</span>
			<span class="contract-separator">•</span>
			<span class="contract-date">{{ formatDate(contract.endDate) }}</span>
			<span v-if="daysUntilEnd !== null" :class="['contract-days', daysClass]">
				({{ daysText }})
			</span>
		</template>
		<template #indicator>
			<StatusBadge :status="contract.status" />
		</template>
		<template #actions>
			<NcActionButton v-if="contract.status === 'active'"
				@click.stop="$emit('archive', contract)">
				<template #icon>
					<ArchiveIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Archivieren') }}
			</NcActionButton>
			<NcActionButton v-if="contract.status === 'archived'"
				@click.stop="$emit('restore', contract)">
				<template #icon>
					<RestoreIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Wiederherstellen') }}
			</NcActionButton>
			<NcActionButton @click.stop="$emit('edit', contract)">
				<template #icon>
					<PencilIcon :size="20" />
				</template>
				{{ t('contractmanager', 'Bearbeiten') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import StatusBadge from './StatusBadge.vue'

export default {
	name: 'ContractListItem',
	components: {
		NcListItem,
		NcActionButton,
		ArchiveIcon,
		RestoreIcon,
		PencilIcon,
		StatusBadge,
	},
	props: {
		contract: {
			type: Object,
			required: true,
		},
	},
	emits: ['click', 'edit', 'archive', 'restore'],
	computed: {
		daysUntilEnd() {
			if (!this.contract.endDate || this.contract.status !== 'active') {
				return null
			}
			const end = new Date(this.contract.endDate)
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			const diffTime = end.getTime() - today.getTime()
			return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
		},
		daysText() {
			if (this.daysUntilEnd === null) return ''
			if (this.daysUntilEnd < 0) {
				return t('contractmanager', '{days} Tage überfällig', { days: Math.abs(this.daysUntilEnd) })
			}
			if (this.daysUntilEnd === 0) {
				return t('contractmanager', 'Heute')
			}
			if (this.daysUntilEnd === 1) {
				return t('contractmanager', 'Morgen')
			}
			return t('contractmanager', 'in {days} Tagen', { days: this.daysUntilEnd })
		},
		daysClass() {
			if (this.daysUntilEnd === null) return ''
			if (this.daysUntilEnd < 0) return 'overdue'
			if (this.daysUntilEnd <= 7) return 'urgent'
			if (this.daysUntilEnd <= 30) return 'warning'
			return 'normal'
		},
	},
	methods: {
		formatDate(dateString) {
			if (!dateString) return ''
			const date = new Date(dateString)
			return date.toLocaleDateString('de-DE', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
			})
		},
	},
}
</script>

<style scoped lang="scss">
.contract-vendor {
	color: var(--color-text-maxcontrast);
}

.contract-separator {
	margin: 0 6px;
	color: var(--color-text-light);
}

.contract-date {
	color: var(--color-text-maxcontrast);
}

.contract-days {
	margin-left: 4px;
	font-size: 12px;

	&.overdue {
		color: var(--color-error);
		font-weight: 500;
	}

	&.urgent {
		color: var(--color-error);
	}

	&.warning {
		color: var(--color-warning);
	}

	&.normal {
		color: var(--color-text-light);
	}
}
</style>
