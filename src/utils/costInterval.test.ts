import { describe, expect, it } from 'vitest'
import { hasMonthlyEquivalent, monthlyCost } from './costInterval'

describe('monthlyCost', () => {
	it('converts a weekly amount with 52/12, not with 4', () => {
		// 52 × 12 € = 624 € im Jahr = 52 € im Monat; Faktor 4 ergäbe 48 €.
		expect(monthlyCost(12, 'weekly')).toBeCloseTo(52, 10)
	})

	it('keeps the existing intervals unchanged', () => {
		expect(monthlyCost(30, 'monthly')).toBe(30)
		expect(monthlyCost(30, 'quarterly')).toBeCloseTo(10, 10)
		expect(monthlyCost(60, 'semi_annual')).toBeCloseTo(10, 10)
		expect(monthlyCost(120, 'yearly')).toBeCloseTo(10, 10)
	})

	it('returns null for one-time payments and unknown intervals', () => {
		expect(monthlyCost(100, 'one_time')).toBeNull()
		expect(monthlyCost(100, null)).toBeNull()
		expect(monthlyCost(100, undefined)).toBeNull()
		expect(monthlyCost(100, 'daily')).toBeNull()
		// Kein Treffer über die Prototypkette eines Objektliterals.
		expect(monthlyCost(100, 'toString')).toBeNull()
	})
})

describe('hasMonthlyEquivalent', () => {
	it('is true for every recurring interval, weekly included', () => {
		for (const interval of ['weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly']) {
			expect(hasMonthlyEquivalent(interval)).toBe(true)
		}
	})

	it('is false for one-time payments and missing values', () => {
		expect(hasMonthlyEquivalent('one_time')).toBe(false)
		expect(hasMonthlyEquivalent(null)).toBe(false)
		expect(hasMonthlyEquivalent('')).toBe(false)
	})
})
