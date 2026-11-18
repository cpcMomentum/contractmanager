<!--
	SPDX-FileCopyrightText: 2026 cpcMomentum
	SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('contractmanager', 'Berichte')" size="normal" @close="$emit('close')">
		<div class="report-dialog">
			<NcLoadingIcon v-if="available === null" :size="32" />

			<NcNoteCard v-else-if="!available" type="info">
				<p>{{ t('contractmanager', 'Berichte gehören zu WerkPlus.') }}</p>
				<p>{{ t('contractmanager', 'Mit WerkPlus lassen sich Vertragsspiegel, Kostenübersicht und Fristenvorschau als PDF und CSV herunterladen.') }}</p>
			</NcNoteCard>

			<template v-else>
				<fieldset class="report-dialog__group">
					<legend>{{ t('contractmanager', 'Bericht') }}</legend>
					<NcCheckboxRadioSwitch v-for="option in typeOptions"
						:key="option.value"
						v-model="type"
						:value="option.value"
						name="reportType"
						type="radio">
						{{ option.label }}
						<span class="report-dialog__hint">{{ option.hint }}</span>
					</NcCheckboxRadioSwitch>
				</fieldset>

				<fieldset v-if="type === 'deadlines'" class="report-dialog__group">
					<legend>{{ t('contractmanager', 'Zeitraum') }}</legend>
					<NcCheckboxRadioSwitch v-for="months in periods"
						:key="months"
						v-model="period"
						:value="String(months)"
						name="reportPeriod"
						type="radio">
						{{ n('contractmanager', 'Nächster Monat', 'Nächste %n Monate', months) }}
					</NcCheckboxRadioSwitch>
				</fieldset>

				<fieldset class="report-dialog__group">
					<legend>{{ t('contractmanager', 'Format') }}</legend>
					<NcCheckboxRadioSwitch v-model="format"
						value="pdf"
						name="reportFormat"
						type="radio">
						PDF
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-model="format"
						value="csv"
						name="reportFormat"
						type="radio">
						{{ t('contractmanager', 'CSV (für Excel)') }}
					</NcCheckboxRadioSwitch>
				</fieldset>

				<NcNoteCard v-if="error" type="error" :text="error" />
			</template>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ available ? t('contractmanager', 'Abbrechen') : t('contractmanager', 'Schließen') }}
			</NcButton>
			<NcButton v-if="available"
				variant="primary"
				:disabled="busy"
				@click="onDownload">
				<template #icon>
					<NcLoadingIcon v-if="busy" :size="20" />
					<DownloadIcon v-else :size="20" />
				</template>
				{{ t('contractmanager', 'Herunterladen') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import ReportService, { ReportError, type ReportFormat, type ReportType } from '../services/ReportService'

const props = defineProps<{
	contractIds: number[]
}>()

const emit = defineEmits<{ close: [] }>()

const periods = [3, 6, 12]

const available = ref<boolean | null>(null)
const type = ref<ReportType>('contracts')
const format = ref<ReportFormat>('pdf')
const period = ref('3')
const busy = ref(false)
const error = ref('')

const typeOptions = computed(() => [
	{
		value: 'contracts',
		label: t('contractmanager', 'Vertragsspiegel'),
		hint: n('contractmanager', 'Der Vertrag der aktuellen Ansicht, mit Filter und Sortierung', 'Die %n Verträge der aktuellen Ansicht, mit Filter und Sortierung', props.contractIds.length),
	},
	{
		value: 'costs',
		label: t('contractmanager', 'Kostenübersicht'),
		hint: t('contractmanager', 'Laufende Kosten aller Verträge, nach Kategorie und Vertragspartner'),
	},
	{
		value: 'deadlines',
		label: t('contractmanager', 'Fristenvorschau'),
		hint: t('contractmanager', 'Kündigungsfristen und automatische Verlängerungen aller Verträge'),
	},
])

onMounted(async () => {
	try {
		available.value = await ReportService.isAvailable()
	} catch {
		available.value = false
	}
})

async function onDownload(): Promise<void> {
	busy.value = true
	error.value = ''
	try {
		await ReportService.download(type.value, format.value, props.contractIds, Number(period.value))
		emit('close')
	} catch (e) {
		if (e instanceof ReportError && e.werkplusLimit) {
			available.value = false
		} else {
			error.value = (e instanceof ReportError && e.message) || t('contractmanager', 'Der Bericht konnte nicht erstellt werden.')
		}
	} finally {
		busy.value = false
	}
}
</script>

<style scoped lang="scss">
.report-dialog {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 8px;

	&__group {
		display: flex;
		flex-direction: column;
		gap: 2px;

		legend {
			font-weight: bold;
			margin-bottom: 4px;
		}
	}

	&__hint {
		display: block;
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
	}
}
</style>
