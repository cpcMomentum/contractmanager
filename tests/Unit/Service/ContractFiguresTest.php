<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Service\ContractFigures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractFiguresTest extends TestCase {

	use ReportTestHelper;

	private const FIXTURE = __DIR__ . '/../../fixtures/contract-figures.json';

	/** @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}> */
	public static function fixtureCases(): array {
		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		$cases = [];
		foreach ($fixture['cases'] as $case) {
			$cases[$case['name']] = [$case['contract'], $case['expected']];
		}
		return $cases;
	}

	#[DataProvider('fixtureCases')]
	public function testMatchesTheFrontend(array $data, array $expected): void {
		$fixture = json_decode((string)file_get_contents(self::FIXTURE), true);
		$today = new DateTime($fixture['today']);
		$figures = $this->figuresFor($this);
		$contract = self::contract($data);

		$cost = ContractFigures::cost($contract);
		$monthly = $cost === null ? null : ContractFigures::monthlyCost($cost, $contract->getCostInterval());
		$actual = [
			'status' => $figures->displayStatus($contract, $today, $fixture['defaultReminderDays']),
			'runsUntil' => $figures->runsUntil($contract, $today)?->format('Y-m-d'),
			'deadline' => $figures->nextCancellationDeadline($contract, $today)?->format('Y-m-d'),
			'monthly' => $monthly === null ? null : round($monthly, 2),
		];

		$this->assertEquals($expected, $actual);
	}

	public function testIsRunningExcludesPlannedEndedAndExpired(): void {
		$today = new DateTime('2026-11-18');
		$figures = $this->figuresFor($this);

		$this->assertTrue($figures->isRunning(self::contract(['startDate' => '2026-01-01', 'endDate' => '2027-01-01']), $today));
		$this->assertTrue($figures->isRunning(self::contract(['startDate' => '2026-01-01', 'endDate' => '2026-11-18']), $today));
		$this->assertFalse($figures->isRunning(self::contract(['startDate' => '2027-01-01', 'endDate' => '2028-01-01']), $today));
		$this->assertFalse($figures->isRunning(self::contract(['startDate' => '2025-01-01', 'endDate' => '2026-11-17']), $today));
		$this->assertFalse($figures->isRunning(self::contract(['status' => 'ended', 'startDate' => '2025-01-01', 'endDate' => '2027-01-01']), $today));
		$this->assertFalse($figures->isRunning(self::contract([
			'status' => 'cancelled', 'cancelledOn' => '2026-01-01', 'cancelledTo' => '2026-10-31',
			'startDate' => '2025-01-01', 'endDate' => '2027-01-01',
		]), $today));
	}

	public function testCostIgnoresEmptyAndNonNumericAmounts(): void {
		$this->assertNull(ContractFigures::cost(self::contract(['cost' => null])));
		$this->assertNull(ContractFigures::cost(self::contract(['cost' => ' '])));
		$this->assertNull(ContractFigures::cost(self::contract(['cost' => 'abc'])));
		$this->assertSame(12.5, ContractFigures::cost(self::contract(['cost' => '12.50'])));
	}
}
