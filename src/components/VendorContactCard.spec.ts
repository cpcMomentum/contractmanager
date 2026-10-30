/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import VendorContactCard from './VendorContactCard.vue'

vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({
	default: { name: 'NcNoteCard', template: '<div class="stub-note"><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: { name: 'NcLoadingIcon', template: '<span class="stub-loading" />' },
}))

const contact = (teil: Record<string, unknown> = {}) => ({
	uid: 'abc',
	name: 'Stadtwerke Kiel',
	organization: 'Stadtwerke',
	emails: ['info@sw.de'],
	phones: ['+49 (431) 1-2'],
	addresses: ['Hafenstr. 1\n24103 Kiel'],
	contactsUrl: '/apps/contacts/direct/contact/abc~contacts',
	...teil,
})

const render = (props: Record<string, unknown>) => mount(VendorContactCard, {
	props,
	global: { mocks: { t: (_app: string, text: string) => text } },
})

describe('VendorContactCard', () => {
	it('verlinkt E-Mail und Telefon und zeigt die Adresse', () => {
		const wrapper = render({ contact: contact() })

		expect(wrapper.find('a[href="mailto:info@sw.de"]').exists()).toBe(true)
		expect(wrapper.find('a[href="tel:+4943112"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Hafenstr. 1')
		expect(wrapper.find('a[href="/apps/contacts/direct/contact/abc~contacts"]').exists()).toBe(true)
	})

	it('zeigt ohne Kontakte-App keinen Link', () => {
		const wrapper = render({ contact: contact({ contactsUrl: null }) })

		expect(wrapper.text()).not.toContain('In Kontakte öffnen')
	})

	it('meldet einen Kontakt ohne Kommunikationsdaten', () => {
		const wrapper = render({ contact: contact({ emails: [], phones: [], addresses: [] }) })

		expect(wrapper.find('ul').exists()).toBe(false)
		expect(wrapper.text()).toContain('keine E-Mail-Adresse')
	})

	it('zeigt den Hinweis, wenn der Betrachter den Kontakt nicht lesen kann', () => {
		const wrapper = render({ contact: null })

		expect(wrapper.find('.stub-note').text()).toContain('nicht verfügbar')
	})

	it('zeigt beim Laden weder Daten noch Hinweis', () => {
		const wrapper = render({ contact: null, loading: true })

		expect(wrapper.find('.stub-loading').exists()).toBe(true)
		expect(wrapper.find('.stub-note').exists()).toBe(false)
	})
})
