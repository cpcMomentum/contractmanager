import { describe, expect, it } from 'vitest'
import { isAiActive } from './aiSettings'

describe('isAiActive (#151)', () => {
	it('is inactive without a provider', () => {
		expect(isAiActive({ aiProvider: '', aiApiKey: '' })).toBe(false)
		expect(isAiActive({ aiProvider: '', aiApiKey: 'sk-real-key' })).toBe(false)
	})

	it('is inactive with a provider but no key — the confusing "looks configured" case', () => {
		// A provider is chosen (URL/model placeholders would show) but no key is
		// stored, so analysis cannot run. Must read as inactive, not active.
		expect(isAiActive({ aiProvider: 'claude', aiApiKey: '' })).toBe(false)
	})

	it('is active with a provider and a stored (masked) key', () => {
		expect(isAiActive({ aiProvider: 'claude', aiApiKey: '••••••••' })).toBe(true)
	})

	it('is active with a provider and a freshly entered clear-text key', () => {
		expect(isAiActive({ aiProvider: 'openai_compatible', aiApiKey: 'sk-abc123' })).toBe(true)
	})

	it('treats null/undefined as absent', () => {
		expect(isAiActive({ aiProvider: null, aiApiKey: null })).toBe(false)
		expect(isAiActive({ aiProvider: 'claude', aiApiKey: null })).toBe(false)
		expect(isAiActive({})).toBe(false)
	})
})
