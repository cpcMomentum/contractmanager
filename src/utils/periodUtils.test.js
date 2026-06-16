import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	addPeriod,
	applyDeadlineType,
	calculateCancellationDeadline,
	getEffectiveEndDate,
	parsePeriod,
	subtractPeriod,
} from './periodUtils.js'

/**
 * Characterization tests for the cancellation-deadline calculation core.
 *
 * Purpose: pin the CURRENT behavior before the "Kündigen zum" feature (#159)
 * touches this code. These tests describe what the code does today — including
 * a couple of quirks that are deliberately documented, not fixed here. Any
 * future change to the math must consciously update a red test rather than
 * surprise a user.
 *
 * Dates are built with `new Date(year, monthIndex, day)` (local time) and
 * asserted via local Y-M-D, so results are timezone-independent like the
 * production code (which uses getDate()/setMonth() in local time).
 */

const FAKE_NOW = new Date(2026, 5, 15, 8, 0, 0) // 2026-06-15 local

function ymd(date) {
	if (!date) return null
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

describe('parsePeriod', () => {
	it('parses plural and singular units', () => {
		expect(parsePeriod('3 months')).toEqual({ value: 3, unit: 'months' })
		expect(parsePeriod('1 month')).toEqual({ value: 1, unit: 'month' })
		expect(parsePeriod('14 days')).toEqual({ value: 14, unit: 'days' })
		expect(parsePeriod('2 weeks')).toEqual({ value: 2, unit: 'weeks' })
		expect(parsePeriod('1 year')).toEqual({ value: 1, unit: 'year' })
	})

	it('tolerates missing whitespace', () => {
		expect(parsePeriod('3months')).toEqual({ value: 3, unit: 'months' })
	})

	it('returns null for empty or invalid input', () => {
		expect(parsePeriod(null)).toBeNull()
		expect(parsePeriod('')).toBeNull()
		expect(parsePeriod('soon')).toBeNull()
		expect(parsePeriod('3 fortnights')).toBeNull()
	})
})

describe('subtractPeriod', () => {
	it('subtracts days, weeks and years', () => {
		expect(ymd(subtractPeriod(new Date(2026, 5, 30), '14 days'))).toBe('2026-06-16')
		expect(ymd(subtractPeriod(new Date(2026, 5, 30), '2 weeks'))).toBe('2026-06-16')
		expect(ymd(subtractPeriod(new Date(2026, 5, 30), '1 year'))).toBe('2025-06-30')
	})

	it('subtracts whole months when the day-of-month exists', () => {
		expect(ymd(subtractPeriod(new Date(2026, 5, 30), '3 months'))).toBe('2026-03-30')
		expect(ymd(subtractPeriod(new Date(2026, 5, 30), '1 month'))).toBe('2026-05-30')
	})

	it('clamps to month end on overflow (31 Mar - 1 month → 28 Feb)', () => {
		expect(ymd(subtractPeriod(new Date(2026, 2, 31), '1 month'))).toBe('2026-02-28')
	})

	it('clamps to Feb 29 in a leap year (31 Mar 2024 - 1 month)', () => {
		expect(ymd(subtractPeriod(new Date(2024, 2, 31), '1 month'))).toBe('2024-02-29')
	})

	// QUIRK (documented, not fixed here): the year branch has NO leap-year
	// guard, so 29 Feb 2024 - 1 year rolls over to 1 Mar 2023 instead of
	// 28 Feb 2023. The PHP backend clamps this differently — see the note in
	// the impact analysis for #159.
	it('does NOT guard leap years on year subtraction (29 Feb - 1 year → 1 Mar)', () => {
		expect(ymd(subtractPeriod(new Date(2024, 1, 29), '1 year'))).toBe('2023-03-01')
	})

	it('returns null for an unparseable period', () => {
		expect(subtractPeriod(new Date(2026, 5, 30), 'bogus')).toBeNull()
	})
})

describe('addPeriod', () => {
	it('adds days, weeks, months and years', () => {
		expect(ymd(addPeriod(new Date(2026, 0, 1), '10 days'))).toBe('2026-01-11')
		expect(ymd(addPeriod(new Date(2026, 0, 1), '2 weeks'))).toBe('2026-01-15')
		expect(ymd(addPeriod(new Date(2026, 0, 21), '12 months'))).toBe('2027-01-21')
		expect(ymd(addPeriod(new Date(2026, 0, 1), '1 year'))).toBe('2027-01-01')
	})

	it('clamps to month end on overflow (31 Jan + 1 month → 28 Feb)', () => {
		expect(ymd(addPeriod(new Date(2026, 0, 31), '1 month'))).toBe('2026-02-28')
	})
})

describe('getEffectiveEndDate', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})
	afterEach(() => {
		vi.useRealTimers()
	})

	it('returns the end date unchanged for fixed contracts', () => {
		expect(ymd(getEffectiveEndDate(new Date(2026, 10, 21), 'fixed', null))).toBe('2026-11-21')
	})

	it('returns a future end date unchanged for auto_renewal', () => {
		expect(ymd(getEffectiveEndDate(new Date(2026, 10, 21), 'auto_renewal', '12 months'))).toBe('2026-11-21')
	})

	it('rolls a past auto_renewal end date forward into the future', () => {
		// 2024-01-21 + 12-month renewals → first end after 2026-06-15 is 2027-01-21.
		expect(ymd(getEffectiveEndDate(new Date(2024, 0, 21), 'auto_renewal', '12 months'))).toBe('2027-01-21')
	})

	it('uses cancelledTo as the effective end for cancelled contracts', () => {
		const result = getEffectiveEndDate(new Date(2026, 10, 21), 'auto_renewal', '12 months', {
			status: 'cancelled',
			cancelledTo: new Date(2026, 7, 31),
		})
		expect(ymd(result)).toBe('2026-08-31')
	})

	it('falls back to the end date for cancelled contracts without cancelledTo', () => {
		const result = getEffectiveEndDate(new Date(2026, 10, 21), 'auto_renewal', '12 months', { status: 'cancelled' })
		expect(ymd(result)).toBe('2026-11-21')
	})

	it('returns null without an end date', () => {
		expect(getEffectiveEndDate(null, 'auto_renewal', '12 months')).toBeNull()
	})
})

