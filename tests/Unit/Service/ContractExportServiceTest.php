<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Service\ContractExportService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class ContractExportServiceTest extends TestCase {

	private ContractMapper $contractMapper;
	private CategoryMapper $categoryMapper;
	private ReminderOptOutMapper $optOutMapper;
	private IAppManager $appManager;
	private ITimeFactory $timeFactory;
	private ContractExportService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-07-24T12:00:00+00:00'));
		$this->appManager->method('getAppVersion')->willReturn('1.4.0');

		$this->service = new ContractExportService(
			$this->contractMapper,
			$this->categoryMapper,
			$this->optOutMapper,
			$this->appManager,
			$this->timeFactory,
		);
	}

	private function makeContract(int $id, string $name, ?int $categoryId): Contract {
		$c = new Contract();
		$c->setId($id);
		$c->setName($name);
		$c->setVendor('ACME');
		$c->setStatus(Contract::STATUS_ACTIVE);
		$c->setCategoryId($categoryId);
		$c->setStartDate(new \DateTime('2026-01-01'));
		$c->setCancellationPeriod('3 Monate');
		$c->setContractType(Contract::TYPE_FIXED);
		$c->setCreatedAt(new \DateTime('2026-01-01T10:00:00+00:00'));
		$c->setUpdatedAt(new \DateTime('2026-01-02T10:00:00+00:00'));
		return $c;
	}

	public function testBuildExportDocumentShape(): void {
		$c1 = $this->makeContract(11, 'Strom', 5);
		$c2 = $this->makeContract(12, 'Handy', null);
		$this->contractMapper->method('findAllByOwner')->with('alice')->willReturn([$c1, $c2]);

		$category = new Category();
		$category->setId(5);
		$category->setName('Versicherung');
		$category->setSortOrder(2);
		$this->categoryMapper->method('find')->with(5)->willReturn($category);

		$this->optOutMapper->method('isOptedOut')->willReturnCallback(
			fn (int $cid, string $uid) => $cid === 11 && $uid === 'alice',
		);

		$doc = $this->service->buildExportDocument('alice');

		$this->assertSame(ContractExportService::SCHEMA_VERSION, $doc['schemaVersion']);
		$this->assertSame('1.4.0', $doc['appVersion']);
		$this->assertCount(1, $doc['categories']);
		$this->assertSame('Versicherung', $doc['categories'][0]['name']);
		$this->assertCount(2, $doc['contracts']);
		$this->assertSame(5, $doc['contracts'][0]['categoryExportId']);
		$this->assertNull($doc['contracts'][1]['categoryExportId']);
		$this->assertCount(1, $doc['optouts']);
		$this->assertSame(11, $doc['optouts'][0]['contractExportId']);
	}

	public function testExportJsonIsValidJson(): void {
		$this->contractMapper->method('findAllByOwner')->willReturn([]);

		$json = $this->service->exportJson('alice');
		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded);
		$this->assertSame([], $decoded['contracts']);
		$this->assertSame([], $decoded['categories']);
	}
}
