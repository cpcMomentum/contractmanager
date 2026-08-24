<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\IConfig;

/**
 * Service for managing app settings
 *
 * Note: User access control is handled via Nextcloud's native group-based
 * app access (Admin → Apps → "Enable only for specific groups").
 * No custom access control logic needed here.
 */
class SettingsService {

	/**
	 * Placeholder string returned from getAdmin() when an API key is configured,
	 * so the secret value never leaves the server. updateAdmin() compares
	 * incoming values against this constant to detect unchanged-key submissions.
	 */
	public const API_KEY_MASK = '••••••••';

	private const KEY_TALK_CHAT_TOKEN = 'talk_chat_token';
	private const KEY_REMINDER_DAYS_1 = 'reminder_days_1';
	private const KEY_REMINDER_DAYS_2 = 'reminder_days_2';
	private const KEY_EMAIL_REMINDER = 'email_reminder';
	private const KEY_REMINDER_MODE = 'reminder_mode';
	private const KEY_SORT_BY = 'sort_by';
	private const KEY_SORT_DIRECTION = 'sort_direction';
	private const KEY_FILTERS = 'filters';
	private const KEY_DEFAULT_AMOUNT_TYPE = 'default_amount_type';

	private const KEY_CUSTOM_FIELD_LABEL_PREFIX = 'custom_field_label_';
	private const KEY_CUSTOM_FIELD_ENABLED_PREFIX = 'custom_field_enabled_';

	private const KEY_DELETION_SUCCESSOR = 'deletion_successor';

	private const KEY_AI_PROVIDER = 'ai_provider';
	private const KEY_AI_API_KEY = 'ai_api_key';
	private const KEY_AI_API_URL = 'ai_api_url';
	private const KEY_AI_MODEL = 'ai_model';

	private const ALLOWED_AI_PROVIDERS = ['claude', 'openai_compatible'];
	private const DEFAULT_AI_URLS = [
		'claude' => 'https://api.anthropic.com',
		'openai_compatible' => 'https://api.openai.com/v1',
	];
	private const DEFAULT_AI_MODELS = [
		'claude' => 'claude-sonnet-4-5-20250514',
		'openai_compatible' => 'gpt-4o',
	];

	private const DEFAULT_REMINDER_DAYS_1 = 14;
	private const DEFAULT_REMINDER_DAYS_2 = 3;

	public const REMINDER_MODE_ALL = 'all';
	public const REMINDER_MODE_OWN = 'own';
	public const REMINDER_MODE_NONE = 'none';
	private const ALLOWED_REMINDER_MODES = [
		self::REMINDER_MODE_ALL,
		self::REMINDER_MODE_OWN,
		self::REMINDER_MODE_NONE,
	];
	private const DEFAULT_REMINDER_MODE = self::REMINDER_MODE_OWN;

	private const ALLOWED_SORT_BY = ['endDate', 'name', 'updatedAt', 'cost', 'cancellationDeadline'];
	private const ALLOWED_AMOUNT_TYPES = ['netto', 'brutto'];
	private const ALLOWED_SORT_DIRECTION = ['asc', 'desc'];
	private const ALLOWED_FILTER_KEYS = ['vendor', 'statuses', 'contractType', 'responsible'];
	private const ALLOWED_STATUSES = ['active', 'cancelled', 'ended'];
	private const ALLOWED_CONTRACT_TYPES = ['', 'fixed', 'auto_renewal'];
	private const DEFAULT_FILTERS = [
		'vendor' => '',
		'statuses' => [],
		'contractType' => '',
		'responsible' => '',
		'ownerMissing' => false,
	];

	public function __construct(
		private IConfig $config,
	) {
	}

	// ========================================
	// System-Diagnose
	// ========================================

	/**
	 * Base URL used by background (cron) jobs to build absolute links.
	 *
	 * Reminder mails are sent from cron, where there is no request context, so
	 * Nextcloud builds links from this system value (see URLGenerator, CLI branch).
	 * If it is empty or stale, reminder links point at the wrong host.
	 */
	public function getCliUrl(): string {
		return $this->config->getSystemValueString('overwrite.cli.url', '');
	}

	// ========================================
	// Admin-Settings
	// ========================================

	/**
	 * Get first reminder days (default: 14)
	 */
	public function getReminderDays1(): int {
		return (int)$this->config->getAppValue(
			Application::APP_ID,
			self::KEY_REMINDER_DAYS_1,
			(string)self::DEFAULT_REMINDER_DAYS_1
		);
	}

