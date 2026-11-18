/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { reactive } from 'vue'

/** Same list as SettingsService::ALLOWED_CURRENCIES. */
export const CURRENCIES = ['EUR', 'USD', 'CHF', 'GBP'] as const

interface Defaults {
	defaultAmountType: string
	defaultCurrency: string
}

const initial = loadState<Partial<Defaults>>('contractmanager', 'userPreferences', {})

// Shared and reactive so a settings change reaches list and form without a reload.
export const userDefaults = reactive<Defaults>({
	defaultAmountType: initial.defaultAmountType || 'netto',
	defaultCurrency: initial.defaultCurrency || 'EUR',
})
