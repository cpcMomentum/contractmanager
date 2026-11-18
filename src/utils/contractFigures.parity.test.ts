import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import fixture from '../../tests/fixtures/contract-figures.json'
import { getDisplayStatus } from './contractStatus'
import { monthlyCost } from './costInterval'
import { calculateCancellationDeadline, getEffectiveEndDate } from './periodUtils.js'
import type { Contract } from '../store/contracts'

function ymd(date: Date | null): string | null {
	if (!date) return null
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function figures(contract: Contract) {
	const options = { status: contract.status, cancelledTo: contract.cancelledTo, deadlineType: contract.cancellationDeadlineType }
	const cost = contract.cost ? parseFloat(contract.cost) : null
	const monthly = cost === null ? null : monthlyCost(cost, contract.costInterval)
	return {
		status: getDisplayStatus(contract, fixture.defaultReminderDays),
		runsUntil: ymd(getEffectiveEndDate(contract.endDate as string, contract.contractType as string, contract.renewalPeriod as string, options)),
		// The list shows a cancellation deadline only for renewing contracts (getDeadlineInfo).
		deadline: contract.contractType !== 'auto_renewal' ? null : ymd(calculateCancellationDeadline(contract.endDate as string, contract.cancellationPeriod as string, contract.contractType as string, contract.renewalPeriod as string, options)),
		monthly: monthly === null ? null : Math.round(monthly * 100) / 100,
	}
}

// The PHP side (ContractFiguresTest) checks the same file, so list and reports agree.
describe('contract figures fixture', () => {
	beforeAll(() => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date(`${fixture.today}T10:00:00`))
	})
	afterAll(() => {
		vi.useRealTimers()
	})

	for (const c of fixture.cases) {
		it(c.name, () => {
			const contract = { id: 1, cancellationDeadlineType: 'normal', ...c.contract } as Contract
			expect(figures(contract)).toEqual((c as { expected?: unknown }).expected)
		})
	}
})
