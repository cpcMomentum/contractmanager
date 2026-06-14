/**
 * Decide the reminder-enabled state when a contract's end date changes.
 *
 * Reminders require an end date, so clearing it always disables them. When an
 * end date is present, the previously chosen state is kept unchanged — we must
 * NOT auto-enable, otherwise loading an existing contract (which sets the end
 * date) would override a saved "disabled" reminder (#180).
 *
 * @param current The current reminderEnabled value
 * @param endDate The (new) end date value, or null when cleared
 * @return The reminderEnabled value to apply
 */
export function reminderEnabledForEndDate(current: boolean, endDate: string | null): boolean {
	return endDate === null ? false : current
}
