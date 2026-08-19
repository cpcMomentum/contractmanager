<?php

declare(strict_types=1);

namespace OCA\ContractManager\Notification;

use OCA\ContractManager\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	public const SUBJECT_USER_DELETED = 'user_deleted';

	public function __construct(
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'Verträge';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		try {
			return $this->prepareContractNotification($notification, $languageCode);
		} catch (UnknownNotificationException $e) {
			// Genuinely unknown subject — let NC handle it.
			throw $e;
		} catch (\InvalidArgumentException $e) {
			// Safety net: an NC INotification setter rejected a value while
			// building a known notification. The concrete #357 cause (a relative
			// icon URL) is fixed below with getAbsoluteURL(), so this should no
			// longer fire in normal operation; it stays to guard against any
			// other setter rejection on a future NC version. NC 34+ deprecates
			// letting \InvalidArgumentException escape prepare(), so convert it
			// and discard the undisplayable notification cleanly.
			throw new UnknownNotificationException();
		}
	}

	/**
	 * Build a known VertragsWerk notification. Any \InvalidArgumentException
	 * raised by an NC setter while building it (e.g. a value NC rejects on a
	 * given version) is caught and handled by prepare().
	 */
	private function prepareContractNotification(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case self::SUBJECT_USER_DELETED:
				$notification->setParsedSubject(
					$l->t('Konto %1$s gelöscht: %2$d Verträge übertragen, %3$d brauchen eine Zuordnung', [
						$params['deletedUser'],
						(int)$params['reassigned'],
						(int)$params['needsAttention'],
					])
				);
				$notification->setParsedMessage(
					((int)$params['needsAttention']) > 0
						? $l->t('Die verbliebenen Verträge findest du über den Filter „Ohne aktiven Eigentümer".')
						: $l->t('Alle Verträge wurden übertragen, es ist nichts weiter zu tun.')
				);
				break;

			default:
				throw new UnknownNotificationException();
		}

		// #357: NC 34's setIcon() rejects anything that is not an absolute
		// http(s) URL, and imagePath() returns a relative path. Passing the raw
		// imagePath() throws InvalidValueException (⊂ \InvalidArgumentException),
		// which aborts prepare() before setLink() — the notification then loses
		// both its icon and its link (and NC 34 logs the throw on every cycle).
		// Wrap it in getAbsoluteURL() so the icon is a valid absolute URL.
		$notification->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
			)
		);
		$notification->setLink(
			$this->urlGenerator->linkToRouteAbsolute('contractmanager.page.index')
		);

		return $notification;
	}
}
