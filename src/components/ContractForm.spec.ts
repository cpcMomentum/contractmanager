/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ContractForm from './ContractForm.vue'

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcModal', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/dialogs', () => ({ getFilePickerBuilder: vi.fn(), showSuccess: vi.fn(), showError: vi.fn(), showWarning: vi.fn() }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'admin' }) }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (_app: string, _key: string, fallback: unknown) => fallback }))

const FAKE_NOW = new Date('2026-06-15T08:00:00Z')
const day = (offset: number) => {
	const d = new Date(FAKE_NOW)
	d.setDate(d.getDate() + offset)
	return d.toISOString().slice(0, 10)
}

const summaryChip = (ContractForm.computed as Record<string, (this: Record<string, unknown>) => unknown>).summaryChip

const chipFor = (payload: Record<string, unknown>) => summaryChip.call({
	formToPayload: () => ({ id: 1, ...payload }),
	defaultReminderDays: 14,
	form: { contractType: payload.contractType },
})

describe('ContractForm: Status im Kopf', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it.each([
		[{ status: 'active', contractType: 'fixed', startDate: day(10), endDate: day(400) }, 'planned', 'Geplant'],
		[{ status: 'active', contractType: 'auto_renewal', endDate: day(20), cancellationPeriod: '7 days', renewalPeriod: '12 months' }, 'soon', 'Kündigungsfrist endet'],
		[{ status: 'active', contractType: 'fixed', endDate: day(5) }, 'soon', 'endet'],
		[{ status: 'active', contractType: 'fixed', endDate: day(-3) }, 'ended', 'Beendet'],
		[{ status: 'cancelled', contractType: 'auto_renewal', endDate: day(60), cancelledOn: day(-5), cancelledTo: day(60) }, 'cancelled', 'Gekündigt'],
		[{ status: 'active', contractType: 'fixed', endDate: day(200) }, 'active-fixed', 'Laufend'],
		[{ status: 'active', contractType: 'auto_renewal', endDate: day(200), cancellationPeriod: '1 months', renewalPeriod: '12 months' }, 'active', 'Laufend'],
	])('%j → %s / %s', (payload, cls, label) => {
		expect(chipFor(payload)).toEqual({ cls, label })
	})
})
