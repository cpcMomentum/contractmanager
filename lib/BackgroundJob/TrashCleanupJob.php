<?php

declare(strict_types=1);

namespace OCA\ContractManager\BackgroundJob;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Background job that cleans up expired contracts from trash
 *
 * - Runs daily
 * - Deletes contracts that have been in trash for more than 30 days
 * - Excludes contracts created by admin users (their trash is never auto-cleaned)
 * - Excludes contracts whose creator no longer has an account (#299): purging
 *   those for good stays a deliberate admin action
 */
class TrashCleanupJob extends TimedJob {

	private const TRASH_RETENTION_DAYS = 30;

	/** @var array<string, bool> */
	private array $userExistsCache = [];

	public function __construct(
		ITimeFactory $time,
		private ContractMapper $contractMapper,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run daily (24 * 60 * 60 = 86400 seconds)
		$this->setInterval(86400);
	}

	protected function run($argument): void {
		$this->logger->info('Starting trash cleanup job', [
			'app' => Application::APP_ID,
		]);

		try {
			$deletedCount = $this->cleanupExpiredTrash();

			$this->logger->info('Trash cleanup completed', [
				'app' => Application::APP_ID,
				'deletedCount' => $deletedCount,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Trash cleanup failed', [
				'app' => Application::APP_ID,
				'exception' => $e->getMessage(),
			]);
		}
	}

	/**
	 * Delete contracts that have been in trash for more than 30 days
	 * Excludes contracts created by admin users
	 *
	 * @return int Number of permanently deleted contracts
	 */
	private function cleanupExpiredTrash(): int {
		$cutoffDate = new DateTime('-' . self::TRASH_RETENTION_DAYS . ' days');

		// Get all admin user IDs - their contracts should not be auto-deleted
		$adminUserIds = $this->getAdminUserIds();

		// Find expired contracts (excluding admins)
		$expiredContracts = $this->contractMapper->findExpiredDeleted($cutoffDate, $adminUserIds);

		$deletedCount = 0;
		$orphanedCount = 0;
		foreach ($expiredContracts as $contract) {
			// Contracts whose creator no longer has an account are left alone
			// (#299): once the owner is gone, nobody can restore them anymore,
			// so purging them silently would take the decision away from the
			// admin. Deleting these for good stays a deliberate admin action.
			if (!$this->userStillExists($contract->getCreatedBy())) {
				$orphanedCount++;
				continue;
			}

			try {
				$this->contractMapper->delete($contract);
				$deletedCount++;

				$this->logger->info('Auto-deleted contract from trash', [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
					'contractName' => $contract->getName(),
					'createdBy' => $contract->getCreatedBy(),
					'deletedAt' => $contract->getDeletedAt()?->format('Y-m-d H:i:s'),
				]);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to auto-delete contract from trash', [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
					'exception' => $e->getMessage(),
				]);
			}
		}

		if ($orphanedCount > 0) {
			$this->logger->info('Kept trashed contracts of deleted users from auto-cleanup', [
				'app' => Application::APP_ID,
				'keptCount' => $orphanedCount,
			]);
		}

		return $deletedCount;
	}

	/**
	 * Whether the given user id still resolves to an account.
	 *
	 * Cached per run: a single user can easily own many expired contracts, and
	 * this cannot be decided in SQL.
	 */
	private function userStillExists(string $userId): bool {
		if ($userId === '') {
			return false;
		}

		if (!array_key_exists($userId, $this->userExistsCache)) {
			$this->userExistsCache[$userId] = $this->userManager->userExists($userId);
		}

		return $this->userExistsCache[$userId];
	}

	/**
	 * Get all user IDs that are in the admin group
	 *
	 * @return string[]
	 */
	private function getAdminUserIds(): array {
		$adminGroup = $this->groupManager->get('admin');

		if ($adminGroup === null) {
			return [];
		}

		$adminUserIds = [];
		foreach ($adminGroup->getUsers() as $user) {
			$adminUserIds[] = $user->getUID();
		}

		return $adminUserIds;
	}
}
