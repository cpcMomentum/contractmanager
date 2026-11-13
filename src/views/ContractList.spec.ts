/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ContractList from './ContractList.vue'

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcActions', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextField', () => ({ default: {} }))
vi.mock('../components/ContractForm.vue', () => ({ default: {} }))
vi.mock('../components/ContractListItem.vue', () => ({ default: {} }))
vi.mock('@nextcloud/dialogs', () => ({ showInfo: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (_app: string, _key: string, fallback: unknown) => fallback }))

const FAKE_NOW = new Date('2026-06-15T08:00:00Z')
const day = (offset: number) => {
	const d = new Date(FAKE_NOW)
	d.setDate(d.getDate() + offset)
	return d.toISOString().slice(0, 10)
}

const contracts = [
	{ id: 1, name: 'Geplant', status: 'active', contractType: 'fixed', startDate: day(10), endDate: day(400) },
	{ id: 2, name: 'Laufend', status: 'active', contractType: 'auto_renewal', startDate: day(-100), endDate: day(200), cancellationPeriod: '1 months', renewalPeriod: '12 months' },
	{ id: 3, name: 'Frist', status: 'active', contractType: 'auto_renewal', startDate: day(-300), endDate: day(20), cancellationPeriod: '7 days', renewalPeriod: '12 months' },
	{ id: 4, name: 'Endet', status: 'active', contractType: 'fixed', startDate: day(-300), endDate: day(5) },
	{ id: 5, name: 'Abgelaufen', status: 'active', contractType: 'fixed', startDate: day(-400), endDate: day(-3) },
	{ id: 6, name: 'Gekuendigt', status: 'cancelled', contractType: 'auto_renewal', endDate: day(60), cancelledOn: day(-5), cancelledTo: day(60) },
	{ id: 7, name: 'Beendet', status: 'ended', contractType: 'fixed', endDate: day(-30) },
]

const computed = ContractList.computed as unknown as Record<string, (this: Record<string, unknown>) => unknown>

function listContext(filterStatuses: string[]) {
	return {
		allContracts: contracts,
		filterStatuses,
		defaultReminderDays: 14,
		searchQuery: '',
		categoryFilter: null,
		filterVendor: null,
		filterContractType: null,
		filterResponsible: null,
		filterDeadlineWithin: null,
		filterMinMonthlyCost: null,
		filterOwnerMissing: false,
		isAdmin: false,
		sortContracts: (list: unknown[]) => list,
	}
}

const names = (list: unknown) => (list as { name: string }[]).map(c => c.name)

describe('ContractList: Status-Filter und KPI', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('bietet genau die sechs Chip-Begriffe als Filter an, ohne Vorauswahl', () => {
		const data = (ContractList.data as unknown as () => Record<string, unknown>).call({})
		expect((data.statusOptions as { id: string, label: string }[]).map(o => [o.id, o.label])).toEqual([
			['planned', 'Geplant'],
			['active', 'Laufend'],
			['ending_soon', 'Kündigungsfrist endet'],
			['ending', 'endet'],
			['cancelled', 'Gekündigt'],
			['ended', 'Beendet'],
		])
		expect(data.filterStatuses).toEqual([])
	})

	it.each([
		[['planned'], ['Geplant']],
		[['active'], ['Laufend']],
		[['ending_soon'], ['Frist']],
		[['ending'], ['Endet']],
		[['cancelled'], ['Gekuendigt']],
		[['ended'], ['Abgelaufen', 'Beendet']],
		[['active', 'ending'], ['Laufend', 'Endet']],
	])('Filter %j findet genau %j', (filter, expected) => {
		expect(names(computed.contracts.call(listContext(filter)))).toEqual(expected)
	})

	it('zeigt ohne Status-Filter alle Verträge', () => {
		expect(names(computed.contracts.call(listContext([])))).toHaveLength(contracts.length)
	})

	it('zählt bei „Aktive Verträge“ weder geplante noch abgelaufene befristete Verträge', () => {
		const ctx: Record<string, unknown> = { kpiBaseContracts: contracts, defaultReminderDays: 14 }
		ctx.kpiRunningContracts = computed.kpiRunningContracts.call(ctx)
		expect(names(ctx.kpiRunningContracts)).toEqual(['Laufend', 'Frist', 'Endet'])
		ctx.kpiActiveCount = computed.kpiActiveCount.call(ctx)
		expect(ctx.kpiActiveCount).toBe(3)
		vi.stubGlobal('t', (_app: string, text: string, vars: Record<string, unknown> = {}) => text.replace(/{(\w+)}/g, (_m, k) => String(vars[k])))
		expect(computed.kpiTypeSub.call(ctx)).toBe('2 mit autom. Verlängerung · 1 weitere')
		vi.unstubAllGlobals()
	})
})
