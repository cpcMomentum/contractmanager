<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;
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

	/** Same intervals in calendar days, for the fixed-hour schedule (#399). */
	private const INTERVAL_DAYS = [
		SettingsService::BACKUP_INTERVAL_DAILY => 1,
		SettingsService::BACKUP_INTERVAL_WEEKLY => 7,
		SettingsService::BACKUP_INTERVAL_MONTHLY => 30,
	];

	public function __construct(
		private ContractExportService $exportService,
		private SettingsService $settingsService,
		private IRootFolder $rootFolder,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private IDateTimeZone $dateTimeZone,
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
	 * The "last run" anchor to store after a due backup.
	 *
	 * Anchoring to the actual run time ($now) would let the hourly check cadence
	 * push each backup up to one tick later, and that lateness accumulates day by
	 * day into a visible drift (#375: ~25 h instead of 24 h). Instead we advance
	 * the previous anchor by whole intervals to the latest scheduled slot at or
	 * before $now. The spacing then stays pinned to the schedule (constant, non-
	 * accumulating lateness of up to one check tick).
	 *
	 * The whole-interval step also collapses missed runs (e.g. server was off for
	 * days) into a single catch-up: the anchor jumps straight to the last due slot
	 * so exactly one snapshot is written, not one per missed interval.
	 *
	 * On the first run ($lastRun <= 0) there is no schedule to anchor to, so we
	 * seed it with $now.
	 */
	public static function nextLastRun(string $interval, int $lastRun, int $now): int {
		if ($lastRun <= 0) {
			return $now;
		}
		$intervalSeconds = self::intervalSeconds($interval);
		$periods = intdiv($now - $lastRun, $intervalSeconds);
		if ($periods < 1) {
			// Not actually due; leave the anchor where it is.
			return $lastRun;
		}
		return $lastRun + ($periods * $intervalSeconds);
	}

	/**
	 * Most recent occurrence of the fixed hour at or before $now, in the
	 * user's timezone (#399).
	 */
	public static function latestSlot(int $now, int $hour, \DateTimeZone $tz): int {
		$slot = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime($hour, 0);
		if ($slot->getTimestamp() > $now) {
			$slot = $slot->modify('-1 day')->setTime($hour, 0);
		}
		return $slot->getTimestamp();
	}

	/**
	 * Next run on the fixed-hour schedule (#399): the first occurrence of the
	 * hour after "last run + (interval - 1) days". Daily therefore means the
	 * next occurrence after the last run, weekly the one six days later.
	 * Calendar days in the user's timezone keep the wall-clock hour across
	 * daylight saving changes.
	 */
	public static function nextSlotAfter(string $interval, int $lastRun, int $hour, \DateTimeZone $tz): int {
		$days = self::INTERVAL_DAYS[$interval] ?? self::INTERVAL_DAYS[SettingsService::BACKUP_INTERVAL_WEEKLY];
		$earliest = (new \DateTimeImmutable('@' . $lastRun))->setTimezone($tz);
		if ($days > 1) {
			$earliest = $earliest->modify('+' . ($days - 1) . ' days');
		}
		$slot = $earliest->setTime($hour, 0);
		if ($slot <= $earliest) {
			$slot = $slot->modify('+1 day')->setTime($hour, 0);
		}
		return $slot->getTimestamp();
	}

	/**
	 * Absolute time of the next scheduled backup, or 0 when none has run yet
	 * (nothing to anchor from). With a fixed hour it follows the hour schedule,
	 * otherwise the drift-free interval anchor.
	 */
	public function nextScheduledRun(string $uid, string $interval, int $lastRun): int {
		if ($lastRun <= 0) {
			return 0;
		}
		$hour = $this->settingsService->getUserBackupHour($uid);
		if ($hour === null) {
			return $lastRun + self::intervalSeconds($interval);
		}
		return self::nextSlotAfter($interval, $lastRun, $hour, $this->dateTimeZone->getTimeZone(false, $uid));
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
			$hour = $this->settingsService->getUserBackupHour($uid);
			$tz = $hour === null ? null : $this->dateTimeZone->getTimeZone(false, $uid);
			$due = $tz === null
				? self::isDue($interval, $lastRun, $now)
				: $lastRun <= 0 || $now >= self::nextSlotAfter($interval, $lastRun, $hour, $tz);
			if (!$due) {
				continue;
			}
			try {
				$this->backupForUser($uid);
				// The anchor stays on the schedule, never on the actual run time:
				// whole intervals without a fixed hour (#375), the hour's latest
				// slot with one (#399). Missed runs collapse into one catch-up.
				// The success timestamp records the real write time for display (#397).
				$anchor = $tz === null ? self::nextLastRun($interval, $lastRun, $now) : self::latestSlot($now, $hour, $tz);
				$this->settingsService->setUserBackupLastRun($uid, $anchor);
				$this->settingsService->setUserBackupLastSuccess($uid, $now);
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
	 * Run a backup for a single user on demand (the "back up now" button, #397)
	 * and record the run time. Unlike the scheduled path the anchor is set to the
	 * actual moment: a manual snapshot legitimately restarts the interval, so the
	 * next automatic backup follows one interval later.
	 *
	 * @return int the stored "last run" timestamp
	 * @throws \Throwable if the snapshot could not be written
	 */
	public function backupNow(string $uid): int {
		$this->backupForUser($uid);
		$now = $this->timeFactory->getTime();
		// Manual run restarts the interval, so anchor and success time coincide.
		$this->settingsService->setUserBackupLastRun($uid, $now);
		$this->settingsService->setUserBackupLastSuccess($uid, $now);
		return $now;
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