describe('calculateCancellationDeadline', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})
	afterEach(() => {
		vi.useRealTimers()
	})

	it('returns null without end date or cancellation period', () => {
		expect(calculateCancellationDeadline(null, '1 month', 'fixed')).toBeNull()
		expect(calculateCancellationDeadline(new Date(2026, 10, 21), null, 'fixed')).toBeNull()
	})

	it('returns null for cancelled contracts', () => {
		expect(calculateCancellationDeadline(new Date(2026, 10, 21), '1 month', 'auto_renewal', '12 months', { status: 'cancelled' })).toBeNull()
	})

	it('subtracts the cancellation period from a fixed end date', () => {
		expect(ymd(calculateCancellationDeadline(new Date(2026, 5, 30), '3 months', 'fixed'))).toBe('2026-03-30')
		expect(ymd(calculateCancellationDeadline(new Date(2026, 5, 30), '14 days', 'fixed'))).toBe('2026-06-16')
	})

	it('clamps the deadline to month end on overflow (fixed, 31 Mar - 1 month)', () => {
		expect(ymd(calculateCancellationDeadline(new Date(2026, 2, 31), '1 month', 'fixed'))).toBe('2026-02-28')
	})

	// Volker's case (#159): auto_renewal, end on the 21st, 1-month notice →
	// today the deadline lands on the 21st of the previous month. The "Kündigen
	// zum: zum Monatsende" feature will later turn this into 2026-10-31 WITHOUT
	// changing this default ('normal') result.
	it('anchors an auto_renewal deadline to the day-of-month (the 21st)', () => {
		expect(ymd(calculateCancellationDeadline(new Date(2026, 10, 21), '1 month', 'auto_renewal', '12 months'))).toBe('2026-10-21')
	})

	it('rolls the deadline forward when the current one already passed (auto_renewal)', () => {
		// End 2026-06-30, 90-day notice → original deadline 2026-04-01 (past).
		// Renews 12 months → next deadline 2027-04-01.
		expect(ymd(calculateCancellationDeadline(new Date(2026, 5, 30), '90 days', 'auto_renewal', '12 months'))).toBe('2027-04-01')
	})

	it('does not roll a fixed contract whose deadline is already in the past', () => {
		// Fixed contracts simply expire; the past deadline is returned as-is.
		expect(ymd(calculateCancellationDeadline(new Date(2026, 5, 30), '90 days', 'fixed'))).toBe('2026-04-01')
	})
})

describe('applyDeadlineType', () => {
	it('leaves the date untouched for "normal" or undefined', () => {
		const d = new Date(2026, 9, 21) // 2026-10-21
		expect(ymd(applyDeadlineType(d, 'normal'))).toBe('2026-10-21')
		expect(ymd(applyDeadlineType(d, undefined))).toBe('2026-10-21')
	})

	it('snaps to the last calendar day of the month for "month_end"', () => {
		expect(ymd(applyDeadlineType(new Date(2026, 9, 21), 'month_end'))).toBe('2026-10-31')
		expect(ymd(applyDeadlineType(new Date(2026, 1, 10), 'month_end'))).toBe('2026-02-28')
		expect(ymd(applyDeadlineType(new Date(2024, 1, 10), 'month_end'))).toBe('2024-02-29') // leap year
	})
})

describe('calculateCancellationDeadline — "zum Monatsende" (#159)', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(FAKE_NOW)
	})
	afterEach(() => {
		vi.useRealTimers()
	})

	// Volker: the 21st-anchored deadline becomes the month end (31.10) with
	// month_end, while the default 'normal' stays 21.10 (asserted alongside).
	it('snaps an auto_renewal deadline to month end (Volker: 21.10 → 31.10)', () => {
		const args = [new Date(2026, 10, 21), '1 month', 'auto_renewal', '12 months']
		expect(ymd(calculateCancellationDeadline(...args, { deadlineType: 'normal' }))).toBe('2026-10-21')
		expect(ymd(calculateCancellationDeadline(...args, { deadlineType: 'month_end' }))).toBe('2026-10-31')
	})

	it('snaps a fixed-contract deadline to month end', () => {
		expect(ymd(calculateCancellationDeadline(new Date(2026, 10, 21), '1 month', 'fixed', null, { deadlineType: 'month_end' }))).toBe('2026-10-31')
	})

	// Correctness of snapping INSIDE the roll loop: the normal deadline (10.06)
	// is just past "now" (15.06), but the month-end deadline (30.06) is still
	// upcoming — so it must NOT be rolled to next year. A buggy "snap after the
	// loop" would yield 2027-06-30 here.
	it('does not skip a month-end deadline that is still upcoming this period', () => {
		expect(ymd(calculateCancellationDeadline(new Date(2026, 6, 10), '1 month', 'auto_renewal', '12 months', { deadlineType: 'month_end' }))).toBe('2026-06-30')
	})
})
