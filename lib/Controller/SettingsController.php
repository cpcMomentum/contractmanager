<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {

	public function __construct(
		IRequest $request,
		private ?string $userId,
		private SettingsService $settingsService,
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
	 */
	public function getAdmin(): JSONResponse {
		return new JSONResponse([
			'allowedUsers' => $this->settingsService->getAllowedUsers(),
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
		?array $allowedUsers = null,
		?string $talkChatToken = null,
		?int $reminderDays1 = null,
		?int $reminderDays2 = null,
	): JSONResponse {
		if ($allowedUsers !== null) {
			$this->settingsService->setAllowedUsers($allowedUsers);
		}

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
			'allowedUsers' => $this->settingsService->getAllowedUsers(),
			'talkChatToken' => $this->settingsService->getTalkChatToken(),
			'reminderDays1' => $this->settingsService->getReminderDays1(),
			'reminderDays2' => $this->settingsService->getReminderDays2(),
		]);
	}
}
