<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\BackgroundJob;

use OCA\ContractManager\BackgroundJob\StatusUpdateJob;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatusUpdateJobTest extends TestCase {

	private ContractMapper $contractMapper;
	private StatusUpdateJob $job;

	protected function setUp(): void {
		parent::setUp();
		$time = $this->createMock(ITimeFactory::class);
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->job = new StatusUpdateJob($time, $this->contractMapper, $logger);
	}

	/**
	 * #176: An expired fixed contract must be set to "ended" AND archived,
	 * consistent with how cancelled contracts are handled.
	 */
	public function testExpiredFixedContractIsEndedAndArchived(): void {
		$contract = new Contract();
		$contract->setName('Abgelaufener Vertrag');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setContractType('fixed');
		$contract->setArchived(0);

		$this->contractMapper->method('findExpiredActiveFixed')->willReturn([$contract]);
		$this->contractMapper->method('findCancelledDue')->willReturn([]);

		$this->job->run(null);

		$this->assertSame('ended', $contract->getStatus());
		$this->assertTrue((bool)$contract->getArchived(), 'Expired contract should be archived');
	}

	/**
	 * Existing behaviour (#136) must remain: cancelled-due contracts are
	 * ended and archived.
	 */
	public function testCancelledDueContractIsEndedAndArchived(): void {
		$contract = new Contract();
		$contract->setName('Gekündigter Vertrag');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setArchived(0);

		$this->contractMapper->method('findExpiredActiveFixed')->willReturn([]);
		$this->contractMapper->method('findCancelledDue')->willReturn([$contract]);

		$this->job->run(null);

		$this->assertSame('ended', $contract->getStatus());
		$this->assertTrue((bool)$contract->getArchived());
	}
}
