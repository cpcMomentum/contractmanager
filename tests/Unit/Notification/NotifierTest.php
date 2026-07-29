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
}
