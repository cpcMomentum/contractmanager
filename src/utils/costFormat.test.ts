import { describe, expect, it } from 'vitest'
import { costForApi, costValidationError, isCostValid, normalizeCostInput } from './costFormat'

describe('costValidationError (#315)', () => {
	it('distinguishes unreadable input from an amount that is too large', () => {
		// Zwei verschiedene Fehler brauchen zwei verschiedene Meldungen: bei
		// "abc" hilft ein Beispiel, bei 100 Millionen die Obergrenze.
		expect(costValidationError('abc')).toBe('format')
		expect(costValidationError('100000000')).toBe('range')
	})

	it('returns null for everything that can be stored', () => {
		expect(costValidationError('')).toBeNull()
		expect(costValidationError(null)).toBeNull()
		expect(costValidationError('10,50')).toBeNull()
		expect(costValidationError('99999999,99')).toBeNull()
	})
})

describe('isCostValid (#305)', () => {
	it('regression: rejects input the DECIMAL column cannot take', () => {
		// Vor dem Wechsel auf type="text" fing der Browser das ab. Ohne diese
		// Pruefung ginge "abc" bis in die Spalte und Postgres wirft
		// 'invalid input syntax for type numeric' — ein 500er fuer den Nutzer.
		expect(isCostValid('abc')).toBe(false)
		expect(isCostValid('1,2,3')).toBe(false)
		expect(isCostValid('12 34')).toBe(false)
		expect(isCostValid('10€')).toBe(false)
	})

	it('accepts an empty amount — the field is optional', () => {
		expect(isCostValid('')).toBe(true)
		expect(isCostValid('   ')).toBe(true)
		expect(isCostValid(null)).toBe(true)
		expect(isCostValid(undefined)).toBe(true)
	})

	it('accepts every form the normalisation understands', () => {
		expect(isCostValid('10')).toBe(true)
		expect(isCostValid('10.50')).toBe(true)
		expect(isCostValid('10,50')).toBe(true)
		expect(isCostValid('1.234,56')).toBe(true)
		expect(isCostValid('-10,5')).toBe(true)
		expect(isCostValid(10.5)).toBe(true)
	})

	it('regression: rejects amounts the DECIMAL(10,2) column cannot hold (#315)', () => {
		// DECIMAL(10,2) fasst 8 Vorkommastellen, also hoechstens 99999999.99.
		// Ohne diese Pruefung liefe der Wert bis in die Spalte und scheiterte
		// erst dort — der Nutzer saehe einen Serverfehler statt einer Meldung.
		expect(isCostValid('100000000')).toBe(false)
		expect(isCostValid('99999999.995')).toBe(false) // rundet auf 100000000.00
		expect(isCostValid('-100000000')).toBe(false)
		expect(isCostValid('1234567890,12')).toBe(false)
	})

	it('accepts the largest amount the column can hold (#315)', () => {
		expect(isCostValid('99999999.99')).toBe(true)
		expect(isCostValid('99999999,99')).toBe(true)
		expect(isCostValid('-99999999.99')).toBe(true)
	})
})

describe('normalizeCostInput (#305)', () => {
	it('regression: keeps the trailing zero a native number input drops', () => {
		// Der eigentliche Bug: <input type="number"> mit v-model laesst Vue den
		// Wert zur JS-Zahl casten, 10.50 wird zu 10.5 und genau so angezeigt.
		expect(normalizeCostInput(10.5)).toBe('10.50')
		expect(normalizeCostInput('10.5')).toBe('10.50')
	})

	it('accepts a German comma as decimal separator', () => {
		expect(normalizeCostInput('10,5')).toBe('10.50')
		expect(normalizeCostInput('10,50')).toBe('10.50')
	})

	it('treats the dot as a thousands separator when a comma is present', () => {
		expect(normalizeCostInput('1.234,56')).toBe('1234.56')
	})

	it('leaves an already canonical value untouched', () => {
		expect(normalizeCostInput('10.50')).toBe('10.50')
	})

	it('pads whole numbers to two decimals', () => {
		expect(normalizeCostInput('10')).toBe('10.00')
		expect(normalizeCostInput(0)).toBe('0.00')
	})

	it('rounds to the two decimals the DECIMAL(10,2) column can hold', () => {
		expect(normalizeCostInput('10,999')).toBe('11.00')
		expect(normalizeCostInput('10.994')).toBe('10.99')
	})

	it('keeps empty input empty', () => {
		expect(normalizeCostInput('')).toBe('')
		expect(normalizeCostInput('   ')).toBe('')
		expect(normalizeCostInput(null)).toBe('')
		expect(normalizeCostInput(undefined)).toBe('')
	})

	it('leaves unparseable input untouched so the user can correct it', () => {
		// Nicht stillschweigend auf 0.00 setzen — das wuerde eine Eingabe
		// veraendern, statt sie als fehlerhaft stehen zu lassen.
		expect(normalizeCostInput('abc')).toBe('abc')
		expect(normalizeCostInput('1,2,3')).toBe('1,2,3')
		expect(normalizeCostInput('12 34')).toBe('12 34')
	})

	it('trims surrounding whitespace', () => {
		expect(normalizeCostInput('  10,5  ')).toBe('10.50')
	})

	it('keeps negative amounts negative', () => {
		expect(normalizeCostInput('-10,5')).toBe('-10.50')
	})
})

describe('costForApi (#305)', () => {
	it('regression: sends two decimals instead of the truncated number', () => {
		// Vor dem Fix schickte das Formular String(10.5) === "10.5".
		expect(costForApi(10.5)).toBe('10.50')
		expect(costForApi('10,50')).toBe('10.50')
	})

	it('maps empty input to null, as the API expects for "no amount"', () => {
		expect(costForApi('')).toBeNull()
		expect(costForApi('   ')).toBeNull()
		expect(costForApi(null)).toBeNull()
		expect(costForApi(undefined)).toBeNull()
	})

	it('passes unparseable input through so the backend can reject it', () => {
		expect(costForApi('abc')).toBe('abc')
	})
})
