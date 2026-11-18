/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/license')

export type LicenseState = 'none' | 'invalid' | 'valid' | 'grace' | 'expired'

/** Antwort von `GET /api/license` (entitlement-v1, eine Lizenz je Nextcloud). */
export interface LicenseStatus {
	instanceUuid: string
	keySet: boolean
	keyHint: string | null
	state: LicenseState
	tier: 'free' | 'werkplus'
	features: string[]
	licensedEmployees: number | null
	/** Schluessel aus dem Testzeitraum; fehlt der Anspruch im Token, false. */
	trial: boolean
	/** Laufzeitende der Lizenz. Nie durch expiresAt ersetzen, das ist nur der naechste Prueftermin. */
	licenseValidUntil: string | null
	/** Schluessel da, kein Token, Server meldete das Ende des Testzeitraums. */
	trialEnded: boolean
	expiresAt: string | null
	graceUntil: string | null
	lastCheckAt: string | null
	lastError: { code: string, message: string, at: string | null } | null
	storeTooNew: boolean
	canManage: boolean
}

/** Fehler mit dem Text des Servers und dem Code, nach dem die Oberflaeche verzweigt. */
export class LicenseError extends Error {

	constructor(message: string, public readonly code: string | undefined) {
		super(message)
	}

}

async function call<T>(request: () => Promise<{ data: T }>): Promise<T> {
	try {
		return (await request()).data
	} catch (e) {
		const data = (e as { response?: { data?: { error?: string, code?: string } } }).response?.data
		throw new LicenseError(data?.error ?? '', data?.code)
	}
}

export default {
	getLicense(): Promise<LicenseStatus> {
		return call(() => axios.get<LicenseStatus>(baseUrl))
	},

	activateLicense(key: string): Promise<LicenseStatus> {
		return call(() => axios.put<LicenseStatus>(baseUrl, { key }))
	},

	/** Der Schluessel steht nur in dieser Antwort vollstaendig. */
	startTrial(): Promise<{ key: string, status: LicenseStatus }> {
		return call(() => axios.post<{ key: string, status: LicenseStatus }>(`${baseUrl}/trial`, {}))
	},

	refreshLicense(): Promise<LicenseStatus> {
		return call(() => axios.post<LicenseStatus>(`${baseUrl}/refresh`, {}))
	},

	removeLicense(): Promise<LicenseStatus> {
		return call(() => axios.delete<LicenseStatus>(baseUrl))
	},
}
