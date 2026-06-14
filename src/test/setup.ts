import { vi } from 'vitest'

// Nextcloud globals injected at runtime by the server (templates/main.php).
// In tests we provide minimal stand-ins so code that calls them doesn't crash.
;(globalThis as Record<string, unknown>).t = (_app: string, text: string) => text
;(globalThis as Record<string, unknown>).n = (_app: string, sg: string, pl: string, count: number) =>
	count === 1 ? sg : pl
;(globalThis as Record<string, unknown>).OC = { requestToken: 'test-token' }

// Stub the Nextcloud helper modules used by the service layer.
// Tests that touch services should override these with vi.mocked() per-suite.
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path: string) => path,
	generateRemoteUrl: (path: string) => path,
}))
