import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export interface ExtractionStatus {
	configured: boolean
	provider?: string
}

export interface ExtractionData {
	[key: string]: unknown
	confidence?: number
	extractionNotes?: string
}

export interface ExtractionResult {
	success?: boolean
	data?: ExtractionData
	error?: string
	isScanned?: boolean
	pageCount?: number
}

export default {
	async extractFromPdf(filePath: string): Promise<ExtractionResult> {
		const response = await axios.post<ExtractionResult>(
			generateUrl('/apps/contractmanager/api/extraction/extract'),
			{ filePath },
		)
		return response.data
	},

	async getStatus(): Promise<ExtractionStatus> {
		const response = await axios.get<ExtractionStatus>(
			generateUrl('/apps/contractmanager/api/extraction/status'),
		)
		return response.data
	},
}
