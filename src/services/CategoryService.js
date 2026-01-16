import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/categories')

export default {
	async getAll() {
		const response = await axios.get(baseUrl)
		return response.data
	},

	async create(name) {
		const response = await axios.post(baseUrl, { name })
		return response.data
	},

	async update(id, name, sortOrder = null) {
		const response = await axios.put(`${baseUrl}/${id}`, { name, sortOrder })
		return response.data
	},

	async delete(id) {
		const response = await axios.delete(`${baseUrl}/${id}`)
		return response.data
	},
}
