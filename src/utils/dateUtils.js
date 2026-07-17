/**
 * Date utility functions for ContractManager
 */

import { getCanonicalLocale } from '@nextcloud/l10n'

/**
 * Formats a date string or Date object for display, following the Nextcloud
 * interface locale (e.g. DD.MM.YYYY for German, MM/DD/YYYY for US English).
 * @param {string|Date|null} dateInput - ISO date string or Date object
 * @returns {string} Formatted date or empty string if invalid
 */
export function formatDate(dateInput) {
	if (!dateInput) return ''

	const date = dateInput instanceof Date ? dateInput : new Date(dateInput)

	if (isNaN(date.getTime())) return ''

	return date.toLocaleDateString(getCanonicalLocale(), {
		day: '2-digit',
		month: '2-digit',
		year: 'numeric',
	})
}

/**
 * Parses a YYYY-MM-DD string as a local-midnight Date to avoid UTC offset shifts.
 * `new Date('YYYY-MM-DD')` produces UTC midnight, which becomes the previous day
 * in UTC- timezones when local date parts are read back.
 * @param {string|null} dateString - ISO date string (YYYY-MM-DD)
 * @returns {Date|null}
 */
export function parseLocalDate(dateString) {
	if (!dateString) return null
	const parts = dateString.split('-')
	if (parts.length !== 3) return null
	const year = parseInt(parts[0], 10)
	const month = parseInt(parts[1], 10) - 1
	const day = parseInt(parts[2], 10)
	if (isNaN(year) || isNaN(month) || isNaN(day)) return null
	// setFullYear statt Konstruktor: new Date(year, ...) mappt Jahre 0-99 auf
	// 1900-1999 und macht damit Tipp-Zwischenstaende wie "0002" zu 1902 (#258)
	const date = new Date(0)
	date.setFullYear(year, month, day)
	date.setHours(0, 0, 0, 0)
	return date
}

/**
 * Formats a date for input fields (YYYY-MM-DD)
 * @param {string|Date|null} dateInput - Date to format
 * @returns {string} ISO date string (YYYY-MM-DD) or empty string
 */
export function formatDateForInput(dateInput) {
	if (!dateInput) return ''

	const date = dateInput instanceof Date ? dateInput : new Date(dateInput)

	if (isNaN(date.getTime())) return ''

	// Use local date parts to avoid timezone conversion issues
	// (toISOString() converts to UTC which can shift the date by -1 day)
	// Jahr auf 4 Stellen padden: <input type="date"> verlangt YYYY-MM-DD,
	// sonst zerstoert der v-model-Writeback Tipp-Zwischenstaende (#258)
	const year = String(date.getFullYear()).padStart(4, '0')
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

/**
 * Checks if a date is in the past
 * @param {string|Date} dateInput - Date to check
 * @returns {boolean}
 */
export function isDateInPast(dateInput) {
	if (!dateInput) return false

	const date = dateInput instanceof Date ? dateInput : new Date(dateInput)
	const today = new Date()
	today.setHours(0, 0, 0, 0)

	return date < today
}

/**
 * Checks if a date is within the next N days
 * @param {string|Date} dateInput - Date to check
 * @param {number} days - Number of days
 * @returns {boolean}
 */
export function isDateWithinDays(dateInput, days) {
	if (!dateInput) return false

	const date = dateInput instanceof Date ? dateInput : new Date(dateInput)
	const today = new Date()
	today.setHours(0, 0, 0, 0)

	const futureDate = new Date(today)
	futureDate.setDate(futureDate.getDate() + days)

	return date >= today && date <= futureDate
}