	/**
	 * Set first reminder days
	 */
	public function setReminderDays1(int $days): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_REMINDER_DAYS_1,
			(string)max(1, $days)
		);
	}

	/**
	 * Get second reminder days (default: 3)
	 */
	public function getReminderDays2(): int {
		return (int)$this->config->getAppValue(
			Application::APP_ID,
			self::KEY_REMINDER_DAYS_2,
			(string)self::DEFAULT_REMINDER_DAYS_2
		);
	}

	/**
	 * Set second reminder days
	 */
	public function setReminderDays2(int $days): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_REMINDER_DAYS_2,
			(string)max(1, $days)
		);
	}

	// ========================================
	// Custom Field Labels (Admin)
	// ========================================

	/**
	 * Get the user contracts are handed over to when their owner's account is
	 * deleted (#299). Empty means no automatic handover happens.
	 */
	public function getDeletionSuccessor(): string {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_DELETION_SUCCESSOR,
			''
		);
	}

	/**
	 * Set the successor for contracts of deleted users. Empty clears it.
	 */
	public function setDeletionSuccessor(string $userId): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_DELETION_SUCCESSOR,
			trim($userId)
		);
	}

	/**
	 * Get label for a custom field (1-3)
	 */
	public function getCustomFieldLabel(int $fieldNumber): string {
		if ($fieldNumber < 1 || $fieldNumber > 3) {
			return '';
		}
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_CUSTOM_FIELD_LABEL_PREFIX . $fieldNumber,
			''
		);
	}

	/**
	 * Set label for a custom field (1-3)
	 */
	public function setCustomFieldLabel(int $fieldNumber, string $label): void {
		if ($fieldNumber < 1 || $fieldNumber > 3) {
			return;
		}
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_CUSTOM_FIELD_LABEL_PREFIX . $fieldNumber,
			trim($label)
		);
	}

	/**
	 * Whether a custom field (1-3) is active.
	 *
	 * The active state is a real flag, independent of the label. For contracts
	 * created before this flag existed the value was never stored; we then fall
	 * back to the historical rule "label not empty = active" so existing setups
	 * keep working (#368).
	 */
	public function getCustomFieldEnabled(int $fieldNumber): bool {
		if ($fieldNumber < 1 || $fieldNumber > 3) {
			return false;
		}
		$stored = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_CUSTOM_FIELD_ENABLED_PREFIX . $fieldNumber,
			''
		);
		if ($stored === '') {
			// Never set: derive from the old label-based behaviour.
			return $this->getCustomFieldLabel($fieldNumber) !== '';
		}
		return $stored === '1';
	}

	/**
	 * Set the active state for a custom field (1-3)
	 */
	public function setCustomFieldEnabled(int $fieldNumber, bool $enabled): void {
		if ($fieldNumber < 1 || $fieldNumber > 3) {
			return;
		}
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_CUSTOM_FIELD_ENABLED_PREFIX . $fieldNumber,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Get the custom field labels a contract form should show.
	 *
	 * A field only appears in the contract form when it is active AND named, so a
	 * deactivated field is gated out here (empty label). The contract form keys
	 * its visibility off a non-empty label, so this keeps that view unchanged
	 * while making it honour the active flag (#368).
	 *
	 * @return array{customFieldLabel1: string, customFieldLabel2: string, customFieldLabel3: string}
	 */
	public function getCustomFieldLabels(): array {
		return [
			'customFieldLabel1' => $this->getCustomFieldEnabled(1) ? $this->getCustomFieldLabel(1) : '',
			'customFieldLabel2' => $this->getCustomFieldEnabled(2) ? $this->getCustomFieldLabel(2) : '',
			'customFieldLabel3' => $this->getCustomFieldEnabled(3) ? $this->getCustomFieldLabel(3) : '',
		];
	}

	// ========================================
	// User-Settings (produktbeschreibung.md Zeile 219)
	// ========================================

	/**
	 * Check if email reminders are enabled for a user
	 */
	public function getUserEmailReminder(string $userId): bool {
		$value = $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_EMAIL_REMINDER,
			'0'
		);
		return $value === '1';
	}

	/**
	 * Set email reminder preference for a user
	 */
	public function setUserEmailReminder(string $userId, bool $enabled): void {
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_EMAIL_REMINDER,
			$enabled ? '1' : '0'
		);
	}

	/**
	 * Get a user's reminder mode: which contracts they want reminders for.
	 * One of 'all', 'own', 'none' (default: 'own').
	 */
	public function getUserReminderMode(string $userId): string {
		$value = $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_REMINDER_MODE,
			self::DEFAULT_REMINDER_MODE
		);
		return in_array($value, self::ALLOWED_REMINDER_MODES, true) ? $value : self::DEFAULT_REMINDER_MODE;
	}

	/**
	 * Set a user's reminder mode (whitelist-validated).
	 */
	public function setUserReminderMode(string $userId, string $mode): void {
		if (!in_array($mode, self::ALLOWED_REMINDER_MODES, true)) {
			return;
		}
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_REMINDER_MODE,
			$mode
		);
	}

	/**
	 * Get a user's personal first-reminder lead time in days, or null when the
	 * user has not set one (falls back to the admin default in that case).
	 */
	public function getUserReminderDays1(string $userId): ?int {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::KEY_REMINDER_DAYS_1, '');
		return $value === '' ? null : max(1, (int)$value);
	}

	/**
	 * Set a user's personal first-reminder lead time. Null/0 clears it (use default).
	 */
	public function setUserReminderDays1(string $userId, ?int $days): void {
		$value = ($days === null || $days < 1) ? '' : (string)$days;
		$this->config->setUserValue($userId, Application::APP_ID, self::KEY_REMINDER_DAYS_1, $value);
	}

	/**
	 * Get a user's personal final-reminder lead time in days, or null when unset.
	 */
	public function getUserReminderDays2(string $userId): ?int {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::KEY_REMINDER_DAYS_2, '');
		return $value === '' ? null : max(1, (int)$value);
	}

	/**
	 * Set a user's personal final-reminder lead time. Null/0 clears it (use default).
	 */
	public function setUserReminderDays2(string $userId, ?int $days): void {
		$value = ($days === null || $days < 1) ? '' : (string)$days;
		$this->config->setUserValue($userId, Application::APP_ID, self::KEY_REMINDER_DAYS_2, $value);
	}

	/**
	 * Get a user's personal Talk chat token for reminders, or null when unset.
	 */
	public function getUserTalkChatToken(string $userId): ?string {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::KEY_TALK_CHAT_TOKEN, '');
		return $value !== '' ? $value : null;
	}

	/**
	 * Set a user's personal Talk chat token for reminders.
	 */
	public function setUserTalkChatToken(string $userId, ?string $token): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::KEY_TALK_CHAT_TOKEN, $token ?? '');
	}

	/**
	 * Get sort-by preference for a user (default: endDate)
	 */
	public function getUserSortBy(string $userId): string {
		return $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_SORT_BY,
			'endDate'
		);
	}

	/**
	 * Set sort-by preference for a user (whitelist-validated)
	 */
	public function setUserSortBy(string $userId, string $sortBy): void {
		if (!in_array($sortBy, self::ALLOWED_SORT_BY, true)) {
			return;
		}
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_SORT_BY,
			$sortBy
		);
	}

	/**
	 * Get sort direction preference for a user (default: asc)
	 */
	public function getUserSortDirection(string $userId): string {
		return $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_SORT_DIRECTION,
			'asc'
		);
	}

	/**
	 * Set sort direction preference for a user
	 */
	public function setUserSortDirection(string $userId, string $direction): void {
		if (!in_array($direction, self::ALLOWED_SORT_DIRECTION, true)) {
			return;
		}
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_SORT_DIRECTION,
			$direction
		);
	}

	/**
	 * Get filter preferences for a user
	 *
	 * @return array{vendor: string, statuses: string[], contractType: string, responsible: string, ownerMissing: bool}
	 */
	public function getUserFilters(string $userId): array {
		$json = $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_FILTERS,
			''
		);

		if ($json === '') {
			return self::DEFAULT_FILTERS;
		}

		$filters = json_decode($json, true);
		if (!is_array($filters)) {
			return self::DEFAULT_FILTERS;
		}

		return [
			'vendor' => isset($filters['vendor']) && is_string($filters['vendor']) ? $filters['vendor'] : '',
			'statuses' => isset($filters['statuses']) && is_array($filters['statuses'])
				? array_values(array_intersect($filters['statuses'], self::ALLOWED_STATUSES))
				: self::DEFAULT_FILTERS['statuses'],
			'contractType' => isset($filters['contractType']) && in_array($filters['contractType'], self::ALLOWED_CONTRACT_TYPES, true)
				? $filters['contractType']
				: '',
			'responsible' => isset($filters['responsible']) && is_string($filters['responsible']) ? $filters['responsible'] : '',
			// Bewusst nicht aus dem gespeicherten Wert gelesen (#332): siehe
			// setUserFilters. Ein aus einer frueheren Version stammendes true
			// wird damit ignoriert und beim naechsten Speichern ueberschrieben.
			'ownerMissing' => false,
		];
	}

	/**
	 * Set filter preferences for a user (validated)
	 */
	public function setUserFilters(string $userId, array $filters): void {
		$validated = [
			'vendor' => isset($filters['vendor']) && is_string($filters['vendor']) ? $filters['vendor'] : '',
			'statuses' => isset($filters['statuses']) && is_array($filters['statuses'])
				? array_values(array_intersect($filters['statuses'], self::ALLOWED_STATUSES))
				: self::DEFAULT_FILTERS['statuses'],
			'contractType' => isset($filters['contractType']) && in_array($filters['contractType'], self::ALLOWED_CONTRACT_TYPES, true)
				? $filters['contractType']
				: '',
			'responsible' => isset($filters['responsible']) && is_string($filters['responsible']) ? $filters['responsible'] : '',
			// "Ohne aktiven Eigentuemer" wird bewusst nie dauerhaft gespeichert
			// (#332). Der Filter ist das Administratoren-Diagnosewerkzeug aus
			// #299 und liefert im Normalbetrieb fast immer null Treffer. Einmal
			// angetippt filterte er ueber Monate stumm weiter, weil die leere
			// Liste keinen Grund nannte. Er gilt jetzt nur fuer die laufende
			// Ansicht und ist nach jedem Neuladen aus.
			'ownerMissing' => false,
		];

		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_FILTERS,
			json_encode($validated)
		);
	}

	public function getUserDefaultAmountType(string $userId): string {
		return $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_DEFAULT_AMOUNT_TYPE,
			'netto'
		);
	}

	public function setUserDefaultAmountType(string $userId, string $amountType): void {
		if (!in_array($amountType, self::ALLOWED_AMOUNT_TYPES, true)) {
			return;
		}
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			self::KEY_DEFAULT_AMOUNT_TYPE,
			$amountType
		);
	}

	// ========================================
	// AI Settings (Admin)
	// ========================================

	public function getAiProvider(): string {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_AI_PROVIDER,
			''
		);
	}

	public function setAiProvider(string $provider): void {
		if ($provider !== '' && !in_array($provider, self::ALLOWED_AI_PROVIDERS, true)) {
			return;
		}
		$this->config->setAppValue(Application::APP_ID, self::KEY_AI_PROVIDER, $provider);
	}

	public function getAiApiKey(): string {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_AI_API_KEY,
			''
		);
	}

	public function setAiApiKey(string $key): void {
		$this->config->setAppValue(Application::APP_ID, self::KEY_AI_API_KEY, $key);
	}

	public function getAiApiUrl(): string {
		$url = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_AI_API_URL,
			''
		);
		if ($url !== '') {
			return $url;
		}
		$provider = $this->getAiProvider();
		return self::DEFAULT_AI_URLS[$provider] ?? '';
	}

	public function setAiApiUrl(string $url): void {
		if ($url !== '' && !$this->isValidAiApiUrl($url)) {
			return;
		}
		$this->config->setAppValue(Application::APP_ID, self::KEY_AI_API_URL, $url);
	}

	/**
	 * Validate AI API URL: must be https, or http only for local hosts (Ollama).
	 * Used as defense-in-depth — only admins reach this path in normal flow.
	 */
	public function isValidAiApiUrl(string $url): bool {
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return false;
		}
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		if ($scheme === 'https') {
			return true;
		}
		if ($scheme !== 'http') {
			return false;
		}
		$host = strtolower((string)parse_url($url, PHP_URL_HOST));
		// parse_url liefert IPv6-Hosts geklammert zurueck (z.B. "[::1]") — Klammern
		// fuer den Loopback-Vergleich normalisieren.
		$host = trim($host, '[]');
		if ($host === '') {
			return false;
		}
		if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
			return true;
		}
		if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
			return true;
		}
		return false;
	}

	public function getAiModel(): string {
		$model = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_AI_MODEL,
			''
		);
		if ($model !== '') {
			return $model;
		}
		$provider = $this->getAiProvider();
		return self::DEFAULT_AI_MODELS[$provider] ?? '';
	}

	public function setAiModel(string $model): void {
		$this->config->setAppValue(Application::APP_ID, self::KEY_AI_MODEL, $model);
	}

	public function isAiConfigured(): bool {
		return $this->getAiProvider() !== '' && $this->getAiApiKey() !== '';
	}

}
