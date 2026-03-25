/**
 * Detect whether a mainDocument value is a URL or a legacy file path
 *
 * @param {string} value mainDocument field value
 * @return {boolean}
 */
export function isUrl(value) {
	return !!value && (value.startsWith('http://') || value.startsWith('https://'))
}

/**
 * Detect whether a URL points to the current Nextcloud instance
 *
 * @param {string} value URL string
 * @return {boolean}
 */
export function isInternalUrl(value) {
	if (!isUrl(value)) return false
	return value.startsWith(window.location.origin)
}

/**
 * Extract a human-readable display name from a mainDocument value
 *
 * - Legacy path (/Documents/file.pdf) → basename ("file.pdf")
 * - Internal URL (https://nc.example.com/f/123) → "Nextcloud Datei"
 * - External URL (https://provider.com/contract.pdf) → hostname ("provider.com")
 *
 * @param {string} value mainDocument field value
 * @return {string}
 */
export function getDisplayName(value) {
	if (!value) return ''

	if (!isUrl(value)) {
		// Legacy path
		return value.split('/').filter(s => s).pop() || value
	}

	try {
		const url = new URL(value)
		if (isInternalUrl(value)) {
			const parts = url.pathname.split('/').filter(s => s)
			const lastPart = parts[parts.length - 1]
			if (lastPart && lastPart.includes('.')) return decodeURIComponent(lastPart)
			return 'Nextcloud Datei'
		}
		return url.hostname
	} catch {
		return value
	}
}
