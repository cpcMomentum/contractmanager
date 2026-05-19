/**
 * Linkify utility — converts plain text to HTML with clickable URLs.
 * XSS-safe: escapes all HTML entities before applying link replacement.
 */

const HTML_ESCAPE_MAP = {
	'&': '&amp;',
	'<': '&lt;',
	'>': '&gt;',
	'"': '&quot;',
	'\'': '&#39;',
}

/**
 * Escape HTML special characters to prevent XSS.
 *
 * @param {string} text - raw input
 * @returns {string} HTML-escaped text
 */
function escapeHtml(text) {
	return text.replace(/[&<>"']/g, ch => HTML_ESCAPE_MAP[ch])
}

/**
 * Convert plain text to HTML with clickable links.
 *
 * Detects http(s) URLs and wraps them in safe anchor tags.
 * Input is fully HTML-escaped first, so the result is safe to use with v-html.
 *
 * @param {string|null|undefined} text - plain text input (notes content)
 * @returns {string} HTML string with linkified URLs and preserved newlines
 */
export function linkifyText(text) {
	if (!text) return ''

	const escaped = escapeHtml(String(text))

	// Match http:// and https:// URLs, stopping at whitespace or common trailing punctuation.
	// Trailing punctuation (.,;:!?) is preserved as text rather than included in the link.
	const urlRegex = /\bhttps?:\/\/[^\s<]+[^\s<.,;:!?)\]}'"]/g

	const linked = escaped.replace(urlRegex, url =>
		`<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`,
	)

	// Preserve newlines in HTML output
	return linked.replace(/\n/g, '<br>')
}
