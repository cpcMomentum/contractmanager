import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { formatDate, formatDateForInput, isDateInPast, isDateWithinDays, parseLocalDate, toLocalDate } from './dateUtils.js'

/**
 * Regression tests for #258: while the user types the year digit by digit,
 * the native date input reports intermediate values like "0002-07-11".
 * parseLocalDate must preserve years 0-99 (the JS Date constructor maps them
 * to 1900-1999) and formatDateForInput must pad the year to 4 digits, so the
 * v-model round-trip is lossless and does not rewrite the field mid-typing.
 */
describe('parseLocalDate', () => {
	it('parses a regular date at local midnight', () => {
		const date = parseLocalDate('2026-07-11')
		expect(date.getFullYear()).toBe(2026)
		expect(date.getMonth()).toBe(6)
		expect(date.getDate()).toBe(11)
		expect(date.getHours()).toBe(0)
	})

	it('preserves years below 100 instead of mapping them to 19XX (#258)', () => {
		expect(parseLocalDate('0002-07-11').getFullYear()).toBe(2)
		expect(parseLocalDate('0026-01-01').getFullYear()).toBe(26)
		expect(parseLocalDate('0099-12-31').getFullYear()).toBe(99)
	})

	it('returns null for empty or malformed input', () => {
		expect(parseLocalDate(null)).toBeNull()
		expect(parseLocalDate('')).toBeNull()
		expect(parseLocalDate('2026-07')).toBeNull()
		expect(parseLocalDate('aa-bb-cc')).toBeNull()
	})
})

describe('formatDateForInput', () => {
	it('formats a regular date as YYYY-MM-DD', () => {
		expect(formatDateForInput(new Date(2026, 6, 11))).toBe('2026-07-11')
	})

	it('pads years below 1000 to 4 digits (#258)', () => {
		const yearTwo = parseLocalDate('0002-07-11')
		expect(formatDateForInput(yearTwo)).toBe('0002-07-11')
	})

	it('round-trips every intermediate typing state losslessly (#258)', () => {
		for (const value of ['0002-07-11', '0020-07-11', '0202-07-11', '2026-07-11']) {
			expect(formatDateForInput(parseLocalDate(value))).toBe(value)
		}
	})
})

describe('toLocalDate', () => {
	it('reads a date-only string as local midnight of that very day', () => {
		const d = toLocalDate('2026-11-10')
		expect([d.getFullYear(), d.getMonth(), d.getDate(), d.getHours()]).toEqual([2026, 10, 10, 0])
	})

	it('keeps timestamps with a time part as they are', () => {
		expect(toLocalDate('2026-10-02T09:30:00+00:00')?.getTime()).toBe(Date.UTC(2026, 9, 2, 9, 30))
	})

	it('returns a copy for Date input and null for empty input', () => {
		const src = new Date(2026, 0, 5)
		const copy = toLocalDate(src)
		expect(copy).not.toBe(src)
		expect(copy.getTime()).toBe(src.getTime())
		expect(toLocalDate(null)).toBeNull()
		expect(toLocalDate('')).toBeNull()
	})
})

describe('formatDate', () => {
	it('shows the calendar day of a date-only string', () => {
		expect(formatDate('2026-11-10')).toBe(new Date(2026, 10, 10).toLocaleDateString(getCanonicalLocale(), { day: '2-digit', month: '2-digit', year: 'numeric' }))
	})
})

describe('isDateInPast / isDateWithinDays with date-only strings', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date(2026, 10, 9, 10, 0))
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('treats today as not past and yesterday as past', () => {
		expect(isDateInPast('2026-11-09')).toBe(false)
		expect(isDateInPast('2026-11-08')).toBe(true)
		expect(isDateInPast(null)).toBe(false)
	})

	it('counts today and the last day of the window as within', () => {
		expect(isDateWithinDays('2026-11-09', 5)).toBe(true)
		expect(isDateWithinDays('2026-11-14', 5)).toBe(true)
		expect(isDateWithinDays('2026-11-15', 5)).toBe(false)
		expect(isDateWithinDays('2026-11-08', 5)).toBe(false)
		expect(isDateWithinDays('', 5)).toBe(false)
	})
})
