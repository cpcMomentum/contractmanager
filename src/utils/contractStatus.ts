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
		{ status: contract.status, cancelledTo: contract.cancelledTo ?? undefined, deadlineType: contract.cancellationDeadlineType ?? undefined },
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
 * "Ending soon" (fixed) — a fixed-term contract whose end date is approaching
 * (today ≤ endDate ≤ today + N days) but not yet passed. Fixed contracts have
 * no cancellation deadline, so the warning is measured against the end date
 * itself (#238).
 *
 * The auto_renewal counterpart `isEndingSoon` warns before the cancellation
 * deadline; this one warns before the contract simply expires. UI-only derived
 * state — the underlying status stays `active`.
 */
export function isEndingSoonFixed(
	contract: Contract,
	defaultReminderDays: number = DEFAULT_REMINDER_DAYS_1,
): boolean {
	if (contract.status !== 'active') return false
	if (contract.contractType !== 'fixed') return false
	if (!contract.endDate) return false

	const reminderDays = contract.reminderDays ?? defaultReminderDays
	const today = new Date()
	today.setHours(0, 0, 0, 0)

	const end = new Date(contract.endDate)
	end.setHours(0, 0, 0, 0)

	const windowStart = new Date(end)
	windowStart.setDate(windowStart.getDate() - reminderDays)

	return today >= windowStart && today <= end
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

/**
 * "Ended" (cancelled) — a cancelled contract whose effective termination date
 * has been reached. Mirrors the backend `ContractMapper::findCancelledDue`
 * boundary (`cancelled_to <= today`, inclusive of the termination day) so the
 * list shows „Beendet" the moment the contract is effectively over, instead of
 * lagging behind the once-a-day `StatusUpdateJob` that flips the stored status
 * to `ended` and archives it (#288).
 *
 * The effective end is the „cancelled to" date if set (e.g. a special
 * termination right), otherwise the regular end date — the same rule the
 * background job uses. UI-only derived state: the stored status stays
 * `cancelled` until the daily job runs, which keeps a typo in „cancelled to"
 * correctable before the contract is actually archived.
 *
 * Note the deliberate asymmetry with `isExpiredFixed`, which uses a strict
 * `<` (a fixed contract still runs on its end date). The cancelled boundary is
 * inclusive because the backend termination query is.
 */
export function isEndedCancelled(contract: Contract): boolean {
	if (contract.status !== 'cancelled') return false

	const effectiveEnd = contract.cancelledTo ?? contract.endDate
	if (!effectiveEnd) return false

	const today = new Date()
	today.setHours(0, 0, 0, 0)
	const end = new Date(effectiveEnd)
	end.setHours(0, 0, 0, 0)

	return end <= today
}
