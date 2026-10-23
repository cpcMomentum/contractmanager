<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Service\ContractImportService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class ContractImportServiceTest extends TestCase {

	private ContractMapper $contractMapper;
	private CategoryMapper $categoryMapper;
	private ReminderOptOutMapper $optOutMapper;
	private IUserManager $userManager;
	private PermissionService $permissionService;
	private ITimeFactory $timeFactory;
	private IL10N $l10n;
	private Folder $userFolder;
	private ContractImportService $service;
	/** @var list<Contract> */
	private array $inserted = [];
	private bool $isAdmin = false;
	private bool $isEditor = true;

	protected function setUp(): void {
		parent::setUp();
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		// Importers are editors by default; the admin and viewer paths have their own tests
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->permissionService->method('isAdmin')->willReturnCallback(fn () => $this->isAdmin);
		$this->permissionService->method('isEditor')->willReturnCallback(fn () => $this->isEditor);
		// getUserFolder() returns IUserFolder on NC dev-master, which NC 32-34 lack
		$this->userFolder = $this->createMock(interface_exists(\OCP\Files\IUserFolder::class) ? \OCP\Files\IUserFolder::class : Folder::class);
		$this->contractMapper->method('transactional')->willReturnCallback(fn (callable $fn) => $fn());

		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($this->userFolder);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-08T12:00:00+00:00'));
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn (string $text, array $p = []) => vsprintf($text, $p));

		$this->contractMapper->method('insert')->willReturnCallback(function (Contract $c) {
			$c->setId(1000 + count($this->inserted));
			$this->inserted[] = $c;
			return $c;
		});

		$this->service = $this->makeService($this->contractMapper, $rootFolder);
	}

	private function makeService(ContractMapper $mapper, IRootFolder $rootFolder): ContractImportService {
		return new ContractImportService(
			$mapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$this->userManager,
			$rootFolder,
			$this->timeFactory,
			$this->l10n,
			$this->permissionService,
		);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function contract(array $overrides = []): array {
		return array_merge([
			'exportId' => 11, 'categoryExportId' => null, 'name' => 'Strom', 'vendor' => 'ACME',
			'status' => 'active', 'startDate' => '2026-01-01T00:00:00+00:00',
			'endDate' => '2026-12-31T00:00:00+00:00', 'cancellationPeriod' => '3 Monate',
			'contractType' => 'fixed', 'costInterval' => 'monthly', 'cost' => '12.50',
			'reminderEnabled' => 1, 'archived' => 0, 'isPrivate' => 0,
		], $overrides);
	}

	/**
	 * @param list<array<string, mixed>> $contracts
	 * @param list<array<string, mixed>> $categories
	 * @return array<string, mixed>
	 */
	private function doc(array $contracts, array $categories = [], array $optouts = []): array {
		return $this->service->parse(json_encode([
			'schemaVersion' => 1,
			'categories' => $categories,
			'contracts' => $contracts,
			'optouts' => $optouts,
		]));
	}

	private function existing(string $name, string $vendor, string $start): Contract {
		$c = new Contract();
		$c->setName($name);
		$c->setVendor($vendor);
		$c->setStartDate(new \DateTime($start));
		return $c;
	}

	public function testParseRejectsUnknownSchemaVersion(): void {
		$this->expectException(ValidationException::class);
		$this->service->parse(json_encode(['schemaVersion' => 2, 'contracts' => []]));
	}

	public function testParseRejectsMissingSchemaVersion(): void {
		$this->expectException(ValidationException::class);
		$this->service->parse(json_encode(['contracts' => []]));
	}

	public function testParseRejectsNonBackupJson(): void {
		$this->expectException(ValidationException::class);
		$this->service->parse('{"foo": 1}');
	}

	public function testParseRejectsInvalidJson(): void {
		$this->expectException(ValidationException::class);
		$this->service->parse('not json');
	}

	public function testImportSkipsContractTheUserAlreadyOwns(): void {
		$this->contractMapper->method('findAllVisible')->with('bob', false)->willReturn([
			$this->existing('strom ', 'Acme', '2026-01-01'),
		]);

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(),
			$this->contract(['exportId' => 12, 'name' => 'Handy']),
		]));

		$this->assertSame(1, $summary['contracts']);
		$this->assertSame(1, $summary['duplicates']);
		$this->assertCount(1, $this->inserted);
		$this->assertSame('Handy', $this->inserted[0]->getName());
	}

	public function testSameNameWithOtherStartDateIsNoDuplicate(): void {
		$this->contractMapper->method('findAllVisible')->willReturn([
			$this->existing('Strom', 'ACME', '2025-01-01'),
		]);

		$summary = $this->service->import('bob', $this->doc([$this->contract()]));

		$this->assertSame(0, $summary['duplicates']);
		$this->assertCount(1, $this->inserted);
	}

	public function testArchivedContractCountsAsDuplicate(): void {
		$this->contractMapper->method('findArchivedVisible')->with('bob', false)->willReturn([
			$this->existing('Strom', 'ACME', '2026-01-01'),
		]);

		$summary = $this->service->import('bob', $this->doc([$this->contract()]));

		$this->assertSame(1, $summary['duplicates']);
		$this->assertCount(0, $this->inserted);
	}

	public function testContractInTrashIsSkippedAndCountedSeparately(): void {
		$this->contractMapper->method('findDeletedRestorableBy')->with('bob', true)->willReturn([
			$this->existing('Strom', 'ACME', '2026-01-01'),
		]);

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(),
			$this->contract(['exportId' => 12, 'name' => 'Handy']),
		]));

		$this->assertSame(1, $summary['inTrash']);
		$this->assertSame(0, $summary['duplicates']);
		$this->assertSame(1, $summary['contracts']);
		$this->assertCount(1, $this->inserted);
		$this->assertSame('Handy', $this->inserted[0]->getName());
	}

	public function testActiveDuplicateWinsOverTrash(): void {
		$this->contractMapper->method('findAllVisible')->willReturn([$this->existing('Strom', 'ACME', '2026-01-01')]);
		$this->contractMapper->method('findDeletedRestorableBy')->willReturn([$this->existing('Strom', 'ACME', '2026-01-01')]);

		$summary = $this->service->preview('bob', $this->doc([$this->contract()]));

		$this->assertSame(1, $summary['duplicates']);
		$this->assertSame(0, $summary['inTrash']);
	}

	public function testEditorComparesAgainstVisibleContractsOnly(): void {
		$this->contractMapper->expects($this->once())->method('findAllVisible')->with('bob', false)->willReturn([]);
		$this->contractMapper->expects($this->once())->method('findArchivedVisible')->with('bob', false)->willReturn([]);
		$this->contractMapper->expects($this->once())->method('findDeletedRestorableBy')->with('bob', true)->willReturn([]);
		$this->contractMapper->expects($this->never())->method('findAllDeleted');

		$this->service->preview('bob', $this->doc([$this->contract()]));
	}

	public function testAdminComparesAgainstAllContracts(): void {
		$this->isAdmin = true;
		$this->contractMapper->expects($this->once())->method('findAllVisible')->with('bob', true)->willReturn([]);
		$this->contractMapper->expects($this->once())->method('findArchivedVisible')->with('bob', true)->willReturn([]);
		$this->contractMapper->expects($this->once())->method('findAllDeleted')->willReturn([
			$this->existing('Strom', 'ACME', '2026-01-01'),
		]);
		$this->contractMapper->expects($this->never())->method('findDeletedRestorableBy');

		$summary = $this->service->preview('bob', $this->doc([$this->contract()]));

		$this->assertSame(1, $summary['inTrash']);
	}

	public function testUserWithoutEditorRoleSeesOnlyOwnTrash(): void {
		// user_migration imports for any user, not only editors
		$this->isEditor = false;
		$this->contractMapper->expects($this->once())->method('findDeletedRestorableBy')->with('bob', false)->willReturn([]);

		$this->service->preview('bob', $this->doc([$this->contract()]));
	}

	public function testUserFolderIsFetchedOncePerRun(): void {
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects($this->once())->method('getUserFolder')->with('bob')->willReturn($this->userFolder);
		$this->userFolder->method('nodeExists')->willReturn(true);

		$summary = $this->makeService($this->contractMapper, $rootFolder)->import('bob', $this->doc([
			$this->contract(['mainDocument' => '/a.pdf', 'contractFolder' => '/A']),
			$this->contract(['exportId' => 12, 'name' => 'Handy', 'mainDocument' => '/b.pdf']),
			$this->contract(['exportId' => 13, 'name' => 'Web', 'contractFolder' => '/C']),
		]));

		$this->assertSame(0, $summary['missingFiles']);
	}

	public function testUnavailableUserFolderCountsPathsAsMissing(): void {
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects($this->once())->method('getUserFolder')->willThrowException(new \RuntimeException('no storage'));

		$summary = $this->makeService($this->contractMapper, $rootFolder)->preview('bob', $this->doc([
			$this->contract(['mainDocument' => '/a.pdf']),
			$this->contract(['exportId' => 12, 'name' => 'Handy', 'mainDocument' => '/b.pdf']),
		]));

		$this->assertSame(2, $summary['missingFiles']);
	}

	public function testDuplicateWithinTheFileIsImportedOnce(): void {

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(),
			$this->contract(['exportId' => 12]),
		]));

		$this->assertSame(1, $summary['contracts']);
		$this->assertSame(1, $summary['duplicates']);
	}

	public function testPreviewWritesNothingAndMatchesImport(): void {
		$this->categoryMapper->method('findByName')->willReturn(null);
		$this->categoryMapper->expects($this->once())->method('insert')->willReturnCallback(function (Category $cat) {
			$cat->setId(99);
			return $cat;
		});
		$doc = $this->doc(
			[$this->contract(['categoryExportId' => 5])],
			[['exportId' => 5, 'name' => 'Energie']],
		);

		$preview = $this->service->preview('bob', $doc);
		$this->assertCount(0, $this->inserted, 'preview must not write');

		$summary = $this->service->import('bob', $doc);
		$this->assertSame($preview, $summary);
		$this->assertSame(1, $summary['categories']);
		$this->assertSame(99, $this->inserted[0]->getCategoryId());
	}

	public function testExistingCategoryIsReusedByName(): void {
		$cat = new Category();
		$cat->setId(7);
		$cat->setName('Energie');
		$this->categoryMapper->method('findByName')->with('Energie')->willReturn($cat);
		$this->categoryMapper->expects($this->never())->method('insert');

		$summary = $this->service->import('bob', $this->doc(
			[$this->contract(['categoryExportId' => 5])],
			[['exportId' => 5, 'name' => 'Energie']],
		));

		$this->assertSame(0, $summary['categories']);
		$this->assertSame(7, $this->inserted[0]->getCategoryId());
	}

	public function testInvalidValuesFallBackToDefaults(): void {

		$this->service->import('bob', $this->doc([$this->contract([
			'status' => 'gekündigt', 'costInterval' => 'biweekly', 'contractType' => 'x',
			'amountType' => 'y', 'cancellationDeadlineType' => 'z', 'cost' => 'viel',
		])]));

		$c = $this->inserted[0];
		$this->assertSame(Contract::STATUS_ACTIVE, $c->getStatus());
		$this->assertNull($c->getCostInterval());
		$this->assertSame(Contract::TYPE_FIXED, $c->getContractType());
		$this->assertSame(Contract::AMOUNT_TYPE_NETTO, $c->getAmountType());
		$this->assertSame(Contract::DEADLINE_TYPE_NORMAL, $c->getCancellationDeadlineType());
		$this->assertNull($c->getCost());
	}

	public function testValidValuesAreKept(): void {

		$this->service->import('bob', $this->doc([$this->contract([
			'status' => 'cancelled', 'costInterval' => 'weekly', 'contractType' => 'auto_renewal',
			'amountType' => 'brutto', 'cancellationDeadlineType' => 'month_end',
		])]));

		$c = $this->inserted[0];
		$this->assertSame(Contract::STATUS_CANCELLED, $c->getStatus());
		$this->assertSame(Contract::INTERVAL_WEEKLY, $c->getCostInterval());
		$this->assertSame(Contract::TYPE_AUTO_RENEWAL, $c->getContractType());
		$this->assertSame(Contract::AMOUNT_TYPE_BRUTTO, $c->getAmountType());
		$this->assertSame(Contract::DEADLINE_TYPE_MONTH_END, $c->getCancellationDeadlineType());
		$this->assertSame('bob', $c->getCreatedBy());
	}

	public function testContractWithoutNameOrStartDateIsSkipped(): void {

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(['name' => '  ']),
			$this->contract(['exportId' => 12, 'startDate' => null]),
			$this->contract(['exportId' => 13, 'startDate' => 'kein Datum']),
		]));

		$this->assertSame(3, $summary['invalid']);
		$this->assertCount(0, $this->inserted);
	}

	public function testUnknownResponsibleUserIsCleared(): void {
		$this->userManager->method('userExists')->willReturnCallback(fn (string $uid) => $uid === 'alice');

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(['responsibleUser' => 'alice']),
			$this->contract(['exportId' => 12, 'name' => 'Handy', 'responsibleUser' => 'ghost']),
		]));

		$this->assertSame(1, $summary['unknownUsers']);
		$this->assertSame('alice', $this->inserted[0]->getResponsibleUser());
		$this->assertNull($this->inserted[1]->getResponsibleUser());
	}

	public function testMissingAttachmentIsCountedButPathKept(): void {
		$this->userFolder->method('nodeExists')->willReturnCallback(fn (string $p) => $p === 'Verträge/da.pdf');

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(['mainDocument' => '/Verträge/da.pdf']),
			$this->contract(['exportId' => 12, 'name' => 'Handy', 'mainDocument' => '/Verträge/weg.pdf']),
			$this->contract(['exportId' => 13, 'name' => 'Web', 'mainDocument' => 'https://example.com/v.pdf']),
		]));

		$this->assertSame(1, $summary['missingFiles']);
		$this->assertSame('/Verträge/weg.pdf', $this->inserted[1]->getMainDocument());
	}

	public function testOptOutIsRemappedToNewContract(): void {
		$this->optOutMapper->expects($this->once())->method('setOptOut')->with(1000, 'bob', true);

		$this->service->import('bob', $this->doc(
			[$this->contract()],
			[],
			[['contractExportId' => 11]],
		));
	}

	public function testOverlongValuesAreCutToColumnLength(): void {

		$this->service->import('bob', $this->doc([$this->contract([
			'name' => str_repeat('ä', 300), 'currency' => 'EURO', 'cancellationPeriod' => str_repeat('x', 80),
			'vendor' => ['kein', 'Text'],
		])]));

		$c = $this->inserted[0];
		$this->assertSame(255, mb_strlen($c->getName()));
		$this->assertSame('EUR', $c->getCurrency());
		$this->assertSame(50, strlen($c->getCancellationPeriod()));
		$this->assertSame('', $c->getVendor());
	}

	public function testImportWritesInsideOneTransaction(): void {
		$mapper = $this->createMock(ContractMapper::class);
		$inTransaction = false;
		$mapper->expects($this->once())->method('transactional')->willReturnCallback(function (callable $fn) use (&$inTransaction) {
			$inTransaction = true;
			$result = $fn();
			$inTransaction = false;
			return $result;
		});
		$mapper->expects($this->once())->method('insert')->willReturnCallback(function (Contract $c) use (&$inTransaction) {
			$this->assertTrue($inTransaction, 'insert must run inside the transaction');
			$c->setId(1);
			return $c;
		});
		$service = $this->makeService($mapper, $this->createMock(IRootFolder::class));

		$service->import('bob', $this->doc([$this->contract()]));
	}
}
