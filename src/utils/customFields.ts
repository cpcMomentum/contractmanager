/**
 * Helpers for the admin custom-field settings (#368).
 *
 * The active state is a real per-field flag, independent of the label. Enabling
 * a field must NOT prefill its label (the old behaviour dropped a hardcoded
 * value in that you had to delete first), and clearing the label must not turn
 * the field off. Older setups have no stored flag; there we fall back to the
 * historical rule "label not empty = active" so nothing silently switches off.
 */

export interface CustomFieldSettingsLike {
	[key: string]: unknown
}

/** Payload/state key for a field's active flag (1-3). */
export function customFieldEnabledKey(n: number): string {
	return 'customField' + n + 'Enabled'
}

/** Payload/state key for a field's label (1-3). */
export function customFieldLabelKey(n: number): string {
	return 'customFieldLabel' + n
}

/**
 * Resolve a field's active state from loaded settings. Uses the explicit flag
 * when present, otherwise falls back to "label not empty" for pre-#368 data.
 */
export function resolveCustomFieldEnabled(settings: CustomFieldSettingsLike, n: number): boolean {
	const flag = settings[customFieldEnabledKey(n)]
	if (typeof flag === 'boolean') {
		return flag
	}
	return String(settings[customFieldLabelKey(n)] ?? '') !== ''
}
