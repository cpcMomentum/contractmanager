import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { hasMonthlyCostAtLeast, isDeadlineWithin } from './listFilters'
import type { Contract } from '../store/contracts'

const FAKE_NOW = new Date('2026-06-15T08:00:00Z')

function daysFromNow(days: number): string {
	const d = new Date(FAKE_NOW)
	d.setDate(d.getDate() + days)
	return d.toISOString().slice(0, 10)
}

describe('isDeadlineWithin', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})
	afterEach(() => {
		vi.useRealTimers()
	})

	it('uses the cancellation deadline for auto-renewing contracts, not the end date', () => {
		// Ende in 60 Tagen, Frist 30 Tage vorher: Frist liegt in 30 Tagen.
		const contract: Contract = {
			id: 1,
			status: 'active',
			contractType: 'auto_renewal',
			endDate: daysFromNow(60),
			cancellationPeriod: '30 days',
			renewalPeriod: '12 months',
		}
		expect(isDeadlineWithin(contract, 30)).toBe(true)
		expect(isDeadlineWithin(contract, 29)).toBe(false)
	})

	it('uses the end date for fixed-term contracts', () => {
		const contract: Contract = { id: 2, status: 'active', contractType: 'fixed', endDate: daysFromNow(45) }
		expect(isDeadlineWithin(contract, 60)).toBe(true)
		expect(isDeadlineWithin(contract, 30)).toBe(false)
	})

	it('includes today and excludes dates in the past', () => {
		expect(isDeadlineWithin({ id: 3, status: 'active', contractType: 'fixed', endDate: daysFromNow(0) }, 30)).toBe(true)
		expect(isDeadlineWithin({ id: 4, status: 'active', contractType: 'fixed', endDate: daysFromNow(-1) }, 30)).toBe(false)
	})

	it('excludes contracts without any date', () => {
		expect(isDeadlineWithin({ id: 5, status: 'active', contractType: 'fixed', endDate: null }, 180)).toBe(false)
	})
})

describe('hasMonthlyCostAtLeast', () => {
	it('compares the monthly equivalent, not the raw amount', () => {
		const yearly: Contract = { id: 1, cost: '120', costInterval: 'yearly' }
		expect(hasMonthlyCostAtLeast(yearly, 10)).toBe(true)
		expect(hasMonthlyCostAtLeast(yearly, 10.01)).toBe(false)
	})

	it('excludes one-time payments and contracts without an amount', () => {
		expect(hasMonthlyCostAtLeast({ id: 2, cost: '500', costInterval: 'one_time' }, 0)).toBe(false)
		expect(hasMonthlyCostAtLeast({ id: 3, cost: null, costInterval: 'monthly' }, 0)).toBe(false)
		expect(hasMonthlyCostAtLeast({ id: 4, cost: '', costInterval: 'monthly' }, 0)).toBe(false)
	})

	it('accepts amounts exactly at the minimum', () => {
		expect(hasMonthlyCostAtLeast({ id: 5, cost: '49.99', costInterval: 'monthly' }, 49.99)).toBe(true)
	})
})
