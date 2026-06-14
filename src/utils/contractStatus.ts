import { calculateCancellationDeadline } from './periodUtils.js'
import type { Contract } from '../store/contracts'

/**
 * Default first-reminder window when the user has not overridden it on the
 * contract and the admin setting is unavailable. Mirrors the backend default
 * in `SettingsService::DEFAULT_REMINDER_DAYS_1`.
 */
export const DEFAULT_REMINDER_DAYS_1 = 14

/**
 * "Ending soon" — an auto_renewal contract whose cancellation deadline falls
 * inside the user's first-reminder window (today ≤ deadline ≤ today + N days).
 *
 * This is a UI-only derived state. The underlying contract status stays
 * `active`; the indicator just nudges the user before the deadline passes.
 */
export function isEndingSoon(
	contract: Contract,
	defaultReminderDays: number = DEFAULT_REMINDER_DAYS_1,
): boolean {
	if (contract.status !== 'active') return false
	if (contract.contractType !== 'auto_renewal') return false
	if (!contract.endDate || !contract.cancellationPeriod) return false

	const deadline = calculateCancellationDeadline(
		contract.endDate,
		contract.cancellationPeriod,
		contract.contractType,
		contract.renewalPeriod ?? undefined,
		{ status: contract.status, cancelledTo: contract.cancelledTo ?? undefined },
	)
	if (!deadline) return false

	const reminderDays = contract.reminderDays ?? defaultReminderDays
	const today = new Date()
	today.setHours(0, 0, 0, 0)

	const windowStart = new Date(deadline)
	windowStart.setDate(windowStart.getDate() - reminderDays)
	windowStart.setHours(0, 0, 0, 0)

	const windowEnd = new Date(deadline)
	windowEnd.setHours(0, 0, 0, 0)

	return today >= windowStart && today <= windowEnd
}

/**
 * "Expired" — a fixed contract whose end date is in the past but whose status
 * has not yet been flipped to `ended` by the background job. The job runs on
 * a schedule (every 6h), so there is a window where this can happen.
 *
 * Returning true here lets the UI signal expiry immediately instead of waiting
 * for the next cron tick.
 */
export function isExpiredFixed(contract: Contract): boolean {
	if (contract.status !== 'active') return false
	if (contract.contractType !== 'fixed') return false
	if (!contract.endDate) return false

	const today = new Date()
	today.setHours(0, 0, 0, 0)
	const end = new Date(contract.endDate)
	end.setHours(0, 0, 0, 0)

	return end < today
}
