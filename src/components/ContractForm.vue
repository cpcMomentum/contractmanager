<template>
	<NcModal :show="show"
		:name="readOnly ? t('contractmanager', 'Vertragsdetails') : (isEdit ? t('contractmanager', 'Vertrag bearbeiten') : t('contractmanager', 'Neuer Vertrag'))"
		size="large"
		@close="$emit('close')">
		<div class="contract-form" :class="{ 'contract-form--readonly': readOnly }">
			<form @submit.prevent="handleSubmit">
				<!-- AI Extraction -->
				<div v-if="aiAvailable && !isEdit && !readOnly" class="form-section ai-section">
					<div class="ai-extract-row">
						<NcButton variant="secondary"
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
							<NcTextField v-model="form.name"
								:disabled="readOnly"
								:placeholder="t('contractmanager', 'z.B. Microsoft 365 Business')" />
						</div>
						<div>
							<label class="form-label">{{ t('contractmanager', 'Vertragspartner') + ' *' }}</label>
							<NcSelect v-model="vendorSelection"
								:options="vendorOptions"
								:disabled="readOnly"
								:taggable="true"
								:multiple="false"
								:close-on-select="true"
								:reduce="option => typeof option === 'string' ? option : option.label"
								label="label"
								:placeholder="t('contractmanager', 'z.B. Microsoft')"
								:create-option="text => text"
								:no-wrap="true"
								@search="onVendorSearch" />
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
							<NcTextField v-model="form.customField1"
								:disabled="readOnly" />
						</div>
						<div v-if="customFieldLabels.customFieldLabel2">
							<label class="form-label">{{ customFieldLabels.customFieldLabel2 }}</label>
							<NcTextField v-model="form.customField2"
								:disabled="readOnly" />
						</div>
						<div v-if="customFieldLabels.customFieldLabel3">
							<label class="form-label">{{ customFieldLabels.customFieldLabel3 }}</label>
							<NcTextField v-model="form.customField3"
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
							<NcTextField v-if="readOnly"
								:model-value="formatDateDisplay(form.startDate)"
								:disabled="true" />
							<NcDateTimePickerNative v-else
								:model-value="form.startDate"
								type="date"
								:label="t('contractmanager', 'Startdatum')"
								hide-label
								@update:model-value="form.startDate = $event" />
						</div>
						<div class="field-date field-date--end">
							<label class="form-label">{{ t('contractmanager', 'Enddatum') }}</label>
							<NcTextField v-if="readOnly"
								:model-value="formatDateDisplay(form.endDate) || '—'"
								:disabled="true" />
							<div v-else class="date-with-clear">
								<NcDateTimePickerNative :model-value="form.endDate"
									type="date"
									:label="t('contractmanager', 'Enddatum')"
									hide-label
									@update:model-value="form.endDate = $event" />
								<NcButton v-if="form.endDate"
									variant="tertiary"
									:title="t('contractmanager', 'Enddatum entfernen (unbefristet)')"
									@click="form.endDate = null">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<div v-if="showCancellationDeadline" class="field-date">
							<label class="form-label">{{ t('contractmanager', 'Kündigen bis') }}</label>
							<NcTextField :model-value="calculatedCancellationDeadline"
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
							<NcTextField v-model="form.cancellationPeriodValue"
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
							<NcTextField v-model="form.renewalPeriodValue"
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
					<div v-if="form.contractType === 'auto_renewal'" class="form-row">
						<div>
							<label class="form-label">{{ t('contractmanager', 'Kündigen zum') }}</label>
							<NcSelect v-model="form.cancellationDeadlineType"
								:options="cancellationDeadlineTypeOptions"
								:disabled="readOnly"
								label="label"
								track-by="value"
								:reduce="option => option.value"
								:clearable="false" />
							<p v-if="form.cancellationDeadlineType === 'month_end'" class="form-hint">
								{{ t('contractmanager', 'Zum Monatsende: Die Kündigungsfrist endet am letzten Tag des Monats.') }}
							</p>
						</div>
					</div>
				</div>

				<!-- Cancellation (#136) -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Kündigung') }}</h3>

					<div class="form-row form-row--cancellation">
						<div class="field-date field-date--end">
							<label class="form-label">{{ t('contractmanager', 'Gekündigt am') }}</label>
							<NcTextField v-if="readOnly"
								:model-value="formatDateDisplay(form.cancelledOn) || '—'"
								:disabled="true" />
							<div v-else class="date-with-clear">
								<NcDateTimePickerNative :model-value="form.cancelledOn"
									type="date"
									:label="t('contractmanager', 'Gekündigt am')"
									hide-label
									@update:model-value="onCancelledOnInput" />
								<NcButton v-if="form.cancelledOn"
									variant="tertiary"
									:title="t('contractmanager', 'Kündigung entfernen')"
									@click="onCancelledOnInput(null)">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
						<div v-if="form.cancelledOn" class="field-date field-date--end">
							<label class="form-label">{{ t('contractmanager', 'Gekündigt zum') }}</label>
							<NcTextField v-if="readOnly"
								:model-value="formatDateDisplay(form.cancelledTo) || '—'"
								:disabled="true" />
							<div v-else class="date-with-clear">
								<NcDateTimePickerNative :model-value="form.cancelledTo"
									type="date"
									:label="t('contractmanager', 'Gekündigt zum')"
									hide-label
									@update:model-value="form.cancelledTo = $event" />
								<NcButton v-if="form.cancelledTo"
									variant="tertiary"
									:title="t('contractmanager', 'Datum entfernen')"
									@click="form.cancelledTo = null">
									<template #icon>
										<Close :size="20" />
									</template>
								</NcButton>
							</div>
						</div>
					</div>

					<p v-if="!readOnly" class="field-hint">
						{{ t('contractmanager', 'Mit „Gekündigt am" wird der Vertrag am Laufzeitende automatisch beendet und archiviert. „Gekündigt zum" beendet ihn stattdessen zu diesem Datum (z. B. bei Sonderkündigung).') }}
					</p>
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
									<NcTextField v-model="form.cost"
										type="number"
										step="0.01"
										:disabled="readOnly"
										:placeholder="t('contractmanager', '0.00')" />
									<div class="amount-type-toggle">
										<NcCheckboxRadioSwitch v-model="form.amountType"
											value="netto"
											name="amountType"
											type="radio"
											:disabled="readOnly">
											{{ t('contractmanager', 'Netto') }}
										</NcCheckboxRadioSwitch>
										<NcCheckboxRadioSwitch v-model="form.amountType"
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
										variant="primary"
										@click="openInNextcloud(form.contractFolder)">
										<template #icon>
											<Folder :size="20" />
										</template>
										{{ t('contractmanager', 'Öffnen') }}
									</NcButton>
									<NcButton v-else
										variant="secondary"
										@click="openFolderPicker">
										<template #icon>
											<Folder :size="20" />
										</template>
										{{ t('contractmanager', 'Wählen') }}
									</NcButton>
									<NcButton v-if="form.contractFolder && !readOnly"
										variant="secondary"
										@click="openFolderPicker">
										{{ t('contractmanager', 'Ändern') }}
									</NcButton>
									<NcButton v-if="form.contractFolder && !readOnly"
										variant="tertiary"
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
										variant="primary"
										@click="openDocument(form.mainDocument)">
										<template #icon>
											<OpenInNewIcon v-if="isExternalDocument" :size="20" />
											<File v-else :size="20" />
										</template>
										{{ t('contractmanager', 'Öffnen') }}
									</NcButton>
									<template v-if="!form.mainDocument && !readOnly">
										<NcButton variant="secondary"
											@click="openFilePicker">
											<template #icon>
												<File :size="20" />
											</template>
											{{ t('contractmanager', 'Datei wählen') }}
										</NcButton>
										<NcButton variant="tertiary"
											@click="showUrlInput = !showUrlInput">
											<template #icon>
												<OpenInNewIcon :size="20" />
											</template>
											{{ t('contractmanager', 'Externer Link') }}
										</NcButton>
									</template>
									<NcButton v-if="form.mainDocument && !readOnly"
										variant="secondary"
										@click="openFilePicker">
										{{ t('contractmanager', 'Ändern') }}
									</NcButton>
									<NcButton v-if="form.mainDocument && !readOnly"
										variant="tertiary"
										:title="t('contractmanager', 'Entfernen')"
										@click="form.mainDocument = ''">
										<template #icon>
											<Close :size="20" />
										</template>
									</NcButton>
								</div>
								<div v-if="showUrlInput && !readOnly && !form.mainDocument" class="url-input-row">
									<NcTextField v-model="urlInput"
										:placeholder="t('contractmanager', 'https://...')"
										@keydown.enter.prevent="addExternalUrl" />
									<NcButton variant="primary" @click="addExternalUrl">
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
								<NcCheckboxRadioSwitch v-model="form.reminderEnabled" :disabled="readOnly">
									{{ t('contractmanager', 'Erinnerung aktivieren') }}
								</NcCheckboxRadioSwitch>
								<div v-if="form.reminderEnabled" class="reminder-days">
									<NcTextField v-model="form.reminderDays"
										:label="t('contractmanager', 'X Tage vorher')"
										type="number"
										:disabled="readOnly"
										:placeholder="t('contractmanager', 'Standard')" />
								</div>
								<!-- Per-user opt-out: only for existing contracts, applies to the current user only -->
								<div v-if="isEdit && form.reminderEnabled" class="reminder-optout">
									<NcCheckboxRadioSwitch :model-value="reminderOptedOut"
										:disabled="optOutSaving"
										@update:model-value="onReminderOptOutChange">
										{{ t('contractmanager', 'Mich nicht an diesen Vertrag erinnern') }}
									</NcCheckboxRadioSwitch>
									<p class="optout-hint">
										{{ t('contractmanager', 'Betrifft nur Ihre eigenen Erinnerungen, nicht die anderer Benutzer.') }}
									</p>
								</div>
							</template>
						</div>
					</div>
				</div>

				<!-- Notes -->
				<div class="form-section">
					<h3>{{ t('contractmanager', 'Notizen') }}</h3>

					<div class="form-row notes-field">
						<!-- eslint-disable vue/no-v-html -- linkifyText escapes HTML before linkifying -->
						<div v-if="readOnly && form.notes"
							class="notes-readonly"
							:aria-label="t('contractmanager', 'Zusätzliche Notizen')"
							v-html="linkifiedNotes" />
						<!-- eslint-enable vue/no-v-html -->
						<NcTextArea v-else
							v-model="form.notes"
							:label="t('contractmanager', 'Zusätzliche Notizen')"
							:placeholder="t('contractmanager', 'Zusätzliche Notizen...')"
							:maxlength="5000"
							:disabled="readOnly"
							resize="vertical"
							rows="4" />
					</div>
				</div>

				<!-- Zuständig -->
				<div class="form-section">
					<div class="form-row">
						<label class="form-label">{{ t('contractmanager', 'Zuständig') }}</label>
						<p v-if="!readOnly" class="form-hint">
							{{ t('contractmanager', 'Wer diesen Vertrag betreut. Leer lassen, dann bleibt der Ersteller zuständig.') }}
						</p>
						<NcSelect v-if="!readOnly"
							v-model="form.responsiblePrincipal"
							:options="responsibleSearchResults"
							:loading="responsibleSearching"
							:placeholder="t('contractmanager', 'Benutzer suchen...')"
							label="displayName"
							track-by="id"
							:clearable="true"
							@search="onResponsibleSearch" />
						<span v-else>{{ form.responsiblePrincipal ? form.responsiblePrincipal.displayName : t('contractmanager', 'Ersteller') }}</span>
					</div>
				</div>

				<!-- Privacy -->
				<div class="form-section">
					<div class="form-row">
						<NcCheckboxRadioSwitch v-model="form.isPrivate" :disabled="readOnly">
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
					<NcButton v-if="readOnly" variant="primary" @click="$emit('close')">
						{{ t('contractmanager', 'Schließen') }}
					</NcButton>
					<template v-else>
						<NcButton variant="tertiary" @click="$emit('close')">
							{{ t('contractmanager', 'Abbrechen') }}
						</NcButton>
						<NcButton variant="primary" type="submit" :disabled="!isValid || loading">
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
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import { getFilePickerBuilder, showSuccess, showError, showWarning } from '@nextcloud/dialogs'
import { mapState } from 'pinia'
import { useCategoriesStore } from '../store/categories'
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
import { formatDate, formatDateForInput, parseLocalDate } from '../utils/dateUtils.js'
import { parsePeriod, calculateCancellationDeadline } from '../utils/periodUtils.js'
import { isUrl, isInternalUrl, getDisplayName } from '../utils/documentUtils.js'
import { linkifyText } from '../utils/linkify.js'
import { reminderEnabledForEndDate } from '../utils/reminderForm'
import ContractService from '../services/ContractService'
import ExtractionService from '../services/ExtractionService'
import SettingsService from '../services/SettingsService'

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
		NcDateTimePickerNative,
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
			vendorOptions: [],
			reminderOptedOut: false,
			optOutSaving: false,
			responsibleSearchResults: [],
			responsibleSearching: false,
		}
	},
	computed: {
		...mapState(useCategoriesStore, ['allCategories']),
		isEdit() {
			return this.contract !== null && this.contract.id != null
		},
		documentDisplayName() {
			return getDisplayName(this.form.mainDocument)
		},
		linkifiedNotes() {
			return linkifyText(this.form.notes)
		},
		vendorSelection: {
			get() {
				return this.form.vendor || null
			},
			set(value) {
				// NcSelect emits string (for created/tag) or option-object — normalize to plain string
				if (value == null) {
					this.form.vendor = ''
				} else if (typeof value === 'string') {
					this.form.vendor = value
				} else if (typeof value === 'object' && value.label) {
					this.form.vendor = value.label
				}
			},
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
		cancellationDeadlineTypeOptions() {
			return [
				{ value: 'normal', label: t('contractmanager', 'Tagesgenau') },
				{ value: 'month_end', label: t('contractmanager', 'Zum Monatsende') },
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
			const deadline = calculateCancellationDeadline(this.form.endDate, periodString, this.form.contractType, renewalPeriod, { deadlineType: this.form.cancellationDeadlineType })
			return deadline ? formatDate(deadline) : null
		},
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
			// Ohne Enddatum keine Erinnerung. Bei vorhandenem Enddatum bleibt der
			// gespeicherte Zustand erhalten (kein Auto-Aktivieren beim Laden, #180).
			this.form.reminderEnabled = reminderEnabledForEndDate(this.form.reminderEnabled, newVal)
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
		if (this.isEdit) {
			try {
				this.reminderOptedOut = await ContractService.getReminderOptOut(this.contract.id)
			} catch (e) {
				console.debug('Failed to load reminder opt-out state:', e)
			}
		}
	},
	mounted() {
		this.loadVendorOptions()
	},
	methods: {
		async onResponsibleSearch(query) {
			if (!query || query.trim().length < 1) {
				this.responsibleSearchResults = []
				return
			}
			try {
				this.responsibleSearching = true
				// Editor-zugänglicher User-Such-Endpunkt (nicht der admin-only Principal-Search)
				this.responsibleSearchResults = await ContractService.searchUsers(query)
			} catch (e) {
				this.responsibleSearchResults = []
			} finally {
				this.responsibleSearching = false
			}
		},
		async onReminderOptOutChange(value) {
			this.optOutSaving = true
			try {
				this.reminderOptedOut = await ContractService.setReminderOptOut(this.contract.id, value)
				showSuccess(t('contractmanager', 'Einstellung gespeichert'))
			} catch (e) {
				console.error('Failed to save reminder opt-out:', e)
				showError(t('contractmanager', 'Fehler beim Speichern'))
			} finally {
				this.optOutSaving = false
			}
		},
		async loadVendorOptions() {
			if (this.readOnly) return
			try {
				const vendors = await ContractService.getVendors()
				this.vendorOptions = Array.isArray(vendors)
					? vendors.map(v => ({ label: v }))
					: []
			} catch (e) {
				// Autocomplete is a nice-to-have — silently fall back to empty suggestions
				this.vendorOptions = []
			}
		},
		onVendorSearch(query) {
			// NcSelect filters the visible list client-side automatically;
			// hook is here for parity with potential future server-side search.
			// No-op for now, kept for the @search binding contract.
			void query
		},
		getInitialForm(defaultAmountType = 'netto') {
			return {
				name: '',
				vendor: '',
				categoryId: null,
				contractStatus: 'active',
				startDate: null,
				endDate: null,
				cancelledOn: null,
				cancelledTo: null,
				cancellationPeriodValue: '1',
				cancellationPeriodUnit: 'months',
				contractType: 'auto_renewal',
				cancellationDeadlineType: 'normal',
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
				responsiblePrincipal: null,
				customField1: '',
				customField2: '',
				customField3: '',
			}
		},
		formatDateDisplay(date) {
			return formatDate(date)
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
			const startDate = parseLocalDate(contract.startDate)
			const endDate = parseLocalDate(contract.endDate)
			return {
				name: contract.name || '',
				vendor: contract.vendor || '',
				categoryId: contract.categoryId,
				contractStatus: contract.status || 'active',
				startDate,
				endDate,
				cancelledOn: parseLocalDate(contract.cancelledOn),
				cancelledTo: parseLocalDate(contract.cancelledTo),
				cancellationPeriodValue: cancellation.value,
				cancellationPeriodUnit: cancellation.unit,
				contractType: contract.contractType || 'fixed',
				cancellationDeadlineType: contract.cancellationDeadlineType || 'normal',
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
				isPrivate: contract.isPrivate === true,
				responsiblePrincipal: contract.responsibleUser
					? { id: 'user:' + contract.responsibleUser, uid: contract.responsibleUser, displayName: contract.responsibleUser, type: 'user' }
					: null,
				customField1: contract.customField1 || '',
				customField2: contract.customField2 || '',
				customField3: contract.customField3 || '',
			}
		},
		formToPayload() {
			const startDate = this.form.startDate
			const endDate = this.form.endDate
			return {
				name: this.form.name.trim(),
				vendor: this.form.vendor.trim(),
				categoryId: this.form.categoryId,
				status: this.form.contractStatus,
				startDate: startDate ? this.formatDateForApi(startDate) : null,
				endDate: endDate ? this.formatDateForApi(endDate) : null,
				cancelledOn: this.form.cancelledOn ? this.formatDateForApi(this.form.cancelledOn) : null,
				cancelledTo: this.form.cancelledOn && this.form.cancelledTo ? this.formatDateForApi(this.form.cancelledTo) : null,
				cancellationPeriod: this.form.contractType === 'auto_renewal'
					? this.formatPeriod(this.form.cancellationPeriodValue, this.form.cancellationPeriodUnit)
					: null,
				contractType: this.form.contractType,
				cancellationDeadlineType: this.form.contractType === 'auto_renewal'
					? this.form.cancellationDeadlineType
					: 'normal',
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
				responsibleUser: this.form.responsiblePrincipal
					? (this.form.responsiblePrincipal.uid || String(this.form.responsiblePrincipal.id).replace('user:', ''))
					: null,
				customField1: this.form.customField1.trim() || null,
				customField2: this.form.customField2.trim() || null,
				customField3: this.form.customField3.trim() || null,
			}
		},
		formatDateForApi(date) {
			return formatDateForInput(date)
		},
		onCancelledOnInput(value) {
			this.form.cancelledOn = value
			if (!value) {
				this.form.cancelledTo = null
				// Cancellation removed — revert auto-set status back to active
				if (this.form.contractStatus === 'cancelled') {
					this.form.contractStatus = 'active'
				}
			}
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
				const start = parseLocalDate(data.startDate)
				if (start && !isNaN(start.getTime())) {
					this.form.startDate = start
				}
			}
			if (data.endDate) {
				const end = parseLocalDate(data.endDate)
				if (end && !isNaN(end.getTime())) {
					this.form.endDate = end
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
	padding: 20px 20px 0;
	max-height: min(90vh, calc(100vh - 120px));
	overflow-y: auto;
}

/*
 * Read-only details view: NC renders disabled fields dimmed (muted colour +
 * 0.7 opacity), which is too faint to read as a details view. Restore full
 * contrast for the displayed values (inputs, textareas and NcSelect values).
 */
.contract-form--readonly {
	:deep(input:disabled),
	:deep(textarea:disabled) {
		opacity: 1;
		color: var(--color-main-text);
		-webkit-text-fill-color: var(--color-main-text);
	}

	:deep(.v-select.vs--disabled .vs__selected) {
		opacity: 1;
		color: var(--color-main-text);
		-webkit-text-fill-color: var(--color-main-text);
	}
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
		grid-template-columns: 160px 210px 1fr;
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
		grid-template-columns: 160px 210px 160px 1fr;
		gap: 12px;
		align-items: start;

		> div {
			display: flex;
			flex-direction: column;
		}
	}

	&--cancellation {
		display: grid;
		grid-template-columns: 210px 210px 1fr;
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
	min-width: 0;

	:deep(.native-datetime-picker) {
		width: 100%;
	}

	:deep(input) {
		width: 100%;
	}

	&--end {
		max-width: 210px;
	}
}

.date-with-clear {
	display: flex;
	align-items: center;
	gap: 4px;

	:deep(.native-datetime-picker) {
		flex: 1 1 auto;
		min-width: 0;
	}
}

.field-hint {
	margin-top: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
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
	position: sticky;
	bottom: 0;
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin: 24px -20px 0;
	padding: 16px 20px;
	border-top: 1px solid var(--color-border);
	background: var(--color-main-background);
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

.extraction-notes {
	margin-top: 8px;
	padding: 8px 12px;
	background: var(--color-warning-hover, #fff3cd);
	border-radius: 4px;
	font-size: 13px;
	color: var(--color-warning-text, #856404);
}

.notes-readonly {
	width: 100%;
	min-height: 80px;
	padding: 8px 12px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	font-size: 14px;
	line-height: 1.5;
	white-space: pre-wrap;
	word-wrap: break-word;

	a {
		color: var(--color-primary-element);
		text-decoration: underline;

		&:hover, &:focus {
			text-decoration: none;
		}
	}
}

// max-height keeps the resize handle inside the dialog viewport and above the modal footer.
.notes-field :deep(textarea) {
	min-height: 6em;
	max-height: 50vh;
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
