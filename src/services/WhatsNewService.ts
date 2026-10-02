import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/contractmanager/api/whatsnew')

/** „Was ist neu?"-Fenster (#415) — ein Eintrag, bereits in Nutzersprache. */
export interface WhatsNewEntry {
	title: string
	text: string
	/** Name aus der Symbolliste des Dialogs; unbekannt oder leer = Stern. */
	icon: string
	/** Wo die Neuerung sitzt, etwa „Einstellungen". Leer = keine Fundort-Zeile. */
	where: string
	/** Stelle ist adminpflichtig; der Dialog schreibt es dazu. */
	adminOnly: boolean
	/** WerkPlus-Feature: Badge und Link auf werkwolke.de. */
	plus: boolean
}

/** Antwort von `GET /api/whatsnew`. Leere Liste heisst: kein Fenster. */
export interface WhatsNewPayload {
	version: string
	entries: WhatsNewEntry[]
}

/** Eine Version mit ihren Eintraegen im Archiv (#427). */
export interface WhatsNewGroup {
	version: string
	entries: WhatsNewEntry[]
}

/** Antwort von `GET /api/whatsnew/all` — alle Versionen, neueste zuerst. */
export interface WhatsNewArchive {
	versions: WhatsNewGroup[]
}

export default {
	/** Noch nicht gesehene Neuerungen der laufenden Version (#415). */
	async getWhatsNew(): Promise<WhatsNewPayload> {
		const response = await axios.get<WhatsNewPayload>(baseUrl)
		return response.data
	},

	/** Alle bisherigen Neuerungen, nach Version gruppiert (#427) — fuers Menue. */
	async getWhatsNewArchive(): Promise<WhatsNewArchive> {
		const response = await axios.get<WhatsNewArchive>(`${baseUrl}/all`)
		return response.data
	},

	/** Quittiert das Fenster; es kommt fuer diese Version nicht wieder. */
	async markWhatsNewSeen(): Promise<void> {
		await axios.post(`${baseUrl}/seen`, {})
	},
}
