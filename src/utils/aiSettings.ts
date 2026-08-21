/**
 * Helpers for the AI-analysis settings block (#151).
 */

export interface AiSettingsLike {
	aiProvider?: string | null
	aiApiKey?: string | null
}

/**
 * Whether the AI contract analysis is actually ready to run.
 *
 * It needs BOTH a chosen provider AND a stored API key. The key arrives from
 * the server masked (a non-empty placeholder) when one is stored, and as the
 * entered clear text before saving — both count as "present". A chosen provider
 * without a key looks configured (URL/model placeholders appear) but cannot
 * analyse anything, so it is reported as inactive rather than active.
 */
export function isAiActive(settings: AiSettingsLike): boolean {
	return !!settings.aiProvider && (settings.aiApiKey ?? '') !== ''
}
