import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	async extractFromPdf(filePath) {
		const response = await axios.post(
			generateUrl('/apps/contractmanager/api/extraction/extract'),
			{ filePath },
		)
		return response.data
	},

	async getStatus() {
		const response = await axios.get(
			generateUrl('/apps/contractmanager/api/extraction/status'),
		)
		return response.data
	},
}
