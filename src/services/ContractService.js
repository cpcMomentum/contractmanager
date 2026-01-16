import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/contracts')

export default {
	async getAll() {
		const response = await axios.get(baseUrl)
		return response.data
	},

	async getArchived() {
		const response = await axios.get(`${baseUrl}/archived`)
		return response.data
	},

	async get(id) {
		const response = await axios.get(`${baseUrl}/${id}`)
		return response.data
	},

	async create(contract) {
		const response = await axios.post(baseUrl, contract)
		return response.data
	},

	async update(id, contract) {
		const response = await axios.put(`${baseUrl}/${id}`, contract)
		return response.data
	},

	async delete(id) {
		const response = await axios.delete(`${baseUrl}/${id}`)
		return response.data
	},

	async archive(id) {
		const response = await axios.post(`${baseUrl}/${id}/archive`)
		return response.data
	},

	async restore(id) {
		const response = await axios.post(`${baseUrl}/${id}/restore`)
		return response.data
	},
}
