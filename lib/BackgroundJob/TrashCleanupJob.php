<?php

declare(strict_types=1);

namespace OCA\ContractManager\BackgroundJob;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * Background job that cleans up expired contracts from trash
 *
 * - Runs daily
 * - Deletes contracts that have been in trash for more than 30 days
 * - Excludes contracts created by admin users (their trash is never auto-cleaned)
 */
class TrashCleanupJob extends TimedJob {

	private const TRASH_RETENTION_DAYS = 30;

	public function __construct(
		ITimeFactory $time,
		private ContractMapper $contractMapper,
		private IGroupManager $groupManager,
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
		foreach ($expiredContracts as $contract) {
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

		return $deletedCount;
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
