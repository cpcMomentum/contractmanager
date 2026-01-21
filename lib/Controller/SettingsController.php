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
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	// ========================================
	// User Settings (mit @NoAdminRequired)
	// ========================================

	/**
	 * Get current user's settings
	 */
	#[NoAdminRequired]
	public function get(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		return new JSONResponse([
			'emailReminder' => $this->settingsService->getUserEmailReminder($this->userId),
		]);
	}

	/**
	 * Update current user's settings
	 */
	#[NoAdminRequired]
	public function update(bool $emailReminder): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$this->settingsService->setUserEmailReminder($this->userId, $emailReminder);

		return new JSONResponse([
			'emailReminder' => $this->settingsService->getUserEmailReminder($this->userId),
		]);
	}

	// ========================================
	// Admin Settings (ohne @NoAdminRequired = nur Admins)
	// ========================================

	/**
	 * Get admin settings
	 * No @NoAdminRequired = only admins can access
	 *
	 * Note: User access control is now handled via Nextcloud's native
	 * group-based app access (Admin → Apps → "Enable only for specific groups")
	 */
	public function getAdmin(): JSONResponse {
		return new JSONResponse([
			'talkChatToken' => $this->settingsService->getTalkChatToken(),
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
		]);
	}

	/**
	 * Update admin settings
	 * No @NoAdminRequired = only admins can access
	 */
	public function updateAdmin(
		?string $talkChatToken = null,
		?int $reminderDays1 = null,
		?int $reminderDays2 = null,
	): JSONResponse {
		if ($talkChatToken !== null) {
			$this->settingsService->setTalkChatToken($talkChatToken ?: null);
		}

		if ($reminderDays1 !== null) {
			$this->settingsService->setReminderDays1($reminderDays1);
		}

		if ($reminderDays2 !== null) {
			$this->settingsService->setReminderDays2($reminderDays2);
		}

		return new JSONResponse([
			'talkChatToken' => $this->settingsService->getTalkChatToken(),
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
		]);
	}

	// ========================================
	// Permission Settings (Admin only)
	// ========================================

	/**
	 * Get permission settings (editors and viewers)
	 * No @NoAdminRequired = only admins can access
	 */
	public function getPermissions(): JSONResponse {
		return new JSONResponse([
			'editors' => $this->permissionService->getEditors(),
			'viewers' => $this->permissionService->getViewers(),
		]);
	}

	/**
	 * Update permission settings
	 * No @NoAdminRequired = only admins can access
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
	 * No @NoAdminRequired = only admins can access
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
