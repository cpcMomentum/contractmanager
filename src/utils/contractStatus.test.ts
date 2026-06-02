import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { DEFAULT_REMINDER_DAYS_1, isEndingSoon, isExpiredFixed } from './contractStatus'
import type { Contract } from '../store/contracts'

/**
 * Helpers to build dates relative to a fixed "today" so tests don't drift
 * with the calendar.
 */
const FAKE_NOW = new Date('2026-06-15T08:00:00Z')

function daysFromNow(days: number): string {
	const d = new Date(FAKE_NOW)
	d.setDate(d.getDate() + days)
	return d.toISOString().slice(0, 10)
}

function autoRenewal(overrides: Partial<Contract> = {}): Contract {
	return {
		id: 1,
		status: 'active',
		contractType: 'auto_renewal',
		// End date 60 days out → with a 30-day cancellation period the
		// deadline lands 30 days out.
		endDate: daysFromNow(60),
		cancellationPeriod: '30 days',
		renewalPeriod: '12 months',
		...overrides,
	}
}

function fixed(overrides: Partial<Contract> = {}): Contract {
	return {
		id: 2,
		status: 'active',
		contractType: 'fixed',
		endDate: daysFromNow(30),
		...overrides,
	}
}

describe('isEndingSoon', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('is true when the cancellation deadline is inside the default 14-day window', () => {
		// End date 20 days out, cancel period 10 days → deadline 10 days from now.
		const contract = autoRenewal({ endDate: daysFromNow(20), cancellationPeriod: '10 days' })
		expect(isEndingSoon(contract)).toBe(true)
	})

	it('is false when the deadline is beyond the reminder window', () => {
		// End date 60 days out, cancel period 30 days → deadline 30 days from now → outside 14d.
		const contract = autoRenewal()
		expect(isEndingSoon(contract)).toBe(false)
	})

	it('respects a per-contract reminderDays override', () => {
		// Deadline 30 days out, override window to 45 days → now is inside the window.
		const contract = autoRenewal({ reminderDays: 45 })
		expect(isEndingSoon(contract)).toBe(true)
	})

	it('is false for cancelled contracts', () => {
		const contract = autoRenewal({ status: 'cancelled', endDate: daysFromNow(20), cancellationPeriod: '10 days' })
		expect(isEndingSoon(contract)).toBe(false)
	})

	it('is false for fixed contracts (no cancellation deadline concept)', () => {
		const contract = fixed({ cancellationPeriod: '10 days' } as Partial<Contract>)
		expect(isEndingSoon(contract as Contract)).toBe(false)
	})

	it('is false when the contract has no cancellation period', () => {
		const contract = autoRenewal({ cancellationPeriod: null })
		expect(isEndingSoon(contract)).toBe(false)
	})

	it('is false when the contract has no end date', () => {
		const contract = autoRenewal({ endDate: null })
		expect(isEndingSoon(contract)).toBe(false)
	})

	it('uses the supplied default reminder days when no override is set', () => {
		// Deadline 25 days out — outside default 14d but inside an explicit 30d default.
		const contract = autoRenewal({ endDate: daysFromNow(55), cancellationPeriod: '30 days' })
		expect(isEndingSoon(contract, DEFAULT_REMINDER_DAYS_1)).toBe(false)
		expect(isEndingSoon(contract, 30)).toBe(true)
	})

	it('does not flag contracts whose deadline already passed (auto_renewal rolls forward)', () => {
		// For auto_renewal we expect calculateCancellationDeadline to roll the
		// deadline into the next period if the current one is past. With a 60-day
		// end + 90-day cancellation period, the original deadline is 30 days ago.
		// The next deadline (after one 12-month renewal) is far in the future,
		// so the window predicate returns false.
		const contract = autoRenewal({ endDate: daysFromNow(60), cancellationPeriod: '90 days' })
		expect(isEndingSoon(contract)).toBe(false)
	})
})

describe('isExpiredFixed', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('is true for an active fixed contract with end date in the past', () => {
		expect(isExpiredFixed(fixed({ endDate: daysFromNow(-1) }))).toBe(true)
	})

	it('is false on the exact end date (still considered running that day)', () => {
		expect(isExpiredFixed(fixed({ endDate: daysFromNow(0) }))).toBe(false)
	})

	it('is false for fixed contracts with end date in the future', () => {
		expect(isExpiredFixed(fixed())).toBe(false)
	})

	it('is false once the status has already flipped to "ended"', () => {
		expect(isExpiredFixed(fixed({ status: 'ended', endDate: daysFromNow(-30) }))).toBe(false)
	})

	it('is false for auto_renewal contracts (they renew, not expire)', () => {
		const contract = autoRenewal({ endDate: daysFromNow(-30) })
		expect(isExpiredFixed(contract)).toBe(false)
	})

	it('is false when end date is missing', () => {
		expect(isExpiredFixed(fixed({ endDate: null }))).toBe(false)
	})
})
