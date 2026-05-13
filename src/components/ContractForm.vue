<template>
	<NcModal :show="show"
		:title="readOnly ? t('contractmanager', 'Vertragsdetails') : (isEdit ? t('contractmanager', 'Vertrag bearbeiten') : t('contractmanager', 'Neuer Vertrag'))"
		size="large"
		@close="$emit('close')">
		<div class="contract-form">
			<form @submit.prevent="handleSubmit">
				<!-- AI Extraction -->
				<div v-if="aiAvailable && !isEdit && !readOnly" class="form-section ai-section">
					<div class="ai-extract-row">
						<NcButton type="secondary"
							:disabled="extracting"
							@click="analyzeDocument">
							<template #icon>
								<NcLoadingIcon v-if="extracting" :size="20" />
								<FileSearchIcon v-else :size="20" />
							</template>
							{{ extracting ? t('contractmanager', 'Analysiere...') : t('contractmanager', 'Dokument analysieren') }}
						</NcButton>
						<span class="ai-hint">{{ t('contractmanager', 'PDF-Vertrag analysieren und Felder automatisch ausfüllen') }}</span>
					</div>
					<p v-if="extractionNotes" class="extraction-notes">
						{{ extractionNotes }}
					</p>
				</div>

				<!-- Basic Info -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Grunddaten') }}</h3>

					<div class="form-row form-row--half">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Vertragsbezeichnung') + ' *' }}</label>
							<NcTextField :value.sync="form.name"
								:disabled="readOnly"
								:placeholder="t('contractmanager', 'z.B. Microsoft 365 Business')" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Vertragspartner') + ' *' }}</label>
							<NcTextField :value.sync="form.vendor"
								:disabled="readOnly"
								:placeholder="t('contractmanager', 'z.B. Microsoft')" />
						</div>
					</div>

					<div class="form-row form-row--half">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Kategorie') }}</label>
							<NcSelect v-model="form.categoryId"
								:options="categoryOptions"
								:placeholder="t('contractmanager', 'Kategorie wählen')"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Status') }}</label>
							<NcSelect v-model="form.contractStatus"
								:options="statusOptions"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>

					<div v-if="hasCustomFields" class="form-row form-row--custom">
						<div v-if="customFieldLabels.customFieldLabel1">
							<label class="form-label">{{ customFieldLabels.customFieldLabel1 }}</label>
							<NcTextField :value.sync="form.customField1"
								:disabled="readOnly" />
						</div>
						<div v-if="customFieldLabels.customFieldLabel2">
							<label class="form-label">{{ customFieldLabels.customFieldLabel2 }}</label>
							<NcTextField :value.sync="form.customField2"
								:disabled="readOnly" />
						</div>
						<div v-if="customFieldLabels.customFieldLabel3">
							<label class="form-label">{{ customFieldLabels.customFieldLabel3 }}</label>
							<NcTextField :value.sync="form.customField3"
								:disabled="readOnly" />
						</div>
					</div>
				</div>

				<!-- Dates -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Laufzeit') }}</h3>

					<div :class="['form-row', showCancellationDeadline ? 'form-row--dates-extended' : 'form-row--dates']">
						<div class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Startdatum') + ' *' }}</label>
							<NcTextField :value.sync="form.startDateFormatted"
								:placeholder="t('contractmanager', 'TT.MM.JJJJ')"
								:disabled="readOnly"
								@blur="parseStartDate" />
						</div>
						<div class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Enddatum') }}</label>
							<NcTextField :value.sync="form.endDateFormatted"
								:placeholder="t('contractmanager', 'TT.MM.JJJJ oder leer für unbefristet')"
								:disabled="readOnly"
								@blur="parseEndDate"
								@input="onEndDateInput" />
						</div>
						<div v-if="showCancellationDeadline" class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Kündigen bis') }}</label>
							<NcTextField :value="calculatedCancellationDeadline"
								:disabled="true"
								class="deadline-field" />
						</div>
						<div class="field-type">
							<label class="form-label">{{ t('contractmanager', 'Vertragstyp') + ' *' }}</label>
							<NcSelect v-model="form.contractType"
								:options="contractTypeOptions"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>

					<NcNoteCard v-if="dateError" type="error">
						{{ dateError }}
					</NcNoteCard>

					<div v-if="form.contractType === 'auto_renewal'" class="form-row form-row--periods">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Kündigungsfrist') }}</label>
							<NcTextField :value.sync="form.cancellationPeriodValue"
								type="number"
								min="1"
								:disabled="readOnly" />
						</div>
						<div>
							<label class="form-label">&nbsp;</label>
							<NcSelect v-model="form.cancellationPeriodUnit"
								:options="periodUnitOptions"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Verlängerung') }}</label>
							<NcTextField :value.sync="form.renewalPeriodValue"
								type="number"
								min="1"
								:disabled="readOnly" />
						</div>
						<div>
							<label class="form-label">&nbsp;</label>
							<NcSelect v-model="form.renewalPeriodUnit"
								:options="periodUnitOptions"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
						</div>
					</div>
				</div>

				<!-- Costs / Documents / Reminder -->
				<div class="form-section">
					<div class="form-row form-row--triple">
						<!-- Kosten -->
						<div class="triple-column">
							<h3>{{ t('contractmanager', 'Kosten') }}</h3>
							<div class="cost-top">
								<div class="field-cost">
									<label class="form-label">
										{{ form.amountType === 'brutto' ? t('contractmanager', 'Betrag (brutto)') : t('contractmanager', 'Betrag (netto)') }}
									</label>
									<NcTextField :value.sync="form.cost"
										type="number"
										step="0.01"
										:disabled="readOnly"
										:placeholder="t('contractmanager', '0.00')" />
									<div class="amount-type-toggle">
										<NcCheckboxRadioSwitch :checked.sync="form.amountType"
											value="netto"
											name="amountType"
											type="radio"
											:disabled="readOnly">
											{{ t('contractmanager', 'Netto') }}
										</NcCheckboxRadioSwitch>
										<NcCheckboxRadioSwitch :checked.sync="form.amountType"
											value="brutto"
											name="amountType"
											type="radio"
											:disabled="readOnly">
											{{ t('contractmanager', 'Brutto') }}
										</NcCheckboxRadioSwitch>
									</div>
								</div>
								<div class="field-currency">
									<label class="form-label">{{ t('contractmanager', 'Währung') }}</label>
									<NcSelect v-model="form.currency"
										:options="currencyOptions"
										:disabled="readOnly"
										label="label"
										track-by="value"
										:reduce="option => option.value"
										:clearable="false" />
								</div>
							</div>
							<div class="cost-bottom">
								<label class="form-label">{{ t('contractmanager', 'Zahlweise') }}</label>
								<NcSelect v-model="form.costInterval"
									:options="costIntervalOptions"
									:disabled="readOnly"
									label="label"
									track-by="value"
									:reduce="option => option.value"
									:clearable="false" />
							</div>
						</div>

						<!-- Dokumente -->
						<div class="triple-column">
							<h3>{{ t('contractmanager', 'Dokumente') }}</h3>
							<div class="doc-row">
								<label class="form-label">{{ t('contractmanager', 'Vertragsordner') }}</label>
								<span v-if="form.contractFolder" class="selected-path" :title="form.contractFolder">
									{{ form.contractFolder.split('/').filter(s => s).pop() }}
								</span>
								<div class="document-buttons document-buttons--compact">
									<span v-if="readOnly && !form.contractFolder" class="no-document-text">
										{{ t('contractmanager', 'Kein Ordner') }}
									</span>
									<NcButton v-else-if="form.contractFolder"
										type="primary"
										@click="openInNextcloud(form.contractFolder)">
										<template #icon>
											<Folder :size="20" />
										</template>
										{{ t('contractmanager', 'Öffnen') }}
									</NcButton>
									<NcButton v-else
										type="secondary"
										@click="openFolderPicker">
										<template #icon>
											<Folder :size="20" />
										</template>
										{{ t('contractmanager', 'Wählen') }}
									</NcButton>
									<NcButton v-if="form.contractFolder && !readOnly"
										type="secondary"
										@click="openFolderPicker">
										{{ t('contractmanager', 'Ändern') }}
									</NcButton>
									<NcButton v-if="form.contractFolder && !readOnly"
										type="tertiary"
										:title="t('contractmanager', 'Entfernen')"
										@click="form.contractFolder = ''">
										<template #icon>
											<Close :size="20" />
										</template>
									</NcButton>
								</div>
							</div>
							<div class="doc-row">
								<label class="form-label">{{ t('contractmanager', 'Vertragsdokument') }}</label>
								<span v-if="form.mainDocument" class="selected-path" :title="form.mainDocument">
									<OpenInNewIcon v-if="isExternalDocument" :size="14" class="external-icon" />
									{{ documentDisplayName }}
								</span>
								<div class="document-buttons document-buttons--compact">
									<span v-if="readOnly && !form.mainDocument" class="no-document-text">
										{{ t('contractmanager', 'Kein Dokument') }}
									</span>
									<NcButton v-else-if="form.mainDocument"
										type="primary"
										@click="openDocument(form.mainDocument)">
										<template #icon>
											<OpenInNewIcon v-if="isExternalDocument" :size="20" />
											<File v-else :size="20" />
										</template>
										{{ t('contractmanager', 'Öffnen') }}
									</NcButton>
									<template v-if="!form.mainDocument && !readOnly">
										<NcButton type="secondary"
											@click="openFilePicker">
											<template #icon>
												<File :size="20" />
											</template>
											{{ t('contractmanager', 'Datei wählen') }}
										</NcButton>
										<NcButton type="tertiary"
											@click="showUrlInput = !showUrlInput">
											<template #icon>
												<OpenInNewIcon :size="20" />
											</template>
											{{ t('contractmanager', 'Externer Link') }}
										</NcButton>
									</template>
									<NcButton v-if="form.mainDocument && !readOnly"
										type="secondary"
										@click="openFilePicker">
										{{ t('contractmanager', 'Ändern') }}
									</NcButton>
									<NcButton v-if="form.mainDocument && !readOnly"
										type="tertiary"
										:title="t('contractmanager', 'Entfernen')"
										@click="form.mainDocument = ''">
										<template #icon>
											<Close :size="20" />
										</template>
									</NcButton>
								</div>
								<div v-if="showUrlInput && !readOnly && !form.mainDocument" class="url-input-row">
									<NcTextField :value.sync="urlInput"
										:placeholder="t('contractmanager', 'https://...')"
										@keydown.enter.native.prevent="addExternalUrl" />
									<NcButton type="primary" @click="addExternalUrl">
										{{ t('contractmanager', 'Hinzufügen') }}
									</NcButton>
								</div>
							</div>
						</div>

						<!-- Erinnerung -->
						<div class="triple-column">
							<h3>{{ t('contractmanager', 'Erinnerung') }}</h3>
							<NcNoteCard v-if="!form.endDate" type="warning">
								{{ t('contractmanager', 'Erinnerungen sind nur mit gesetztem Enddatum möglich.') }}
							</NcNoteCard>
							<template v-else>
								<NcCheckboxRadioSwitch :checked.sync="form.reminderEnabled" :disabled="readOnly">
									{{ t('contractmanager', 'Erinnerung aktivieren') }}
								</NcCheckboxRadioSwitch>
								<div v-if="form.reminderEnabled" class="reminder-days">
									<NcTextField :label="t('contractmanager', 'X Tage vorher')"
										:value.sync="form.reminderDays"
										type="number"
										:disabled="readOnly"
										:placeholder="t('contractmanager', 'Standard')" />
								</div>
							</template>
						</div>
					</div>
				</div>

				<!-- Notes -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Notizen') }}</h3>

					<div class="form-row">
						<div v-if="readOnly && form.notes"
							class="notes-readonly"
							v-html="notesWithLinks" />
						<NcTextArea v-else
							:value.sync="form.notes"
							:label="t('contractmanager', 'Zusätzliche Notizen')"
							:placeholder="t('contractmanager', 'Zusätzliche Notizen...')"
							:maxlength="5000"
							resize="vertical"
							rows="4" />
					</div>
				</div>

				<!-- Privacy -->
				<div class="form-section">
					<div class="form-row">
						<NcCheckboxRadioSwitch :checked.sync="form.isPrivate" :disabled="readOnly">
							<template #icon>
								<LockIcon v-if="form.isPrivate" :size="20" />
								<LockOpenVariantIcon v-else :size="20" />
							</template>
							{{ form.isPrivate
								? t('contractmanager', 'Privater Vertrag (nur für mich sichtbar)')
								: t('contractmanager', 'Öffentlicher Vertrag (für alle Berechtigten sichtbar)') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<!-- Actions -->
				<div class="form-actions">
					<NcButton v-if="readOnly" type="primary" @click="$emit('close')">
						{{ t('contractmanager', 'Schließen') }}
					</NcButton>
					<template v-else>
						<NcButton type="tertiary" @click="$emit('close')">
							{{ t('contractmanager', 'Abbrechen') }}
						</NcButton>
						<NcButton type="primary" native-type="submit" :disabled="!isValid || loading">
							<template #icon>
								<NcLoadingIcon v-if="loading" :size="20" />
							</template>
							{{ isEdit ? t('contractmanager', 'Speichern') : t('contractmanager', 'Erstellen') }}
						</NcButton>
					</template>
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
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
import Folder from 'vue-material-design-icons/Folder.vue'
import File from 'vue-material-design-icons/File.vue'
import Close from 'vue-material-design-icons/Close.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import LockOpenVariantIcon from 'vue-material-design-icons/LockOpenVariant.vue'
import FileSearchIcon from 'vue-material-design-icons/FileSearch.vue'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { formatDate, formatDateForInput } from '../utils/dateUtils.js'
import { parsePeriod, calculateCancellationDeadline } from '../utils/periodUtils.js'
import { isUrl, isInternalUrl, getDisplayName } from '../utils/documentUtils.js'
import ExtractionService from '../services/ExtractionService.js'
import SettingsService from '../services/SettingsService.js'
import { showSuccess, showError, showWarning } from '@nextcloud/dialogs'

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
		NcNoteCard,
		Folder,
		File,
		Close,
		OpenInNewIcon,
		LockIcon,
		LockOpenVariantIcon,
		FileSearchIcon,
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
		readOnly: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'submit'],
	data() {
		const prefs = loadState('contractmanager', 'userPreferences', { defaultAmountType: 'netto' })
		const defaultAmountType = prefs.defaultAmountType || 'netto'
		return {
			defaultAmountType,
			form: this.getInitialForm(defaultAmountType),
			aiAvailable: false,
			extracting: false,
			extractionNotes: null,
			showUrlInput: false,
			urlInput: '',
			customFieldLabels: {
				customFieldLabel1: '',
				customFieldLabel2: '',
				customFieldLabel3: '',
			},
		}
	},
	computed: {
		...mapGetters('categories', ['allCategories']),
		notesWithLinks() {
			if (!this.form.notes) return ''
			const escaped = this.form.notes
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
			return escaped
				.replace(/https?:\/\/[^\s<>"{}|\\^`[\]]+/g, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`)
				.replace(/\n/g, '<br>')
		},
		isEdit() {
			return this.contract !== null && this.contract.id != null
		},
		documentDisplayName() {
			return getDisplayName(this.form.mainDocument)
		},
		isExternalDocument() {
			return isUrl(this.form.mainDocument) && !isInternalUrl(this.form.mainDocument)
		},
		isValid() {
			return (
				this.form.name.trim() !== ''
				&& this.form.vendor.trim() !== ''
				&& this.form.startDate !== null
				&& !this.dateError
				&& this.form.contractType !== null
				&& (this.form.contractType !== 'auto_renewal' || (
					this.form.cancellationPeriodValue !== ''
					&& this.form.cancellationPeriodUnit !== null
				))
			)
		},
		dateError() {
			if (this.form.startDateFormatted && !this.form.startDate) {
				return t('contractmanager', 'Startdatum: Bitte gültiges Datum im Format TT.MM.JJJJ eingeben')
			}
			if (this.form.endDateFormatted && !this.form.endDate) {
				return t('contractmanager', 'Enddatum: Bitte gültiges Datum im Format TT.MM.JJJJ eingeben')
			}
			if (this.form.startDate && this.form.endDate && this.form.startDate >= this.form.endDate) {
				return t('contractmanager', 'Enddatum muss nach dem Startdatum liegen')
			}
			return null
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
				{ value: 'unlimited', label: t('contractmanager', 'Unbefristet') },
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
		costIntervalOptions() {
			return [
				{ value: 'monthly', label: t('contractmanager', 'Monatlich') },
				{ value: 'quarterly', label: t('contractmanager', 'Quartalsweise') },
				{ value: 'semi_annual', label: t('contractmanager', 'Halbjährlich') },
				{ value: 'yearly', label: t('contractmanager', 'Jährlich') },
				{ value: 'one_time', label: t('contractmanager', 'Einmalig') },
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
		showCancellationDeadline() {
			return this.form.contractType === 'auto_renewal' && this.calculatedCancellationDeadline
		},
		hasCustomFields() {
			return this.customFieldLabels.customFieldLabel1
				|| this.customFieldLabels.customFieldLabel2
				|| this.customFieldLabels.customFieldLabel3
		},
		calculatedCancellationDeadline() {
			if (!this.form.endDate || !this.form.cancellationPeriodValue || !this.form.cancellationPeriodUnit) {
				return null
			}
			const periodString = `${this.form.cancellationPeriodValue} ${this.form.cancellationPeriodUnit}`
			const renewalPeriod = this.form.renewalPeriodValue && this.form.renewalPeriodUnit
				? `${this.form.renewalPeriodValue} ${this.form.renewalPeriodUnit}`
				: null
			const deadline = calculateCancellationDeadline(this.form.endDate, periodString, this.form.contractType, renewalPeriod)
			return deadline ? formatDate(deadline) : null
		},
	},
	async created() {
		try {
			const status = await ExtractionService.getStatus()
			this.aiAvailable = status.configured
		} catch (e) {
			this.aiAvailable = false
		}
		try {
			const settings = await SettingsService.getUserSettings()
			if (settings.customFieldLabels) {
				this.customFieldLabels = settings.customFieldLabels
			}
		} catch (e) {
			console.debug('Failed to load custom field labels:', e)
		}
	},
	watch: {
		show(newVal) {
			if (newVal) {
				this.form = this.getInitialForm(this.defaultAmountType)
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
		'form.endDate'(newVal) {
			if (newVal === null) {
				this.form.reminderEnabled = false
			} else {
				this.form.reminderEnabled = true
			}
		},
	},
	methods: {
		getInitialForm(defaultAmountType = 'netto') {
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
				costInterval: 'monthly',
				amountType: defaultAmountType,
				contractFolder: '',
				mainDocument: '',
				reminderEnabled: true,
				reminderDays: '',
				notes: '',
				isPrivate: false,
				customField1: '',
				customField2: '',
				customField3: '',
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
			if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900) return null
			return new Date(year, month - 1, day)
		},
		parseStartDate() {
			const date = this.parseDateInput(this.form.startDateFormatted)
			this.form.startDate = date
			if (date) {
				this.form.startDateFormatted = this.formatDateDisplay(date)
			} else {
				this.form.startDateFormatted = ''
			}
		},
		parseEndDate() {
			const date = this.parseDateInput(this.form.endDateFormatted)
			this.form.endDate = date
			if (date) {
				this.form.endDateFormatted = this.formatDateDisplay(date)
			} else {
				this.form.endDateFormatted = ''
			}
		},
		onEndDateInput(value) {
			if (!value || value.trim() === '') {
				this.form.endDate = null
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
				costInterval: contract.costInterval || 'monthly',
				contractFolder: contract.contractFolder || '',
				mainDocument: contract.mainDocument || '',
				reminderEnabled: contract.reminderEnabled !== false,
				reminderDays: contract.reminderDays ? String(contract.reminderDays) : '',
				notes: contract.notes || '',
				amountType: contract.amountType || 'netto',
				isPrivate: contract.isPrivate === true || contract.isPrivate === 1,
				customField1: contract.customField1 || '',
				customField2: contract.customField2 || '',
				customField3: contract.customField3 || '',
			}
		},
		formToPayload() {
			// Parse dates fresh from formatted fields to avoid stale state
			const startDate = this.parseDateInput(this.form.startDateFormatted)
			const endDate = this.parseDateInput(this.form.endDateFormatted)
			return {
				name: this.form.name.trim(),
				vendor: this.form.vendor.trim(),
				categoryId: this.form.categoryId,
				status: this.form.contractStatus,
				startDate: startDate ? this.formatDateForApi(startDate) : null,
				endDate: endDate ? this.formatDateForApi(endDate) : null,
				cancellationPeriod: this.form.contractType === 'auto_renewal'
					? this.formatPeriod(this.form.cancellationPeriodValue, this.form.cancellationPeriodUnit)
					: null,
				contractType: this.form.contractType,
				renewalPeriod: this.form.contractType === 'auto_renewal'
					? this.formatPeriod(this.form.renewalPeriodValue, this.form.renewalPeriodUnit)
					: null,
				cost: this.form.cost || null,
				currency: this.form.currency,
				costInterval: this.form.costInterval || null,
				contractFolder: this.form.contractFolder.trim() || null,
				mainDocument: this.form.mainDocument.trim() || null,
				reminderEnabled: this.form.reminderEnabled,
				reminderDays: this.form.reminderDays ? parseInt(this.form.reminderDays, 10) : null,
				notes: this.form.notes.trim() || null,
				amountType: this.form.amountType,
				isPrivate: this.form.isPrivate,
				customField1: this.form.customField1.trim() || null,
				customField2: this.form.customField2.trim() || null,
				customField3: this.form.customField3.trim() || null,
			}
		},
		formatDateForApi(date) {
			return formatDateForInput(date)
		},
		async openFolderPicker() {
			try {
				const picker = getFilePickerBuilder(t('contractmanager', 'Vertragsordner wählen'))
					.setMultiSelect(false)
					.setType(1)
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
				const picker = getFilePickerBuilder(t('contractmanager', 'Vertragsdokument wählen'))
					.setMultiSelect(false)
					.setType(1)
					.build()
				const path = await picker.pick()
				if (path) {
					this.form.mainDocument = path
				}
			} catch (e) {
				console.debug('File picker cancelled', e)
			}
		},
		addExternalUrl() {
			const url = this.urlInput.trim()
			if (!url) return
			if (!isUrl(url)) {
				showError(t('contractmanager', 'Bitte eine gültige URL eingeben (https://...)'))
				return
			}
			this.form.mainDocument = url
			this.urlInput = ''
			this.showUrlInput = false
		},
		async analyzeDocument() {
			try {
				const picker = getFilePickerBuilder(t('contractmanager', 'PDF-Vertrag auswählen'))
					.setMultiSelect(false)
					.setType(1)
					.setMimeTypeFilter(['application/pdf'])
					.build()
				const path = await picker.pick()
				if (!path) return

				this.extracting = true
				this.extractionNotes = null

				const result = await ExtractionService.extractFromPdf(path)

				if (result.success && result.data) {
					this.applyExtractedData(result.data)

					if (result.data.confidence < 0.5) {
						showWarning(t('contractmanager', 'Niedrige Erkennungsgenauigkeit – bitte alle Felder prüfen'))
					} else {
						showSuccess(t('contractmanager', 'Vertragsdaten erfolgreich erkannt'))
					}

					if (result.data.extractionNotes) {
						this.extractionNotes = result.data.extractionNotes
					}

					// Set the document as main document
					this.form.mainDocument = path
				} else {
					showError(result.error || t('contractmanager', 'Analyse fehlgeschlagen'))
				}
			} catch (e) {
				console.error('Document analysis failed:', e)
				const message = e.response?.data?.error || t('contractmanager', 'Analyse fehlgeschlagen')
				showError(message)
			} finally {
				this.extracting = false
			}
		},
		applyExtractedData(data) {
			if (data.name) this.form.name = data.name
			if (data.vendor) this.form.vendor = data.vendor
			if (data.contractType) this.form.contractType = data.contractType
			if (data.currency) this.form.currency = data.currency
			if (data.cost) this.form.cost = data.cost
			if (data.startDate) {
				const start = new Date(data.startDate)
				if (!isNaN(start.getTime())) {
					this.form.startDate = start
					this.form.startDateFormatted = this.formatDateDisplay(start)
				}
			}
			if (data.endDate) {
				const end = new Date(data.endDate)
				if (!isNaN(end.getTime())) {
					this.form.endDate = end
					this.form.endDateFormatted = this.formatDateDisplay(end)
				}
			}
			if (data.cancellationPeriod) {
				const cp = this.parsePeriodForForm(data.cancellationPeriod)
				this.form.cancellationPeriodValue = cp.value
				this.form.cancellationPeriodUnit = cp.unit
			}
			if (data.renewalPeriod) {
				const rp = this.parsePeriodForForm(data.renewalPeriod)
				this.form.renewalPeriodValue = rp.value
				this.form.renewalPeriodUnit = rp.unit
			}
			if (data.contractStatus) {
				this.form.contractStatus = data.contractStatus
			}
		},
		handleSubmit() {
			if (!this.isValid) return
			this.$emit('submit', this.formToPayload())
		},
		openInNextcloud(folder) {
			if (!folder) return
			window.open(generateUrl('/apps/files/?dir={dir}', { dir: folder }), '_blank', 'noopener,noreferrer')
		},
		async openDocument(value) {
			// Externe/interne URLs: direkt im neuen Tab oeffnen
			if (isUrl(value)) {
				window.open(value, '_blank', 'noopener,noreferrer')
				return
			}
			// Nextcloud Viewer Overlay (bevorzugt, kein neuer Tab)
			if (window.OCA?.Viewer?.open) {
				OCA.Viewer.open({ path: value })
				return
			}
			// Fallback: File-ID per WebDAV holen
			try {
				const user = getCurrentUser()?.uid
				const davPath = `/remote.php/dav/files/${user}${value}`
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
			// Letzter Fallback: Files-App scrollto
			const parentDir = value.substring(0, value.lastIndexOf('/')) || '/'
			const fileName = value.substring(value.lastIndexOf('/') + 1)
			window.open(generateUrl('/apps/files/?dir={dir}&scrollto={file}', { dir: parentDir, file: fileName }), '_blank', 'noopener,noreferrer')
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
	margin-bottom: 20px;

	h3 {
		margin-bottom: 8px;
		font-size: 14px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
}

.form-row {
	margin-bottom: 12px;

	&--half {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 12px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--dates {
		display: grid;
		grid-template-columns: 120px 120px 1fr;
		gap: 12px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--custom {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 12px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--dates-extended {
		display: grid;
		grid-template-columns: 120px 120px 120px 1fr;
		gap: 12px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--triple {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr;
		gap: 20px;
		align-items: start;
	}
}

.field-date {
	max-width: 120px;

	:deep(.input-field) {
		max-width: 120px;
	}
}

.field-type {
	min-width: 140px;
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

.form-row--periods {
	display: grid;
	grid-template-columns: 55px 160px 55px 160px;
	gap: 12px;
	align-items: start;

	> div {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	:deep(.v-select.select) {
		min-width: 0 !important;
		width: 100% !important;
		margin: 0 !important;
	}

	:deep(.select) {
		min-width: 0 !important;
		width: 100% !important;
		margin: 0 !important;
	}
}

.deadline-field {
	:deep(.input-field) {
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

.document-buttons--compact {
	gap: 4px;
	height: auto;
}

.url-input-row {
	display: flex;
	gap: 8px;
	align-items: flex-end;
	margin-top: 4px;
}

.no-document-text {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.triple-column {
	display: flex;
	flex-direction: column;

	h3 {
		margin-bottom: 8px;
		font-size: 14px;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
}

.cost-top {
	display: flex;
	gap: 8px;
	margin-bottom: 8px;

	.field-cost {
		flex: 0 0 120px;
	}

	.field-currency {
		flex: 0 0 72px;

		:deep(.v-select.select) {
			min-width: 0 !important;
		}
	}
}

.cost-bottom {
	:deep(.v-select.select) {
		min-width: 0 !important;
	}
}

.doc-row {
	margin-bottom: 8px;

	&:last-child {
		margin-bottom: 0;
	}
}

.reminder-days {
	margin-top: 8px;
}

.ai-section {
	background: var(--color-primary-element-light, #e8f0fe);
	padding: 16px;
	border-radius: 8px;
	margin-bottom: 20px;
}

.ai-extract-row {
	display: flex;
	align-items: center;
	gap: 12px;
}

.ai-hint {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.notes-readonly {
	width: 100%;
	min-height: 80px;
	padding: 8px 12px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: var(--default-font-size);
	line-height: 1.5;
	color: var(--color-main-text);
	white-space: pre-wrap;
	word-break: break-word;

	a {
		color: var(--color-primary-element);
		text-decoration: underline;

		&:hover {
			text-decoration: none;
		}
	}
}

.extraction-notes {
	margin-top: 8px;
	padding: 8px 12px;
	background: var(--color-warning-hover, #fff3cd);
	border-radius: 4px;
	font-size: 13px;
	color: var(--color-warning-text, #856404);
}

.selected-path {
	display: block;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	margin-bottom: 2px;
}

</style>
