<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Notification;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Notification\Notifier;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCA\ContractManager\Service\EntitlementService;
use OCA\ContractManager\Service\TrialNoticeService;
use OCA\ContractManager\Service\WerkPlusLinks;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {

	private Notifier $notifier;
	private ContractMapper $contractMapper;
	private const TRIAL_END = 1796134323;

	private int $now = self::TRIAL_END - 3 * 86400;
	private ?int $trialEndsAt = self::TRIAL_END;
	private string $tier = 'werkplus';

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

		$dates = $this->createMock(IDateTimeFormatter::class);
		$dates->method('formatDate')->willReturnCallback(
			static fn (int $ts, string $format, \DateTimeZone $tz) => '15.12.2026 ' . $tz->getName()
		);
		$timeZones = $this->createMock(IDateTimeZone::class);
		$timeZones->method('getTimeZone')->willReturnCallback(
			static fn (int|false $ts, ?string $user) => new \DateTimeZone($user === 'admin' ? 'Pacific/Auckland' : 'UTC')
		);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn () => $this->now);

		$entitlement = $this->createMock(EntitlementService::class);
		$entitlement->method('trialEndsAt')->willReturnCallback(fn () => $this->trialEndsAt);
		$entitlement->method('tier')->willReturnCallback(fn () => $this->tier);

		$this->notifier = new Notifier($urlGenerator, $l10nFactory, $this->contractMapper, $userManager, $dates, $timeZones, $time, $entitlement);
	}

	private function notification(string $app, string $subject, array $params = []): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($params);
		$notification->method('getObjectId')->willReturn('42');
		$notification->method('getUser')->willReturn('admin');
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

		$notifier = new Notifier($urlGenerator, $l10nFactory, $this->createMock(ContractMapper::class), $this->createMock(IUserManager::class), $this->createMock(IDateTimeFormatter::class), $this->createMock(IDateTimeZone::class), $this->createMock(ITimeFactory::class), $this->createMock(EntitlementService::class));

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

	/**
	 * @return array<string, array{string, int, string}>
	 */
	public static function trialStages(): array {
		return [
			'7 Tage' => [TrialNoticeService::STAGE_7D, 3 * 86400, 'Der WerkPlus-Testzeitraum endet am 15.12.2026 Pacific/Auckland.'],
			'1 Tag' => [TrialNoticeService::STAGE_1D, 3600, 'Der WerkPlus-Testzeitraum endet am 15.12.2026 Pacific/Auckland.'],
			'Ende' => [TrialNoticeService::STAGE_END, -3600, 'Der WerkPlus-Testzeitraum ist beendet.'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('trialStages')]
	public function testTrialNoticeOffersTheBuyLink(string $stage, int $left, string $subject): void {
		$this->now = self::TRIAL_END - $left;
		if ($stage === TrialNoticeService::STAGE_END) {
			$this->trialEndsAt = null;
			$this->tier = 'free';
		}
		$notification = $this->notification('contractmanager', Notifier::SUBJECT_TRIAL, ['stage' => $stage, 'trialEnd' => self::TRIAL_END]);
		$action = $this->createMock(IAction::class);
		$action->method('setLabel')->willReturnSelf();
		$action->method('setParsedLabel')->willReturnSelf();
		$action->method('setPrimary')->willReturnSelf();
		$action->expects($this->once())->method('setLink')->with(WerkPlusLinks::BUY, IAction::TYPE_WEB)->willReturnSelf();
		$notification->method('createAction')->willReturn($action);
		$notification->expects($this->once())->method('addParsedAction')->with($action)->willReturnSelf();
		$notification->expects($this->once())->method('setParsedSubject')->with($subject)->willReturnSelf();

		$this->notifier->prepare($notification, 'de');
	}

	public function testBuyLinkCarriesOnlyTheAppName(): void {
		foreach (WerkPlusLinks::all() as $url) {
			$query = (string)parse_url($url, PHP_URL_QUERY);
			$this->assertContains($query, ['', 'app=vertragswerk'], $url);
			$this->assertStringStartsWith('https://werkwolke.de/', $url);
		}
	}

	public function testAnAnnouncedEndVanishesOnceTheTrialIsOver(): void {
		$this->now = self::TRIAL_END + 60;
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial(TrialNoticeService::STAGE_7D);
	}

	private function prepareTrial(string $stage): void {
		$this->notifier->prepare($this->notification('contractmanager', Notifier::SUBJECT_TRIAL, ['stage' => $stage, 'trialEnd' => self::TRIAL_END]), 'de');
	}

	public function testSevenDayNoticeVanishesOnceTheOneDayStageIsDue(): void {
		$this->now = self::TRIAL_END - 86400;
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial(TrialNoticeService::STAGE_7D);
	}

	public function testOneDayNoticeVanishesAtTheTrialEnd(): void {
		$this->now = self::TRIAL_END;
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial(TrialNoticeService::STAGE_1D);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function announcements(): array {
		return ['7 Tage' => [TrialNoticeService::STAGE_7D], '1 Tag' => [TrialNoticeService::STAGE_1D]];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('announcements')]
	public function testAnnouncementsVanishAfterBuying(string $stage): void {
		$this->now = self::TRIAL_END - 2 * 86400;
		$this->trialEndsAt = null;
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial($stage);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('announcements')]
	public function testAnnouncementsOfAnEarlierTrialVanish(string $stage): void {
		$this->now = self::TRIAL_END - 2 * 86400;
		$this->trialEndsAt = self::TRIAL_END + 30 * 86400;
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial($stage);
	}

	public function testEndNoticeVanishesAfterBuying(): void {
		$this->now = self::TRIAL_END + 86400;
		$this->trialEndsAt = null;
		$this->tier = 'werkplus';
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial(TrialNoticeService::STAGE_END);
	}

	public function testEndNoticeStaysWithoutABoughtKey(): void {
		$this->now = self::TRIAL_END + 86400;
		$this->trialEndsAt = null;
		$this->tier = 'free';
		$notification = $this->notification('contractmanager', Notifier::SUBJECT_TRIAL, ['stage' => TrialNoticeService::STAGE_END, 'trialEnd' => self::TRIAL_END]);
		$action = $this->createMock(IAction::class);
		$action->method('setLabel')->willReturnSelf();
		$action->method('setParsedLabel')->willReturnSelf();
		$action->method('setPrimary')->willReturnSelf();
		$action->method('setLink')->willReturnSelf();
		$notification->method('createAction')->willReturn($action);
		$notification->expects($this->once())->method('setParsedSubject')->with('Der WerkPlus-Testzeitraum ist beendet.')->willReturnSelf();

		$this->notifier->prepare($notification, 'de');
	}

	public function testEndNoticeStaysWhileATrialTokenIsStillActive(): void {
		$this->now = self::TRIAL_END + 3600;
		$this->trialEndsAt = self::TRIAL_END;
		$this->tier = 'werkplus';
		$notification = $this->notification('contractmanager', Notifier::SUBJECT_TRIAL, ['stage' => TrialNoticeService::STAGE_END, 'trialEnd' => self::TRIAL_END]);
		$action = $this->createMock(IAction::class);
		$action->method('setLabel')->willReturnSelf();
		$action->method('setParsedLabel')->willReturnSelf();
		$action->method('setPrimary')->willReturnSelf();
		$action->method('setLink')->willReturnSelf();
		$notification->method('createAction')->willReturn($action);
		$notification->expects($this->once())->method('setParsedSubject')->with('Der WerkPlus-Testzeitraum ist beendet.')->willReturnSelf();

		$this->notifier->prepare($notification, 'de');
	}

	public function testUnknownStageVanishes(): void {
		$this->expectException(AlreadyProcessedException::class);

		$this->prepareTrial('2d');
	}
}
