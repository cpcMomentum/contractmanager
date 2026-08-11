<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Listener;

use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Listener\UserDeletedListener;
use OCA\ContractManager\Notification\NotificationService;
use OCA\ContractManager\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * #299: when an account is deleted, its contracts must not lose their owner
 * silently. Nothing that documents a business event is removed here.
 */
class UserDeletedListenerTest extends TestCase {

	private ContractMapper $contractMapper;
	private ReminderOptOutMapper $optOutMapper;
	private SettingsService $settingsService;
	private NotificationService $notificationService;
	private IUserManager $userManager;
	private UserDeletedListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->notificationService = $this->createMock(NotificationService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->listener = new UserDeletedListener(
			$this->contractMapper,
			$this->optOutMapper,
			$this->settingsService,
			$this->notificationService,
			$this->userManager,
			$logger
		);
	}

	private function deletionOf(string $uid): UserDeletedEvent {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return new UserDeletedEvent($user);
	}

	public function testOptOutsOfDeletedUserAreRemoved(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('');

		$this->optOutMapper->expects($this->once())
			->method('deleteByUser')
			->with('alice')
			->willReturn(2);

		$this->listener->handle($this->deletionOf('alice'));
	}

	public function testNoSuccessorConfiguredLeavesContractsAlone(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('');

		$this->contractMapper->expects($this->never())->method('reassignOnOwnerDeletion');

		$this->listener->handle($this->deletionOf('alice'));
	}

	public function testContractsAreHandedToConfiguredSuccessor(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('bob');
		$this->userManager->method('userExists')->with('bob')->willReturn(true);

		$this->contractMapper->expects($this->once())
			->method('reassignOnOwnerDeletion')
			->with('alice', 'bob')
			->willReturn(3);

		$this->listener->handle($this->deletionOf('alice'));
	}

	public function testSuccessorWithoutAccountIsIgnored(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('ghost');
		$this->userManager->method('userExists')->with('ghost')->willReturn(false);

		$this->contractMapper->expects($this->never())->method('reassignOnOwnerDeletion');

		$this->listener->handle($this->deletionOf('alice'));
	}

	/**
	 * Guards against configuring the very user that is being deleted, which
	 * would hand the contracts to an account that no longer exists.
	 */
	public function testSuccessorEqualToDeletedUserIsIgnored(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('alice');

		$this->contractMapper->expects($this->never())->method('reassignOnOwnerDeletion');

		$this->listener->handle($this->deletionOf('alice'));
	}

	/**
	 * Deleting an account must never fail because of this app.
	 */
	public function testFailingMapperDoesNotBreakUserDeletion(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('bob');
		$this->userManager->method('userExists')->willReturn(true);
		$this->contractMapper->method('reassignOnOwnerDeletion')
			->willThrowException(new \RuntimeException('database gone'));

		$this->listener->handle($this->deletionOf('alice'));

		$this->addToAssertionCount(1);
	}

	public function testUnrelatedEventIsIgnored(): void {
		$this->optOutMapper->expects($this->never())->method('deleteByUser');

		$this->listener->handle(new Event());
	}

	/**
	 * #299 PR 2: the admins are told what happened, with the count of contracts
	 * that could not be handed over (private ones, or all of them when no
	 * successor is set). Counted after the handover.
	 */
	public function testAdminsAreNotifiedWithBothCounts(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('bob');
		$this->userManager->method('userExists')->willReturn(true);
		$this->contractMapper->method('reassignOnOwnerDeletion')->willReturn(3);
		$this->contractMapper->method('countByEffectiveOwner')->with('alice')->willReturn(2);

		$this->notificationService->expects($this->once())
			->method('notifyAdminsAboutDeletedUser')
			->with('alice', 3, 2);

		$this->listener->handle($this->deletionOf('alice'));
	}

	public function testNotificationReportsLeftoversWhenNoSuccessorIsConfigured(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('');
		$this->contractMapper->method('countByEffectiveOwner')->willReturn(4);

		$this->notificationService->expects($this->once())
			->method('notifyAdminsAboutDeletedUser')
			->with('alice', 0, 4);

		$this->listener->handle($this->deletionOf('alice'));
	}

	/**
	 * A failing notification must not take the account deletion down with it.
	 */
	public function testFailingNotificationDoesNotBreakUserDeletion(): void {
		$this->settingsService->method('getDeletionSuccessor')->willReturn('');
		$this->contractMapper->method('countByEffectiveOwner')->willReturn(1);
		$this->notificationService->method('notifyAdminsAboutDeletedUser')
			->willThrowException(new \RuntimeException('notification backend gone'));

		$this->listener->handle($this->deletionOf('alice'));

		$this->addToAssertionCount(1);
	}
}
