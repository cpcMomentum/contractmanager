<template>
	<span :class="['status-badge', `status-${status}`]">
		{{ statusLabel }}
	</span>
</template>

<script>
export default {
	name: 'StatusBadge',
	props: {
		status: {
			type: String,
			required: true,
			validator: (value) => ['active', 'cancelled', 'ended', 'archived'].includes(value),
		},
	},
	computed: {
		statusLabel() {
			const labels = {
				active: t('contractmanager', 'Aktiv'),
				cancelled: t('contractmanager', 'Gekündigt'),
				ended: t('contractmanager', 'Beendet'),
				archived: t('contractmanager', 'Archiviert'),
			}
			return labels[this.status] || this.status
		},
	},
}
</script>

<style scoped lang="scss">
.status-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 12px;
	font-weight: 500;

	&.status-active {
		background-color: var(--color-success-light, #e8f5e9);
		color: var(--color-success, #2e7d32);
	}

	&.status-cancelled {
		background-color: var(--color-warning-light, #fff3e0);
		color: var(--color-warning, #ef6c00);
	}

	&.status-ended {
		background-color: var(--color-background-dark, #f5f5f5);
		color: var(--color-text-maxcontrast, #757575);
	}

	&.status-archived {
		background-color: var(--color-background-darker, #eeeeee);
		color: var(--color-text-light, #9e9e9e);
	}
}
</style>
