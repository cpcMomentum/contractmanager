<?php

declare(strict_types=1);

namespace OCA\ContractManager\BackgroundJob;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\AutoBackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Periodically writes JSON snapshots of contract data for users who enabled the
 * auto-backup (#296). Runs hourly as a check cadence; the actual per-user
 * spacing (daily/weekly/monthly) is enforced inside AutoBackupService.
 */
class AutoBackupJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private AutoBackupService $autoBackupService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Check hourly; per-user interval decides who is actually due.
		$this->setInterval(3600);
	}

	protected function run($argument): void {
		try {
			$count = $this->autoBackupService->runDueBackups();
			if ($count > 0) {
				$this->logger->info('Auto-backup wrote snapshots', [
					'app' => Application::APP_ID,
					'users' => $count,
				]);
			}
		} catch (\Exception $e) {
			$this->logger->error('Auto-backup run failed', [
				'app' => Application::APP_ID,
				'exception' => $e->getMessage(),
			]);
		}
	}
}
