<?php

declare(strict_types=1);

namespace OCA\ContractManager\Listener;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Keeps contract data usable when a Nextcloud account is deleted (#299).
 *
 * Guiding rule: nothing that documents a business event is removed. Contracts
 * are company data, and createdBy records who set a contract up - that stays,
 * account or no account. What the listener does is hand responsibility to a
 * configured successor so the contracts do not silently lose their owner.
 *
 * Deliberately NOT done here:
 * - deleting contracts, including the ones in the trash (see TrashCleanupJob,
 *   which keeps them from being auto-purged once their creator is gone)
 * - touching contractmgr_reminders, a dated delivery log and therefore a record
 * - handing over private contracts, which stay for an admin to decide on
 * - nulling responsibleUser: createdBy carries the stale uid anyway, so it
 *   would only drop information
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {

	public function __construct(
		private ContractMapper $contractMapper,
		private ReminderOptOutMapper $optOutMapper,
		private SettingsService $settingsService,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		$uid = $event->getUser()->getUID();

		// Account deletion must never fail because of this app. Anything that
		// goes wrong here is logged and swallowed.
		try {
			$removedOptOuts = $this->optOutMapper->deleteByUser($uid);

			$successor = $this->resolveSuccessor($uid);
			$reassigned = $successor === null
				? 0
				: $this->contractMapper->reassignOnOwnerDeletion($uid, $successor);

			$this->logger->info('Handled contract data of deleted user', [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'successor' => $successor,
				'reassignedContracts' => $reassigned,
				'removedOptOuts' => $removedOptOuts,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to handle contract data of deleted user: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'exception' => $e,
			]);
		}
	}

	/**
	 * The configured successor, or null when no handover should happen.
	 *
	 * Empty is the default and means contracts are left as they are - the
	 * conservative choice for instances that never configured this.
	 */
	private function resolveSuccessor(string $deletedUid): ?string {
		$successor = $this->settingsService->getDeletionSuccessor();

		if ($successor === '' || $successor === $deletedUid) {
			return null;
		}

		if (!$this->userManager->userExists($successor)) {
			$this->logger->warning('Configured successor does not exist, contracts left unassigned', [
				'app' => Application::APP_ID,
				'successor' => $successor,
			]);
			return null;
		}

		return $successor;
	}
}
