import { describe, expect, it } from 'vitest'
import { endDateForSave, isEndDateApplicable } from './contractFormRules'

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
