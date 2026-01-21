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

	// Permission Settings (Admin only)
	async getPermissionSettings() {
		const response = await axios.get(`${baseUrl}/permissions`)
		return response.data
	},

	async updatePermissionSettings(settings) {
		const response = await axios.put(`${baseUrl}/permissions`, settings)
		return response.data
	},

	// Search users and groups for the permission picker
	async searchUsersAndGroups(query) {
		const response = await axios.get(generateUrl('/apps/contractmanager/api/settings/search-principals'), {
			params: { query },
		})
		return response.data
	},
}
