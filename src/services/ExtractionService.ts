import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export interface ExtractionStatus {
	configured: boolean
}

export interface ExtractionResult {
	[key: string]: unknown
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
