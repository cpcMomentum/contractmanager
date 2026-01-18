import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/settings')

export default {
	// User Settings
	async getUserSettings() {
		const response = await axios.get(baseUrl)
		return response.data
	},

	async updateUserSettings(settings) {
		const response = await axios.put(baseUrl, settings)
		return response.data
	},

	// Admin Settings
	async getAdminSettings() {
		const response = await axios.get(`${baseUrl}/admin`)
		return response.data
	},

	async updateAdminSettings(settings) {
		const response = await axios.put(`${baseUrl}/admin`, settings)
		return response.data
	},
}
