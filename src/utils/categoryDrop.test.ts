import { describe, expect, it } from 'vitest'
import {
	CONTRACT_DND_TYPE,
	UNCATEGORIZED_KEY,
	parseContractId,
	resolveTargetCategoryId,
} from './categoryDrop'

/** Minimal DataTransfer stub carrying a single typed payload. */
function dataTransfer(type: string, value: string): DataTransfer {
	return {
		getData: (t: string) => (t === type ? value : ''),
	} as unknown as DataTransfer
}

describe('parseContractId (#359)', () => {
	it('reads a positive integer id from the contract drag type', () => {
		expect(parseContractId(dataTransfer(CONTRACT_DND_TYPE, '42'))).toBe(42)
	})

	it('returns null when no dataTransfer is present', () => {
		expect(parseContractId(null)).toBeNull()
	})

	it('ignores payloads under a different type (plain text, files)', () => {
		expect(parseContractId(dataTransfer('text/plain', '42'))).toBeNull()
	})

	it('rejects non-numeric or non-positive payloads', () => {
		expect(parseContractId(dataTransfer(CONTRACT_DND_TYPE, 'abc'))).toBeNull()
		expect(parseContractId(dataTransfer(CONTRACT_DND_TYPE, '0'))).toBeNull()
		expect(parseContractId(dataTransfer(CONTRACT_DND_TYPE, '-5'))).toBeNull()
		expect(parseContractId(dataTransfer(CONTRACT_DND_TYPE, ''))).toBeNull()
	})
})

describe('resolveTargetCategoryId (#359)', () => {
	it('maps a numeric category key to that id', () => {
		expect(resolveTargetCategoryId(7)).toBe(7)
	})

	it('maps a numeric string category key to a number', () => {
		expect(resolveTargetCategoryId('7')).toBe(7)
	})

	it('maps the uncategorized target to null (clears the category)', () => {
		expect(resolveTargetCategoryId(UNCATEGORIZED_KEY)).toBeNull()
	})
})
