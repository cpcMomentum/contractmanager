<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\UserMigration;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Service\ContractExportService;
use OCA\ContractManager\UserMigration\ContractMigrator;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class ContractMigratorTest extends TestCase {

	private ContractMapper $contractMapper;
	private CategoryMapper $categoryMapper;
	private ReminderOptOutMapper $optOutMapper;
	private IAppManager $appManager;
	private ITimeFactory $timeFactory;
	private ContractMigrator $migrator;

	protected function setUp(): void {
		parent::setUp();
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-07-24T12:00:00+00:00'));
		$this->appManager->method('getAppVersion')->willReturn('1.2.6');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		// The migrator delegates the serialization to ContractExportService; a real
		// one over the mocked mappers keeps the end-to-end export assertions valid.
		$exportService = new ContractExportService(
			$this->contractMapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$this->appManager,
			$this->timeFactory,
		);

		$this->migrator = new ContractMigrator(
			$this->contractMapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$exportService,
			$this->timeFactory,
			$l10n,
		);
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	private function makeContract(int $id, string $name, ?int $categoryId, string $createdBy): Contract {
		$c = new Contract();
		$c->setId($id);
		$c->setName($name);
		$c->setVendor('ACME');
		$c->setStatus(Contract::STATUS_ACTIVE);
		$c->setCategoryId($categoryId);
		$c->setStartDate(new \DateTime('2026-01-01'));
		$c->setEndDate(new \DateTime('2026-12-31'));
		$c->setCancellationPeriod('3 Monate');
		$c->setContractType(Contract::TYPE_FIXED);
		$c->setCreatedBy($createdBy);
		$c->setCreatedAt(new \DateTime('2026-01-01T10:00:00+00:00'));
		$c->setUpdatedAt(new \DateTime('2026-01-02T10:00:00+00:00'));
		return $c;
	}

	public function testExportProducesExpectedDocument(): void {
		$c1 = $this->makeContract(11, 'Strom', 5, 'alice');
		$c2 = $this->makeContract(12, 'Handy', null, 'alice');

		$this->contractMapper->method('findAllByOwner')->with('alice')->willReturn([$c1, $c2]);

		$category = new Category();
		$category->setId(5);
		$category->setName('Versicherung');
		$category->setSortOrder(2);
		$this->categoryMapper->method('find')->with(5)->willReturn($category);

		// c1 opted out, c2 not.
		$this->optOutMapper->method('isOptedOut')->willReturnCallback(
			fn (int $cid, string $uid) => $cid === 11 && $uid === 'alice',
		);

		$captured = null;
		$dest = $this->createMock(IExportDestination::class);
		$dest->expects($this->once())
			->method('addFileContents')
			->with('contractmanager/contracts.json', $this->callback(function (string $content) use (&$captured) {
				$captured = $content;
				return true;
			}));

		$this->migrator->export($this->user('alice'), $dest, new NullOutput());

		$doc = json_decode($captured, true);
		$this->assertSame(1, $doc['schemaVersion']);
		$this->assertSame('1.2.6', $doc['appVersion']);
		$this->assertCount(1, $doc['categories']);
		$this->assertSame(5, $doc['categories'][0]['exportId']);
		$this->assertSame('Versicherung', $doc['categories'][0]['name']);
		$this->assertCount(2, $doc['contracts']);
		$this->assertSame(11, $doc['contracts'][0]['exportId']);
		$this->assertSame(5, $doc['contracts'][0]['categoryExportId']);
		$this->assertNull($doc['contracts'][1]['categoryExportId']);
		$this->assertSame('Strom', $doc['contracts'][0]['name']);
		// Only c1 opted out.
		$this->assertCount(1, $doc['optouts']);
		$this->assertSame(11, $doc['optouts'][0]['contractExportId']);
	}

	public function testImportCreatesContractsWithRemappedCategoryAndOwner(): void {
		$json = json_encode([
			'schemaVersion' => 1,
			'categories' => [
				['exportId' => 5, 'name' => 'Versicherung', 'sortOrder' => 2],
			],
			'contracts' => [
				[
					'exportId' => 11, 'categoryExportId' => 5, 'name' => 'Strom', 'vendor' => 'ACME',
					'status' => 'active', 'startDate' => '2026-01-01T00:00:00+00:00',
					'endDate' => '2026-12-31T00:00:00+00:00', 'cancellationPeriod' => '3 Monate',
					'contractType' => 'fixed', 'reminderEnabled' => 1, 'archived' => 0, 'isPrivate' => 0,
					'createdAt' => '2026-01-01T10:00:00+00:00', 'updatedAt' => '2026-01-02T10:00:00+00:00',
				],
			],
			'optouts' => [
				['contractExportId' => 11],
			],
		]);

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->with('contractmanager')->willReturn(1);
		$source->method('pathExists')->with('contractmanager/contracts.json')->willReturn(true);
		$source->method('getFileContents')->with('contractmanager/contracts.json')->willReturn($json);

		// New category (not present yet) → insert, gets id 99.
		$this->categoryMapper->method('findByName')->with('Versicherung')->willReturn(null);
		$this->categoryMapper->method('getMaxSortOrder')->willReturn(7);
		$this->categoryMapper->method('insert')->willReturnCallback(function (Category $cat) {
			$cat->setId(99);
			return $cat;
		});

		$insertedContracts = [];
		$this->contractMapper->method('insert')->willReturnCallback(function (Contract $c) use (&$insertedContracts) {
			$c->setId(1000 + count($insertedContracts));
			$insertedContracts[] = $c;
			return $c;
		});

		$optOutCalls = [];
		$this->optOutMapper->method('setOptOut')->willReturnCallback(
			function (int $cid, string $uid, bool $v) use (&$optOutCalls): void {
				$optOutCalls[] = [$cid, $uid, $v];
			},
		);

		$this->migrator->import($this->user('bob'), $source, new NullOutput());

		$this->assertCount(1, $insertedContracts);
		$contract = $insertedContracts[0];
		$this->assertSame('Strom', $contract->getName());
		$this->assertSame(99, $contract->getCategoryId(), 'category id must be remapped to the newly inserted one');
		$this->assertSame('bob', $contract->getCreatedBy(), 'owner must become the importing user');
		$this->assertSame('3 Monate', $contract->getCancellationPeriod());
		// Opt-out remapped to the new contract id and owned by bob.
		$this->assertSame([[1000, 'bob', true]], $optOutCalls);
	}

	public function testImportDeduplicatesCategoryByName(): void {
		$json = json_encode([
			'schemaVersion' => 1,
			'categories' => [
				['exportId' => 5, 'name' => 'Versicherung', 'sortOrder' => 2],
			],
			'contracts' => [
				[
					'exportId' => 11, 'categoryExportId' => 5, 'name' => 'Strom', 'vendor' => 'ACME',
					'status' => 'active', 'startDate' => '2026-01-01T00:00:00+00:00',
					'endDate' => '2026-12-31T00:00:00+00:00', 'cancellationPeriod' => '3 Monate',
					'contractType' => 'fixed', 'reminderEnabled' => 1, 'archived' => 0, 'isPrivate' => 0,
				],
			],
			'optouts' => [],
		]);

		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(1);
		$source->method('pathExists')->willReturn(true);
		$source->method('getFileContents')->willReturn($json);

		// Category with that name already exists globally → reuse id 7, no insert.
		$existing = new Category();
		$existing->setId(7);
		$existing->setName('Versicherung');
		$this->categoryMapper->method('findByName')->with('Versicherung')->willReturn($existing);
		$this->categoryMapper->expects($this->never())->method('insert');

		$insertedContracts = [];
		$this->contractMapper->method('insert')->willReturnCallback(function (Contract $c) use (&$insertedContracts) {
			$c->setId(2000);
			$insertedContracts[] = $c;
			return $c;
		});

		$this->migrator->import($this->user('carol'), $source, new NullOutput());

		$this->assertCount(1, $insertedContracts);
		$this->assertSame(7, $insertedContracts[0]->getCategoryId(), 'must reuse the existing category id');
	}

	public function testImportSkipsWhenMigratorVersionMissing(): void {
		$source = $this->createMock(IImportSource::class);
		$source->method('getMigratorVersion')->willReturn(null);
		$this->contractMapper->expects($this->never())->method('insert');

		$this->migrator->import($this->user('dave'), $source, new NullOutput());
	}
}
