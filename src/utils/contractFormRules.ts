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
