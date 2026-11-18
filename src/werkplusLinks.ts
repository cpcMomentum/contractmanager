/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

/** Adressen auf werkwolke.de nach app-links-v1. Einzige Quelle ist lib/Service/WerkPlusLinks.php. */
export interface WerkPlusLinks {
	buy: string
	features: string
	trial: string
}

export function werkplusLinks(): WerkPlusLinks {
	return loadState<WerkPlusLinks>('contractmanager', 'werkplusLinks', { buy: '', features: '', trial: '' })
}
