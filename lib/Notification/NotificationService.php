<?php

declare(strict_types=1);

namespace OCA\ContractManager\Notification;

use OCA\ContractManager\AppInfo\Application;
use OCP\IGroupManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationService {

	public function __construct(
		private INotificationManager $notificationManager,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Tell the admins that an account was deleted and what became of its
	 * contracts (#299).
	 *
	 * Without this the handover is silent: contracts that could not be passed
	 * on (private ones, or any of them when no successor is configured) would
	 * sit around with nobody aware of it.
	 *
	 * Nothing is sent when the deleted account had no contracts at all, so
	 * instances that barely use the app do not get a message on every deletion.
	 */
	public function notifyAdminsAboutDeletedUser(
		string $deletedUser,
		int $reassigned,
		int $needsAttention,
	): void {
		if ($reassigned === 0 && $needsAttention === 0) {
			return;
		}

		try {
			foreach ($this->getAdminUserIds() as $adminUserId) {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID);
				$notification->setUser($adminUserId);
				$notification->setDateTime(new \DateTime());
				$notification->setObject('user', $deletedUser);
				$notification->setSubject(Notifier::SUBJECT_USER_DELETED, [
					'deletedUser' => $deletedUser,
					'reassigned' => $reassigned,
					'needsAttention' => $needsAttention,
				]);

				$this->notificationManager->notify($notification);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Failed to notify admins about deleted user: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'deletedUser' => $deletedUser,
				'exception' => $e,
			]);
		}
	}

	/**
	 * @return string[]
	 */
	private function getAdminUserIds(): array {
		$adminGroup = $this->groupManager->get('admin');
		if ($adminGroup === null) {
			return [];
		}

		$userIds = [];
		foreach ($adminGroup->getUsers() as $user) {
			$userIds[] = $user->getUID();
		}

		return $userIds;
	}
}
