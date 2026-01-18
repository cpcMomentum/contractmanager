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
						<label class="form-label">{{ t('contractmanager', 'Vertragsbezeichnung') + ' *' }}</label>
						<NcTextField :value.sync="form.name"
							:required="true"
							:placeholder="t('contractmanager', 'z.B. Microsoft 365 Business')" />
					</div>

					<div class="form-row">
						<label class="form-label">{{ t('contractmanager', 'Vertragspartner') + ' *' }}</label>
						<NcTextField :value.sync="form.vendor"
							:required="true"
							:placeholder="t('contractmanager', 'z.B. Microsoft')" />
					</div>

					<div class="form-row form-row--half">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Kategorie') }}</label>
							<NcSelect v-model="form.categoryId"
								:options="categoryOptions"
								:placeholder="t('contractmanager', 'Kategorie wählen')"
								label="label"
								track-by="value"
								:reduce="option => option.value" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Status') }}</label>
							<NcSelect v-model="form.contractStatus"
								:options="statusOptions"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>
				</div>

				<!-- Dates -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Laufzeit') }}</h3>

					<div class="form-row form-row--dates">
						<div class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Startdatum') + ' *' }}</label>
							<NcTextField :value.sync="form.startDateFormatted"
								:placeholder="t('contractmanager', 'TT.MM.JJJJ')"
								@blur="parseStartDate" />
						</div>
						<div class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Enddatum') + ' *' }}</label>
							<NcTextField :value.sync="form.endDateFormatted"
								:placeholder="t('contractmanager', 'TT.MM.JJJJ')"
								@blur="parseEndDate" />
						</div>
						<div class="field-type">
							<label class="form-label">{{ t('contractmanager', 'Vertragstyp') + ' *' }}</label>
							<NcSelect v-model="form.contractType"
								:options="contractTypeOptions"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>

					<div class="form-row form-row--cancellation">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Kündigungsfrist') + ' *' }}</label>
							<div class="period-fields">
								<NcTextField :value.sync="form.cancellationPeriodValue"
									type="number"
									min="1"
									:required="true"
									class="period-number" />
								<NcSelect v-model="form.cancellationPeriodUnit"
									:options="periodUnitOptions"
									label="label"
									track-by="value"
									:reduce="option => option.value"
									:clearable="false"
									class="period-unit" />
							</div>
						</div>
						<div v-if="calculatedCancellationDeadline" class="field-deadline">
							<label class="form-label">{{ t('contractmanager', 'Kündigen bis') }}</label>
							<NcTextField :value="calculatedCancellationDeadline"
								:disabled="true"
								class="deadline-field" />
						</div>
					</div>

					<div v-if="form.contractType === 'auto_renewal'" class="form-row">
						<label class="form-label">{{ t('contractmanager', 'Verlängerungsintervall') }}</label>
						<div class="period-fields">
							<NcTextField :value.sync="form.renewalPeriodValue"
								type="number"
								min="1"
								class="period-number" />
							<NcSelect v-model="form.renewalPeriodUnit"
								:options="periodUnitOptions"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false"
								class="period-unit" />
						</div>
					</div>
				</div>

				<!-- Costs -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Kosten') }}</h3>

					<div class="form-row form-row--half">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Betrag (netto)') }}</label>
							<NcTextField :value.sync="form.cost"
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
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>
				</div>

				<!-- Documents -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Dokumente') }}</h3>

					<div class="form-row form-row--half">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Vertragsordner') }}</label>
							<div class="document-buttons">
								<NcButton :type="form.contractFolder ? 'primary' : 'secondary'"
									:title="form.contractFolder || t('contractmanager', 'Kein Ordner ausgewählt')"
									@click="form.contractFolder ? openInNextcloud(form.contractFolder) : openFolderPicker()">
									<template #icon>
										<Folder :size="20" />
									</template>
									{{ form.contractFolder ? t('contractmanager', 'Öffnen') : t('contractmanager', 'Wählen') }}
								</NcButton>
								<NcButton v-if="form.contractFolder"
									type="secondary"
									@click="openFolderPicker">
									{{ t('contractmanager', 'Ändern') }}
								</NcButton>
								<NcButton v-if="form.contractFolder"
									type="tertiary"
									:title="t('contractmanager', 'Entfernen')"
									@click="form.contractFolder = ''">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Hauptvertragsdatei') }}</label>
							<div class="document-buttons">
								<NcButton :type="form.mainDocument ? 'primary' : 'secondary'"
									:title="form.mainDocument || t('contractmanager', 'Keine Datei ausgewählt')"
									@click="form.mainDocument ? openInNextcloud(form.mainDocument) : openFilePicker()">
									<template #icon>
										<File :size="20" />
									</template>
									{{ form.mainDocument ? t('contractmanager', 'Öffnen') : t('contractmanager', 'Wählen') }}
								</NcButton>
								<NcButton v-if="form.mainDocument"
									type="secondary"
									@click="openFilePicker">
									{{ t('contractmanager', 'Ändern') }}
								</NcButton>
								<NcButton v-if="form.mainDocument"
									type="tertiary"
									:title="t('contractmanager', 'Entfernen')"
									@click="form.mainDocument = ''">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
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
						<NcTextArea :value.sync="form.notes"
							:label="t('contractmanager', 'Zusätzliche Notizen')"
							:placeholder="t('contractmanager', 'Zusätzliche Notizen...')"
							:maxlength="5000"
							resize="vertical"
							rows="4" />
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
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Folder from 'vue-material-design-icons/Folder.vue'
import File from 'vue-material-design-icons/File.vue'
import Close from 'vue-material-design-icons/Close.vue'
import { generateUrl } from '@nextcloud/router'
import { formatDate, formatDateForInput } from '../utils/dateUtils.js'
import { parsePeriod, calculateCancellationDeadline } from '../utils/periodUtils.js'

