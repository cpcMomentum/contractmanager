/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/reports')

export type ReportType = 'contracts' | 'costs' | 'deadlines'
export type ReportFormat = 'pdf' | 'csv'

export class ReportError extends Error {

	constructor(message: string, public readonly werkplusLimit: boolean) {
		super(message)
	}

}

function fileNameFrom(disposition: string | undefined, fallback: string): string {
	const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(disposition ?? '')
	if (utf8) return decodeURIComponent(utf8[1])
	const plain = /filename="?([^";]+)"?/i.exec(disposition ?? '')
	return plain ? plain[1] : fallback
}

// Errors arrive as a Blob because the request asks for one.
async function errorFrom(e: unknown): Promise<ReportError> {
	const data = (e as { response?: { data?: unknown } }).response?.data
	try {
		const body = JSON.parse(data instanceof Blob ? await data.text() : String(data ?? '')) as { error?: string, werkplusLimit?: boolean }
		return new ReportError(body.error ?? '', body.werkplusLimit === true)
	} catch {
		return new ReportError('', false)
	}
}

export default {
	async isAvailable(): Promise<boolean> {
		const response = await axios.get<{ available: boolean }>(baseUrl)
		return response.data.available === true
	},

	async download(type: ReportType, format: ReportFormat, ids: number[], months: number): Promise<void> {
		let response
		try {
			response = await axios.post<Blob>(`${baseUrl}/${type}`, { format, ids, months }, { responseType: 'blob' })
		} catch (e) {
			throw await errorFrom(e)
		}
		const url = URL.createObjectURL(response.data)
		const link = document.createElement('a')
		link.href = url
		link.download = fileNameFrom(response.headers['content-disposition'], `${type}.${format}`)
		document.body.appendChild(link)
		link.click()
		link.remove()
		URL.revokeObjectURL(url)
	},
}
