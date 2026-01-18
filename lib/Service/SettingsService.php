<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;

class SettingsService {

	private const KEY_ALLOWED_USERS = 'allowed_users';
	private const KEY_TALK_CHAT_TOKEN = 'talk_chat_token';
	private const KEY_REMINDER_DAYS_1 = 'reminder_days_1';
	private const KEY_REMINDER_DAYS_2 = 'reminder_days_2';
	private const KEY_EMAIL_REMINDER = 'email_reminder';

	private const DEFAULT_REMINDER_DAYS_1 = 14;
	private const DEFAULT_REMINDER_DAYS_2 = 3;

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
	) {
	}

	// ========================================
	// Admin-Settings (produktbeschreibung.md Zeile 206-213)
	// ========================================

	/**
	 * Get list of user IDs that have access to ContractManager
	 *
	 * @return string[]
	 */
	public function getAllowedUsers(): array {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_ALLOWED_USERS,
			'[]'
		);
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Set list of user IDs that have access to ContractManager
	 *
	 * @param string[] $userIds
	 */
	public function setAllowedUsers(array $userIds): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_ALLOWED_USERS,
			json_encode(array_values($userIds))
		);
	}

	/**
	 * Get Nextcloud Talk chat token for reminders
	 */
	public function getTalkChatToken(): ?string {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_TALK_CHAT_TOKEN,
			''
		);
		return $value !== '' ? $value : null;
	}

	/**
	 * Set Nextcloud Talk chat token for reminders
	 */
	public function setTalkChatToken(?string $token): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_TALK_CHAT_TOKEN,
			$token ?? ''
		);
	}

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

	// ========================================
	// Access Control
	// ========================================

	/**
	 * Check if a user can access ContractManager
	 *
	 * Access is granted if:
	 * 1. User is admin, OR
	 * 2. allowed_users is empty (all users allowed), OR
	 * 3. User is in allowed_users list
	 */
	public function canAccess(string $userId): bool {
		// Admins always have access
		if ($this->groupManager->isAdmin($userId)) {
			return true;
		}

		$allowedUsers = $this->getAllowedUsers();

		// Empty list = all users allowed
		if (empty($allowedUsers)) {
			return true;
		}

		return in_array($userId, $allowedUsers, true);
	}
}
