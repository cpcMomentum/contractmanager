// Payments per month on average; a week is 52/12, not 4, or the yearly sum comes out ~8 % low.
const MONTHLY_FACTOR: Record<string, number> = {
	weekly: 52 / 12,
	monthly: 1,
	quarterly: 1 / 3,
	semi_annual: 1 / 6,
	yearly: 1 / 12,
}

export function hasMonthlyEquivalent(interval: string | null | undefined): boolean {
	return interval !== null && interval !== undefined && Object.hasOwn(MONTHLY_FACTOR, interval)
}

export function monthlyCost(cost: number, interval: string | null | undefined): number | null {
	if (!hasMonthlyEquivalent(interval)) {
		return null
	}
	return cost * MONTHLY_FACTOR[interval as string]
}
