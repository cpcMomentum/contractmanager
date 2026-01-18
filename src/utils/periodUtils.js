/**
 * Period utility functions for ContractManager
 * Handles period strings like "3 months", "14 days", "1 year"
 */

/**
 * Parses a period string into value and unit
 * @param {string|null} periodString - e.g. "3 months", "14 days"
 * @returns {{ value: number, unit: string } | null}
 */
export function parsePeriod(periodString) {
	if (!periodString) return null

	const match = periodString.match(/^(\d+)\s*(days?|weeks?|months?|years?)$/)
	if (!match) return null

	return {
		value: parseInt(match[1], 10),
		unit: match[2],
	}
}

/**
 * Subtracts a period from a date
 * @param {Date} date - The date to subtract from
 * @param {string} periodString - e.g. "3 months"
 * @returns {Date|null} New date or null if parsing fails
 */
export function subtractPeriod(date, periodString) {
	const period = parsePeriod(periodString)
	if (!period) return null

	const result = new Date(date)
	const { value, unit } = period

	if (unit.startsWith('day')) {
		result.setDate(result.getDate() - value)
	} else if (unit.startsWith('week')) {
		result.setDate(result.getDate() - (value * 7))
	} else if (unit.startsWith('month')) {
		result.setMonth(result.getMonth() - value)
	} else if (unit.startsWith('year')) {
		result.setFullYear(result.getFullYear() - value)
	}

	return result
}

/**
 * Adds a period to a date
 * @param {Date} date - The date to add to
 * @param {string} periodString - e.g. "12 months"
 * @returns {Date|null} New date or null if parsing fails
 */
export function addPeriod(date, periodString) {
	const period = parsePeriod(periodString)
	if (!period) return null

	const result = new Date(date)
	const { value, unit } = period

	if (unit.startsWith('day')) {
		result.setDate(result.getDate() + value)
	} else if (unit.startsWith('week')) {
		result.setDate(result.getDate() + (value * 7))
	} else if (unit.startsWith('month')) {
		result.setMonth(result.getMonth() + value)
	} else if (unit.startsWith('year')) {
		result.setFullYear(result.getFullYear() + value)
	}

	return result
}

/**
 * Formats a period string for display in German
 * @param {string|null} periodString - e.g. "3 months"
 * @returns {string} Formatted string e.g. "3 Monate"
 */
export function formatPeriod(periodString) {
	const period = parsePeriod(periodString)
	if (!period) return periodString || ''

	const units = {
		day: t('contractmanager', 'Tag'),
		days: t('contractmanager', 'Tage'),
		week: t('contractmanager', 'Woche'),
		weeks: t('contractmanager', 'Wochen'),
		month: t('contractmanager', 'Monat'),
		months: t('contractmanager', 'Monate'),
		year: t('contractmanager', 'Jahr'),
		years: t('contractmanager', 'Jahre'),
	}

	return `${period.value} ${units[period.unit] || period.unit}`
}

/**
 * Calculates the cancellation deadline for a contract
 * @param {string|Date} endDate - Contract end date
 * @param {string} cancellationPeriod - e.g. "3 months"
 * @returns {Date|null} Cancellation deadline or null
 */
export function calculateCancellationDeadline(endDate, cancellationPeriod) {
	if (!endDate || !cancellationPeriod) return null

	const end = endDate instanceof Date ? endDate : new Date(endDate)
	if (isNaN(end.getTime())) return null

	return subtractPeriod(end, cancellationPeriod)
}
