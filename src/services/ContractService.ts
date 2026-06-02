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
}
