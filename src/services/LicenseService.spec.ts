/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach } from 'vitest'
import axios from '@nextcloud/axios'
import LicenseService, { LicenseError } from './LicenseService'

describe('LicenseService', () => {
	beforeEach(() => {
		vi.mocked(axios.get).mockReset()
		vi.mocked(axios.put).mockReset()
		vi.mocked(axios.post).mockReset()
		vi.mocked(axios.delete).mockReset()
	})

	it('spricht die Lizenz-Routen der App an', async () => {
		vi.mocked(axios.get).mockResolvedValue({ data: { keySet: false } })
		vi.mocked(axios.put).mockResolvedValue({ data: { keySet: true } })
		vi.mocked(axios.post).mockResolvedValue({ data: {} })
		vi.mocked(axios.delete).mockResolvedValue({ data: {} })

		await LicenseService.getLicense()
		await LicenseService.activateLicense('wp_ABC')
		await LicenseService.startTrial()
		await LicenseService.refreshLicense()
		await LicenseService.removeLicense()

		expect(axios.get).toHaveBeenCalledWith('/apps/contractmanager/api/license')
		expect(axios.put).toHaveBeenCalledWith('/apps/contractmanager/api/license', { key: 'wp_ABC' })
		expect(axios.post).toHaveBeenCalledWith('/apps/contractmanager/api/license/trial', {})
		expect(axios.post).toHaveBeenCalledWith('/apps/contractmanager/api/license/refresh', {})
		expect(axios.delete).toHaveBeenCalledWith('/apps/contractmanager/api/license')
	})

	it('reicht Text und Code des Servers als LicenseError weiter', async () => {
		vi.mocked(axios.post).mockRejectedValue({ response: { data: { error: 'Schon genutzt', code: 'TRIAL_ALREADY_USED' } } })

		const error = await LicenseService.startTrial().catch((e) => e)

		expect(error).toBeInstanceOf(LicenseError)
		expect(error.message).toBe('Schon genutzt')
		expect(error.code).toBe('TRIAL_ALREADY_USED')
	})

	it('ohne Antwort des Servers: leerer Text, kein Code', async () => {
		vi.mocked(axios.get).mockRejectedValue(new Error('Network Error'))

		const error = await LicenseService.getLicense().catch((e) => e)

		expect(error).toBeInstanceOf(LicenseError)
		expect(error.message).toBe('')
		expect(error.code).toBeUndefined()
	})
})
