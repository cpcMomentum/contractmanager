import { describe, it, expect } from 'vitest'
import { reminderEnabledForEndDate } from './reminderForm'

/**
 * Regression tests for #180: "Erinnerung lässt sich im Vertrag nicht deaktivieren".
 * Loading an existing contract sets the end date, which previously force-enabled
 * the reminder and overrode the saved "disabled" state.
 */
describe('reminderEnabledForEndDate (#180)', () => {
	it('keeps a saved disabled reminder when an end date is present', () => {
		expect(reminderEnabledForEndDate(false, '2026-12-31')).toBe(false)
	})

	it('keeps an enabled reminder when an end date is present', () => {
		expect(reminderEnabledForEndDate(true, '2026-12-31')).toBe(true)
	})

	it('disables the reminder when the end date is cleared', () => {
		expect(reminderEnabledForEndDate(true, null)).toBe(false)
		expect(reminderEnabledForEndDate(false, null)).toBe(false)
	})
})
