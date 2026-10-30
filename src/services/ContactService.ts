import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/contacts')

export interface ContactOption {
	uid: string
	name: string
	organization: string
	email: string
}

export interface ResolvedContact {
	uid: string
	name: string
	organization: string
	emails: string[]
	phones: string[]
	addresses: string[]
	contactsUrl: string | null
}

export default {
	async isAvailable(): Promise<boolean> {
		const response = await axios.get<{ available: boolean }>(`${baseUrl}/status`)
		return response.data.available === true
	},

	async search(query: string): Promise<ContactOption[]> {
		const response = await axios.get<ContactOption[]>(`${baseUrl}/search`, { params: { query } })
		return response.data
	},

	async resolve(uid: string): Promise<ResolvedContact | null> {
		const response = await axios.get<{ contact: ResolvedContact | null }>(`${baseUrl}/resolve`, { params: { uid } })
		return response.data.contact
	},
}
