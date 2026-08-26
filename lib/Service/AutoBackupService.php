<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

/**
 * Writes periodic JSON snapshots of a user's own contract data into a folder in
 * their Nextcloud files, so the current state rides along in every normal
 * Nextcloud file backup without the user triggering a full account export (#296).
 *
 * The serialization is shared with the user_migration export via
 * ContractExportService, so the on-disk format never drifts.
 */
class AutoBackupService {

	/** How many timestamped snapshots to keep per user before pruning oldest. */
	public const RETENTION_KEEP = 30;

	private const INTERVAL_SECONDS = [
		SettingsService::BACKUP_INTERVAL_DAILY => 86400,
		SettingsService::BACKUP_INTERVAL_WEEKLY => 604800,
		SettingsService::BACKUP_INTERVAL_MONTHLY => 2592000, // 30 days
	];

	public function __construct(
		private ContractExportService $exportService,
		private SettingsService $settingsService,
		private IRootFolder $rootFolder,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * The minimum spacing in seconds for an interval. Unknown intervals fall
	 * back to weekly so a bad stored value can never mean "back up every run".
	 */
	public static function intervalSeconds(string $interval): int {
		return self::INTERVAL_SECONDS[$interval] ?? self::INTERVAL_SECONDS[SettingsService::BACKUP_INTERVAL_WEEKLY];
	}

	/**
	 * Whether a backup is due: never run before, or the interval has elapsed.
	 */
	public static function isDue(string $interval, int $lastRun, int $now): bool {
		if ($lastRun <= 0) {
			return true;
		}
		return ($now - $lastRun) >= self::intervalSeconds($interval);
	}

	/**
	 * Run backups for every user whose interval has elapsed.
	 *
	 * @return int number of users backed up this pass
	 */
	public function runDueBackups(): int {
		$now = $this->timeFactory->getTime();
		$count = 0;
		foreach ($this->settingsService->getUsersWithBackupEnabled() as $uid) {
			$interval = $this->settingsService->getUserBackupInterval($uid);
			$lastRun = $this->settingsService->getUserBackupLastRun($uid);
			if (!self::isDue($interval, $lastRun, $now)) {
				continue;
			}
			try {
				$this->backupForUser($uid);
				$this->settingsService->setUserBackupLastRun($uid, $now);
				$count++;
			} catch (\Throwable $e) {
				// One user's failure (e.g. missing home, quota) must not stop the
				// others. lastRun stays untouched so it is retried next pass.
				$this->logger->error('Auto-backup failed for user', [
					'app' => Application::APP_ID,
					'user' => $uid,
					'exception' => $e->getMessage(),
				]);
			}
		}
		return $count;
	}

	/**
	 * Write one timestamped snapshot into the user's configured folder and prune
	 * old ones beyond the retention limit.
	 */
	public function backupForUser(string $uid): void {
		$userFolder = $this->rootFolder->getUserFolder($uid);
		$relativePath = ltrim($this->settingsService->getUserBackupFolder($uid), '/');

		if ($userFolder->nodeExists($relativePath)) {
			$node = $userFolder->get($relativePath);
			if (!$node instanceof Folder) {
				throw new NotPermittedException('Backup target "' . $relativePath . '" exists but is not a folder');
			}
			$folder = $node;
		} else {
			$folder = $userFolder->newFolder($relativePath);
		}

		$filename = 'contracts-' . $this->timeFactory->getDateTime()->format('Y-m-d-His') . '.json';
		$folder->newFile($filename, $this->exportService->exportJson($uid));

		$this->prune($folder);
	}

	/**
	 * Delete the oldest snapshots beyond RETENTION_KEEP. Filenames carry a sortable
	 * timestamp, so lexical order is chronological order.
	 */
	private function prune(Folder $folder): void {
		$snapshots = [];
		foreach ($folder->getDirectoryListing() as $node) {
			$name = $node->getName();
			if (preg_match('/^contracts-\d{4}-\d{2}-\d{2}-\d{6}\.json$/', $name) === 1) {
				$snapshots[$name] = $node;
			}
		}
		if (count($snapshots) <= self::RETENTION_KEEP) {
			return;
		}
		ksort($snapshots);
		$toDelete = array_slice($snapshots, 0, count($snapshots) - self::RETENTION_KEEP, true);
		foreach ($toDelete as $node) {
			$node->delete();
		}
	}
}
