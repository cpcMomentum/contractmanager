import { describe, expect, it } from 'vitest'
import { formatDateForInput, parseLocalDate } from './dateUtils.js'

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
