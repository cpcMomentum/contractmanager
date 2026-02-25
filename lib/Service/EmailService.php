<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/**
 * Service for sending email reminders
 */
class EmailService {

	public function __construct(
		private IMailer $mailer,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Send a reminder email to a user
	 *
	 * @param Contract $contract The contract
	 * @param string $userId The user ID to send to
	 * @param string $deadline The formatted deadline date
	 * @param string $reminderType 'first' or 'final'
	 * @return bool True if email was sent successfully
	 */
	public function sendReminder(Contract $contract, string $userId, string $deadline, string $reminderType): bool {
		// Check if user has email reminders enabled
		if (!$this->settingsService->getUserEmailReminder($userId)) {
			$this->logger->debug('User has email reminders disabled', [
				'app' => Application::APP_ID,
				'userId' => $userId,
			]);
			return false;
		}

		// Get user email
		$user = $this->userManager->get($userId);
		if ($user === null) {
			$this->logger->warning('User not found for email reminder', [
				'app' => Application::APP_ID,
				'userId' => $userId,
			]);
			return false;
		}

		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			$this->logger->warning('User has no email address configured', [
				'app' => Application::APP_ID,
				'userId' => $userId,
			]);
			return false;
		}

		$displayName = $user->getDisplayName() ?: $userId;

		return $this->sendReminderEmail($email, $contract, $deadline, $reminderType, $displayName);
	}

	/**
	 * Send reminder email to an address
	 */
	private function sendReminderEmail(string $toEmail, Contract $contract, string $deadline, string $reminderType, string $displayName): bool {
		try {
			$l = $this->l10nFactory->get(Application::APP_ID);
			$message = $this->mailer->createMessage();

			// Set subject based on reminder type (no emoji)
			if ($reminderType === 'first') {
				$subject = $l->t('Erinnerung: %s läuft bald ab', [$contract->getName()]);
			} else {
				$subject = $l->t('Erinnerung: %s läuft in wenigen Tagen ab', [$contract->getName()]);
			}

			$message->setSubject($subject);
			$message->setTo([$toEmail]);

			// Build HTML body
			$appUrl = $this->urlGenerator->linkToRouteAbsolute('contractmanager.page.index');
			$htmlBody = $this->buildHtmlBody($contract, $deadline, $reminderType, $appUrl, $l, $displayName);
			$plainBody = $this->buildPlainBody($contract, $deadline, $reminderType, $appUrl, $l, $displayName);

			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($plainBody);

			$this->mailer->send($message);

			$this->logger->info('Email reminder sent successfully', [
				'app' => Application::APP_ID,
				'toEmail' => $toEmail,
				'contractId' => $contract->getId(),
				'reminderType' => $reminderType,
			]);

			return true;

		} catch (\Exception $e) {
			$this->logger->error('Failed to send email reminder: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'toEmail' => $toEmail,
				'contractId' => $contract->getId(),
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * Build HTML email body
	 */
	private function buildHtmlBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l, string $displayName): string {
		$contractName = htmlspecialchars($contract->getName());
		$vendor = htmlspecialchars($contract->getVendor());
		$displayNameEscaped = htmlspecialchars($displayName);

		if ($reminderType === 'first') {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft bald ab.', [$contractName, $vendor]);
		} else {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft in wenigen Tagen ab.', [$contractName, $vendor]);
		}

		$greeting = $l->t('Hallo %s,', [$displayNameEscaped]);
		$deadlineText = $l->t('Wenn du kündigen möchtest, musst du das bis zum %s tun.', [$deadline]);
		$linkText = $l->t('Im ContractManager findest du alle Details:');

		return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .content { background: #f5f5f5; padding: 20px; border-radius: 8px; }
        .button { display: inline-block; background: #0082c9; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <p>{$greeting}</p>
            <p>{$intro}</p>
            <p>{$deadlineText}</p>
            <p>{$linkText}</p>
            <a href="{$appUrl}" class="button">{$l->t('Zum ContractManager')}</a>
        </div>
    </div>
</body>
</html>
HTML;
	}

	/**
	 * Build plain text email body
	 */
	private function buildPlainBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l, string $displayName): string {
		if ($reminderType === 'first') {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft bald ab.', [$contract->getName(), $contract->getVendor()]);
		} else {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft in wenigen Tagen ab.', [$contract->getName(), $contract->getVendor()]);
		}

		$greeting = $l->t('Hallo %s,', [$displayName]);
		$deadlineText = $l->t('Wenn du kündigen möchtest, musst du das bis zum %s tun.', [$deadline]);
		$linkText = $l->t('Im ContractManager findest du alle Details:');

		return <<<TEXT
{$greeting}

{$intro}

{$deadlineText}

{$linkText}
{$appUrl}
TEXT;
	}
}
