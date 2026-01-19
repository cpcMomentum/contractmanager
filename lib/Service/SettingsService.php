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

	private const KEY_TALK_CHAT_TOKEN = 'talk_chat_token';
	private const KEY_REMINDER_DAYS_1 = 'reminder_days_1';
	private const KEY_REMINDER_DAYS_2 = 'reminder_days_2';
	private const KEY_EMAIL_REMINDER = 'email_reminder';

	private const DEFAULT_REMINDER_DAYS_1 = 14;
	private const DEFAULT_REMINDER_DAYS_2 = 3;

	public function __construct(
		private IConfig $config,
	) {
	}

	// ========================================
	// Admin-Settings
	// ========================================

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

}
