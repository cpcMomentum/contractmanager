module.exports = {
	root: true,
	extends: ['@nextcloud/eslint-config/vue3'],
	rules: {
		// JSDoc is enforced only when authors choose to write it — internal stores
		// and utility functions rely on TS types / clear naming instead of redundant stubs.
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/require-param': 'off',
		'jsdoc/require-param-description': 'off',
		'jsdoc/require-param-type': 'off',
		// Allow `@returns` alongside `@return` (TS convention is widely used).
		'jsdoc/check-tag-names': 'off',
		// `void` is used intentionally to mark fire-and-forget promises and
		// explicitly unused parameters kept for binding/contract parity.
		'no-void': 'off',
	},
	overrides: [
		{
			// Test files and the shared test setup are allowed to import devDependencies.
			files: ['src/**/*.{test,spec}.{ts,js}', 'src/test/**/*.{ts,js}'],
			rules: {
				'n/no-unpublished-import': 'off',
			},
		},
	],
}
