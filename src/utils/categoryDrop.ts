/**
 * Helpers for assigning a contract's category by dragging its list row onto a
 * category entry in the sidebar (#359).
 */

/**
 * Custom drag data type. Using an app-specific MIME-like type (rather than
 * plain "text/plain") means the sidebar drop targets only react to a contract
 * row being dragged, not to arbitrary text or files dropped from outside.
 */
export const CONTRACT_DND_TYPE = 'application/x-contractmanager-contract-id'

/**
 * The sentinel key used for the "Ohne Kategorie" (uncategorized) drop target,
 * mirroring the value the sidebar uses for its category filter.
 */
export const UNCATEGORIZED_KEY = 'uncategorized'

/**
 * Read the dragged contract id from a drop event's dataTransfer.
 * Returns the numeric id, or null if the payload is missing or not a contract
 * drag (e.g. text or a file was dropped instead).
 */
export function parseContractId(dataTransfer: DataTransfer | null): number | null {
	if (!dataTransfer) {
		return null
	}
	const raw = dataTransfer.getData(CONTRACT_DND_TYPE)
	if (!raw) {
		return null
	}
	const id = Number.parseInt(raw, 10)
	return Number.isInteger(id) && id > 0 ? id : null
}

/**
 * Map a sidebar drop-target key to the categoryId to store on the contract.
 * A real category key is its numeric id; the uncategorized target clears the
 * category (null). The "all contracts" entry is not a drop target, so it never
 * reaches here.
 */
export function resolveTargetCategoryId(key: number | string): number | null {
	if (key === UNCATEGORIZED_KEY) {
		return null
	}
	const id = typeof key === 'number' ? key : Number.parseInt(key, 10)
	return Number.isInteger(id) ? id : null
}
