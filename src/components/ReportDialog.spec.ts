/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ReportDialog from './ReportDialog.vue'

const isAvailable = vi.fn<() => Promise<boolean>>()
const download = vi.fn<(type: string, format: string, ids: number[], months: number) => Promise<void>>()

vi.mock('../services/ReportService', async () => {
	const actual = await vi.importActual<typeof import('../services/ReportService')>('../services/ReportService')
	return {
		...actual,
		default: {
			isAvailable: () => isAvailable(),
			download: (type: string, format: string, ids: number[], months: number) => download(type, format, ids, months),
		},
	}
})

vi.mock('../werkplusLinks', () => ({
	werkplusLinks: () => ({ buy: 'https://werkwolke.de/preis?app=vertragswerk', features: '', trial: '' }),
}))

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		props: ['variant', 'disabled'],
		emits: ['click'],
		template: '<button class="stub-button" :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
	},
}))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: { props: ['type', 'text'], template: '<div :class="[\'stub-note\', \'stub-note--\' + type]"><slot>{{ text }}</slot></div>' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: { props: ['name'], emits: ['close'], template: '<div><slot /><div class="stub-actions"><slot name="actions" /></div></div>' },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: { template: '<span />' } }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {
		props: ['modelValue', 'value'],
		emits: ['update:modelValue'],
		template: '<label class="stub-radio" :data-value="value" @click="$emit(\'update:modelValue\', value)"><slot /></label>',
	},
}))

const radio = (wrapper: ReturnType<typeof mount>, value: string) => wrapper.find(`.stub-radio[data-value="${value}"]`)
const primary = (wrapper: ReturnType<typeof mount>) => wrapper.find('.stub-button[data-variant="primary"]')

describe('ReportDialog', () => {
	beforeEach(() => {
		isAvailable.mockReset()
		download.mockReset()
	})

	it('names WerkPlus as the reason and offers no download without the flag', async () => {
		isAvailable.mockResolvedValue(false)
		const wrapper = mount(ReportDialog, { props: { contractIds: [1] } })
		await flushPromises()

		expect(wrapper.find('.stub-note--info').text()).toContain('Berichte gehören zu WerkPlus.')
		expect(wrapper.find('.stub-note--info a').attributes('href')).toBe('https://werkwolke.de/preis?app=vertragswerk')
		expect(primary(wrapper).exists()).toBe(false)
	})

	it('downloads the contract sheet for the ids of the current view', async () => {
		isAvailable.mockResolvedValue(true)
		download.mockResolvedValue()
		const wrapper = mount(ReportDialog, { props: { contractIds: [3, 1, 2] } })
		await flushPromises()

		await primary(wrapper).trigger('click')
		await flushPromises()

		expect(download).toHaveBeenCalledWith('contracts', 'pdf', [3, 1, 2], 3)
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('passes the chosen period and format for the deadline preview', async () => {
		isAvailable.mockResolvedValue(true)
		download.mockResolvedValue()
		const wrapper = mount(ReportDialog, { props: { contractIds: [] } })
		await flushPromises()

		await radio(wrapper, 'deadlines').trigger('click')
		await radio(wrapper, '12').trigger('click')
		await radio(wrapper, 'csv').trigger('click')
		await primary(wrapper).trigger('click')

		expect(download).toHaveBeenCalledWith('deadlines', 'csv', [], 12)
	})

	it('switches to the WerkPlus note when the server refuses because of the licence', async () => {
		isAvailable.mockResolvedValue(true)
		const { ReportError } = await import('../services/ReportService')
		download.mockRejectedValue(new ReportError('Berichte gehören zu WerkPlus.', true))
		const wrapper = mount(ReportDialog, { props: { contractIds: [1] } })
		await flushPromises()

		await primary(wrapper).trigger('click')
		await flushPromises()

		expect(wrapper.find('.stub-note--info').exists()).toBe(true)
		expect(wrapper.emitted('close')).toBeUndefined()
	})

	it('shows other errors in the dialog', async () => {
		isAvailable.mockResolvedValue(true)
		const { ReportError } = await import('../services/ReportService')
		download.mockRejectedValue(new ReportError('', false))
		const wrapper = mount(ReportDialog, { props: { contractIds: [1] } })
		await flushPromises()

		await primary(wrapper).trigger('click')
		await flushPromises()

		expect(wrapper.find('.stub-note--error').text()).toBe('Der Bericht konnte nicht erstellt werden.')
	})
})
