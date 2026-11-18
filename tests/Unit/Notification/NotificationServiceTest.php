<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Notification;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Notification\NotificationService;
use OCA\ContractManager\Notification\Notifier;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * #438: the owner learns when someone else moves their contract to the trash.
 */
class NotificationServiceTest extends TestCase {

	private INotificationManager $notificationManager;
	private IUserManager $userManager;
	private NotificationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->service = new NotificationService(
			$this->notificationManager,
			$this->createMock(IGroupManager::class),
			$this->userManager,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function trashed(string $createdBy = 'alice', ?string $responsible = null): Contract {
		$contract = new Contract();
		$contract->setId(42);
		$contract->setCreatedBy($createdBy);
		$contract->setResponsibleUser($responsible);
		$contract->setDeletedAt(new DateTime('2026-10-02T09:30:00+00:00'));
		return $contract;
	}

	public function testOwnerIsNotifiedWithIdsOnly(): void {
		$this->userManager->method('userExists')->with('alice')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$notification->expects($this->once())->method('setUser')->with('alice')->willReturnSelf();
		$notification->expects($this->once())->method('setObject')->with('contract', '42')->willReturnSelf();
		$notification->expects($this->once())
			->method('setSubject')
			->with(Notifier::SUBJECT_CONTRACT_TRASHED, [
				'deletedBy' => 'bob',
				'deletedAt' => '2026-10-02T09:30:00+00:00',
			])
			->willReturnSelf();

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('notify')->with($notification);

		$this->service->notifyOwnerAboutTrashedContract($this->trashed('alice'), 'bob');
	}

	public function testResponsibleUserIsTheRecipient(): void {
		$this->userManager->method('userExists')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$notification->expects($this->once())->method('setUser')->with('carol')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->service->notifyOwnerAboutTrashedContract($this->trashed('alice', 'carol'), 'alice');
	}

	public function testNoNotificationWhenOwnerDeletedItThemselves(): void {
		// The account exists, so only the self-deletion rule can hold this back.
		$this->userManager->method('userExists')->willReturn(true);
		$this->notificationManager->expects($this->never())->method('notify');

		$this->service->notifyOwnerAboutTrashedContract($this->trashed('alice'), 'alice');
	}

	public function testNoNotificationWhenOwnerAccountIsGone(): void {
		$this->userManager->method('userExists')->willReturn(false);
		$this->notificationManager->expects($this->never())->method('notify');

		$this->service->notifyOwnerAboutTrashedContract($this->trashed('ghost'), 'bob');
	}

	public function testFailureToNotifyNeverBreaksTheDeletion(): void {
		$this->userManager->method('userExists')->willReturn(true);
		$this->notificationManager->method('createNotification')->willThrowException(new \RuntimeException('down'));

		$this->service->notifyOwnerAboutTrashedContract($this->trashed('alice'), 'bob');

		$this->addToAssertionCount(1);
	}

	private function serviceWithAdmins(LoggerInterface $logger, string ...$admins): NotificationService {
		$users = array_map(function (string $uid): IUser {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		}, $admins);
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn($users);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('admin')->willReturn($group);
		return new NotificationService($this->notificationManager, $groupManager, $this->userManager, $logger);
	}

	public function testTrialReminderGoesToEachAdminOnly(): void {
		$recipients = [];
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$notification->method('setUser')->willReturnCallback(function (string $uid) use (&$recipients, $notification) {
			$recipients[] = $uid;
			return $notification;
		});
		$notification->expects($this->exactly(2))->method('setObject')->with('werkplus_trial', '7d')->willReturnSelf();
		$notification->expects($this->exactly(2))->method('setSubject')
			->with(Notifier::SUBJECT_TRIAL, ['stage' => '7d', 'trialEnd' => 1796134323])->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->exactly(2))->method('notify');

		$this->serviceWithAdmins($this->createMock(LoggerInterface::class), 'admin', 'zweitadmin')
			->notifyAdminsAboutTrial('7d', 1796134323);

		$this->assertSame(['admin', 'zweitadmin'], $recipients);
	}

	public function testTrialReminderFailureIsLoggedNotThrown(): void {
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->method('notify')->willThrowException(new \RuntimeException('kaputt'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->serviceWithAdmins($logger, 'admin')->notifyAdminsAboutTrial('end', 1796134323);
	}
}
