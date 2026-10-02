<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\BackgroundJob;

use OCA\ContractManager\BackgroundJob\TrashCleanupJob;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * #299: trashed contracts of a deleted user must survive the automatic
 * retention cleanup. Once the owner is gone nobody can restore them, so
 * deleting them for good stays a deliberate admin action.
 */
class TrashCleanupJobTest extends TestCase {

	private ContractMapper $contractMapper;
	private IUserManager $userManager;
	private TrashCleanupJob $job;

	protected function setUp(): void {
		parent::setUp();
		$time = $this->createMock(ITimeFactory::class);
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$logger = $this->createMock(LoggerInterface::class);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->willReturn(null);

		$this->job = new TrashCleanupJob(
			$time,
			$this->contractMapper,
			$groupManager,
			$this->userManager,
			$logger
		);
	}

	private function invokeRun(): void {
		(new \ReflectionMethod($this->job, 'run'))->invoke($this->job, null);
	}

	/**
	 * A trashed contract. By default the creator deleted it themselves, which
	 * is the case the automatic cleanup is meant for (#438).
	 */
	private function contractOf(string $createdBy, ?string $deletedBy = 'creator', ?string $responsible = null): Contract {
		$contract = new Contract();
		$contract->setName('Vertrag von ' . $createdBy);
		$contract->setCreatedBy($createdBy);
		if ($responsible !== null) {
			$contract->setResponsibleUser($responsible);
		}
		$contract->setDeletedBy($deletedBy === 'creator' ? $createdBy : $deletedBy);
		return $contract;
	}

	public function testExpiredContractOfExistingUserIsDeleted(): void {
		$contract = $this->contractOf('alice');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->with('alice')->willReturn(true);

		$this->contractMapper->expects($this->once())
			->method('delete')
			->with($contract);

		$this->invokeRun();
	}

	public function testExpiredContractOfDeletedUserIsKept(): void {
		$contract = $this->contractOf('ghost');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->with('ghost')->willReturn(false);

		$this->contractMapper->expects($this->never())->method('delete');

		$this->invokeRun();
	}

	public function testExistenceIsCheckedOncePerUser(): void {
		$contracts = [
			$this->contractOf('alice'),
			$this->contractOf('alice'),
			$this->contractOf('bob'),
		];

		$this->contractMapper->method('findExpiredDeleted')->willReturn($contracts);

		$this->userManager->expects($this->exactly(2))
			->method('userExists')
			->willReturn(true);

		$this->invokeRun();
	}

	public function testMixedBatchDeletesOnlyContractsOfExistingUsers(): void {
		$alive = $this->contractOf('alice');
		$orphan = $this->contractOf('ghost');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$alive, $orphan]);
		$this->userManager->method('userExists')->willReturnMap([
			['alice', true],
			['ghost', false],
		]);

		$this->contractMapper->expects($this->once())
			->method('delete')
			->with($alive);

		$this->invokeRun();
	}

	// ----- #438: only what the owner deleted themselves is purged -----

	public function testContractDeletedBySomeoneElseIsKept(): void {
		$contract = $this->contractOf('alice', 'bob');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->willReturn(true);

		$this->contractMapper->expects($this->never())->method('delete');

		$this->invokeRun();
	}

	public function testContractWithoutRecordedDeleterIsKept(): void {
		// Trashed before deleted_by existed: who deleted it is unknown.
		$contract = $this->contractOf('alice', null);

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->willReturn(true);

		$this->contractMapper->expects($this->never())->method('delete');

		$this->invokeRun();
	}

	public function testResponsibleUserDeletingIsTheOwnerAndPurged(): void {
		$contract = $this->contractOf('alice', 'carol', 'carol');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->willReturn(true);

		$this->contractMapper->expects($this->once())->method('delete')->with($contract);

		$this->invokeRun();
	}

	public function testCreatorDeletingContractOfAnotherResponsibleIsKept(): void {
		// The creator is no longer the owner once someone else is responsible.
		$contract = $this->contractOf('alice', 'alice', 'carol');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$contract]);
		$this->userManager->method('userExists')->willReturn(true);

		$this->contractMapper->expects($this->never())->method('delete');

		$this->invokeRun();
	}

	public function testMixedBatchPurgesOnlySelfDeleted(): void {
		$self = $this->contractOf('alice');
		$foreign = $this->contractOf('alice', 'bob');

		$this->contractMapper->method('findExpiredDeleted')->willReturn([$foreign, $self]);
		$this->userManager->method('userExists')->willReturn(true);

		$this->contractMapper->expects($this->once())->method('delete')->with($self);

		$this->invokeRun();
	}
}
