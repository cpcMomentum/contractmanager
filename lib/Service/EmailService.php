<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCP\IConfig;
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
		private IConfig $config,
		private IFactory $l10nFactory,
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Build an absolute deep link to a contract.
	 *
	 * The reminder runs in a background (cron) job, where named app routes are
	 * not resolvable — linkToRouteAbsolute() collapses to the bare instance root
	 * and the recipient lands on their default app (Dashboard) instead of here.
	 * We therefore build the path explicitly. The front-controller check mirrors
	 * core's URLGenerator so the link is correct with and without URL rewriting.
	 */
	private function buildContractUrl(Contract $contract): string {
		$frontControllerActive = (getenv('front_controller_active') === 'true')
			|| $this->config->getSystemValueBool('htaccess.IgnoreFrontController', false);
		$path = ($frontControllerActive ? '' : '/index.php')
			. '/apps/' . Application::APP_ID . '/?contract=' . $contract->getId();

		return $this->urlGenerator->getAbsoluteURL($path);
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
	public function sendReminder(Contract $contract, string $userId, string $deadline, string $reminderType, string $contractType = 'auto_renewal'): bool {
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

		return $this->sendReminderEmail($email, $contract, $deadline, $reminderType, $displayName, $contractType);
	}

	/**
	 * Send reminder email to an address
	 */
	private function sendReminderEmail(string $toEmail, Contract $contract, string $deadline, string $reminderType, string $displayName, string $contractType = 'auto_renewal'): bool {
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
			$appUrl = htmlspecialchars($this->buildContractUrl($contract), ENT_QUOTES, 'UTF-8');
			$htmlBody = $this->buildHtmlBody($contract, $deadline, $reminderType, $appUrl, $l, $displayName, $contractType);
			$plainBody = $this->buildPlainBody($contract, $deadline, $reminderType, $appUrl, $l, $displayName, $contractType);

			$message->setHtmlBody($htmlBody);
			$message->setPlainBody($plainBody);

			$this->mailer->send($message);

			$this->logger->debug('Email reminder sent successfully', [
				'app' => Application::APP_ID,
				'toEmail' => self::maskEmail($toEmail),
				'contractId' => $contract->getId(),
				'reminderType' => $reminderType,
			]);

			return true;

		} catch (\Exception $e) {
			$this->logger->error('Failed to send email reminder: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'toEmail' => self::maskEmail($toEmail),
				'contractId' => $contract->getId(),
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * Mask an email address for log output: keep first char of local part,
	 * the domain TLD, and obfuscate the rest. Examples:
	 *   axel@example.com → a***@e***.com
	 *   a@b.de           → a***@b***.de
	 *   (invalid)        → ***
	 */
	public static function maskEmail(string $email): string {
		$at = strrpos($email, '@');
		if ($at === false || $at === 0 || $at === strlen($email) - 1) {
			return '***';
		}
		$local = substr($email, 0, $at);
		$domain = substr($email, $at + 1);
		$dot = strrpos($domain, '.');
		if ($dot === false || $dot === 0) {
			return $local[0] . '***@***';
		}
		$domainName = substr($domain, 0, $dot);
		$tld = substr($domain, $dot);
		return $local[0] . '***@' . $domainName[0] . '***' . $tld;
	}

	/**
	 * Build HTML email body
	 */
	private function buildHtmlBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l, string $displayName, string $contractType = 'auto_renewal'): string {
		$contractName = htmlspecialchars($contract->getName());
		$vendor = htmlspecialchars($contract->getVendor());
		$displayNameEscaped = htmlspecialchars($displayName);

		if ($reminderType === 'first') {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft bald ab.', [$contractName, $vendor]);
		} else {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft in wenigen Tagen ab.', [$contractName, $vendor]);
		}

		$greeting = $l->t('Hallo %s,', [$displayNameEscaped]);
		if ($contractType === 'auto_renewal') {
			$deadlineText = $l->t('Wenn du kündigen möchtest, musst du das bis zum %s tun.', [$deadline]);
		} else {
			$deadlineText = $l->t('Der Vertrag endet am %s.', [$deadline]);
		}
		$linkText = $l->t('Hier kommst du direkt zum Vertrag:');

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
            <a href="{$appUrl}" class="button">{$l->t('Vertrag öffnen')}</a>
        </div>
    </div>
</body>
</html>
HTML;
	}

	/**
	 * Build plain text email body
	 */
	private function buildPlainBody(Contract $contract, string $deadline, string $reminderType, string $appUrl, $l, string $displayName, string $contractType = 'auto_renewal'): string {
		if ($reminderType === 'first') {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft bald ab.', [$contract->getName(), $contract->getVendor()]);
		} else {
			$intro = $l->t('dein Vertrag "%1$s" bei %2$s läuft in wenigen Tagen ab.', [$contract->getName(), $contract->getVendor()]);
		}

		$greeting = $l->t('Hallo %s,', [$displayName]);
		if ($contractType === 'auto_renewal') {
			$deadlineText = $l->t('Wenn du kündigen möchtest, musst du das bis zum %s tun.', [$deadline]);
		} else {
			$deadlineText = $l->t('Der Vertrag endet am %s.', [$deadline]);
		}
		$linkText = $l->t('Hier kommst du direkt zum Vertrag:');

		return <<<TEXT
{$greeting}

{$intro}

{$deadlineText}

{$linkText}
{$appUrl}
TEXT;
	}
}
