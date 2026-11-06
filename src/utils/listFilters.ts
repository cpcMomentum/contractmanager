import { getDeadlineInfo } from './periodUtils.js'
import { monthlyCost } from './costInterval'
import type { Contract } from '../store/contracts'

export const DEADLINE_WITHIN_OPTIONS = [30, 60, 90, 180]

// Uses the date of the "Frist / Ende" column, so the filter matches what the list shows.
export function isDeadlineWithin(contract: Contract, days: number): boolean {
	const date = getDeadlineInfo(contract).date
	if (!date) return false

	const today = new Date()
	today.setHours(0, 0, 0, 0)
	const limit = new Date(today)
	limit.setDate(limit.getDate() + days)

	const day = new Date(date)
	day.setHours(0, 0, 0, 0)
	return day >= today && day <= limit
}

export function hasMonthlyCostAtLeast(contract: Contract, minimum: number): boolean {
	const cost = parseFloat(String(contract.cost ?? ''))
	if (!Number.isFinite(cost)) return false
	const monthly = monthlyCost(cost, contract.costInterval)
	if (monthly === null) return false
	// Cent rounding, so 120 € yearly still counts as "ab 10 €".
	return Math.round(monthly * 100) >= Math.round(minimum * 100)
}
