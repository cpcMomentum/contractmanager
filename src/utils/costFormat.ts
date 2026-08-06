/**
 * Normalising a contract amount for display and for the API (#305).
 *
 * A money amount carries the information "two decimals" — 10,50 is not the same
 * text as 10,5, even though it is the same number. A JavaScript number cannot
 * carry that information, so the amount is kept as a string throughout the form
 * and normalised here.
 *
 * This also removes the reason the bug existed: a native `<input type="number">`
 * makes Vue cast the model to a number on every keystroke (regardless of the
 * `.number` modifier) and writes the cast value back into the field on `change`,
 * which is what visibly turned 10,50 into 10,5. The field is a text input now.
 *
 * Normalising on display also covers installations on SQLite, where the
 * DECIMAL(10,2) column has NUMERIC affinity and the server already returns
 * "10.5" instead of "10.50".
 */

/**
 * Normalise a user-entered or server-delivered amount to a canonical string with
 * exactly two decimals.
 *
 * Accepts both decimal separators: German input uses a comma, the API uses a
 * dot. When both appear the dot is read as a thousands separator ("1.234,56").
 * A lone dot is the canonical API form and stays the decimal separator.
 *
 * Input that is not a number is returned unchanged rather than replaced by
 * "0.00" — a typo should stay visible for the user to correct, not be silently
 * turned into a valid-looking amount.
 *
 * @param value The raw value from the input field, the API or the AI extraction
 * @return Canonical amount with two decimals, '' for empty, input unchanged when unparseable
 */
export function normalizeCostInput(value: string | number | null | undefined): string {
	if (value === null || value === undefined) {
		return ''
	}

	const raw = String(value).trim()
	if (raw === '') {
		return ''
	}

	const canonical = raw.includes(',')
		? raw.replace(/\./g, '').replace(',', '.')
		: raw

	const amount = Number(canonical)
	if (!Number.isFinite(amount)) {
		return raw
	}

	return amount.toFixed(2)
}

/**
 * The largest amount the `cost` column can hold: it is DECIMAL(10,2), so eight
 * digits before the decimal point (`Version010000Date20260116120000.php`).
 */
const MAX_COST = 99999999.99

/**
 * Whether the entered amount can be stored at all.
 *
 * Two ways it cannot be. Both end in a database error rather than a message the
 * user can act on, so both are caught here:
 *
 * 1. It is not a number. The field is a text input (see above), so the browser
 *    no longer rejects non-numeric input the way a `type="number"` field did —
 *    "abc" would reach the column and Postgres answers with
 *    `invalid input syntax for type numeric`.
 * 2. It does not fit the column (#315). DECIMAL(10,2) holds at most
 *    99999999.99; anything larger is rejected by the database on write.
 *
 * The range is checked against the *normalised* value, because rounding can
 * push a value over the edge: 99999999.995 becomes 100000000.00.
 *
 * An empty amount is valid: the field is optional.
 *
 * @param value The current form value
 * @return true when the value is empty, or a number the column can hold
 */
export function isCostValid(value: string | number | null | undefined): boolean {
	return costValidationError(value) === null
}

/**
 * Why an amount cannot be stored, so the form can say something useful instead
 * of one message for two different problems (#315).
 *
 * @param value The current form value
 * @return 'format' when it is not a number, 'range' when it does not fit the
 *   column, null when it can be stored
 */
export function costValidationError(value: string | number | null | undefined): 'format' | 'range' | null {
	const normalized = normalizeCostInput(value)
	if (normalized === '') {
		return null
	}

	const amount = Number(normalized)
	if (!Number.isFinite(amount)) {
		return 'format'
	}

	return Math.abs(amount) > MAX_COST ? 'range' : null
}

/**
 * The amount as the API expects it: a canonical two-decimal string, or null when
 * no amount is set. The backend declares cost as `?string` and stores it in a
 * DECIMAL(10,2) column.
 *
 * @param value The current form value
 * @return Two-decimal string, or null for an empty amount
 */
export function costForApi(value: string | number | null | undefined): string | null {
	const normalized = normalizeCostInput(value)
	return normalized === '' ? null : normalized
}
