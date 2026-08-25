<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

class SettingsController extends Controller {

	public function __construct(
		IRequest $request,
		private ?string $userId,
		private SettingsService $settingsService,
		private PermissionService $permissionService,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IL10N $l,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	// ========================================
	// User Settings (mit #[NoAdminRequired])
	// ========================================

	/**
	 * Get current user's settings
	 */
	#[NoAdminRequired]
	public function get(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l->t('Nicht angemeldet')], 401);
		}

		return new JSONResponse([
			'emailReminder' => $this->settingsService->getUserEmailReminder($this->userId),
			'reminderMode' => $this->settingsService->getUserReminderMode($this->userId),
			'reminderDays1Personal' => $this->settingsService->getUserReminderDays1($this->userId),
			'reminderDays2Personal' => $this->settingsService->getUserReminderDays2($this->userId),
			'talkChatToken' => $this->settingsService->getUserTalkChatToken($this->userId),
			'sortBy' => $this->settingsService->getUserSortBy($this->userId),
			'sortDirection' => $this->settingsService->getUserSortDirection($this->userId),
			'filters' => $this->settingsService->getUserFilters($this->userId),
			'defaultAmountType' => $this->settingsService->getUserDefaultAmountType($this->userId),
			'backupEnabled' => $this->settingsService->getUserBackupEnabled($this->userId),
			'backupFolder' => $this->settingsService->getUserBackupFolder($this->userId),
			'backupInterval' => $this->settingsService->getUserBackupInterval($this->userId),
			'customFieldLabels' => $this->settingsService->getCustomFieldLabels(),
			// App-globale Standard-Vorlaufzeiten. Das Frontend nutzt sie zur Berechnung
			// von "Kündigungsfrist endet" und als Fallback-Anzeige, wenn der Nutzer keine
			// persönliche Vorlaufzeit gesetzt hat. Read-only für Nicht-Admins.
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
		]);
	}

	/**
	 * Update current user's settings
	 */
	#[NoAdminRequired]
	public function update(
		?bool $emailReminder = null,
		?string $reminderMode = null,
		?int $reminderDays1Personal = null,
		?int $reminderDays2Personal = null,
		?string $talkChatToken = null,
		?string $sortBy = null,
		?string $sortDirection = null,
		?array $filters = null,
		?string $defaultAmountType = null,
		?bool $backupEnabled = null,
		?string $backupFolder = null,
		?string $backupInterval = null,
	): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => $this->l->t('Nicht angemeldet')], 401);
		}

		if ($emailReminder !== null) {
			$this->settingsService->setUserEmailReminder($this->userId, $emailReminder);
		}
		if ($reminderMode !== null) {
			$this->settingsService->setUserReminderMode($this->userId, $reminderMode);
		}
		// 0 clears the personal value (fall back to the admin default).
		if ($reminderDays1Personal !== null) {
			$this->settingsService->setUserReminderDays1($this->userId, $reminderDays1Personal ?: null);
		}
		if ($reminderDays2Personal !== null) {
			$this->settingsService->setUserReminderDays2($this->userId, $reminderDays2Personal ?: null);
		}
		if ($talkChatToken !== null) {
			$this->settingsService->setUserTalkChatToken($this->userId, $talkChatToken ?: null);
		}
		if ($sortBy !== null) {
			$this->settingsService->setUserSortBy($this->userId, $sortBy);
		}
		if ($sortDirection !== null) {
			$this->settingsService->setUserSortDirection($this->userId, $sortDirection);
		}
		if ($filters !== null) {
			$this->settingsService->setUserFilters($this->userId, $filters);
		}
		if ($defaultAmountType !== null) {
			$this->settingsService->setUserDefaultAmountType($this->userId, $defaultAmountType);
		}
		if ($backupEnabled !== null) {
			$this->settingsService->setUserBackupEnabled($this->userId, $backupEnabled);
		}
		if ($backupFolder !== null) {
			$this->settingsService->setUserBackupFolder($this->userId, $backupFolder);
		}
		if ($backupInterval !== null) {
			$this->settingsService->setUserBackupInterval($this->userId, $backupInterval);
		}

		return new JSONResponse([
			'emailReminder' => $this->settingsService->getUserEmailReminder($this->userId),
			'reminderMode' => $this->settingsService->getUserReminderMode($this->userId),
			'reminderDays1Personal' => $this->settingsService->getUserReminderDays1($this->userId),
			'reminderDays2Personal' => $this->settingsService->getUserReminderDays2($this->userId),
			'talkChatToken' => $this->settingsService->getUserTalkChatToken($this->userId),
			'sortBy' => $this->settingsService->getUserSortBy($this->userId),
			'sortDirection' => $this->settingsService->getUserSortDirection($this->userId),
			'filters' => $this->settingsService->getUserFilters($this->userId),
			'defaultAmountType' => $this->settingsService->getUserDefaultAmountType($this->userId),
			'backupEnabled' => $this->settingsService->getUserBackupEnabled($this->userId),
			'backupFolder' => $this->settingsService->getUserBackupFolder($this->userId),
			'backupInterval' => $this->settingsService->getUserBackupInterval($this->userId),
		]);
	}

	// ========================================
	// Admin Settings (ohne #[NoAdminRequired] = nur Admins)
	// ========================================

	/**
	 * Get admin settings
	 * No #[NoAdminRequired] = only admins can access
	 *
	 * Note: User access control is now handled via Nextcloud's native
	 * group-based app access (Admin → Apps → "Enable only for specific groups")
	 */
	public function getAdmin(): JSONResponse {
		return new JSONResponse([
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
			'customFieldLabel1' => $this->settingsService->getCustomFieldLabel(1),
			'customFieldLabel2' => $this->settingsService->getCustomFieldLabel(2),
			'customFieldLabel3' => $this->settingsService->getCustomFieldLabel(3),
			'customField1Enabled' => $this->settingsService->getCustomFieldEnabled(1),
			'customField2Enabled' => $this->settingsService->getCustomFieldEnabled(2),
			'customField3Enabled' => $this->settingsService->getCustomFieldEnabled(3),
			'aiProvider' => $this->settingsService->getAiProvider(),
			'aiApiKey' => $this->settingsService->getAiApiKey() !== '' ? SettingsService::API_KEY_MASK : '',
			'aiApiUrl' => $this->settingsService->getAiApiUrl(),
			'aiModel' => $this->settingsService->getAiModel(),
			'reminderLink' => $this->getReminderLinkDiagnostics(),
			'deletionSuccessor' => $this->settingsService->getDeletionSuccessor(),
			'deletionSuccessorDisplayName' => $this->getDeletionSuccessorDisplayName(),
		]);
	}

	/**
	 * Display name of the configured successor, so the settings field shows a
	 * name instead of a bare uid. Falls back to the uid when that account is
	 * gone - worth seeing, because a successor without an account receives
	 * nothing (#299).
	 */
	private function getDeletionSuccessorDisplayName(): string {
		$uid = $this->settingsService->getDeletionSuccessor();
		if ($uid === '') {
			return '';
		}

		return $this->userManager->get($uid)?->getDisplayName() ?? $uid;
	}

	/**
	 * Diagnose whether reminder mail links will point at the right host.
	 *
	 * Reminder mails are built in a background (cron) job from the system value
	 * `overwrite.cli.url`. If it is empty or points at a different host than the
	 * one the admin actually uses, the "open contract" link lands on the wrong
	 * place. We compare it against the host of the current admin request (which
	 * is the real access URL) and surface a hint in the settings UI.
	 *
	 * @return array{status: string, cliUrl: string, accessHost: string}
	 */
	private function getReminderLinkDiagnostics(): array {
		$cliUrl = $this->settingsService->getCliUrl();
		$accessHost = $this->request->getServerHost();

		if ($cliUrl === '') {
			$status = 'missing';
		} else {
			$cliHost = (string)parse_url($cliUrl, PHP_URL_HOST);
			$status = $this->normalizeHost($cliHost) === $this->normalizeHost($accessHost)
				? 'ok'
				: 'mismatch';
		}

		return [
			'status' => $status,
			'cliUrl' => $cliUrl,
			'accessHost' => $accessHost,
		];
	}

	/**
	 * Lower-case the host and strip an optional port so a pure scheme/port/path
	 * difference does not trigger a false "mismatch".
	 */
	private function normalizeHost(string $host): string {
		$host = strtolower(trim($host));
		$colon = strpos($host, ':');
		return $colon === false ? $host : substr($host, 0, $colon);
	}

	/**
	 * Update admin settings
	 * No #[NoAdminRequired] = only admins can access
	 */
	public function updateAdmin(
		?int $reminderDays1 = null,
		?int $reminderDays2 = null,
		?string $customFieldLabel1 = null,
		?string $customFieldLabel2 = null,
		?string $customFieldLabel3 = null,
		?bool $customField1Enabled = null,
		?bool $customField2Enabled = null,
		?bool $customField3Enabled = null,
		?string $aiProvider = null,
		?string $aiApiKey = null,
		?string $aiApiUrl = null,
		?string $aiModel = null,
		?string $deletionSuccessor = null,
	): JSONResponse {
		if ($reminderDays1 !== null) {
			$this->settingsService->setReminderDays1($reminderDays1);
		}

		if ($reminderDays2 !== null) {
			$this->settingsService->setReminderDays2($reminderDays2);
		}

		if ($customFieldLabel1 !== null) {
			$this->settingsService->setCustomFieldLabel(1, $customFieldLabel1);
		}

		if ($customFieldLabel2 !== null) {
			$this->settingsService->setCustomFieldLabel(2, $customFieldLabel2);
		}

		if ($customFieldLabel3 !== null) {
			$this->settingsService->setCustomFieldLabel(3, $customFieldLabel3);
		}

		if ($customField1Enabled !== null) {
			$this->settingsService->setCustomFieldEnabled(1, $customField1Enabled);
		}

		if ($customField2Enabled !== null) {
			$this->settingsService->setCustomFieldEnabled(2, $customField2Enabled);
		}

		if ($customField3Enabled !== null) {
			$this->settingsService->setCustomFieldEnabled(3, $customField3Enabled);
		}

		if ($aiProvider !== null) {
			$this->settingsService->setAiProvider($aiProvider);
		}

		// Only update API key if it's not the masked placeholder echoed back
		// by the frontend after a getAdmin() call (see SettingsService::API_KEY_MASK).
		if ($aiApiKey !== null && $aiApiKey !== SettingsService::API_KEY_MASK) {
			$this->settingsService->setAiApiKey($aiApiKey);
		}

		if ($aiApiUrl !== null) {
			$this->settingsService->setAiApiUrl($aiApiUrl);
		}

		if ($aiModel !== null) {
			$this->settingsService->setAiModel($aiModel);
		}

		// An empty string clears the successor, which is a valid choice: no
		// successor means contracts are left untouched on account deletion.
		if ($deletionSuccessor !== null) {
			$this->settingsService->setDeletionSuccessor($deletionSuccessor);
		}

		return new JSONResponse([
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
			'customFieldLabel1' => $this->settingsService->getCustomFieldLabel(1),
			'customFieldLabel2' => $this->settingsService->getCustomFieldLabel(2),
			'customFieldLabel3' => $this->settingsService->getCustomFieldLabel(3),
			'customField1Enabled' => $this->settingsService->getCustomFieldEnabled(1),
			'customField2Enabled' => $this->settingsService->getCustomFieldEnabled(2),
			'customField3Enabled' => $this->settingsService->getCustomFieldEnabled(3),
			'aiProvider' => $this->settingsService->getAiProvider(),
			'aiApiKey' => $this->settingsService->getAiApiKey() !== '' ? SettingsService::API_KEY_MASK : '',
			'aiApiUrl' => $this->settingsService->getAiApiUrl(),
			'aiModel' => $this->settingsService->getAiModel(),
			'reminderLink' => $this->getReminderLinkDiagnostics(),
			'deletionSuccessor' => $this->settingsService->getDeletionSuccessor(),
			'deletionSuccessorDisplayName' => $this->getDeletionSuccessorDisplayName(),
		]);
	}

	// ========================================
	// Permission Settings (Admin only)
	// ========================================

	/**
	 * Get permission settings (editors and viewers)
	 * No #[NoAdminRequired] = only admins can access
	 */
	public function getPermissions(): JSONResponse {
		return new JSONResponse([
			'editors' => $this->permissionService->getEditors(),
			'viewers' => $this->permissionService->getViewers(),
		]);
	}

	/**
	 * Update permission settings
	 * No #[NoAdminRequired] = only admins can access
	 *
	 * @param string[] $editors Array of "group:groupId" or "user:userId" entries
	 * @param string[] $viewers Array of "group:groupId" or "user:userId" entries
	 */
	public function updatePermissions(
		?array $editors = null,
		?array $viewers = null,
	): JSONResponse {
		if ($editors !== null) {
			$this->permissionService->setEditors($editors);
		}

		if ($viewers !== null) {
			$this->permissionService->setViewers($viewers);
		}

		return new JSONResponse([
			'editors' => $this->permissionService->getEditors(),
			'viewers' => $this->permissionService->getViewers(),
		]);
	}

	/**
	 * Search for users and groups
	 * Used by the permission picker in settings
	 * No #[NoAdminRequired] = only admins can access
	 */
	public function searchPrincipals(string $query = ''): JSONResponse {
		$results = [];
		$limit = 25;

		// Search groups
		$groups = $this->groupManager->search($query, $limit);
		foreach ($groups as $group) {
			$results[] = [
				'id' => 'group:' . $group->getGID(),
				'type' => 'group',
				'displayName' => $group->getDisplayName(),
				'gid' => $group->getGID(),
			];
		}

		// Search users
		$users = $this->userManager->search($query, $limit);
		foreach ($users as $user) {
			$results[] = [
				'id' => 'user:' . $user->getUID(),
				'type' => 'user',
				'displayName' => $user->getDisplayName(),
				'uid' => $user->getUID(),
			];
		}

		return new JSONResponse($results);
	}
}
