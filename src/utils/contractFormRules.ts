import { getEffectiveEndDate } from './periodUtils.js'

/**
 * Whether the end-date field applies to a contract type.
 *
 * Unlimited contracts have no end date by definition (#257): the field is
 * disabled in the form, a value kept in memory is ignored for validation and
 * never saved. Keeping the in-memory value means switching the type back
 * restores what the user had typed.
 *
 * @param contractType 'fixed' | 'auto_renewal' | 'unlimited' | null
 */
export function isEndDateApplicable(contractType: string | null): boolean {
	return contractType !== 'unlimited'
}

/**
 * The end date that may be persisted for a contract type: the given date for
 * fixed/auto_renewal, always null for unlimited (#257).
 */
export function endDateForSave(contractType: string | null, endDate: Date | null): Date | null {
	return isEndDateApplicable(contractType) ? endDate : null
}

interface FormEndInput {
	endDate: Date | null
	contractType: string | null
	renewalPeriod: string | null
	status: string | null
	cancelledOn: Date | null
	cancelledTo: Date | null
}

// Same rule as the list column, so the modal header never shows a stale, already renewed end date.
export function effectiveEndForForm(form: FormEndInput): Date | null {
	if (!form.endDate) return null
	return getEffectiveEndDate(form.endDate, form.contractType ?? '', form.renewalPeriod ?? '', {
		status: form.status ?? undefined,
		cancelledTo: form.cancelledOn ? form.cancelledTo : null,
	})
}
