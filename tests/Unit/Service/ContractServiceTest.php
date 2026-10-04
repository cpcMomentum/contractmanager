<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Notification\NotificationService;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\ForbiddenException;
use OCA\ContractManager\Service\NotFoundException;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractServiceTest extends TestCase {

	private ContractMapper $mapper;
	private ReminderOptOutMapper $optOutMapper;
	private IFactory $l10nFactory;
	private NotificationService $notificationService;
	private ContractService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(ContractMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$this->l10nFactory->method('get')->willReturn($l);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->service = new ContractService($this->mapper, $this->optOutMapper, $this->l10nFactory, $this->notificationService);
	}

	// ========================================
	// Validation Tests
	// ========================================

	public function testValidatePassesWithValidData(): void {
		$data = [
			'name' => 'Test Contract',
			'vendor' => 'Test Vendor',
			'startDate' => '2026-01-01',
			'endDate' => '2026-12-31',
			'status' => 'active',
		];

		// Should not throw
		$this->service->validate($data);
		$this->assertTrue(true);
	}

	public function testValidateThrowsWhenNameEmpty(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => '',
			'vendor' => 'Test Vendor',
		];

		$this->service->validate($data);
	}

	public function testValidateThrowsWhenNameWhitespaceOnly(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => '   ',
			'vendor' => 'Test Vendor',
		];

		$this->service->validate($data);
	}

	public function testValidateThrowsWhenVendorEmpty(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => 'Test Contract',
			'vendor' => '',
		];

		$this->service->validate($data);
	}

	public function testValidateThrowsWhenEndDateBeforeStartDate(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => 'Test Contract',
			'vendor' => 'Test Vendor',
			'startDate' => '2026-12-31',
			'endDate' => '2026-01-01',
		];

		$this->service->validate($data);
	}

	public function testValidateThrowsWhenEndDateEqualsStartDate(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => 'Test Contract',
			'vendor' => 'Test Vendor',
			'startDate' => '2026-06-15',
			'endDate' => '2026-06-15',
		];

		$this->service->validate($data);
	}

	public function testValidateThrowsWhenStatusInvalid(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => 'Test Contract',
			'vendor' => 'Test Vendor',
			'status' => 'invalid_status',
		];

		$this->service->validate($data);
	}

	public function testValidateAcceptsAllValidStatuses(): void {
		$validStatuses = ['active', 'cancelled', 'ended'];

		foreach ($validStatuses as $status) {
			$data = [
				'name' => 'Test Contract',
				'vendor' => 'Test Vendor',
				'status' => $status,
			];

			$this->service->validate($data);
		}

		$this->assertTrue(true);
	}

	public function testValidateAcceptsAllValidCostIntervals(): void {
		$validIntervals = ['weekly', 'monthly', 'quarterly', 'semi_annual', 'yearly', 'one_time'];

		foreach ($validIntervals as $interval) {
			$data = [
				'name' => 'Test Contract',
				'vendor' => 'Test Vendor',
				'costInterval' => $interval,
			];

			$this->service->validate($data);
		}

		$this->assertTrue(true);
	}

	public function testValidateThrowsWhenCostIntervalInvalid(): void {
		$this->expectException(ValidationException::class);

		$data = [
			'name' => 'Test Contract',
			'vendor' => 'Test Vendor',
			'costInterval' => 'daily',
		];

		$this->service->validate($data);
	}

	// ========================================
	// Access Control Tests
	// ========================================

	public function testCheckAccessPassesForOwner(): void {
		$contract = $this->createRealContract('testuser');

		// Should not throw
		$this->service->checkAccess($contract, 'testuser');
		$this->assertTrue(true);
	}

	public function testCheckAccessThrowsForNonOwner(): void {
		$this->expectException(ForbiddenException::class);

		$contract = $this->createRealContract('owner');

		$this->service->checkAccess($contract, 'otheruser');
	}

	// ========================================
	// Find Tests
	// ========================================

	public function testFindAllReturnsContracts(): void {
		$contracts = [
			$this->createMock(Contract::class),
			$this->createMock(Contract::class),
		];

		$this->mapper->expects($this->once())
			->method('findAll')
			->with('testuser')
			->willReturn($contracts);

		$result = $this->service->findAll('testuser');

		$this->assertCount(2, $result);
	}

	public function testFindArchivedReturnsArchivedContracts(): void {
		$contracts = [
			$this->createMock(Contract::class),
		];

		$this->mapper->expects($this->once())
			->method('findArchived')
			->with('testuser')
			->willReturn($contracts);

		$result = $this->service->findArchived('testuser');

		$this->assertCount(1, $result);
	}

	public function testFindReturnsContract(): void {
		$contract = $this->createMock(Contract::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$result = $this->service->find(1);

		$this->assertSame($contract, $result);
	}

	public function testFindThrowsNotFoundExceptionWhenNotExists(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->find(999);
	}

	public function testSearchReturnsMatchingContracts(): void {
		$contracts = [
			$this->createMock(Contract::class),
		];

		$this->mapper->expects($this->once())
			->method('search')
			->with('test', 'testuser')
			->willReturn($contracts);

		$result = $this->service->search('test', 'testuser');

		$this->assertCount(1, $result);
	}

	// ========================================
	// Create Tests
	// ========================================

	public function testCreateReturnsNewContract(): void {
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Contract $contract) {
				$this->assertEquals('Test Contract', $contract->getName());
				$this->assertEquals('Test Vendor', $contract->getVendor());
				$this->assertEquals('active', $contract->getStatus());
				$this->assertEquals('testuser', $contract->getCreatedBy());
				$this->assertEquals('EUR', $contract->getCurrency());
				return $contract;
			});

		$result = $this->service->create(
			name: 'Test Contract',
			vendor: 'Test Vendor',
			startDate: '2026-01-01',
			endDate: '2026-12-31',
			cancellationPeriod: '3 months',
			contractType: 'auto_renewal',
			userId: 'testuser',
		);

		$this->assertInstanceOf(Contract::class, $result);
	}

	public function testCreateWithAllParameters(): void {
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Contract $contract) {
				$this->assertEquals('Full Contract', $contract->getName());
				$this->assertEquals('Full Vendor', $contract->getVendor());
				$this->assertEquals(1, $contract->getCategoryId());
				$this->assertEquals('12 months', $contract->getRenewalPeriod());
				$this->assertEquals('100.00', $contract->getCost());
				$this->assertEquals('USD', $contract->getCurrency());
				$this->assertEquals('/Documents/contracts', $contract->getContractFolder());
				$this->assertEquals('/Documents/contracts/main.pdf', $contract->getMainDocument());
				$this->assertEquals(0, $contract->getReminderEnabled());
				$this->assertEquals(7, $contract->getReminderDays());
				$this->assertEquals('Test notes', $contract->getNotes());
				return $contract;
			});

		$result = $this->service->create(
			name: 'Full Contract',
			vendor: 'Full Vendor',
			startDate: '2026-01-01',
			endDate: '2026-12-31',
			cancellationPeriod: '3 months',
			contractType: 'auto_renewal',
			userId: 'testuser',
			categoryId: 1,
			renewalPeriod: '12 months',
			cost: '100.00',
			currency: 'USD',
			costInterval: 'monthly',
			contractFolder: '/Documents/contracts',
			mainDocument: '/Documents/contracts/main.pdf',
			reminderEnabled: false,
			reminderDays: 7,
			notes: 'Test notes',
		);

		$this->assertInstanceOf(Contract::class, $result);
	}

	// ========================================
	// Delete Tests
	// ========================================

	public function testDeleteRemovesContract(): void {
		$contract = $this->createRealContract('testuser');

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('delete')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->delete(1, 'testuser');

		$this->assertSame($contract, $result);
	}

	public function testDeleteThrowsNotFoundExceptionWhenNotExists(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->delete(999, 'testuser');
	}

	public function testDeleteThrowsForbiddenForNonOwner(): void {
		$this->expectException(ForbiddenException::class);

		$contract = $this->createRealContract('owner');

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->service->delete(1, 'otheruser');
	}

	// ========================================
	// Archive Tests
	// ========================================

	public function testArchiveSetsArchivedFlag(): void {
		$contract = $this->createRealContract('testuser');
		$this->assertEquals(0, $contract->getArchived());

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('update')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->archive(1);

		$this->assertSame($contract, $result);
		$this->assertEquals(1, $contract->getArchived());
	}

	public function testArchiveThrowsNotFoundForNonExistentContract(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->archive(999);
	}

	// ========================================
	// Restore Tests
	// ========================================

	public function testRestoreClearsArchivedFlag(): void {
		$contract = $this->createRealContract('testuser');
		$contract->setArchived(1);
		$this->assertEquals(1, $contract->getArchived());

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('update')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->restore(1);

		$this->assertSame($contract, $result);
		$this->assertEquals(0, $contract->getArchived());
	}

	public function testRestoreThrowsNotFoundForNonExistentContract(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->restore(999);
	}

	// ========================================
	// findVisibleVendors Tests (Issue #107)
	// ========================================

	public function testFindVisibleVendorsForwardsToMapper(): void {
		$expected = ['Allianz', 'Microsoft', 'Vodafone'];

		$this->mapper->expects($this->once())
			->method('findVisibleVendors')
			->with('alice', false)
			->willReturn($expected);

		$result = $this->service->findVisibleVendors('alice', false);

		$this->assertSame($expected, $result);
	}

	public function testFindVisibleVendorsPassesAdminFlag(): void {
		$this->mapper->expects($this->once())
			->method('findVisibleVendors')
			->with('admin', true)
			->willReturn(['SomePartner']);

		$result = $this->service->findVisibleVendors('admin', true);

		$this->assertSame(['SomePartner'], $result);
	}

	public function testFindVisibleVendorsReturnsEmptyArrayWhenNoneFound(): void {
		$this->mapper->expects($this->once())
			->method('findVisibleVendors')
			->willReturn([]);

		$result = $this->service->findVisibleVendors('bob', false);

		$this->assertSame([], $result);
	}

	// ========================================
	// setCategory (#359)
	// ========================================

	public function testSetCategoryReassignsContract(): void {
		$contract = $this->createRealContract('alice');
		$contract->setCategoryId(null);

		$this->mapper->expects($this->once())
			->method('find')
			->with(7)
			->willReturn($contract);
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(static fn (Contract $c): bool => $c->getCategoryId() === 5))
			->willReturnArgument(0);

		$result = $this->service->setCategory(7, 5);

		$this->assertSame(5, $result->getCategoryId());
	}

	public function testSetCategoryToNullRemovesCategory(): void {
		$contract = $this->createRealContract('alice');
		$contract->setCategoryId(3);

		$this->mapper->expects($this->once())
			->method('find')
			->with(7)
			->willReturn($contract);
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(static fn (Contract $c): bool => $c->getCategoryId() === null))
			->willReturnArgument(0);

		$result = $this->service->setCategory(7, null);

		$this->assertNull($result->getCategoryId());
	}

	public function testSetCategoryThrowsNotFoundWhenMissing(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->setCategory(999, 5);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Create a real Contract instance (Entity uses __call magic, so mocks don't work)
	 */
	private function createRealContract(string $createdBy): Contract {
		$contract = new Contract();
		$contract->setName('Test Contract');
		$contract->setVendor('Test Vendor');
		$contract->setCreatedBy($createdBy);
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setArchived(0);
		return $contract;
	}

	// ========================================
	// Trash (#438)
	// ========================================

	private function trashable(string $createdBy = 'alice', ?string $responsible = null, bool $private = false): Contract {
		$contract = new Contract();
		$contract->setId(7);
		$contract->setName('Mobilfunk');
		$contract->setCreatedBy($createdBy);
		$contract->setResponsibleUser($responsible);
		$contract->setIsPrivate($private);
		return $contract;
	}

	public function testSoftDeleteRecordsWhoDeleted(): void {
		$contract = $this->trashable();
		$this->mapper->method('find')->willReturn($contract);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->softDelete(7, 'bob');

		$this->assertSame('bob', $result->getDeletedBy());
		$this->assertNotNull($result->getDeletedAt());
	}

	public function testSoftDeleteByOtherUserNotifiesTheOwner(): void {
		$this->mapper->method('find')->willReturn($this->trashable('alice'));
		$this->mapper->method('update')->willReturnArgument(0);

		$this->notificationService->expects($this->once())
			->method('notifyOwnerAboutTrashedContract')
			->with($this->isInstanceOf(Contract::class), 'bob');

		$this->service->softDelete(7, 'bob');
	}

	public function testSoftDeleteByOwnerDoesNotNotify(): void {
		$this->mapper->method('find')->willReturn($this->trashable('alice'));
		$this->mapper->method('update')->willReturnArgument(0);

		$this->notificationService->expects($this->never())->method('notifyOwnerAboutTrashedContract');

		$this->service->softDelete(7, 'alice');
	}

	public function testSoftDeleteByCreatorNotifiesTheResponsibleUser(): void {
		// The creator is not the owner once someone else is responsible.
		$this->mapper->method('find')->willReturn($this->trashable('alice', 'carol'));
		$this->mapper->method('update')->willReturnArgument(0);

		$this->notificationService->expects($this->once())->method('notifyOwnerAboutTrashedContract');

		$this->service->softDelete(7, 'alice');
	}

	public function testRestoreFromTrashClearsDeleter(): void {
		$contract = $this->trashable();
		$contract->setDeletedAt(new DateTime());
		$contract->setDeletedBy('bob');
		$this->mapper->method('find')->willReturn($contract);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->restoreFromTrash(7);

		$this->assertNull($result->getDeletedBy());
		$this->assertNull($result->getDeletedAt());
	}

	/**
	 * @return array<string, array{0: string, 1: ?string, 2: bool, 3: string, 4: bool, 5: bool, 6: bool}>
	 */
	public static function restoreAccessCases(): array {
		// creator, responsible, private, user, isAdmin, isEditor, allowed
		return [
			'admin, fremder privater Vertrag' => ['alice', null, true, 'root', true, false, true],
			'Ersteller ohne Editor-Rolle' => ['alice', null, false, 'alice', false, false, true],
			'Zuständiger ohne Editor-Rolle' => ['alice', 'carol', false, 'carol', false, false, true],
			'Editor, fremder offener Vertrag' => ['alice', null, false, 'bob', false, true, true],
			'Editor, fremder privater Vertrag' => ['alice', null, true, 'bob', false, true, false],
			'Viewer, fremder offener Vertrag' => ['alice', null, false, 'bob', false, false, false],
			'Ersteller, Zuständiger ist ein anderer' => ['alice', 'carol', true, 'alice', false, false, true],
		];
	}

	#[DataProvider('restoreAccessCases')]
	public function testCheckRestoreAccess(string $creator, ?string $responsible, bool $private, string $user, bool $isAdmin, bool $isEditor, bool $allowed): void {
		$contract = $this->trashable($creator, $responsible, $private);

		if (!$allowed) {
			$this->expectException(ForbiddenException::class);
		}

		$this->service->checkRestoreAccess($contract, $user, $isAdmin, $isEditor);

		if ($allowed) {
			$this->addToAssertionCount(1);
		}
	}
}
