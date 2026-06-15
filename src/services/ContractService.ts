import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { Contract } from '../store/contracts'

const baseUrl = generateUrl('/apps/contractmanager/api/contracts')

export interface Permissions {
	isAdmin: boolean
	isEditor: boolean
	isViewer: boolean
	canEdit: boolean
	canDeletePermanently: boolean
}

export default {
	async getAll(): Promise<Contract[]> {
		const response = await axios.get<Contract[]>(baseUrl)
		return response.data
	},

	async getArchived(): Promise<Contract[]> {
		const response = await axios.get<Contract[]>(`${baseUrl}/archived`)
		return response.data
	},

	async get(id: number): Promise<Contract> {
		const response = await axios.get<Contract>(`${baseUrl}/${id}`)
		return response.data
	},

	async create(contract: Partial<Contract>): Promise<Contract> {
		const response = await axios.post<Contract>(baseUrl, contract)
		return response.data
	},

	async update(id: number, contract: Partial<Contract>): Promise<Contract> {
		const response = await axios.put<Contract>(`${baseUrl}/${id}`, contract)
		return response.data
	},

	async delete(id: number): Promise<void> {
		await axios.delete(`${baseUrl}/${id}`)
	},

	async archive(id: number): Promise<Contract> {
		const response = await axios.post<Contract>(`${baseUrl}/${id}/archive`)
		return response.data
	},

	async restore(id: number): Promise<Contract> {
		const response = await axios.post<Contract>(`${baseUrl}/${id}/restore`)
		return response.data
	},

	async getTrashed(): Promise<Contract[]> {
		const response = await axios.get<Contract[]>(`${baseUrl}/trash`)
		return response.data
	},

	async getVendors(): Promise<string[]> {
		const response = await axios.get<string[]>(`${baseUrl}/vendors`)
		return response.data
	},

	async getPermissions(): Promise<Permissions> {
		const response = await axios.get<Permissions>(`${baseUrl}/permissions`)
		return response.data
	},

	async restoreFromTrash(id: number): Promise<Contract> {
		const response = await axios.post<Contract>(`${baseUrl}/${id}/restore-from-trash`)
		return response.data
	},

	async deletePermanently(id: number): Promise<void> {
		await axios.delete(`${baseUrl}/${id}/permanent`)
	},

	async emptyTrash(): Promise<{ deleted: number }> {
		const response = await axios.post<{ deleted: number }>(`${baseUrl}/trash/empty`)
		return response.data
	},

	// Per-user reminder opt-out for a single contract
	async getReminderOptOut(id: number): Promise<boolean> {
		const response = await axios.get<{ optedOut: boolean }>(`${baseUrl}/${id}/reminder-optout`)
		return response.data.optedOut
	},

	async setReminderOptOut(id: number, optedOut: boolean): Promise<boolean> {
		const response = await axios.put<{ optedOut: boolean }>(`${baseUrl}/${id}/reminder-optout`, { optedOut })
		return response.data.optedOut
	},

	// Search users for the "responsible" picker (available to editors)
	async searchUsers(query: string): Promise<Array<{ id: string, uid: string, displayName: string, type: string }>> {
		const response = await axios.get(`${baseUrl}/users/search`, { params: { query } })
		return response.data
	},

	// Admin: transfer all contracts from one user to another
	async transferPreview(from: string): Promise<number> {
		const response = await axios.get<{ count: number }>(`${baseUrl}/transfer-preview`, { params: { from } })
		return response.data.count
	},

	async transfer(from: string, to: string): Promise<number> {
		const response = await axios.post<{ transferred: number }>(`${baseUrl}/transfer`, { from, to })
		return response.data.transferred
	},
}
