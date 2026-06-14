import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { Category } from '../store/categories'

const baseUrl = generateUrl('/apps/contractmanager/api/categories')

export default {
	async getAll(): Promise<Category[]> {
		const response = await axios.get<Category[]>(baseUrl)
		return response.data
	},

	async create(name: string): Promise<Category> {
		const response = await axios.post<Category>(baseUrl, { name })
		return response.data
	},

	async update(id: number, name: string, sortOrder?: number | null): Promise<Category> {
		const response = await axios.put<Category>(`${baseUrl}/${id}`, { name, sortOrder })
		return response.data
	},

	async delete(id: number): Promise<void> {
		await axios.delete(`${baseUrl}/${id}`)
	},
}
