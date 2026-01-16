<template>
	<NcModal :show="show"
		:title="isEdit ? t('contractmanager', 'Vertrag bearbeiten') : t('contractmanager', 'Neuer Vertrag')"
		size="large"
		@close="$emit('close')">
		<div class="contract-form">
			<form @submit.prevent="handleSubmit">
				<!-- Basic Info -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Grunddaten') }}</h3>

					<div class="form-row">
						<NcTextField :label="t('contractmanager', 'Vertragsbezeichnung') + ' *'"
							:value.sync="form.name"
							:required="true"
							:placeholder="t('contractmanager', 'z.B. Microsoft 365 Business')" />
					</div>

					<div class="form-row">
						<NcTextField :label="t('contractmanager', 'Vertragspartner') + ' *'"
							:value.sync="form.vendor"
							:required="true"
							:placeholder="t('contractmanager', 'z.B. Microsoft')" />
					</div>

					<div class="form-row">
						<label class="form-label">{{ t('contractmanager', 'Kategorie') }}</label>
						<NcSelect v-model="form.categoryId"
							:options="categoryOptions"
							:placeholder="t('contractmanager', 'Kategorie wählen')"
							label="label"
							track-by="value"
							:reduce="option => option.value" />
					</div>
				</div>

				<!-- Dates -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Laufzeit') }}</h3>

					<div class="form-row form-row--half">
						<div>
							<NcDateTimePicker v-model="form.startDate"
								:label="t('contractmanager', 'Startdatum') + ' *'"
								type="date"
								:required="true" />
						</div>
						<div>
							<NcDateTimePicker v-model="form.endDate"
								:label="t('contractmanager', 'Enddatum') + ' *'"
								type="date"
								:required="true" />
						</div>
					</div>

					<div class="form-row">
						<NcTextField :label="t('contractmanager', 'Kündigungsfrist') + ' *'"
							:value.sync="form.cancellationPeriod"
							:required="true"
							:placeholder="t('contractmanager', 'z.B. 3 Monate')" />
					</div>

					<div class="form-row">
						<label class="form-label">{{ t('contractmanager', 'Vertragstyp') + ' *' }}</label>
						<NcSelect v-model="form.contractType"
							:options="contractTypeOptions"
							:placeholder="t('contractmanager', 'Vertragstyp wählen')"
							label="label"
							track-by="value"
							:reduce="option => option.value" />
					</div>

					<div v-if="form.contractType === 'auto_renewal'" class="form-row">
						<NcTextField :label="t('contractmanager', 'Verlängerungszeitraum')"
							:value.sync="form.renewalPeriod"
							:placeholder="t('contractmanager', 'z.B. 12 Monate')" />
					</div>
				</div>

				<!-- Costs -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Kosten') }}</h3>

					<div class="form-row form-row--thirds">
						<div>
							<NcTextField :label="t('contractmanager', 'Betrag (netto)')"
								:value.sync="form.cost"
								type="number"
								step="0.01"
								:placeholder="t('contractmanager', '0.00')" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Währung') }}</label>
							<NcSelect v-model="form.currency"
								:options="currencyOptions"
								label="label"
								track-by="value"
								:reduce="option => option.value" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Intervall') }}</label>
							<NcSelect v-model="form.costInterval"
								:options="intervalOptions"
								:placeholder="t('contractmanager', 'Intervall wählen')"
								label="label"
								track-by="value"
								:reduce="option => option.value" />
						</div>
					</div>
				</div>

				<!-- Documents -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Dokumente') }}</h3>

					<div class="form-row">
						<NcTextField :label="t('contractmanager', 'Vertragsordner')"
							:value.sync="form.contractFolder"
							:placeholder="t('contractmanager', '/Verträge/Microsoft')" />
					</div>

					<div class="form-row">
						<NcTextField :label="t('contractmanager', 'Hauptvertragsdatei')"
							:value.sync="form.mainDocument"
							:placeholder="t('contractmanager', '/Verträge/Microsoft/Vertrag.pdf')" />
					</div>
				</div>

				<!-- Reminder -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Erinnerung') }}</h3>

					<div class="form-row">
						<NcCheckboxRadioSwitch :checked.sync="form.reminderEnabled">
							{{ t('contractmanager', 'Erinnerung aktivieren') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div v-if="form.reminderEnabled" class="form-row">
						<NcTextField :label="t('contractmanager', 'Erinnerung X Tage vorher (optional)')"
							:value.sync="form.reminderDays"
							type="number"
							:placeholder="t('contractmanager', 'Standard verwenden')" />
					</div>
				</div>

				<!-- Notes -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Notizen') }}</h3>

					<div class="form-row">
						<NcRichContenteditable :value.sync="form.notes"
							:placeholder="t('contractmanager', 'Zusätzliche Notizen...')"
							:auto-complete="() => []"
							:maxlength="5000" />
					</div>
				</div>

				<!-- Actions -->
				<div class="form-actions">
					<NcButton type="tertiary" @click="$emit('close')">
						{{ t('contractmanager', 'Abbrechen') }}
					</NcButton>
					<NcButton type="primary" native-type="submit" :disabled="!isValid || loading">
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
						</template>
						{{ isEdit ? t('contractmanager', 'Speichern') : t('contractmanager', 'Erstellen') }}
					</NcButton>
				</div>
			</form>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcDateTimePicker from '@nextcloud/vue/dist/Components/NcDateTimePicker.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { mapGetters } from 'vuex'

export default {
	name: 'ContractForm',
	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcSelect,
		NcDateTimePicker,
		NcCheckboxRadioSwitch,
		NcRichContenteditable,
		NcLoadingIcon,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		contract: {
			type: Object,
			default: null,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'submit'],
	data() {
		return {
			form: this.getInitialForm(),
		}
	},
	computed: {
		...mapGetters('categories', ['allCategories']),
		isEdit() {
			return this.contract !== null
		},
		isValid() {
			return (
				this.form.name.trim() !== ''
				&& this.form.vendor.trim() !== ''
				&& this.form.startDate !== null
				&& this.form.endDate !== null
				&& this.form.cancellationPeriod.trim() !== ''
				&& this.form.contractType !== null
			)
		},
		categoryOptions() {
			return [
				{ value: null, label: t('contractmanager', 'Keine Kategorie') },
				...this.allCategories.map((c) => ({
					value: c.id,
					label: c.name,
				})),
			]
		},
		contractTypeOptions() {
			return [
				{ value: 'fixed', label: t('contractmanager', 'Befristet') },
				{ value: 'auto_renewal', label: t('contractmanager', 'Automatische Verlängerung') },
			]
		},
		currencyOptions() {
			return [
				{ value: 'EUR', label: 'EUR' },
				{ value: 'USD', label: 'USD' },
				{ value: 'CHF', label: 'CHF' },
				{ value: 'GBP', label: 'GBP' },
			]
		},
		intervalOptions() {
			return [
				{ value: null, label: t('contractmanager', 'Kein Intervall') },
				{ value: 'monthly', label: t('contractmanager', 'Monatlich') },
				{ value: 'yearly', label: t('contractmanager', 'Jährlich') },
				{ value: 'one_time', label: t('contractmanager', 'Einmalig') },
			]
		},
	},
	watch: {
		show(newVal) {
			if (newVal) {
				this.form = this.getInitialForm()
			}
		},
		contract: {
			immediate: true,
			handler(newVal) {
				if (newVal) {
					this.form = this.contractToForm(newVal)
				}
			},
		},
	},
	methods: {
		getInitialForm() {
			return {
				name: '',
				vendor: '',
				categoryId: null,
				startDate: null,
				endDate: null,
				cancellationPeriod: '',
				contractType: 'fixed',
				renewalPeriod: '',
				cost: '',
				currency: 'EUR',
				costInterval: null,
				contractFolder: '',
				mainDocument: '',
				reminderEnabled: true,
				reminderDays: '',
				notes: '',
			}
		},
		contractToForm(contract) {
			return {
				name: contract.name || '',
				vendor: contract.vendor || '',
				categoryId: contract.categoryId,
				startDate: contract.startDate ? new Date(contract.startDate) : null,
				endDate: contract.endDate ? new Date(contract.endDate) : null,
				cancellationPeriod: contract.cancellationPeriod || '',
				contractType: contract.contractType || 'fixed',
				renewalPeriod: contract.renewalPeriod || '',
				cost: contract.cost || '',
				currency: contract.currency || 'EUR',
				costInterval: contract.costInterval,
				contractFolder: contract.contractFolder || '',
				mainDocument: contract.mainDocument || '',
				reminderEnabled: contract.reminderEnabled !== false,
				reminderDays: contract.reminderDays ? String(contract.reminderDays) : '',
				notes: contract.notes || '',
			}
		},
		formToPayload() {
			return {
				name: this.form.name.trim(),
				vendor: this.form.vendor.trim(),
				categoryId: this.form.categoryId,
				startDate: this.form.startDate ? this.formatDateForApi(this.form.startDate) : null,
				endDate: this.form.endDate ? this.formatDateForApi(this.form.endDate) : null,
				cancellationPeriod: this.form.cancellationPeriod.trim(),
				contractType: this.form.contractType,
				renewalPeriod: this.form.renewalPeriod.trim() || null,
				cost: this.form.cost || null,
				currency: this.form.currency,
				costInterval: this.form.costInterval,
				contractFolder: this.form.contractFolder.trim() || null,
				mainDocument: this.form.mainDocument.trim() || null,
				reminderEnabled: this.form.reminderEnabled,
				reminderDays: this.form.reminderDays ? parseInt(this.form.reminderDays, 10) : null,
				notes: this.form.notes.trim() || null,
			}
		},
		formatDateForApi(date) {
			const d = new Date(date)
			return d.toISOString().split('T')[0]
		},
		handleSubmit() {
			if (!this.isValid) return
			this.$emit('submit', this.formToPayload())
		},
	},
}
</script>

<style scoped lang="scss">
.contract-form {
	padding: 20px;
	max-height: 70vh;
	overflow-y: auto;
}

.form-section {
	margin-bottom: 24px;

	h3 {
		margin-bottom: 12px;
		font-size: 14px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
}

.form-row {
	margin-bottom: 16px;

	&--half {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
	}

	&--thirds {
		display: grid;
		grid-template-columns: 2fr 1fr 1fr;
		gap: 16px;
	}
}

.form-label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}

.form-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}
</style>
