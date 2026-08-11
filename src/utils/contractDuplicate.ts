import type { Contract } from '../store/contracts'

/**
 * A contract prefilled into the create form. `id: null` is what tells
 * ContractForm it is in create mode (`ContractForm.vue`, `isEditMode`).
 */
export type ContractDraft = Omit<Partial<Contract>, 'id'> & { id: null }

/**
 * Build the contract that the create form is prefilled with when the user
 * duplicates an existing one (#307).
 *
 * Not carried over:
 *
 * - `startDate`/`endDate` describe the term of the original contract, not of
 *   the follow-up one. Copying them prefilled the start date with the old
 *   value, and a native `<input type="date">` always opens its picker on the
 *   month of the value it holds, which is what made users page back through
 *   the calendar for years. Cleared, the picker opens on the current month by
 *   itself. `startDate` stays required, so the save button remains disabled
 *   until a date is picked deliberately.
 * - `cancelledOn`/`cancelledTo` were doubly wrong: the copy is created as
 *   `active`, so it inherited a cancellation that never applied to it.
 * - `archived`/`deletedAt` describe where the original sits, never a new
 *   contract. Defensive today: the only caller is the active list, where
 *   `archived` is false anyway. ArchiveView renders the same list item but
 *   does not bind `@duplicate` at all, so duplicating from the archive is
 *   broken in its own way and is not fixed here.
 *
 * Everything worth reusing for a follow-up contract is kept: vendor, category,
 * cost, cancellation period, reminder settings, notes and the private flag.
 *
 * @param contract the contract being duplicated
 * @param copySuffix translated word appended to the name, e.g. 'Kopie'
 */
export function contractForDuplicate(contract: Contract, copySuffix: string): ContractDraft {
	return {
		...contract,
		id: null,
		name: `${contract.name ?? ''} (${copySuffix})`,
		status: 'active',
		startDate: null,
		endDate: null,
		cancelledOn: null,
		cancelledTo: null,
		archived: false,
		deletedAt: null,
	}
}
