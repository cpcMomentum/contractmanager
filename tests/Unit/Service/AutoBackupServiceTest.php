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

		$home = $this->createMock(Folder::class);
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

		$home = $this->createMock(Folder::class);
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

		$home = $this->createMock(Folder::class);
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
		$home = $this->createMock(Folder::class);
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
}
