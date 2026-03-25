<?php

declare(strict_types=1);

namespace OCA\ContractManager\BackgroundJob;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Background job that automatically sets expired fixed contracts to "ended"
 * Runs once per day
 */
class StatusUpdateJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private ContractMapper $contractMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once per day (24 * 60 * 60 = 86400 seconds)
		$this->setInterval(86400);
	}

	protected function run($argument): void {
		$this->logger->info('Starting expired contract status check', [
			'app' => Application::APP_ID,
		]);

		try {
			$expiredContracts = $this->contractMapper->findExpiredActiveFixed();
			$updatedCount = 0;

			foreach ($expiredContracts as $contract) {
				$contract->setStatus('ended');
				$contract->setUpdatedAt(new DateTime());
				$this->contractMapper->update($contract);
				$updatedCount++;

				$this->logger->info('Contract status set to ended: ' . $contract->getName(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
					'endDate' => $contract->getEndDate()?->format('Y-m-d'),
				]);
			}

			$this->logger->info('Expired contract status check completed', [
				'app' => Application::APP_ID,
				'updatedCount' => $updatedCount,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Expired contract status check failed', [
				'app' => Application::APP_ID,
				'exception' => $e->getMessage(),
			]);
		}
	}
}
