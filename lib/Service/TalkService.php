<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCP\App\IAppManager;
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
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if Talk app is installed and enabled
	 */
	public function isTalkAvailable(): bool {
		return $this->appManager->isEnabledForUser('spreed');
	}

	/**
	 * Check if Talk is configured (token is set)
	 */
	public function isTalkConfigured(): bool {
		$token = $this->settingsService->getTalkChatToken();
		return $token !== null && $token !== '';
	}

	/**
	 * Send a message to the configured Talk chat
	 *
	 * @param string $message The message to send
	 * @return bool True if message was sent successfully
	 */
	public function sendMessage(string $message): bool {
		if (!$this->isTalkAvailable()) {
			$this->logger->warning('Talk app is not available', [
				'app' => Application::APP_ID,
			]);
			return false;
		}

		$token = $this->settingsService->getTalkChatToken();
		if ($token === null || $token === '') {
			$this->logger->warning('Talk chat token is not configured', [
				'app' => Application::APP_ID,
			]);
			return false;
		}

		return $this->sendToChat($token, $message);
	}

	/**
	 * Send a reminder message for a contract
	 *
	 * @param string $contractName The contract name
	 * @param string $deadline The deadline date formatted
	 * @param string $reminderType 'first' or 'final'
	 * @return bool True if message was sent successfully
	 */
	public function sendReminderMessage(string $contractName, string $deadline, string $reminderType): bool {
		if ($reminderType === 'first') {
			$message = "📋 **Kündigungserinnerung**\n\nDer Vertrag \"$contractName\" muss bis **$deadline** gekündigt werden.\n\n_Dies ist die erste Erinnerung._";
		} else {
			$message = "⚠️ **Letzte Kündigungserinnerung**\n\nDer Vertrag \"$contractName\" muss bis **$deadline** gekündigt werden!\n\n_Dies ist die letzte Erinnerung vor Ablauf der Kündigungsfrist._";
		}

		return $this->sendMessage($message);
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
