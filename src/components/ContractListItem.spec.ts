/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Papierkorb-Zeile (#438): zeigt, wer geloescht hat, und bietet das
 * Wiederherstellen jedem an, dem der Server den Eintrag liefert — auch ohne
 * Editor-Rolle, damit ein zustaendiger Viewer eine Fremdloeschung rueckgaengig
 * machen kann.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useContractsStore } from '../store/contracts'
import ContractListItem from './ContractListItem.vue'

vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn(), showError: vi.fn() }))

// Die echten Komponenten ziehen ihr CSS mit, das der Test-Runner nicht laedt.
vi.mock('@nextcloud/vue/components/NcActions', () => ({
	default: { name: 'NcActions', template: '<div class="stub-actions"><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button class="stub-action" @click="$emit(\'click\')"><slot /></button>',
	},
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: { name: 'NcButton', template: '<button class="stub-button"><slot /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: { name: 'NcDialog', template: '<div class="stub-dialog"><slot /></div>' },
}))

const trashed = (teil: Record<string, unknown> = {}) => ({
	id: 7,
	name: 'Mobilfunk',
	vendor: 'Telco',
	status: 'active',
	archived: false,
	isPrivate: false,
	createdBy: 'alice',
	deletedAt: '2026-10-02T09:30:00+00:00',
	deletedBy: 'bob',
	...teil,
})

function asUser(canEdit: boolean) {
	const store = useContractsStore()
	store.permissions = { ...store.permissions, canEdit, isEditor: canEdit, isViewer: !canEdit }
}

// Die App haengt t/n in main.ts an globalProperties; im Test fehlt das.
const global = { mocks: { t: (_app: string, text: string) => text } }

const actionLabels = (wrapper: ReturnType<typeof mount>) =>
	wrapper.findAll('.stub-action').map(b => b.text())

describe('ContractListItem im Papierkorb', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('zeigt, wer den Vertrag geloescht hat', () => {
		asUser(true)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed(), mode: 'trash' } })

		expect(wrapper.text()).toContain('Gelöscht von: bob')
	})

	it('laesst die Zeile weg, wenn der Loeschende unbekannt ist (Altbestand)', () => {
		asUser(true)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed({ deletedBy: null }), mode: 'trash' } })

		expect(wrapper.text()).not.toContain('Gelöscht von')
	})

	it('bietet Wiederherstellen auch ohne Editor-Rolle an', async () => {
		asUser(false)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed(), mode: 'trash' } })

		const restore = wrapper.findAll('.stub-action').find(b => b.text() === 'Wiederherstellen')
		expect(restore).toBeDefined()
		await restore!.trigger('click')
		expect(wrapper.emitted('restore')).toHaveLength(1)
	})

	it('laesst das Wiederherstellen aus dem Archiv weiter an der Editor-Rolle', () => {
		asUser(false)
		const wrapper = mount(ContractListItem, { global, props: { contract: trashed({ archived: true, deletedAt: null }), mode: 'archive' } })

		expect(actionLabels(wrapper)).not.toContain('Wiederherstellen')
	})
})