export default {
	name: 'ContractForm',
	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcTextArea,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		Folder,
		File,
		Close,
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
				&& this.form.cancellationPeriodValue !== ''
				&& this.form.cancellationPeriodUnit !== null
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
		periodUnitOptions() {
			return [
				{ value: 'days', label: t('contractmanager', 'Tage') },
				{ value: 'weeks', label: t('contractmanager', 'Wochen') },
				{ value: 'months', label: t('contractmanager', 'Monate') },
				{ value: 'years', label: t('contractmanager', 'Jahre') },
			]
		},
		statusOptions() {
			return [
				{ value: 'active', label: t('contractmanager', 'Laufend') },
				{ value: 'cancelled', label: t('contractmanager', 'Gekündigt') },
				{ value: 'ended', label: t('contractmanager', 'Beendet') },
			]
		},
		calculatedCancellationDeadline() {
			if (!this.form.endDate || !this.form.cancellationPeriodValue || !this.form.cancellationPeriodUnit) {
				return null
			}
			const periodString = `${this.form.cancellationPeriodValue} ${this.form.cancellationPeriodUnit}`
			const deadline = calculateCancellationDeadline(this.form.endDate, periodString)
			return deadline ? formatDate(deadline) : null
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
				contractStatus: 'active',
				startDate: null,
				endDate: null,
				startDateFormatted: '',
				endDateFormatted: '',
				cancellationPeriodValue: '1',
				cancellationPeriodUnit: 'months',
				contractType: 'auto_renewal',
				renewalPeriodValue: '1',
				renewalPeriodUnit: 'months',
				cost: '',
				currency: 'EUR',
				contractFolder: '',
				mainDocument: '',
				reminderEnabled: true,
				reminderDays: '',
				notes: '',
			}
		},
		formatDateDisplay(date) {
			return formatDate(date)
		},
		parseDateInput(value) {
			if (!value) return null
			const parts = value.split('.')
			if (parts.length !== 3) return null
			const day = parseInt(parts[0], 10)
			const month = parseInt(parts[1], 10)
			const year = parseInt(parts[2], 10)
			if (isNaN(day) || isNaN(month) || isNaN(year)) return null
			return new Date(year, month - 1, day)
		},
		parseStartDate() {
			const date = this.parseDateInput(this.form.startDateFormatted)
			this.form.startDate = date
			if (date) {
				this.form.startDateFormatted = this.formatDateDisplay(date)
			}
		},
		parseEndDate() {
			const date = this.parseDateInput(this.form.endDateFormatted)
			this.form.endDate = date
			if (date) {
				this.form.endDateFormatted = this.formatDateDisplay(date)
			}
		},
		parsePeriodForForm(periodString, defaultValue = '') {
			// Parse format like "3 months" into value and unit for form fields
			if (!periodString) return { value: defaultValue, unit: 'months' }
			const parsed = parsePeriod(periodString)
			if (parsed) {
				// Normalize to plural form for select options
				let unit = parsed.unit
				if (unit === 'day') unit = 'days'
				if (unit === 'week') unit = 'weeks'
				if (unit === 'month') unit = 'months'
				if (unit === 'year') unit = 'years'
				return { value: String(parsed.value), unit }
			}
			// Fallback: try to extract just the number
			const numMatch = periodString.match(/(\d+)/)
			return { value: numMatch ? numMatch[1] : '', unit: 'months' }
		},
		formatPeriod(value, unit) {
			if (!value) return null
			return `${value} ${unit}`
		},
		contractToForm(contract) {
			const cancellation = this.parsePeriodForForm(contract.cancellationPeriod, '1')
			const renewal = this.parsePeriodForForm(contract.renewalPeriod, '1')
			const startDate = contract.startDate ? new Date(contract.startDate) : null
			const endDate = contract.endDate ? new Date(contract.endDate) : null
			return {
				name: contract.name || '',
				vendor: contract.vendor || '',
				categoryId: contract.categoryId,
				contractStatus: contract.status || 'active',
				startDate,
				endDate,
				startDateFormatted: this.formatDateDisplay(startDate),
				endDateFormatted: this.formatDateDisplay(endDate),
				cancellationPeriodValue: cancellation.value,
				cancellationPeriodUnit: cancellation.unit,
				contractType: contract.contractType || 'fixed',
				renewalPeriodValue: renewal.value,
				renewalPeriodUnit: renewal.unit,
				cost: contract.cost || '',
				currency: contract.currency || 'EUR',
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
				status: this.form.contractStatus,
				startDate: this.form.startDate ? this.formatDateForApi(this.form.startDate) : null,
				endDate: this.form.endDate ? this.formatDateForApi(this.form.endDate) : null,
				cancellationPeriod: this.formatPeriod(this.form.cancellationPeriodValue, this.form.cancellationPeriodUnit),
				contractType: this.form.contractType,
				renewalPeriod: this.formatPeriod(this.form.renewalPeriodValue, this.form.renewalPeriodUnit),
				cost: this.form.cost || null,
				currency: this.form.currency,
				contractFolder: this.form.contractFolder.trim() || null,
				mainDocument: this.form.mainDocument.trim() || null,
				reminderEnabled: this.form.reminderEnabled,
				reminderDays: this.form.reminderDays ? parseInt(this.form.reminderDays, 10) : null,
				notes: this.form.notes.trim() || null,
			}
		},
		formatDateForApi(date) {
			return formatDateForInput(date)
		},
		async openFolderPicker() {
			try {
				const picker = getFilePickerBuilder(t('contractmanager', 'Vertragsordner wählen'))
					.setMultiSelect(false)
					.setType(1) // Directories only
					.allowDirectories()
					.build()
				const path = await picker.pick()
				if (path) {
					this.form.contractFolder = path
				}
			} catch (e) {
				// User cancelled - do nothing
				console.debug('Folder picker cancelled', e)
			}
		},
		async openFilePicker() {
			try {
				const picker = getFilePickerBuilder(t('contractmanager', 'Vertragsdatei wählen'))
					.setMultiSelect(false)
					.setType(1) // Files
					.setMimeTypeFilter(['application/pdf', 'image/*', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
					.build()
				const path = await picker.pick()
				if (path) {
					this.form.mainDocument = path
				}
			} catch (e) {
				// User cancelled - do nothing
				console.debug('File picker cancelled', e)
			}
		},
		handleSubmit() {
			if (!this.isValid) return
			this.$emit('submit', this.formToPayload())
		},
		openInNextcloud(path) {
			// Check if path is a file (has extension) or folder
			const isFile = path.includes('.') && !path.endsWith('/')
			let filesUrl

			if (isFile) {
				// For files: open directly in viewer by navigating to parent dir with file selected
				const parentDir = path.substring(0, path.lastIndexOf('/')) || '/'
				const fileName = path.substring(path.lastIndexOf('/') + 1)
				filesUrl = generateUrl('/apps/files/?dir={dir}&scrollto={file}&openfile={file}', {
					dir: parentDir,
					file: fileName,
				})
			} else {
				// For folders: just open the folder
				filesUrl = generateUrl('/apps/files/?dir={dir}', {
					dir: path,
				})
			}
			window.open(filesUrl, '_blank')
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
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--thirds {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr;
		gap: 16px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--dates {
		display: grid;
		grid-template-columns: 130px 130px 1fr;
		gap: 16px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}
}

.field-date {
	max-width: 130px;

	:deep(.input-field) {
		max-width: 130px;
	}
}

.field-type {
	min-width: 200px;
}

.form-label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
	height: 20px;
}

.form-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

// Period fields (Kündigungsfrist, Verlängerungsintervall)
.period-fields {
	display: flex;
	gap: 8px;
	align-items: center;
	max-width: 280px;
	height: 44px;
}

.period-number {
	width: 70px;
	flex-shrink: 0;

	:deep(.input-field) {
		width: 70px;
	}
}

.period-unit {
	flex: 1;
	min-width: 120px;
}

.form-row--cancellation {
	display: flex;
	gap: 16px;
	align-items: flex-start;

	> div {
		display: flex;
		flex-direction: column;
	}
}

.field-deadline {
	min-width: 140px;
	margin-left: 60px;

	:deep(.input-field) {
		max-width: 140px;

		input {
			color: var(--color-main-text) !important;
			-webkit-text-fill-color: var(--color-main-text) !important;
			opacity: 1 !important;
		}
	}
}

// Document buttons (compact)
.document-buttons {
	display: flex;
	gap: 8px;
	align-items: center;
	height: 44px;
}
</style>
