<template>
	<div class="vendor-contact-card" :class="{ 'vendor-contact-card--framed': loading || contact }">
		<NcLoadingIcon v-if="loading" :size="20" />
		<template v-else-if="contact">
			<div class="vendor-contact-card__head">
				<CardAccountDetailsOutlineIcon :size="20" />
				<b>{{ contact.name }}</b>
				<span v-if="contact.organization && contact.organization !== contact.name" class="vendor-contact-card__org">
					{{ contact.organization }}
				</span>
			</div>
			<ul v-if="hasDetails" class="vendor-contact-card__list">
				<li v-for="email in contact.emails" :key="'e' + email">
					<EmailOutlineIcon :size="16" />
					<a :href="'mailto:' + email">{{ email }}</a>
				</li>
				<li v-for="phone in contact.phones" :key="'p' + phone">
					<PhoneIcon :size="16" />
					<a :href="'tel:' + telHref(phone)">{{ phone }}</a>
				</li>
				<li v-for="address in contact.addresses" :key="'a' + address">
					<MapMarkerOutlineIcon :size="16" />
					<span class="vendor-contact-card__address">{{ address }}</span>
				</li>
			</ul>
			<p v-else class="vendor-contact-card__empty">
				{{ t('contractmanager', 'Im Kontakt sind keine E-Mail-Adresse, Telefonnummer oder Adresse hinterlegt.') }}
			</p>
			<a v-if="contact.contactsUrl"
				class="vendor-contact-card__open"
				:href="contact.contactsUrl"
				target="_blank"
				rel="noopener noreferrer">
				{{ t('contractmanager', 'In Kontakte öffnen') }}
				<OpenInNewIcon :size="14" />
			</a>
		</template>
		<NcNoteCard v-else type="info">
			{{ t('contractmanager', 'Der verknüpfte Kontakt ist in Ihren Adressbüchern nicht verfügbar.') }}
		</NcNoteCard>
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import CardAccountDetailsOutlineIcon from 'vue-material-design-icons/CardAccountDetailsOutline.vue'
import EmailOutlineIcon from 'vue-material-design-icons/EmailOutline.vue'
import MapMarkerOutlineIcon from 'vue-material-design-icons/MapMarkerOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import PhoneIcon from 'vue-material-design-icons/Phone.vue'

export default {
	name: 'VendorContactCard',
	components: {
		NcLoadingIcon,
		NcNoteCard,
		CardAccountDetailsOutlineIcon,
		EmailOutlineIcon,
		MapMarkerOutlineIcon,
		OpenInNewIcon,
		PhoneIcon,
	},
	props: {
		contact: {
			type: Object,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		hasDetails() {
			return this.contact.emails.length + this.contact.phones.length + this.contact.addresses.length > 0
		},
	},
	methods: {
		telHref(phone) {
			return phone.replace(/[^\d+]/g, '')
		},
	},
}
</script>

<style scoped lang="scss">
.vendor-contact-card {
	margin-top: 8px;

	&--framed {
		padding: 10px 12px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large, 10px);
	}

	&__head {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 6px;
	}

	&__org {
		color: var(--color-text-maxcontrast);
	}

	&__list {
		margin: 8px 0 0;
		padding: 0;
		list-style: none;

		li {
			display: flex;
			align-items: flex-start;
			gap: 8px;
			padding: 2px 0;
			overflow-wrap: anywhere;

			.material-design-icon {
				flex: 0 0 auto;
				align-self: flex-start;
				margin-top: 2px;
				color: var(--color-text-maxcontrast);
			}
		}

		a {
			color: var(--color-primary-element);
			text-decoration: underline;

			&:hover, &:focus {
				text-decoration: none;
			}
		}
	}

	&__address {
		white-space: pre-line;
	}

	&__empty {
		margin: 8px 0 0;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
	}

	&__open {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		margin-top: 8px;
		font-size: 13px;
		color: var(--color-primary-element);
	}

	:deep(.notecard) {
		margin: 0;
	}
}
</style>
