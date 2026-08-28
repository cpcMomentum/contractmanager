<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Service\CalendarFeedService;
use OCA\ContractManager\Service\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class CalendarFeedServiceTest extends TestCase {

	private ReminderService $reminderService;
	private ITimeFactory $timeFactory;
	private CalendarFeedService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->reminderService = $this->createMock(ReminderService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-08-28T09:00:00+00:00'));

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			fn (string $text, array $params = []): string => $params === [] ? $text : vsprintf($text, $params),
		);

		$this->service = new CalendarFeedService($this->reminderService, $this->timeFactory, $l10n);
	}

	private function contract(int $id, string $name, string $type, ?string $vendor = null): Contract {
		$c = new Contract();
		$c->setId($id);
		$c->setName($name);
		$c->setContractType($type);
		$c->setVendor($vendor ?? '');
		return $c;
	}

	public function testEmptyFeedIsStillValidCalendar(): void {
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([]);

		$ics = $this->service->buildIcs('alice');

		$this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
		$this->assertStringContainsString("VERSION:2.0\r\n", $ics);
		$this->assertStringContainsString('PRODID:', $ics);
		$this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
		$this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
	}

	public function testAutoRenewalProducesCancellationDeadlineAllDayEvent(): void {
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([
			['contract' => $this->contract(11, 'Strom', Contract::TYPE_AUTO_RENEWAL, 'Stadtwerke'),
				'deadline' => new \DateTime('2026-12-01')],
		]);

		$ics = $this->service->buildIcs('alice');

		$this->assertStringContainsString('BEGIN:VEVENT', $ics);
		$this->assertStringContainsString('UID:contract-11-deadline@contractmanager', $ics);
		$this->assertStringContainsString('DTSTART;VALUE=DATE:20261201', $ics);
		$this->assertStringContainsString('SUMMARY:Kündigungsfrist: Strom', $ics);
		$this->assertStringContainsString('DESCRIPTION:Vertragspartner: Stadtwerke', $ics);
		$this->assertStringContainsString('TRANSP:TRANSPARENT', $ics);
		// DTSTAMP comes from the injected clock, in UTC.
		$this->assertStringContainsString('DTSTAMP:20260828T090000Z', $ics);
	}

	public function testFixedContractIsLabelledAsContractEnd(): void {
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([
			['contract' => $this->contract(12, 'Handy', Contract::TYPE_FIXED),
				'deadline' => new \DateTime('2027-03-15')],
		]);

		$ics = $this->service->buildIcs('alice');

		$this->assertStringContainsString('SUMMARY:Vertragsende: Handy', $ics);
		$this->assertStringContainsString('DTSTART;VALUE=DATE:20270315', $ics);
	}

	public function testSpecialCharactersAreEscaped(): void {
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([
			['contract' => $this->contract(13, 'Strom, Gas; Wasser', Contract::TYPE_AUTO_RENEWAL),
				'deadline' => new \DateTime('2026-12-01')],
		]);

		$ics = $this->service->buildIcs('alice');

		// Comma and semicolon in the name must be backslash-escaped per RFC 5545.
		$this->assertStringContainsString('SUMMARY:Kündigungsfrist: Strom\\, Gas\\; Wasser', $ics);
	}

	public function testUsesCrlfLineEndings(): void {
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([]);

		$ics = $this->service->buildIcs('alice');

		// Every line break must be CRLF; there must be no bare LF.
		$this->assertSame(0, preg_match('/(?<!\r)\n/', $ics), 'ICS must use CRLF line endings only');
	}

	public function testFoldingNeverSplitsAMultiByteUtf8Character(): void {
		// A long name padded so a multi-byte umlaut lands right on the 75-octet
		// fold boundary — splitting it there would corrupt the byte sequence.
		$name = str_repeat('a', 74 - strlen('SUMMARY:Kündigungsfrist: ')) . 'ü' . 'restrest';
		$this->reminderService->method('getUpcomingDeadlinesForUser')->willReturn([
			['contract' => $this->contract(14, $name, Contract::TYPE_AUTO_RENEWAL),
				'deadline' => new \DateTime('2026-12-01')],
		]);

		$ics = $this->service->buildIcs('alice');

		foreach (explode("\r\n", $ics) as $line) {
			$this->assertTrue(mb_check_encoding(ltrim($line, ' '), 'UTF-8'), 'Folded line is not valid UTF-8: ' . $line);
		}
	}
}
