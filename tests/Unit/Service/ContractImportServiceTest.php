<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Service\ContractImportService;
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
	private Folder $userFolder;
	private ContractImportService $service;
	/** @var list<Contract> */
	private array $inserted = [];

	protected function setUp(): void {
		parent::setUp();
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->contractMapper->method('transactional')->willReturnCallback(fn (callable $fn) => $fn());

		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($this->userFolder);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-08T12:00:00+00:00'));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $p = []) => vsprintf($text, $p));

		$this->contractMapper->method('insert')->willReturnCallback(function (Contract $c) {
			$c->setId(1000 + count($this->inserted));
			$this->inserted[] = $c;
			return $c;
		});

		$this->service = new ContractImportService(
			$this->contractMapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$this->userManager,
			$rootFolder,
			$timeFactory,
			$l10n,
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
		$this->contractMapper->method('findAllByOwner')->with('bob')->willReturn([
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
		$this->contractMapper->method('findAllByOwner')->willReturn([
			$this->existing('Strom', 'ACME', '2025-01-01'),
		]);

		$summary = $this->service->import('bob', $this->doc([$this->contract()]));

		$this->assertSame(0, $summary['duplicates']);
		$this->assertCount(1, $this->inserted);
	}

	public function testDuplicateWithinTheFileIsImportedOnce(): void {
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(),
			$this->contract(['exportId' => 12]),
		]));

		$this->assertSame(1, $summary['contracts']);
		$this->assertSame(1, $summary['duplicates']);
	}

	public function testPreviewWritesNothingAndMatchesImport(): void {
		$this->contractMapper->method('findAllByOwner')->willReturn([]);
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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);
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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

		$summary = $this->service->import('bob', $this->doc([
			$this->contract(['name' => '  ']),
			$this->contract(['exportId' => 12, 'startDate' => null]),
			$this->contract(['exportId' => 13, 'startDate' => 'kein Datum']),
		]));

		$this->assertSame(3, $summary['invalid']);
		$this->assertCount(0, $this->inserted);
	}

	public function testUnknownResponsibleUserIsCleared(): void {
		$this->contractMapper->method('findAllByOwner')->willReturn([]);
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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);
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
		$this->contractMapper->method('findAllByOwner')->willReturn([]);
		$this->optOutMapper->expects($this->once())->method('setOptOut')->with(1000, 'bob', true);

		$this->service->import('bob', $this->doc(
			[$this->contract()],
			[],
			[['contractExportId' => 11]],
		));
	}

	public function testOverlongValuesAreCutToColumnLength(): void {
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

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
		$mapper->method('findAllByOwner')->willReturn([]);
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
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime());

		$service = new ContractImportService(
			$mapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$this->userManager,
			$this->createMock(IRootFolder::class),
			$timeFactory,
			$this->createMock(IL10N::class),
		);

		$service->import('bob', $this->doc([$this->contract()]));
	}
}
