<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Service\AutoBackupService;
use OCA\ContractManager\Service\ContractExportService;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AutoBackupServiceTest extends TestCase {

	private ContractExportService $exportService;
	private SettingsService $settingsService;
	private IRootFolder $rootFolder;
	private ITimeFactory $timeFactory;
	private AutoBackupService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->exportService = $this->createMock(ContractExportService::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-08-25T09:00:00+00:00'));

		$this->service = new AutoBackupService(
			$this->exportService,
			$this->settingsService,
			$this->rootFolder,
			$this->timeFactory,
			$this->createMock(LoggerInterface::class),
			$this->berlin(),
		);
	}

	public function testIntervalSeconds(): void {
		$this->assertSame(86400, AutoBackupService::intervalSeconds('daily'));
		$this->assertSame(604800, AutoBackupService::intervalSeconds('weekly'));
		$this->assertSame(2592000, AutoBackupService::intervalSeconds('monthly'));
		// Unknown value must never mean "every run" — falls back to weekly.
		$this->assertSame(604800, AutoBackupService::intervalSeconds('bogus'));
	}

	public function testIsDue(): void {
		$now = 1_000_000;
		// Never run before.
		$this->assertTrue(AutoBackupService::isDue('daily', 0, $now));
		// Exactly one interval elapsed.
		$this->assertTrue(AutoBackupService::isDue('daily', $now - 86400, $now));
		// Not enough time passed.
		$this->assertFalse(AutoBackupService::isDue('daily', $now - 86399, $now));
		$this->assertFalse(AutoBackupService::isDue('weekly', $now - 100, $now));
	}

	public function testBackupForUserWritesTimestampedFileIntoExistingFolder(): void {
		$this->settingsService->method('getUserBackupFolder')->with('alice')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->with('alice')->willReturn('{"contracts":[]}');

		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		$target->expects($this->once())
			->method('newFile')
			->with(
				$this->matchesRegularExpression('/^contracts-\d{4}-\d{2}-\d{2}-\d{6}\.json$/'),
				'{"contracts":[]}',
			)
			->willReturn($this->createMock(File::class));

		$home = $this->mockUserHome();
		$home->method('nodeExists')->with('VertragsWerk-Backup')->willReturn(true);
		$home->method('get')->with('VertragsWerk-Backup')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($home);

		$this->service->backupForUser('alice');
	}

	public function testBackupForUserCreatesFolderWhenMissing(): void {
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		$target->method('newFile')->willReturn($this->createMock(File::class));

		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(false);
		$home->expects($this->once())->method('newFolder')->with('VertragsWerk-Backup')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->willReturn($home);

		$this->service->backupForUser('bob');
	}

	public function testBackupForUserPrunesOldestBeyondRetention(): void {
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		// 31 existing snapshots (already over the limit of 30). Oldest one must be
		// deleted, the newest must survive.
		$existing = [];
		for ($i = 1; $i <= 31; $i++) {
			$name = sprintf('contracts-2026-08-%02d-090000.json', $i);
			$node = $this->createMock(File::class);
			$node->method('getName')->willReturn($name);
			$expectDeleted = ($i === 1); // only the single oldest is beyond keep=30
			$node->expects($expectDeleted ? $this->once() : $this->never())->method('delete');
			$existing[] = $node;
		}
		// An unrelated file must never be pruned.
		$other = $this->createMock(File::class);
		$other->method('getName')->willReturn('notes.txt');
		$other->expects($this->never())->method('delete');
		$existing[] = $other;

		$target = $this->createMock(Folder::class);
		$target->method('newFile')->willReturn($this->createMock(File::class));
		$target->method('getDirectoryListing')->willReturn($existing);

		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(true);
		$home->method('get')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->willReturn($home);

		$this->service->backupForUser('alice');
	}

	public function testRunDueBackupsOnlyBacksUpDueUsers(): void {
		$now = 1_724_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->settingsService->method('getUsersWithBackupEnabled')->willReturn(['due', 'notdue']);
		$this->settingsService->method('getUserBackupInterval')->willReturn('daily');
		$this->settingsService->method('getUserBackupLastRun')->willReturnMap([
			['due', 0],                 // never run -> due
			['notdue', $now - 100],     // just ran -> not due
		]);
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		// Only the due user's folder is touched.
		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		$target->method('newFile')->willReturn($this->createMock(File::class));
		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(true);
		$home->method('get')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->with('due')->willReturn($home);

		// lastRun is stamped only for the user that was actually backed up.
		$this->settingsService->expects($this->once())
			->method('setUserBackupLastRun')
			->with('due', $now);

		$count = $this->service->runDueBackups();

		$this->assertSame(1, $count);
	}

	public function testBackupNowWritesSnapshotAndStampsCurrentTime(): void {
		$now = 1_724_000_123;
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		$target->expects($this->once())->method('newFile')->willReturn($this->createMock(File::class));
		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(true);
		$home->method('get')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($home);

		// A manual run anchors to now (restarts the interval) and records the same
		// time as the actual last-success timestamp; returns it.
		$this->settingsService->expects($this->once())
			->method('setUserBackupLastRun')
			->with('alice', $now);
		$this->settingsService->expects($this->once())
			->method('setUserBackupLastSuccess')
			->with('alice', $now);

		$this->assertSame($now, $this->service->backupNow('alice'));
	}

	public function testNextLastRunSeedsFirstRunWithNow(): void {
		$now = 1_700_000_000;
		// No prior anchor to align to -> seed with now.
		$this->assertSame($now, AutoBackupService::nextLastRun('daily', 0, $now));
		$this->assertSame($now, AutoBackupService::nextLastRun('weekly', -5, $now));
	}

	public function testNextLastRunCatchesUpMissedIntervalsInOneJump(): void {
		$day = 86400;
		$anchor = 1_700_000_000;
		// Server was off; three-and-a-bit intervals passed since the last anchor.
		$now = $anchor + (3 * $day) + 5000;
		// The anchor jumps to the last scheduled slot at or before now (3 whole
		// intervals), never to now itself and never one interval at a time.
		$this->assertSame($anchor + (3 * $day), AutoBackupService::nextLastRun('daily', $anchor, $now));
	}

	/**
	 * Regression for #375: the hourly check cadence observes "now" a little past
	 * each boundary tick. Anchoring to now (the old behaviour) slipped the daily
	 * slot one hour later every cycle (~25 h instead of 24 h). Anchoring to the
	 * schedule must keep consecutive slots exactly one interval apart.
	 */
	public function testNextLastRunKeepsScheduleAcrossHourlyChecks(): void {
		$interval = 'daily';
		$day = 86400;
		$hour = 3600;
		$jitter = 12; // cron fires a few seconds after the nominal hourly tick

		$firstNow = 1_700_000_000 + $jitter;
		$anchor = AutoBackupService::nextLastRun($interval, 0, $firstNow);
		$this->assertSame($firstNow, $anchor);

		$lastRun = $anchor;
		$fireTimes = [$anchor];
		$startTick = $anchor - ($anchor % $hour) + $hour;
		for ($tick = $startTick; $tick <= $anchor + (5 * $day) + $hour; $tick += $hour) {
			$now = $tick + $jitter;
			if (!AutoBackupService::isDue($interval, $lastRun, $now)) {
				continue;
			}
			$lastRun = AutoBackupService::nextLastRun($interval, $lastRun, $now);
			$fireTimes[] = $lastRun;
		}

		$this->assertGreaterThanOrEqual(6, count($fireTimes), 'expected ~5 daily cycles to fire');
		for ($i = 1, $n = count($fireTimes); $i < $n; $i++) {
			$this->assertSame(
				$day,
				$fireTimes[$i] - $fireTimes[$i - 1],
				"Cycle $i drifted from the 24h schedule",
			);
		}
	}

	public function testRunDueBackupsWritesSingleSnapshotWhenCatchingUp(): void {
		$day = 86400;
		$now = 1_724_000_000;
		$lastRun = $now - ((3 * $day) + 5000); // three missed daily intervals
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->settingsService->method('getUsersWithBackupEnabled')->willReturn(['alice']);
		$this->settingsService->method('getUserBackupInterval')->willReturn('daily');
		$this->settingsService->method('getUserBackupLastRun')->willReturn($lastRun);
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		// Exactly one snapshot despite three missed intervals.
		$target->expects($this->once())->method('newFile')->willReturn($this->createMock(File::class));
		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(true);
		$home->method('get')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->willReturn($home);

		// Anchor advances by three whole intervals, not to now...
		$this->settingsService->expects($this->once())
			->method('setUserBackupLastRun')
			->with('alice', $lastRun + (3 * $day));
		// ...but the user-facing "last success" is the real write time (#397): the
		// two must diverge after a catch-up so the display is not stale.
		$this->settingsService->expects($this->once())
			->method('setUserBackupLastSuccess')
			->with('alice', $now);

		$this->assertSame(1, $this->service->runDueBackups());
	}

	/**
	 * Ein Mock des Home-Ordners, wie ihn `IRootFolder::getUserFolder()` liefert.
	 *
	 * **Plattformabhaengig, wegen des Rueckgabetyps.** NC dev-master (Canary,
	 * #406) engt `getUserFolder()` von `Folder` auf `IUserFolder` ein; NC 32-34
	 * kennen das Interface noch nicht. Ein fest auf `Folder` gemocktes `$home`
	 * scheitert auf dev-master an PHPUnits Rueckgabetyp-Pruefung, ein fest auf
	 * `IUserFolder` gemocktes an der fehlenden Klasse auf NC 32-34. Der Mock
	 * traegt deshalb je nach Plattform die passende Klasse -- `IUserFolder`
	 * erweitert `Folder`, die in den Tests konfigurierten Methoden bleiben
	 * dieselben. Fleet-Muster aus projektwerk#275.
	 *
	 * `IUserFolder::class` ist eine Konstante zur Uebersetzungszeit und laedt die
	 * Klasse nicht; `interface_exists()` entscheidet zur Laufzeit.
	 */
	private function mockUserHome(): MockObject {
		$class = interface_exists(\OCP\Files\IUserFolder::class)
			? \OCP\Files\IUserFolder::class
			: Folder::class;

		return $this->createMock($class);
	}

	// ========================================
	// Fixed backup hour (#399), user in Europe/Berlin
	// ========================================

	private function berlin(): IDateTimeZone {
		$tz = $this->createMock(IDateTimeZone::class);
		$tz->method('getTimeZone')->willReturn(new \DateTimeZone('Europe/Berlin'));
		return $tz;
	}

	/** Unix timestamp for a Berlin wall-clock time. */
	private static function at(string $berlin): int {
		return (new \DateTimeImmutable($berlin, new \DateTimeZone('Europe/Berlin')))->getTimestamp();
	}

	private static function asBerlin(int $ts): string {
		return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d H:i');
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function nextSlotCases(): array {
		// interval, last run (Berlin), expected next run (Berlin) — hour 03
		return [
			'täglich nach planmäßigem Lauf' => ['daily', '2026-10-01 03:00', '2026-10-02 03:00'],
			'täglich nach manuellem Lauf am Nachmittag' => ['daily', '2026-10-01 14:00', '2026-10-02 03:00'],
			'täglich nach manuellem Lauf vor der Uhrzeit' => ['daily', '2026-10-01 01:00', '2026-10-01 03:00'],
			'wöchentlich' => ['weekly', '2026-09-28 03:00', '2026-10-05 03:00'],
			'monatlich (30 Tage)' => ['monthly', '2026-09-01 03:00', '2026-10-01 03:00'],
			'über die Zeitumstellung hinweg' => ['daily', '2026-10-24 03:00', '2026-10-25 03:00'],
		];
	}

	#[DataProvider('nextSlotCases')]
	public function testNextSlotAfter(string $interval, string $lastRun, string $expected): void {
		$next = AutoBackupService::nextSlotAfter($interval, self::at($lastRun), 3, new \DateTimeZone('Europe/Berlin'));

		$this->assertSame($expected, self::asBerlin($next));
	}

	public function testDaylightSavingKeepsTheWallClockHour(): void {
		// 24 Oct is still summer time, 25 Oct winter time: one day = 25 hours.
		$next = AutoBackupService::nextSlotAfter('daily', self::at('2026-10-24 03:00'), 3, new \DateTimeZone('Europe/Berlin'));

		$this->assertSame(25 * 3600, $next - self::at('2026-10-24 03:00'));
	}

	public function testLatestSlot(): void {
		$tz = new \DateTimeZone('Europe/Berlin');

		$this->assertSame('2026-10-01 03:00', self::asBerlin(AutoBackupService::latestSlot(self::at('2026-10-02 02:30'), 3, $tz)));
		$this->assertSame('2026-10-02 03:00', self::asBerlin(AutoBackupService::latestSlot(self::at('2026-10-02 03:30'), 3, $tz)));
	}

	/**
	 * Runs the scheduler once for a single user with hour 03 and returns the
	 * stored anchor, or null when nothing was backed up.
	 */
	private function runWithHour(string $interval, int $lastRun, int $now): ?int {
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->settingsService->method('getUsersWithBackupEnabled')->willReturn(['alice']);
		$this->settingsService->method('getUserBackupInterval')->willReturn($interval);
		$this->settingsService->method('getUserBackupLastRun')->willReturn($lastRun);
		$this->settingsService->method('getUserBackupHour')->willReturn(3);
		$this->settingsService->method('getUserBackupFolder')->willReturn('/VertragsWerk-Backup');
		$this->exportService->method('exportJson')->willReturn('{}');

		$target = $this->createMock(Folder::class);
		$target->method('getDirectoryListing')->willReturn([]);
		$target->method('newFile')->willReturn($this->createMock(File::class));
		$home = $this->mockUserHome();
		$home->method('nodeExists')->willReturn(true);
		$home->method('get')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->willReturn($home);

		$anchor = null;
		$this->settingsService->method('setUserBackupLastRun')->willReturnCallback(
			static function (string $uid, int $ts) use (&$anchor): void {
				$anchor = $ts;
			}
		);

		$this->service->runDueBackups();
		return $anchor;
	}

	public function testFixedHourIsNotDueBeforeTheHour(): void {
		$this->assertNull($this->runWithHour('daily', self::at('2026-10-01 03:00'), self::at('2026-10-02 02:30')));
	}

	public function testFixedHourRunsAtTheHourAndAnchorsOnTheSlot(): void {
		// The hourly job fires at 03:40; the anchor is 03:00, not the run time,
		// so the hour does not creep later day by day.
		$anchor = $this->runWithHour('daily', self::at('2026-10-01 03:00'), self::at('2026-10-02 03:40'));

		$this->assertSame('2026-10-02 03:00', self::asBerlin((int)$anchor));
	}

	public function testFixedHourCatchesUpMissedDaysWithOneSnapshot(): void {
		$anchor = $this->runWithHour('daily', self::at('2026-09-25 03:00'), self::at('2026-10-02 10:00'));

		$this->assertSame('2026-10-02 03:00', self::asBerlin((int)$anchor));
	}

	public function testFixedHourRunsNextMorningAfterAnAfternoonBackup(): void {
		// "Jetzt sichern" at 14:00: the plain interval would wait until 14:00
		// the next day, the fixed hour runs at 03:00 already.
		$anchor = $this->runWithHour('daily', self::at('2026-10-01 14:00'), self::at('2026-10-02 03:10'));

		$this->assertSame('2026-10-02 03:00', self::asBerlin((int)$anchor));
	}

	public function testWeeklyFixedHourWaitsTheFullWeek(): void {
		$this->assertNull($this->runWithHour('weekly', self::at('2026-09-28 03:00'), self::at('2026-10-04 23:00')));
	}

	public function testNextScheduledRunWithoutHourKeepsThePlainInterval(): void {
		$this->settingsService->method('getUserBackupHour')->willReturn(null);

		$this->assertSame(1_700_000_000 + 86400, $this->service->nextScheduledRun('alice', 'daily', 1_700_000_000));
	}

	public function testNextScheduledRunWithHourFollowsTheSchedule(): void {
		$this->settingsService->method('getUserBackupHour')->willReturn(3);

		$next = $this->service->nextScheduledRun('alice', 'daily', self::at('2026-10-01 14:00'));

		$this->assertSame('2026-10-02 03:00', self::asBerlin($next));
	}

	public function testNextScheduledRunIsZeroBeforeTheFirstBackup(): void {
		$this->settingsService->method('getUserBackupHour')->willReturn(3);

		$this->assertSame(0, $this->service->nextScheduledRun('alice', 'daily', 0));
	}
}

