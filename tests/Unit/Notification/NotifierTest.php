<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Notification;

use OCA\ContractManager\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {

	private Notifier $notifier;

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

		$this->notifier = new Notifier($urlGenerator, $l10nFactory);
	}

	private function notification(string $app, string $subject, array $params = []): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($params);
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

		$notifier = new Notifier($urlGenerator, $l10nFactory);

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
}
