import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/settings')

export interface UserSettings {
	[key: string]: unknown
}

export interface AdminSettings {
	[key: string]: unknown
}

export interface PermissionSettings {
	editors: string[]
	[key: string]: unknown
}

export interface Principal {
	id: string
	displayName: string
	type: 'user' | 'group'
}

export default {
	// User Settings
	async getUserSettings(): Promise<UserSettings> {
		const response = await axios.get<UserSettings>(baseUrl)
		return response.data
	},

	async updateUserSettings(settings: Partial<UserSettings>): Promise<UserSettings> {
		const response = await axios.put<UserSettings>(baseUrl, settings)
		return response.data
	},

	// Admin Settings
	async getAdminSettings(): Promise<AdminSettings> {
		const response = await axios.get<AdminSettings>(`${baseUrl}/admin`)
		return response.data
	},

	async updateAdminSettings(settings: Partial<AdminSettings>): Promise<AdminSettings> {
		const response = await axios.put<AdminSettings>(`${baseUrl}/admin`, settings)
		return response.data
	},

	// Permission Settings (Admin only)
	async getPermissionSettings(): Promise<PermissionSettings> {
		const response = await axios.get<PermissionSettings>(`${baseUrl}/permissions`)
		return response.data
	},

	async updatePermissionSettings(settings: Partial<PermissionSettings>): Promise<PermissionSettings> {
		const response = await axios.put<PermissionSettings>(`${baseUrl}/permissions`, settings)
		return response.data
	},

	// Search users and groups for the permission picker
	async searchUsersAndGroups(query: string): Promise<Principal[]> {
		const response = await axios.get<Principal[]>(
			generateUrl('/apps/contractmanager/api/settings/search-principals'),
			{ params: { query } },
		)
		return response.data
	},
}
