<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Service\CategoryService;
use OCA\ContractManager\Service\ContactService;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\ReportService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase {

	use ReportTestHelper;

	/** @var list<Contract> */
	private array $visible = [];
	private ContractService $contractService;
	private ContactService $contactService;
	private ReportService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->contractService = $this->createMock(ContractService::class);
		$this->contractService->method('findAllVisible')->willReturnCallback(fn () => $this->visible);

		$categories = $this->createMock(CategoryService::class);
		$categories->method('findAll')->willReturn([self::category(1, 'Software'), self::category(2, 'Miete')]);

		$this->contactService = $this->createMock(ContactService::class);
		$settings = $this->createMock(SettingsService::class);
		$settings->method('getReminderDays1')->willReturn(14);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturnCallback(
			fn (string $time = 'now', ?\DateTimeZone $tz = null): DateTime => (new DateTime('2026-11-18 23:30:00', new \DateTimeZone('UTC')))->setTimezone($tz ?? new \DateTimeZone('UTC')),
		);
		$zone = $this->createMock(IDateTimeZone::class);
		$zone->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));

		$this->service = new ReportService(
			$this->contractService,
			$categories,
			$this->contactService,
			$this->figuresFor($this),
			$settings,
			$timeFactory,
			$zone,
		);
	}

	private static function category(int $id, string $name): Category {
		$category = new Category();
		$category->setId($id);
		$category->setName($name);
		return $category;
	}

	public function testTodayIsTheReadersDay(): void {
		$this->assertSame('2026-11-19', $this->service->today('alice')->format('Y-m-d'));
	}

	public function testContractSheetKeepsListOrderAndDropsWhatTheReaderCannotSee(): void {
		$this->visible = [
			self::contract(['name' => 'A', 'categoryId' => 1, 'startDate' => '2026-01-01', 'endDate' => '2027-06-30'], 1),
			self::contract(['name' => 'B', 'categoryId' => 2, 'startDate' => '2026-01-01', 'endDate' => '2027-06-30'], 2),
			self::contract(['name' => 'C', 'categoryId' => 1, 'startDate' => '2026-01-01', 'endDate' => '2027-06-30'], 3),
			self::contract(['name' => 'D', 'startDate' => '2026-01-01', 'endDate' => '2027-06-30'], 4),
		];
		$this->contractService->expects($this->once())->method('findAllVisible')->with('alice', false);

		$report = $this->service->contractSheet('alice', false, [3, 99, 4, 2, 1, 3]);

		$groups = array_map(fn (array $g): array => [$g['category'], array_column($g['rows'], 'name')], $report['groups']);
		$this->assertSame([['Miete', ['B']], ['Software', ['C', 'A']], [null, ['D']]], $groups);
	}

	public function testContractSheetShowsTheContactOfThePartner(): void {
		$this->visible = [self::contract(['vendor' => 'Telekom', 'vendorContactUid' => 'uid-1', 'startDate' => '2026-01-01', 'endDate' => '2027-01-01'], 1)];
		$this->contactService->method('resolve')->with('uid-1')->willReturn([
			'uid' => 'uid-1', 'name' => 'Telekom', 'organization' => 'Telekom', 'contactsUrl' => null,
			'addresses' => ["Landgrabenweg 151\n53227 Bonn"], 'phones' => ['0800 330 1000'], 'emails' => ['info@example.com'],
		]);

		$row = $this->service->contractSheet('alice', false, [1])['groups'][0]['rows'][0];

		$this->assertSame(["Landgrabenweg 151\n53227 Bonn", '0800 330 1000', 'info@example.com'], $row['contact']);
	}

	public function testCostOverviewNeverAddsNetGrossOrCurrenciesTogether(): void {
		$base = ['startDate' => '2026-01-01', 'endDate' => '2027-12-31', 'costInterval' => 'monthly', 'categoryId' => 1, 'vendor' => 'X'];
		$this->visible = [
			self::contract($base + ['cost' => '10', 'amountType' => 'netto', 'currency' => 'EUR'], 1),
			self::contract($base + ['cost' => '20', 'amountType' => 'brutto', 'currency' => 'EUR'], 2),
			self::contract($base + ['cost' => '30', 'amountType' => 'netto', 'currency' => 'USD'], 3),
			self::contract($base + ['cost' => '40', 'amountType' => 'brutto', 'currency' => 'USD'], 4),
			self::contract($base + ['cost' => '5', 'amountType' => 'netto', 'currency' => 'EUR'], 5),
		];

		$report = $this->service->costOverview('alice', false);

		$totals = array_map(fn (array $s): array => [$s['currency'], $s['amountType'], $s['count'], $s['monthly'], $s['yearly']], $report['totals']);
		$this->assertSame([
			['EUR', 'brutto', 1, 20.0, 240.0],
			['EUR', 'netto', 2, 15.0, 180.0],
			['USD', 'brutto', 1, 40.0, 480.0],
			['USD', 'netto', 1, 30.0, 360.0],
		], $totals);
		$this->assertCount(4, $report['byCategory']);
		$this->assertCount(4, $report['byVendor']);
	}

	public function testCostOverviewListsIrregularPaymentsSeparatelyAndSkipsWhatDoesNotRun(): void {
		$this->visible = [
			self::contract(['name' => 'Einmal', 'cost' => '500', 'costInterval' => 'one_time', 'startDate' => '2026-01-01', 'endDate' => '2027-01-01'], 1),
			self::contract(['name' => 'Geplant', 'cost' => '50', 'costInterval' => 'monthly', 'startDate' => '2027-01-01', 'endDate' => '2028-01-01'], 2),
			self::contract(['name' => 'Vorbei', 'cost' => '50', 'costInterval' => 'monthly', 'startDate' => '2025-01-01', 'endDate' => '2026-01-01'], 3),
			self::contract(['name' => 'Ohne Betrag', 'costInterval' => 'monthly', 'startDate' => '2026-01-01', 'endDate' => '2027-01-01'], 4),
			self::contract(['name' => 'Wöchentlich', 'cost' => '12', 'costInterval' => 'weekly', 'startDate' => '2026-01-01', 'endDate' => '2027-01-01'], 5),
		];

		$report = $this->service->costOverview('alice', false);

		$this->assertSame(['Einmal'], array_column($report['irregular'], 'name'));
		$this->assertCount(1, $report['totals']);
		$this->assertEqualsWithDelta(52.0, $report['totals'][0]['monthly'], 0.0001);
		$this->assertSame(1, $report['totals'][0]['count']);
	}

	public function testDeadlinePreviewTakesDeadlinesAndRenewalsWithinThePeriod(): void {
		$renewing = ['contractType' => 'auto_renewal', 'renewalPeriod' => '12 months', 'cancellationPeriod' => '3 months', 'startDate' => '2025-01-01'];
		$this->visible = [
			self::contract($renewing + ['name' => 'Frist bald', 'endDate' => '2027-03-31'], 1),
			self::contract($renewing + ['name' => 'Frist vorbei, verlängert sich', 'endDate' => '2027-01-15'], 2),
			self::contract($renewing + ['name' => 'Weit weg', 'endDate' => '2028-06-30'], 3),
			self::contract(['name' => 'Befristet', 'startDate' => '2025-01-01', 'endDate' => '2027-01-31'], 4),
			self::contract($renewing + ['name' => 'Gekündigt', 'endDate' => '2027-03-31', 'status' => 'cancelled', 'cancelledOn' => '2026-10-01'], 5),
		];

		$report = $this->service->deadlinePreview('alice', false, 3);

		$this->assertSame('2027-02-19', $report['until']->format('Y-m-d'));
		$rows = array_map(fn (array $r): array => [$r['name'], $r['deadline']?->format('Y-m-d'), $r['renews']?->format('Y-m-d')], $report['rows']);
		$this->assertSame([
			['Frist bald', '2026-12-31', null],
			['Frist vorbei, verlängert sich', null, '2027-01-15'],
		], $rows);
	}

	public function testDeadlinePreviewRejectsOtherPeriods(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->deadlinePreview('alice', false, 4);
	}
}
