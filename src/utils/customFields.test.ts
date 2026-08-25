import { describe, expect, it } from 'vitest'
import {
	customFieldEnabledKey,
	customFieldLabelKey,
	resolveCustomFieldEnabled,
} from './customFields'

describe('customFields (#368)', () => {
	describe('key helpers', () => {
		it('builds the enabled and label keys', () => {
			expect(customFieldEnabledKey(2)).toBe('customField2Enabled')
			expect(customFieldLabelKey(2)).toBe('customFieldLabel2')
		})
	})

	describe('resolveCustomFieldEnabled', () => {
		it('uses the explicit flag when present', () => {
			expect(resolveCustomFieldEnabled({ customField1Enabled: true, customFieldLabel1: '' }, 1)).toBe(true)
			expect(resolveCustomFieldEnabled({ customField1Enabled: false, customFieldLabel1: 'Kostenstelle' }, 1)).toBe(false)
		})

		it('an enabled field with an empty label stays enabled', () => {
			// The old "label empty = off" rule is gone: an active field may be blank.
			expect(resolveCustomFieldEnabled({ customField2Enabled: true, customFieldLabel2: '' }, 2)).toBe(true)
		})

		it('falls back to "label not empty" for pre-#368 data without a flag', () => {
			expect(resolveCustomFieldEnabled({ customFieldLabel3: 'Vertragsnummer' }, 3)).toBe(true)
			expect(resolveCustomFieldEnabled({ customFieldLabel3: '' }, 3)).toBe(false)
			expect(resolveCustomFieldEnabled({}, 3)).toBe(false)
		})
	})
})
