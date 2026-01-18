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
			validator: (value) => ['active', 'cancelled', 'ended'].includes(value),
		},
	},
	computed: {
		statusLabel() {
			const labels = {
				active: t('contractmanager', 'Laufend'),
				cancelled: t('contractmanager', 'Gekündigt'),
				ended: t('contractmanager', 'Beendet'),
			}
			return labels[this.status] || this.status
		},
	},
}
</script>

<style scoped lang="scss">
.status-badge {
	display: inline-flex;
	align-items: center;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 13px;
	font-weight: 600;
	letter-spacing: 0.2px;
	white-space: nowrap;

	&.status-active {
		background-color: #dcfce7;
		color: #166534;
	}

	&.status-cancelled {
		background-color: #fef3c7;
		color: #92400e;
	}

	&.status-ended {
		background-color: var(--color-background-dark, #f5f5f5);
		color: var(--color-text-maxcontrast, #757575);
	}
}
</style>
