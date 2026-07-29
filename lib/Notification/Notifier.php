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

		$notification->setIcon(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
		$notification->setLink(
			$this->urlGenerator->linkToRouteAbsolute('contractmanager.page.index')
		);

		return $notification;
	}
}
