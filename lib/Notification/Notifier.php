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

	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('ContractManager');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('Unknown app');
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$parameters = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case 'cancellation_reminder':
				// Legacy support - treat as first reminder
				$this->prepareFirstReminder($notification, $l, $parameters);
				break;

			case 'cancellation_reminder_first':
				$this->prepareFirstReminder($notification, $l, $parameters);
				break;

			case 'cancellation_reminder_final':
				$this->prepareFinalReminder($notification, $l, $parameters);
				break;

			default:
				throw new UnknownNotificationException('Unknown subject');
		}

		return $notification;
	}

	/**
	 * Prepare the first reminder notification
	 */
	private function prepareFirstReminder(INotification $notification, $l, array $parameters): void {
		$notification->setParsedSubject(
			$l->t('Kündigungserinnerung: %s', [$parameters['contractName']])
		);
		$notification->setParsedMessage(
			$l->t('Der Vertrag "%s" muss bis %s gekündigt werden.', [
				$parameters['contractName'],
				$parameters['deadline'],
			])
		);
		$notification->setRichSubject(
			$l->t('Kündigungserinnerung: {contract}'),
			[
				'contract' => [
					'type' => 'highlight',
					'id' => $parameters['contractId'] ?? 0,
					'name' => $parameters['contractName'],
				],
			]
		);
		$notification->setRichMessage(
			$l->t('Der Vertrag "{contract}" muss bis {deadline} gekündigt werden.'),
			[
				'contract' => [
					'type' => 'highlight',
					'id' => $parameters['contractId'] ?? 0,
					'name' => $parameters['contractName'],
				],
				'deadline' => [
					'type' => 'highlight',
					'id' => 'deadline',
					'name' => $parameters['deadline'],
				],
			]
		);

		$this->setCommonProperties($notification);
	}

	/**
	 * Prepare the final reminder notification (more urgent)
	 */
	private function prepareFinalReminder(INotification $notification, $l, array $parameters): void {
		$notification->setParsedSubject(
			$l->t('LETZTE Kündigungserinnerung: %s', [$parameters['contractName']])
		);
		$notification->setParsedMessage(
			$l->t('ACHTUNG: Der Vertrag "%s" muss bis %s gekündigt werden! Dies ist die letzte Erinnerung.', [
				$parameters['contractName'],
				$parameters['deadline'],
			])
		);
		$notification->setRichSubject(
			$l->t('LETZTE Kündigungserinnerung: {contract}'),
			[
				'contract' => [
					'type' => 'highlight',
					'id' => $parameters['contractId'] ?? 0,
					'name' => $parameters['contractName'],
				],
			]
		);
		$notification->setRichMessage(
			$l->t('ACHTUNG: Der Vertrag "{contract}" muss bis {deadline} gekündigt werden! Dies ist die letzte Erinnerung.'),
			[
				'contract' => [
					'type' => 'highlight',
					'id' => $parameters['contractId'] ?? 0,
					'name' => $parameters['contractName'],
				],
				'deadline' => [
					'type' => 'highlight',
					'id' => 'deadline',
					'name' => $parameters['deadline'],
				],
			]
		);

		$this->setCommonProperties($notification);
	}

	/**
	 * Set common notification properties (link and icon)
	 */
	private function setCommonProperties(INotification $notification): void {
		// Link to the app
		$notification->setLink(
			$this->urlGenerator->linkToRouteAbsolute('contractmanager.page.index')
		);

		// Set icon
		$notification->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
			)
		);
	}
}
