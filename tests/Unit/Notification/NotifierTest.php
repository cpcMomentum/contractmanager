<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Notification;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Notification\Notifier;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {

	private Notifier $notifier;
	private ContractMapper $contractMapper;

	protected function setUp(): void {
		parent::setUp();

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturn('/img/app.svg');
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/contractmanager/');

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => vsprintf($text, $params)
		);

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l);

		$this->contractMapper = $this->createMock(ContractMapper::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('getDisplayName')->willReturnMap([['bob', 'Bob Beispiel']]);

		$this->notifier = new Notifier($urlGenerator, $l10nFactory, $this->contractMapper, $userManager);
	}

	private function notification(string $app, string $subject, array $params = []): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($params);
		$notification->method('getObjectId')->willReturn('42');
		$notification->method('setParsedSubject')->willReturnSelf();
		$notification->method('setParsedMessage')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		return $notification;
	}

	public function testForeignAppIsRejected(): void {
		$this->expectException(UnknownNotificationException::class);

		$this->notifier->prepare($this->notification('files', Notifier::SUBJECT_USER_DELETED), 'de');
	}

	public function testUnknownSubjectIsRejected(): void {
		$this->expectException(UnknownNotificationException::class);

		$this->notifier->prepare($this->notification('contractmanager', 'something_else'), 'de');
	}

	public function testUserDeletedSubjectIsParsed(): void {
		$notification = $this->notification('contractmanager', Notifier::SUBJECT_USER_DELETED, [
			'deletedUser' => 'alice',
			'reassigned' => 3,
			'needsAttention' => 2,
		]);

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($this->stringContains('alice'))
			->willReturnSelf();

		$this->assertSame($notification, $this->notifier->prepare($notification, 'de'));
	}

	public function testGetIdAndName(): void {
		$this->assertSame('contractmanager', $this->notifier->getID());
		$this->assertNotSame('', $this->notifier->getName());
	}

	public function testIconIsSetAsAbsoluteUrl(): void {
		// #357 root cause: NC 34's setIcon() rejects a non-absolute URL, but
		// imagePath() returns a relative path. The notifier must wrap it in
		// getAbsoluteURL(); otherwise setIcon() throws and the notification
		// loses both its icon and its link on NC 34. This locks the icon in as
		// an absolute (http) URL so a regression to raw imagePath() fails here.
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->willReturn('/custom_apps/contractmanager/img/app.svg');
		$urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(static fn (string $path): string => 'http://localhost' . $path);
		$urlGenerator->method('linkToRouteAbsolute')
			->willReturn('http://localhost/apps/contractmanager/');

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => vsprintf($text, $params)
		);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l);

		$notifier = new Notifier($urlGenerator, $l10nFactory, $this->createMock(ContractMapper::class), $this->createMock(IUserManager::class));

		$notification = $this->notification('contractmanager', Notifier::SUBJECT_USER_DELETED, [
			'deletedUser' => 'alice',
			'reassigned' => 3,
			'needsAttention' => 2,
		]);
		$notification->expects($this->once())
			->method('setIcon')
			->with($this->stringStartsWith('http://'))
			->willReturnSelf();

		$notifier->prepare($notification, 'de');
	}

	public function testSetterRejectionIsDiscardedAsUnknown(): void {
		// #357 safety net: if an NC INotification setter rejects a value while
		// building a known notification, the resulting \InvalidArgumentException
		// must not escape prepare() (deprecated on NC 34+). It is converted to
		// UnknownNotificationException so the undisplayable notification is
		// discarded cleanly instead of spamming the log every cycle.
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('contractmanager');
		$notification->method('getSubject')->willReturn(Notifier::SUBJECT_USER_DELETED);
		$notification->method('getSubjectParameters')->willReturn([
			'deletedUser' => 'alice',
			'reassigned' => 3,
			'needsAttention' => 2,
		]);
		$notification->method('setParsedSubject')
			->willThrowException(new \InvalidArgumentException('invalid subject'));

		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($notification, 'de');
	}

	// ----- #438: trash notification, resolved lazily -----

	private const DELETED_AT = '2026-10-02T09:30:00+00:00';

	private function trashedContract(?string $deletedBy = 'bob', ?string $deletedAt = self::DELETED_AT): Contract {
		$contract = new Contract();
		$contract->setName('Mobilfunk');
		$contract->setCreatedBy('alice');
		$contract->setDeletedBy($deletedBy);
		$contract->setDeletedAt($deletedAt === null ? null : new DateTime($deletedAt));
		return $contract;
	}

	private function trashNotification(): INotification {
		return $this->notification('contractmanager', Notifier::SUBJECT_CONTRACT_TRASHED, [
			'deletedBy' => 'bob',
			'deletedAt' => self::DELETED_AT,
		]);
	}

	public function testTrashedSubjectNamesDeleterAndContract(): void {
		$this->contractMapper->method('find')->with(42)->willReturn($this->trashedContract());
		$notification = $this->trashNotification();

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($this->logicalAnd(
				$this->stringContains('Bob Beispiel'),
				$this->stringContains('Mobilfunk'),
			))
			->willReturnSelf();

		$this->assertSame($notification, $this->notifier->prepare($notification, 'de'));
	}

	public function testTrashedNotificationVanishesOnceRestored(): void {
		$this->contractMapper->method('find')->willReturn($this->trashedContract('bob', null));

		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->trashNotification(), 'de');
	}

	public function testTrashedNotificationVanishesWhenDeletedForGood(): void {
		$this->contractMapper->method('find')->willThrowException(new DoesNotExistException('gone'));

		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->trashNotification(), 'de');
	}

	public function testTrashedNotificationVanishesWhenTrashedAgainLater(): void {
		// Restored and trashed again: this notification belongs to the old deletion.
		$this->contractMapper->method('find')->willReturn($this->trashedContract('bob', '2026-10-05T08:00:00+00:00'));

		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->trashNotification(), 'de');
	}

	public function testTrashedNotificationVanishesWhenSomeoneElseTrashedIt(): void {
		$this->contractMapper->method('find')->willReturn($this->trashedContract('carol'));

		$this->expectException(AlreadyProcessedException::class);
		$this->notifier->prepare($this->trashNotification(), 'de');
	}
}
