import { describe, expect, it } from 'vitest'
import type { Contract } from '../store/contracts'
import { contractForDuplicate } from './contractDuplicate'

/** A cancelled, archived contract: exactly the case reported in #307. */
const cancelled: Contract = {
	id: 42,
	name: 'Stromvertrag',
	vendor: 'Stadtwerke',
	status: 'cancelled',
	categoryId: 7,
	startDate: '2019-03-01',
	endDate: '2026-02-28',
	cancelledOn: '2026-08-01',
	cancelledTo: '2026-02-28',
	cancellationPeriod: '3 months',
	contractType: 'auto_renewal',
	renewalPeriod: '12 months',
	cost: '84.50',
	currency: 'EUR',
	costInterval: 'monthly',
	reminderEnabled: true,
	reminderDays: 30,
	notes: 'Kundennummer 12345',
	isPrivate: true,
	archived: true,
}

describe('contractForDuplicate (#307)', () => {
	it('clears the term dates so the picker opens on the current month', () => {
		const copy = contractForDuplicate(cancelled, 'Kopie')

		expect(copy.startDate).toBeNull()
		expect(copy.endDate).toBeNull()
	})

	it('drops the cancellation of the original, which never applied to the copy', () => {
		const copy = contractForDuplicate(cancelled, 'Kopie')

		expect(copy.cancelledOn).toBeNull()
		expect(copy.cancelledTo).toBeNull()
		expect(copy.status).toBe('active')
	})

	it('never marks a new contract as archived or trashed', () => {
		const copy = contractForDuplicate(cancelled, 'Kopie')

		expect(copy.archived).toBe(false)
		expect(copy.deletedAt).toBeNull()
	})

	it('keeps everything worth reusing for a follow-up contract', () => {
		const copy = contractForDuplicate(cancelled, 'Kopie')

		expect(copy.vendor).toBe('Stadtwerke')
		expect(copy.categoryId).toBe(7)
		expect(copy.cost).toBe('84.50')
		expect(copy.currency).toBe('EUR')
		expect(copy.costInterval).toBe('monthly')
		expect(copy.cancellationPeriod).toBe('3 months')
		expect(copy.contractType).toBe('auto_renewal')
		expect(copy.renewalPeriod).toBe('12 months')
		expect(copy.reminderEnabled).toBe(true)
		expect(copy.reminderDays).toBe(30)
		expect(copy.notes).toBe('Kundennummer 12345')
	})

	it('keeps the private flag so a copy is never more visible than its original', () => {
		expect(contractForDuplicate(cancelled, 'Kopie').isPrivate).toBe(true)
		expect(contractForDuplicate({ ...cancelled, isPrivate: false }, 'Kopie').isPrivate).toBe(false)
	})

	it('marks the copy as new and appends the translated suffix', () => {
		const copy = contractForDuplicate(cancelled, 'Kopie')

		expect(copy.id).toBeNull()
		expect(copy.name).toBe('Stromvertrag (Kopie)')
		expect(contractForDuplicate(cancelled, 'copy').name).toBe('Stromvertrag (copy)')
	})

	it('leaves the original untouched', () => {
		contractForDuplicate(cancelled, 'Kopie')

		expect(cancelled.startDate).toBe('2019-03-01')
		expect(cancelled.cancelledOn).toBe('2026-08-01')
		expect(cancelled.archived).toBe(true)
	})
})
