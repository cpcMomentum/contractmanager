<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\BackgroundJob;

use OCA\ContractManager\BackgroundJob\StatusUpdateJob;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatusUpdateJobTest extends TestCase {

	private ContractMapper $contractMapper;
	private StatusUpdateJob $job;

	protected function setUp(): void {
		parent::setUp();
		// 23:30 UTC on 10 Nov = 00:30 on 11 Nov in the server's timezone (Berlin).
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(
			static fn (string $t = 'now', ?\DateTimeZone $tz = null): \DateTime
				=> (new \DateTime('2026-11-10 23:30:00', new \DateTimeZone('UTC')))->setTimezone($tz ?? new \DateTimeZone('UTC')),
		);
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getDefaultTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->job = new StatusUpdateJob($time, $this->contractMapper, $logger, $dateTimeZone);
	}

	/**
	 * #512: Both queries get today's date in the server's timezone, not the
	 * UTC day, which is still yesterday shortly after local midnight.
	 */
	public function testQueriesUseTheLocalDay(): void {
		$isLocalDay = $this->callback(static fn (\DateTime $d): bool => $d->format('Y-m-d') === '2026-11-11');
		$this->contractMapper->expects($this->once())->method('findExpiredActiveFixed')->with($isLocalDay)->willReturn([]);
		$this->contractMapper->expects($this->once())->method('findCancelledDue')->with($isLocalDay)->willReturn([]);

		$this->invokeRun(null);
	}

	private function invokeRun(mixed $argument): void {
		(new \ReflectionMethod($this->job, 'run'))->invoke($this->job, $argument);
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

		$this->invokeRun(null);

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

		$this->invokeRun(null);

		$this->assertSame('ended', $contract->getStatus());
		$this->assertTrue((bool)$contract->getArchived());
	}
}
