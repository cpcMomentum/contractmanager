<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\ForbiddenException;
use OCA\ContractManager\Service\NotFoundException;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class ContractServiceTest extends TestCase {

	private ContractMapper $mapper;
	private ContractService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(ContractMapper::class);
		$this->service = new ContractService($this->mapper);
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

	// ========================================
	// Access Control Tests
	// ========================================

	public function testCheckAccessPassesForOwner(): void {
		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('testuser');

		// Should not throw
		$this->service->checkAccess($contract, 'testuser');
		$this->assertTrue(true);
	}

	public function testCheckAccessThrowsForNonOwner(): void {
		$this->expectException(ForbiddenException::class);

		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('owner');

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
			->willReturn($contracts);

		$result = $this->service->findAll();

		$this->assertCount(2, $result);
	}

	public function testFindArchivedReturnsArchivedContracts(): void {
		$contracts = [
			$this->createMock(Contract::class),
		];

		$this->mapper->expects($this->once())
			->method('findArchived')
			->willReturn($contracts);

		$result = $this->service->findArchived();

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
			->with('test')
			->willReturn($contracts);

		$result = $this->service->search('test');

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
		$contract = $this->createMock(Contract::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('delete')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->delete(1);

		$this->assertSame($contract, $result);
	}

	public function testDeleteThrowsNotFoundExceptionWhenNotExists(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->delete(999);
	}

	// ========================================
	// Archive Tests
	// ========================================

	public function testArchiveSetsArchivedFlag(): void {
		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('testuser');

		$contract->expects($this->once())
			->method('setArchived')
			->with(true);

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('update')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->archive(1, 'testuser');

		$this->assertSame($contract, $result);
	}

	public function testArchiveThrowsForbiddenForNonOwner(): void {
		$this->expectException(ForbiddenException::class);

		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('owner');

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->service->archive(1, 'otheruser');
	}

	public function testArchiveThrowsNotFoundExceptionWhenNotExists(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->archive(999, 'testuser');
	}

	// ========================================
	// Restore Tests
	// ========================================

	public function testRestoreClearsArchivedFlag(): void {
		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('testuser');

		$contract->expects($this->once())
			->method('setArchived')
			->with(false);

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->mapper->expects($this->once())
			->method('update')
			->with($contract)
			->willReturn($contract);

		$result = $this->service->restore(1, 'testuser');

		$this->assertSame($contract, $result);
	}

	public function testRestoreThrowsForbiddenForNonOwner(): void {
		$this->expectException(ForbiddenException::class);

		$contract = $this->createMock(Contract::class);
		$contract->method('getCreatedBy')->willReturn('owner');

		$this->mapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($contract);

		$this->service->restore(1, 'otheruser');
	}

	public function testRestoreThrowsNotFoundExceptionWhenNotExists(): void {
		$this->expectException(NotFoundException::class);

		$this->mapper->expects($this->once())
			->method('find')
			->with(999)
			->willThrowException(new DoesNotExistException(''));

		$this->service->restore(999, 'testuser');
	}
}
