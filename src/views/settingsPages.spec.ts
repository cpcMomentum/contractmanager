/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { translate, translatePlural } from '@nextcloud/l10n'
import AdminView from './AdminView.vue'
import SettingsView from './SettingsView.vue'

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
const { stub } = vi.hoisted(() => ({
	stub: (name: string) => ({ default: { name, template: '<div><slot /><slot name="actions" /></div>' } }),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => stub('NcButton'))
vi.mock('@nextcloud/vue/components/NcDialog', () => stub('NcDialog'))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => stub('NcCheckboxRadioSwitch'))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => stub('NcLoadingIcon'))
vi.mock('@nextcloud/vue/components/NcTextField', () => stub('NcTextField'))
vi.mock('@nextcloud/vue/components/NcSelect', () => stub('NcSelect'))
vi.mock('@nextcloud/dialogs', () => ({ getFilePickerBuilder: vi.fn(), showSuccess: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/dialogs/style.css', () => ({}))
vi.mock('../components/WerkPlusSettings.vue', () => ({ default: { name: 'WerkPlusSettings', template: '<div class="stub-werkplus" />' } }))
vi.mock('../services/CategoryService', () => ({ default: { getAll: vi.fn().mockResolvedValue([]) } }))
vi.mock('../services/ImportService', () => ({ default: {} }))
vi.mock('../services/ContractService', () => ({ default: { searchUsers: vi.fn().mockResolvedValue([]) } }))
vi.mock('../services/SettingsService', () => ({
	default: {
		getUserSettings: vi.fn().mockResolvedValue({}),
		getAdminSettings: vi.fn().mockResolvedValue({}),
		getPermissionSettings: vi.fn().mockResolvedValue({ editors: [], viewers: [] }),
		searchUsersAndGroups: vi.fn().mockResolvedValue([]),
	},
}))

const ADMIN_KEYS = ['permissions', 'transfer', 'general', 'customFields', 'ai', 'categories', 'werkplus']

async function mountPage(component: object) {
	const wrapper = mount(component, {
		attachTo: document.body,
		global: {
			plugins: [createPinia()],
			mocks: { t: translate, n: translatePlural, $isAdmin: true },
		},
	})
	await flushPromises()
	return wrapper
}

function navModel(wrapper: ReturnType<typeof mount>): { label: string, items: { key: string, label: string }[] }[] {
	return (wrapper.vm as unknown as { navGroups: { label: string, items: { key: string, label: string }[] }[] }).navGroups
}

// Klickt jeden Punkt und meldet je Punkt die sichtbaren Abschnitte samt Titel.
async function visiblePerItem(wrapper: ReturnType<typeof mount>): Promise<{ label: string, titles: string[] }[]> {
	const result = []
	for (const button of wrapper.findAll('.settings-nav-item')) {
		await button.trigger('click')
		const titles = wrapper.findAll('.settings-section')
			.filter((section) => (section.element as HTMLElement).style.display !== 'none')
			.map((section) => section.find('h3').text())
		result.push({ label: button.text(), titles })
	}
	return result
}

describe('Verwaltung (AdminView)', () => {
	it('ordnet die Punkte in Zugriff, Verträge und zuletzt Lizenz', async () => {
		const wrapper = await mountPage(AdminView)

		expect(wrapper.find('h2').text()).toBe('Verwaltung')
		expect(navModel(wrapper).map((g) => [g.label, g.items.map((i) => i.label)])).toEqual([
			['Zugriff', ['Berechtigungen', 'Verträge übertragen']],
			['Verträge', ['Allgemein', 'Zusatzfelder', 'KI-Vertragsanalyse', 'Kategorien']],
			['Lizenz', ['WerkPlus']],
		])
		expect(navModel(wrapper).flatMap((g) => g.items.map((i) => i.key))).toEqual(ADMIN_KEYS)
	})

	it('zeigt je Punkt genau einen Abschnitt mit dem Namen des Punkts', async () => {
		const wrapper = await mountPage(AdminView)

		for (const { label, titles } of await visiblePerItem(wrapper)) {
			expect(titles).toEqual([label])
		}
		expect(wrapper.find('.settings-nav-item.active').attributes('aria-current')).toBe('page')
	})

	it('der WerkPlus-Abschnitt sitzt in der Verwaltung', async () => {
		const wrapper = await mountPage(AdminView)
		await wrapper.findAll('.settings-nav-item').find((b) => b.text() === 'WerkPlus')!.trigger('click')

		expect(wrapper.find('.stub-werkplus').exists()).toBe(true)
	})
})

describe('Einstellungen (SettingsView)', () => {
	it('enthält nur die persönliche Gruppe', async () => {
		const wrapper = await mountPage(SettingsView)

		expect(wrapper.find('h2').text()).toBe('Einstellungen')
		expect(navModel(wrapper).map((g) => [g.label, g.items.map((i) => i.label)])).toEqual([
			['Persönlich', ['Benachrichtigungen', 'Betragsangabe', 'Automatisches Backup', 'Kalender-Abo']],
		])
	})

	it('hat keinen app-weiten Punkt, auch keine Kategorien', async () => {
		const wrapper = await mountPage(SettingsView)
		const keys = navModel(wrapper).flatMap((g) => g.items.map((i) => i.key))

		expect(keys.filter((k) => ADMIN_KEYS.includes(k))).toEqual([])
		expect(wrapper.find('.stub-werkplus').exists()).toBe(false)
	})

	it('zeigt je Punkt genau einen Abschnitt mit dem Namen des Punkts', async () => {
		const wrapper = await mountPage(SettingsView)

		for (const { label, titles } of await visiblePerItem(wrapper)) {
			expect(titles).toEqual([label])
		}
	})
})
