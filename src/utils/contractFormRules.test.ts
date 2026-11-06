import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectiveEndForForm, endDateForSave, isEndDateApplicable } from './contractFormRules'

describe('isEndDateApplicable (#257)', () => {
	it('applies to fixed and auto_renewal contracts', () => {
		expect(isEndDateApplicable('fixed')).toBe(true)
		expect(isEndDateApplicable('auto_renewal')).toBe(true)
	})

	it('does not apply to unlimited contracts', () => {
		expect(isEndDateApplicable('unlimited')).toBe(false)
	})

	it('applies when no type is selected yet (no premature lock)', () => {
		expect(isEndDateApplicable(null)).toBe(true)
	})
})

describe('endDateForSave (#257)', () => {
	const date = new Date(2027, 5, 30)

	it('keeps the end date for fixed and auto_renewal', () => {
		expect(endDateForSave('fixed', date)).toBe(date)
		expect(endDateForSave('auto_renewal', date)).toBe(date)
	})

	it('always saves null for unlimited — a stray in-memory date must never persist', () => {
		expect(endDateForSave('unlimited', date)).toBeNull()
		expect(endDateForSave('unlimited', null)).toBeNull()
	})

	it('passes through null when no end date is set', () => {
		expect(endDateForSave('fixed', null)).toBeNull()
	})
})

describe('effectiveEndForForm', () => {
	const base = {
		endDate: new Date(2026, 8, 18),
		contractType: 'auto_renewal',
		renewalPeriod: '1 months',
		status: 'active',
		cancelledOn: null,
		cancelledTo: null,
	}

	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date(2026, 10, 6, 12))
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('rolls an auto-renewal end date that already passed forward to the current term', () => {
		expect(effectiveEndForForm(base)).toEqual(new Date(2026, 10, 18))
	})

	it('shows "cancelled to" once the contract is cancelled', () => {
		expect(effectiveEndForForm({
			...base,
			status: 'cancelled',
			cancelledOn: new Date(2026, 10, 6),
			cancelledTo: new Date(2026, 10, 18),
		})).toEqual(new Date(2026, 10, 18))
	})

	it('ignores a "cancelled to" without a cancellation date, like the save does', () => {
		expect(effectiveEndForForm({ ...base, status: 'cancelled', cancelledTo: new Date(2027, 0, 1) })).toEqual(new Date(2026, 8, 18))
	})

	it('keeps the end date of a fixed contract', () => {
		expect(effectiveEndForForm({ ...base, contractType: 'fixed' })).toEqual(new Date(2026, 8, 18))
	})

	it('returns null without an end date', () => {
		expect(effectiveEndForForm({ ...base, endDate: null })).toBeNull()
	})
})
