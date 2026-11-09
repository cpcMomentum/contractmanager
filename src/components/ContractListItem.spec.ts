/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Papierkorb-Zeile (#438): zeigt, wer geloescht hat, und bietet das
 * Wiederherstellen jedem an, dem der Server den Eintrag liefert — auch ohne
 * Editor-Rolle, damit ein zustaendiger Viewer eine Fremdloeschung rueckgaengig
 * machen kann.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useContractsStore } from '../store/contracts'
import ContractListItem from './ContractListItem.vue'

vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn(), showError: vi.fn() }))

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcActions', () => ({
	default: { name: 'NcActions', template: '<div class="stub-actions"><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button class="stub-action" @click="$emit(\'click\')"><slot /></button>',
	},
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: { name: 'NcButton', template: '<button class="stub-button"><slot /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: { name: 'NcDialog', template: '<div class="stub-dialog"><slot /></div>' },
}))

const trashed = (teil: Record<string, unknown> = {}) => ({
	id: 7,
	name: 'Mobilfunk',
	vendor: 'Telco',
	status: 'active',
	archived: false,
	isPrivate: false,
	createdBy: 'alice',
	deletedAt: '2026-10-02T09:30:00+00:00',
	deletedBy: 'bob',
	...teil,
})

function asUser(canEdit: boolean) {
	const store = useContractsStore()
	store.permissions = { ...store.permissions, canEdit, isEditor: canEdit, isViewer: !canEdit }
}

// Die App haengt t/n in main.ts an globalProperties; im Test fehlt das.
const global = { mocks: { t: (_app: string, text: string) => text } }

const actionLabels = (wrapper: ReturnType<typeof mount>) =>
	wrapper.findAll('.stub-action').map(b => b.text())

describe('ContractListItem im Papierkorb', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('zeigt, wer den Vertrag geloescht hat', () => {
		asUser(true)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed(), mode: 'trash' } })

		expect(wrapper.text()).toContain('Gelöscht von: bob')
	})

	it('laesst die Zeile weg, wenn der Loeschende unbekannt ist (Altbestand)', () => {
		asUser(true)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed({ deletedBy: null }), mode: 'trash' } })

		expect(wrapper.text()).not.toContain('Gelöscht von')
	})

	it('bietet Wiederherstellen auch ohne Editor-Rolle an', async () => {
		asUser(false)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed(), mode: 'trash' } })

		const restore = wrapper.findAll('.stub-action').find(b => b.text() === 'Wiederherstellen')
		expect(restore).toBeDefined()
		await restore!.trigger('click')
		expect(wrapper.emitted('restore')).toHaveLength(1)
	})

	it('laesst das Wiederherstellen aus dem Archiv weiter an der Editor-Rolle', () => {
		asUser(false)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed({ archived: true, deletedAt: null }), mode: 'archive' } })

		expect(actionLabels(wrapper)).not.toContain('Wiederherstellen')
	})
})

describe('ContractListItem: Status-Chip', () => {
	const FAKE_NOW = new Date('2026-06-15T08:00:00Z')
	const day = (offset: number) => {
		const d = new Date(FAKE_NOW)
		d.setDate(d.getDate() + offset)
		return d.toISOString().slice(0, 10)
	}
	const chipOf = (contract: Record<string, unknown>) => {
		const wrapper = mount(ContractListItem, { global, props: { contract: { id: 1, name: 'X', vendor: 'Y', ...contract } } })
		return wrapper.find('.contract-list-item__status .cm-chip')
	}

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('zeigt einen abgelaufenen befristeten Vertrag grau als „Beendet“ mit Hinweis', () => {
		const chip = chipOf({ status: 'active', contractType: 'fixed', endDate: day(-3) })
		expect(chip.text()).toBe('Beendet')
		expect(chip.classes()).toContain('cm-chip--ended')
		expect(chip.attributes('title')).toBe('Das Enddatum ist überschritten.')
	})

	it('zeigt einen regulär beendeten Vertrag ohne Hinweis', () => {
		const chip = chipOf({ status: 'ended', contractType: 'fixed', endDate: day(-30) })
		expect(chip.text()).toBe('Beendet')
		expect(chip.attributes('title')).toBe('')
	})

	it.each([
		[{ status: 'active', contractType: 'fixed', startDate: day(10), endDate: day(400) }, 'Geplant', 'cm-chip--planned'],
		[{ status: 'active', contractType: 'fixed', endDate: day(5) }, 'endet', 'cm-chip--soon'],
		[{ status: 'active', contractType: 'auto_renewal', endDate: day(20), cancellationPeriod: '7 days', renewalPeriod: '12 months' }, 'Kündigungsfrist endet', 'cm-chip--soon'],
		[{ status: 'cancelled', contractType: 'auto_renewal', endDate: day(60), cancelledOn: day(-5), cancelledTo: day(60) }, 'Gekündigt', 'cm-chip--cancelled'],
		[{ status: 'active', contractType: 'fixed', endDate: day(200) }, 'Laufend', 'cm-chip--active-fixed'],
		[{ status: 'active', contractType: 'unlimited' }, 'Laufend', 'cm-chip--active'],
	])('%j → %s', (contract, label, cls) => {
		const chip = chipOf(contract)
		expect(chip.text()).toBe(label)
		expect(chip.classes()).toContain(cls)
	})
})
