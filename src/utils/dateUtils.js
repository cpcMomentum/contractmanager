/**
 * Date utility functions for ContractManager
 */

/**
 * Formats a date string or Date object as DD.MM.YYYY
 * @param {string|Date|null} dateInput - ISO date string or Date object
 * @returns {string} Formatted date or empty string if invalid
 */
export function formatDate(dateInput) {
	if (!dateInput) return ''

	const date = dateInput instanceof Date ? dateInput : new Date(dateInput)

	if (isNaN(date.getTime())) return ''

	return date.toLocaleDateString('de-DE', {
		day: '2-digit',
		month: '2-digit',
		year: 'numeric',
	})
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

	return date.toISOString().split('T')[0]
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
