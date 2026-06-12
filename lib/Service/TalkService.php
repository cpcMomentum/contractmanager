<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Service for sending messages to Nextcloud Talk
 *
 * Uses the Talk Bot API to send messages as a system/bot user
 *
 * EXPERIMENTAL: This service uses internal Talk APIs (OCA\Talk\*) which are
 * not part of Nextcloud's public API contract and may change without notice
 * between Talk versions. If Talk integration fails after an update, this
 * service may need to be adapted to the new internal API.
 *
 * Alternative approach for production use: Implement HTTP-based Talk OCS API
 * (POST /ocs/v2.php/apps/spreed/api/v1/chat/{token}) with proper authentication.
 */
class TalkService {

	public function __construct(
		private IAppManager $appManager,
		private LoggerInterface $logger,
		private IFactory $l10nFactory,
	) {
	}

	/**
	 * Check if Talk app is installed and enabled
	 */
	public function isTalkAvailable(): bool {
		return $this->appManager->isEnabledForUser('spreed');
	}

	/**
	 * Send a message to a Talk chat identified by its token
	 *
	 * @param string $chatToken The target chat token
	 * @param string $message The message to send
	 * @return bool True if message was sent successfully
	 */
	public function sendMessage(string $chatToken, string $message): bool {
		if (!$this->isTalkAvailable()) {
			$this->logger->warning('Talk app is not available', [
				'app' => Application::APP_ID,
			]);
			return false;
		}

		if ($chatToken === '') {
			return false;
		}

		return $this->sendToChat($chatToken, $message);
	}

	/**
	 * Send a reminder message for a contract to a specific chat
	 *
	 * @param string $chatToken The target chat token
	 * @param string $contractName The contract name
	 * @param string $deadline The deadline date formatted
	 * @param string $reminderType 'first' or 'final'
	 * @return bool True if message was sent successfully
	 */
	public function sendReminderMessage(string $chatToken, string $contractName, string $deadline, string $reminderType, string $contractType = 'auto_renewal'): bool {
		$l = $this->l10nFactory->get(Application::APP_ID);

		if ($contractType === 'auto_renewal') {
			if ($reminderType === 'first') {
				$title = $l->t('Cancellation reminder');
				$body = $l->t('The contract "%1$s" must be cancelled by **%2$s**.', [$contractName, $deadline]);
				$footer = $l->t('This is the first reminder.');
				$message = "📋 **$title**\n\n$body\n\n_{$footer}_";
			} else {
				$title = $l->t('Final cancellation reminder');
				$body = $l->t('The contract "%1$s" must be cancelled by **%2$s**!', [$contractName, $deadline]);
				$footer = $l->t('This is the final reminder before the cancellation deadline.');
				$message = "⚠️ **$title**\n\n$body\n\n_{$footer}_";
			}
		} else {
			if ($reminderType === 'first') {
				$title = $l->t('Contract expiring');
				$body = $l->t('The contract "%1$s" expires on **%2$s**.', [$contractName, $deadline]);
				$footer = $l->t('This is the first reminder.');
				$message = "📋 **$title**\n\n$body\n\n_{$footer}_";
			} else {
				$title = $l->t('Contract expiring');
				$body = $l->t('The contract "%1$s" expires on **%2$s**!', [$contractName, $deadline]);
				$footer = $l->t('This is the final reminder.');
				$message = "⚠️ **$title**\n\n$body\n\n_{$footer}_";
			}
		}

		return $this->sendMessage($chatToken, $message);
	}

	/**
	 * Send a message to a specific Talk chat using the internal Talk API
	 *
	 * @param string $chatToken The chat token
	 * @param string $message The message to send
	 * @return bool True if message was sent successfully
	 */
	private function sendToChat(string $chatToken, string $message): bool {
		try {
			// Get the Talk chat manager directly
			$chatManager = \OCP\Server::get(\OCA\Talk\Chat\ChatManager::class);

			// Get the room by token
			$roomManager = \OCP\Server::get(\OCA\Talk\Manager::class);
			$room = $roomManager->getRoomByToken($chatToken);

			// Send message as guest (triggers unread counter)
			$chatManager->sendMessage(
				$room,
				null, // No specific attendee
				'guests', // Actor type - triggers unread notifications
				'ContractManager', // Actor ID (display name)
				$message,
				new \DateTime(),
				null, // No parent message (replyTo)
				'', // No reference ID
				false, // Not silent
				true // rateLimitGuestMentions
			);

			$this->logger->info('Talk message sent successfully', [
				'app' => Application::APP_ID,
				'chatToken' => $this->anonymizeToken($chatToken),
			]);
			return true;

		} catch (\OCP\AppFramework\Db\DoesNotExistException $e) {
			$this->logger->error('Talk chat not found', [
				'app' => Application::APP_ID,
				'chatToken' => $this->anonymizeToken($chatToken),
				'exception' => $e,
			]);
			return false;
		} catch (\Exception $e) {
			$this->logger->error('Exception while sending Talk message: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'chatToken' => $this->anonymizeToken($chatToken),
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * Anonymize a token for logging (show only first 3 chars)
	 */
	private function anonymizeToken(string $token): string {
		if (strlen($token) <= 3) {
			return '***';
		}
		return substr($token, 0, 3) . '***';
	}
}
