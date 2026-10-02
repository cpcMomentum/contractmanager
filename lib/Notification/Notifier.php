<?php

declare(strict_types=1);

namespace OCA\ContractManager\Notification;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	public const SUBJECT_USER_DELETED = 'user_deleted';
	public const SUBJECT_CONTRACT_TRASHED = 'contract_trashed';

	public function __construct(
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private ContractMapper $contractMapper,
		private IUserManager $userManager,
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
	 * The contract a trash notification refers to, as long as it still lies in
	 * the trash from this very deletion (#438).
	 *
	 * Lazy cleanup instead of retracting: OCP offers no public way to withdraw
	 * a notification since NC 33. Once the contract is restored, deleted for
	 * good, or trashed again (by anyone), this notification is obsolete and
	 * AlreadyProcessedException makes Nextcloud drop it.
	 *
	 * @param array<string, mixed> $params
	 * @throws AlreadyProcessedException
	 */
	private function findStillTrashed(INotification $notification, array $params): Contract {
		try {
			$contract = $this->contractMapper->find((int)$notification->getObjectId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			throw new AlreadyProcessedException();
		}

		if (!$contract->isDeleted()
			|| $contract->getDeletedBy() !== ($params['deletedBy'] ?? null)
			|| $contract->getDeletedAt()?->format('c') !== ($params['deletedAt'] ?? null)) {
			throw new AlreadyProcessedException();
		}

		return $contract;
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

			case self::SUBJECT_CONTRACT_TRASHED:
				$contract = $this->findStillTrashed($notification, $params);
				$deletedBy = (string)($params['deletedBy'] ?? '');
				$notification->setParsedSubject(
					$l->t('%1$s hat deinen Vertrag „%2$s“ in den Papierkorb gelegt', [
						$this->userManager->getDisplayName($deletedBy) ?? $deletedBy,
						$contract->getName(),
					])
				);
				$notification->setParsedMessage(
					$l->t('Du kannst ihn im Papierkorb wiederherstellen.')
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
