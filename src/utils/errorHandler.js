/**
 * Error Handler for ContractManager
 *
 * Provides consistent error handling across the application.
 */

import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

/**
 * Error types for categorization
 */
export const ErrorType = {
	NETWORK: 'network',
	VALIDATION: 'validation',
	AUTH: 'auth',
	NOT_FOUND: 'not_found',
	FORBIDDEN: 'forbidden',
	UNKNOWN: 'unknown',
}

/**
 * Categorize error based on HTTP status or error type
 */
export function categorizeError(error) {
	if (!error.response) {
		return ErrorType.NETWORK
	}

	const status = error.response?.status
	switch (status) {
	case 400:
		return ErrorType.VALIDATION
	case 401:
		return ErrorType.AUTH
	case 403:
		return ErrorType.FORBIDDEN
	case 404:
		return ErrorType.NOT_FOUND
	default:
		return ErrorType.UNKNOWN
	}
}

/**
 * Get a user-friendly error message
 */
export function getErrorMessage(error, context = '') {
	const type = categorizeError(error)

	// Check for backend validation errors
	if (error.response?.data?.errors) {
		const errors = error.response.data.errors
		return Object.values(errors).join(', ')
	}

	// Check for backend error message
	if (error.response?.data?.error) {
		return error.response.data.error
	}

	// Generate message based on error type
	switch (type) {
	case ErrorType.NETWORK:
		return t('contractmanager', 'Netzwerkfehler. Bitte prüfen Sie Ihre Verbindung.')
	case ErrorType.AUTH:
		return t('contractmanager', 'Sie sind nicht angemeldet. Bitte laden Sie die Seite neu.')
	case ErrorType.FORBIDDEN:
		return t('contractmanager', 'Sie haben keine Berechtigung für diese Aktion.')
	case ErrorType.NOT_FOUND:
		return t('contractmanager', 'Der angeforderte Eintrag wurde nicht gefunden.')
	case ErrorType.VALIDATION:
		return t('contractmanager', 'Ungültige Eingabe. Bitte überprüfen Sie Ihre Daten.')
	default:
		if (context) {
			return t('contractmanager', 'Fehler bei: {context}', { context })
		}
		return t('contractmanager', 'Ein unbekannter Fehler ist aufgetreten.')
	}
}

/**
 * Handle an error by showing a toast and logging
 *
 * @param {Error} error - The error object
 * @param {string} context - Context for the error (e.g., 'loading contracts')
 * @param {object} options - Additional options
 * @param {boolean} options.silent - If true, don't show toast
 * @param {boolean} options.rethrow - If true, rethrow after handling
 */
export function handleError(error, context = '', options = {}) {
	const { silent = false, rethrow = false } = options

	// Log error for debugging
	console.error(`[ContractManager] Error${context ? ` (${context})` : ''}:`, error)

	// Show toast unless silent
	if (!silent) {
		const message = getErrorMessage(error, context)
		showError(message)
	}

	// Optionally rethrow
	if (rethrow) {
		throw error
	}

	return error
}

/**
 * Wrap an async function with error handling
 *
 * @param {Function} fn - Async function to wrap
 * @param {string} context - Error context
 * @param {object} options - Error handler options
 * @returns {Function} Wrapped function
 */
export function withErrorHandling(fn, context = '', options = {}) {
	return async (...args) => {
		try {
			return await fn(...args)
		} catch (error) {
			handleError(error, context, options)
			return null
		}
	}
}

/**
 * Create an error handler for Vuex actions
 *
 * @param {string} context - Error context
 * @returns {Function} Error handler
 */
export function createActionErrorHandler(context) {
	return (error) => {
		handleError(error, context)
		throw error // Rethrow so the action can handle it
	}
}

export default {
	ErrorType,
	categorizeError,
	getErrorMessage,
	handleError,
	withErrorHandling,
	createActionErrorHandler,
}
