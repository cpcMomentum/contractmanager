<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCA\ContractManager\AppInfo\Application;

/**
 * Service to check user permissions for ContractManager
 *
 * Permission hierarchy:
 * - Admin: Nextcloud admin, has all permissions
 * - Editor: Can view, create, edit all visible contracts
 * - Viewer: Can only view contracts (read-only)
 */
class PermissionService {

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * Check if user is a Nextcloud admin
	 */
	public function isAdmin(string $userId): bool {
		return $this->groupManager->isAdmin($userId);
	}

	/**
	 * Check if user has Editor permission
	 */
	public function isEditor(string $userId): bool {
		return $this->hasPermission($userId, 'editors');
	}

	/**
	 * Check if user has Viewer permission
	 */
	public function isViewer(string $userId): bool {
		return $this->hasPermission($userId, 'viewers');
	}

	/**
	 * Check if user has any access to the app
	 * Admin always has access, or user must be Editor or Viewer
	 */
	public function hasAccess(string $userId): bool {
		return $this->isAdmin($userId) || $this->isEditor($userId) || $this->isViewer($userId);
	}

	/**
	 * Check if user can edit contracts (Admin or Editor)
	 */
	public function canEdit(string $userId): bool {
		return $this->isAdmin($userId) || $this->isEditor($userId);
	}

	/**
	 * Check if user can permanently delete contracts (Admin only)
	 */
	public function canDeletePermanently(string $userId): bool {
		return $this->isAdmin($userId);
	}

	/**
	 * Get permission info for frontend
	 */
	public function getPermissionInfo(string $userId): array {
		$isAdmin = $this->isAdmin($userId);
		$isEditor = $this->isEditor($userId);
		$isViewer = $this->isViewer($userId);

		return [
			'isAdmin' => $isAdmin,
			'isEditor' => $isEditor || $isAdmin,
			'isViewer' => $isViewer || $isEditor || $isAdmin,
			'canEdit' => $isAdmin || $isEditor,
			'canDeletePermanently' => $isAdmin,
		];
	}

	/**
	 * Get configured editors (for admin settings UI)
	 *
	 * @return string[] Array of "group:groupId" or "user:userId" entries
	 */
	public function getEditors(): array {
		$value = $this->config->getAppValue(Application::APP_ID, 'editors', '[]');
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Set configured editors
	 *
	 * @param string[] $entries Array of "group:groupId" or "user:userId" entries
	 */
	public function setEditors(array $entries): void {
		$this->config->setAppValue(Application::APP_ID, 'editors', json_encode(array_values($entries)));
	}

	/**
	 * Get configured viewers (for admin settings UI)
	 *
	 * @return string[] Array of "group:groupId" or "user:userId" entries
	 */
	public function getViewers(): array {
		$value = $this->config->getAppValue(Application::APP_ID, 'viewers', '[]');
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Set configured viewers
	 *
	 * @param string[] $entries Array of "group:groupId" or "user:userId" entries
	 */
	public function setViewers(array $entries): void {
		$this->config->setAppValue(Application::APP_ID, 'viewers', json_encode(array_values($entries)));
	}

	/**
	 * Check if user has a specific permission based on config
	 *
	 * Config format: JSON array with entries like:
	 * - "group:buchhaltung" - user is member of group "buchhaltung"
	 * - "user:max.mustermann" - user ID is "max.mustermann"
	 */
	private function hasPermission(string $userId, string $configKey): bool {
		$value = $this->config->getAppValue(Application::APP_ID, $configKey, '[]');
		$entries = json_decode($value, true);

		if (!is_array($entries)) {
			return false;
		}

		foreach ($entries as $entry) {
			if (!is_string($entry)) {
				continue;
			}

			if (str_starts_with($entry, 'user:')) {
				// Direct user match
				$entryUserId = substr($entry, 5);
				if ($entryUserId === $userId) {
					return true;
				}
			} elseif (str_starts_with($entry, 'group:')) {
				// Group membership check
				$groupId = substr($entry, 6);
				if ($this->groupManager->isInGroup($userId, $groupId)) {
					return true;
				}
			}
		}

		return false;
	}
}
