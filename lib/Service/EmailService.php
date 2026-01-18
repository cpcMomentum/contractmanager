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

		return $this->sendReminderEmail($email, $contract, $deadline, $reminderType);
	}

	/**
	 * Send reminder email to an address
	 */
	private function sendReminderEmail(string $toEmail, Contract $contract, string $deadline, string $reminderType): bool {
		try {
			$l = $this->l10nFactory->get(Application::APP_ID);
			$message = $this->mailer->createMessage();

			// Set subject based on reminder type
			if ($reminderType === 'first') {
				$subject = $l->t('Kündigungserinnerung: %s', [$contract->getName()]);
			} else {
				$subject = $l->t('⚠️ Letzte Kündigungserinnerung: %s', [$contract->getName()]);
			}

			$message->setSubject($subject);
			$message->setTo([$toEmail]);

			// Build HTML body
			$appUrl = $this->urlGenerator->linkToRouteAbsolute('contractmanager.page.index');
			$htmlBody = $this->buildHtmlBody($contract, $deadline, $reminderType, $appUrl, $l);
			$plainBody = $this->buildPlainBody($contract, $deadline, $reminderType, $appUrl, $l);

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
	private function buildHtmlBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l): string {
		$contractName = htmlspecialchars($contract->getName());
		$vendor = htmlspecialchars($contract->getVendor());

		if ($reminderType === 'first') {
			$title = $l->t('Kündigungserinnerung');
			$intro = $l->t('Dies ist eine Erinnerung, dass der folgende Vertrag bald gekündigt werden muss:');
		} else {
			$title = $l->t('Letzte Kündigungserinnerung');
			$intro = $l->t('Dies ist die letzte Erinnerung! Der folgende Vertrag muss dringend gekündigt werden:');
		}

		return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0082c9; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 8px 8px; }
        .contract-info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .deadline { font-size: 1.2em; font-weight: bold; color: #c00; }
        .button { display: inline-block; background: #0082c9; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">{$title}</h1>
        </div>
        <div class="content">
            <p>{$intro}</p>
            <div class="contract-info">
                <p><strong>{$l->t('Vertrag')}:</strong> {$contractName}</p>
                <p><strong>{$l->t('Vertragspartner')}:</strong> {$vendor}</p>
                <p class="deadline"><strong>{$l->t('Kündigungsfrist')}:</strong> {$deadline}</p>
            </div>
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
	private function buildPlainBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l): string {
		if ($reminderType === 'first') {
			$title = $l->t('Kündigungserinnerung');
			$intro = $l->t('Dies ist eine Erinnerung, dass der folgende Vertrag bald gekündigt werden muss:');
		} else {
			$title = $l->t('⚠️ Letzte Kündigungserinnerung');
			$intro = $l->t('Dies ist die letzte Erinnerung! Der folgende Vertrag muss dringend gekündigt werden:');
		}

		return <<<TEXT
{$title}
{'='.repeat(strlen($title))}

{$intro}

{$l->t('Vertrag')}: {$contract->getName()}
{$l->t('Vertragspartner')}: {$contract->getVendor()}
{$l->t('Kündigungsfrist')}: {$deadline}

{$l->t('Zum ContractManager')}: {$appUrl}
TEXT;
	}
}
