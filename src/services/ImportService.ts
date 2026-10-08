import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/import')

export interface ImportSummary {
	contracts: number
	duplicates: number
	invalid: number
	categories: number
	missingFiles: number
	unknownUsers: number
}

export default {
	async preview(path: string): Promise<ImportSummary> {
		const response = await axios.post(`${baseUrl}/preview`, { path })
		return response.data
	},

	async import(path: string): Promise<ImportSummary> {
		const response = await axios.post(baseUrl, { path })
		return response.data
	},
}
