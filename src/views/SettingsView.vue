<template>
	<div class="settings-view">
		<div class="settings-view__header">
			<h2>{{ t('contractmanager', 'Einstellungen') }}</h2>
		</div>

		<div class="settings-section">
			<h3>{{ t('contractmanager', 'Benachrichtigungen') }}</h3>

			<div class="settings-item">
				<NcCheckboxRadioSwitch :checked.sync="emailNotifications">
					{{ t('contractmanager', 'E-Mail-Benachrichtigungen aktivieren') }}
				</NcCheckboxRadioSwitch>
				<p class="settings-description">
					{{ t('contractmanager', 'Sie erhalten E-Mails, wenn Verträge bald auslaufen.') }}
				</p>
			</div>
		</div>

		<div class="settings-section">
			<h3>{{ t('contractmanager', 'Kategorien') }}</h3>
			<p class="settings-description">
				{{ t('contractmanager', 'Kategorien können nur von Administratoren verwaltet werden.') }}
			</p>

			<div class="category-list">
				<div v-for="category in categories"
					:key="category.id"
					class="category-item">
					<span class="category-name">{{ category.name }}</span>
				</div>
			</div>
		</div>

		<div class="settings-actions">
			<NcButton type="primary" :disabled="saving" @click="saveSettings">
				<template #icon>
					<NcLoadingIcon v-if="saving" :size="20" />
				</template>
				{{ t('contractmanager', 'Speichern') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'SettingsView',
	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
	},
	data() {
		return {
			emailNotifications: true,
			saving: false,
		}
	},
	computed: {
		...mapGetters('categories', {
			categories: 'allCategories',
		}),
	},
	created() {
		this.fetchCategories()
	},
	methods: {
		...mapActions('categories', ['fetchCategories']),

		async saveSettings() {
			this.saving = true
			try {
				// TODO: Implement settings save
				await new Promise((resolve) => setTimeout(resolve, 500))
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.settings-view {
	padding: 20px;
	max-width: 800px;

	&__header {
		margin-bottom: 24px;

		h2 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
		}
	}
}

.settings-section {
	margin-bottom: 32px;
	padding-bottom: 24px;
	border-bottom: 1px solid var(--color-border);

	h3 {
		margin: 0 0 12px;
		font-size: 16px;
		font-weight: 600;
	}
}

.settings-item {
	margin-bottom: 16px;
}

.settings-description {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.category-list {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 12px;
}

.category-item {
	padding: 6px 12px;
	background: var(--color-background-dark);
	border-radius: 16px;
	font-size: 13px;
}

.settings-actions {
	margin-top: 24px;
}
</style>
