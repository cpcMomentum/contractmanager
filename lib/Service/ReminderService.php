<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use DateInterval;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderSent;
use OCA\ContractManager\Db\ReminderSentMapper;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Service for checking contracts and sending reminders
 *
 * Supports two reminder timepoints:
 * - First reminder: X days before cancellation deadline (default: 14 days)
 * - Final reminder: Y days before cancellation deadline (default: 3 days)
 *
 * Notifications are sent via:
 * - Nextcloud Notifications (always)
 * - Nextcloud Talk (if configured by admin)
 * - E-Mail (if enabled by user)
 */
class ReminderService {

	public function __construct(
		private ContractMapper $contractMapper,
		private ReminderSentMapper $reminderSentMapper,
		private INotificationManager $notificationManager,
		private SettingsService $settingsService,
		private TalkService $talkService,
		private EmailService $emailService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check all contracts and send reminders where needed
	 *
	 * @return int Number of reminders sent
	 */
	public function checkAndSendReminders(): int {
		$remindersSent = 0;
		$contracts = $this->contractMapper->findContractsNeedingReminder();

		foreach ($contracts as $contract) {
			// Check for first reminder
			if ($this->shouldSendFirstReminder($contract)) {
				try {
					$this->sendReminders($contract, 'first');
					$this->markReminderSent($contract, 'first');
					$remindersSent++;
					$this->logger->info('Sent first cancellation reminder for contract: ' . $contract->getName(), [
						'app' => Application::APP_ID,
						'contractId' => $contract->getId(),
					]);
				} catch (\Exception $e) {
					$this->logger->error('Failed to send first reminder for contract: ' . $contract->getName(), [
						'app' => Application::APP_ID,
						'contractId' => $contract->getId(),
						'exception' => $e->getMessage(),
					]);
				}
			}

			// Check for final reminder
			if ($this->shouldSendFinalReminder($contract)) {
				try {
					$this->sendReminders($contract, 'final');
					$this->markReminderSent($contract, 'final');
					$remindersSent++;
					$this->logger->info('Sent final cancellation reminder for contract: ' . $contract->getName(), [
						'app' => Application::APP_ID,
						'contractId' => $contract->getId(),
					]);
				} catch (\Exception $e) {
					$this->logger->error('Failed to send final reminder for contract: ' . $contract->getName(), [
						'app' => Application::APP_ID,
						'contractId' => $contract->getId(),
						'exception' => $e->getMessage(),
					]);
				}
			}
		}

		return $remindersSent;
	}

	/**
	 * Calculate the cancellation deadline based on end date and cancellation period
	 * Uses conservative month-end calculation (1 month before March 31 = Feb 28, not Feb 31)
	 */
	public function calculateCancellationDeadline(Contract $contract): ?DateTime {
		$endDate = $contract->getEndDate();
		$cancellationPeriod = $contract->getCancellationPeriod();

		if ($endDate === null || empty($cancellationPeriod)) {
			return null;
		}

		// Parse cancellation period (e.g., "3 months", "14 days", "1 year")
		if (!preg_match('/^(\d+)\s+(day|days|week|weeks|month|months|year|years)$/i', trim($cancellationPeriod), $matches)) {
			return null;
		}

		$value = (int) $matches[1];
		$unit = strtolower($matches[2]);

		// Normalize unit to singular
		$unit = rtrim($unit, 's');

		$deadline = clone $endDate;

		// For months, use conservative calculation to avoid invalid dates
		if ($unit === 'month') {
			// Store original day to handle month-end edge cases
			$originalDay = (int) $deadline->format('d');

			// Subtract months
			$deadline->modify("-{$value} month");

			// Check if we overflowed into the next month (e.g., March 31 - 1 month = March 3 instead of Feb 28)
			$newDay = (int) $deadline->format('d');
			if ($newDay > $originalDay || ($originalDay > 28 && $newDay < $originalDay)) {
				// We hit a month-end edge case, go back to last day of previous month
				$deadline->modify('last day of previous month');
			}
		} elseif ($unit === 'year') {
			$deadline->modify("-{$value} year");
		} elseif ($unit === 'week') {
			$deadline->modify("-{$value} week");
		} else {
			// days
			$deadline->modify("-{$value} day");
		}

		return $deadline;
	}

	/**
	 * Check if the first reminder should be sent for this contract
	 */
	public function shouldSendFirstReminder(Contract $contract): bool {
		if (!$this->isContractEligibleForReminder($contract)) {
			return false;
		}

		$cancellationDeadline = $this->calculateCancellationDeadline($contract);
		if ($cancellationDeadline === null) {
			return false;
		}

		// Get reminder days - contract override or global setting
		$reminderDays = $contract->getReminderDays() ?? $this->settingsService->getReminderDays1();
		$now = new DateTime();
		$reminderDate = clone $cancellationDeadline;
		$reminderDate->modify("-{$reminderDays} days");

		// Check if we're within the first reminder window
		if ($now < $reminderDate) {
			return false; // Too early
		}
		if ($now > $cancellationDeadline) {
			return false; // Too late, deadline passed
		}

		// Check if first reminder was already sent
		$reminderType = $this->getReminderType($contract, 'first');
		if ($this->reminderSentMapper->hasBeenSent($contract->getId(), $reminderType)) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the final reminder should be sent for this contract
	 */
	public function shouldSendFinalReminder(Contract $contract): bool {
		if (!$this->isContractEligibleForReminder($contract)) {
			return false;
		}

		$cancellationDeadline = $this->calculateCancellationDeadline($contract);
		if ($cancellationDeadline === null) {
			return false;
		}

		// Final reminder uses the second setting (default: 3 days)
		$reminderDays = $this->settingsService->getReminderDays2();
		$now = new DateTime();
		$reminderDate = clone $cancellationDeadline;
		$reminderDate->modify("-{$reminderDays} days");

		// Check if we're within the final reminder window
		if ($now < $reminderDate) {
			return false; // Too early
		}
		if ($now > $cancellationDeadline) {
			return false; // Too late, deadline passed
		}

		// Check if final reminder was already sent
		$reminderType = $this->getReminderType($contract, 'final');
		if ($this->reminderSentMapper->hasBeenSent($contract->getId(), $reminderType)) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a contract is eligible for any reminder
	 */
	private function isContractEligibleForReminder(Contract $contract): bool {
		// Only active contracts with reminders enabled
		if ($contract->getStatus() !== Contract::STATUS_ACTIVE) {
			return false;
		}
		if (!$contract->getReminderEnabled()) {
			return false;
		}
		if ($contract->getArchived()) {
			return false;
		}

		return true;
	}

	/**
	 * Get a unique reminder type identifier for this contract/period
	 *
	 * @param Contract $contract The contract
	 * @param string $type 'first' or 'final'
	 * @return string Unique identifier
	 */
	private function getReminderType(Contract $contract, string $type): string {
		// Include end date in type so if contract is renewed, a new reminder can be sent
		$endDateStr = $contract->getEndDate()?->format('Y-m-d') ?? 'unknown';
		return "cancellation_{$endDateStr}_{$type}";
	}

	/**
	 * Send all configured reminders for a contract
	 *
	 * @param Contract $contract The contract
	 * @param string $reminderType 'first' or 'final'
	 */
	private function sendReminders(Contract $contract, string $reminderType): void {
		$cancellationDeadline = $this->calculateCancellationDeadline($contract);
		if ($cancellationDeadline === null) {
			return;
		}

		$deadlineFormatted = $cancellationDeadline->format('d.m.Y');
		$userId = $contract->getCreatedBy();

		// 1. Send Talk message if configured
		if ($this->talkService->isTalkAvailable() && $this->talkService->isTalkConfigured()) {
			try {
				$this->talkService->sendReminderMessage(
					$contract->getName(),
					$deadlineFormatted,
					$reminderType
				);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send Talk reminder: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
				]);
			}
		}

		// 2. Send E-Mail if user has enabled it
		if ($this->settingsService->getUserEmailReminder($userId)) {
			try {
				$this->emailService->sendReminder($contract, $userId, $deadlineFormatted, $reminderType);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send Email reminder: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
				]);
			}
		}
	}

	/**
	 * Send a Nextcloud notification to the contract owner
	 *
	 * @param Contract $contract The contract
	 * @param string $reminderType 'first' or 'final'
	 * @param string $deadlineFormatted Formatted deadline date
	 */
	private function sendNotification(Contract $contract, string $reminderType, string $deadlineFormatted): void {
		$subject = $reminderType === 'first' ? 'cancellation_reminder_first' : 'cancellation_reminder_final';

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(Application::APP_ID)
			->setUser($contract->getCreatedBy())
			->setDateTime(new DateTime())
			->setObject('contract', (string) $contract->getId())
			->setSubject($subject, [
				'contractId' => $contract->getId(),
				'contractName' => $contract->getName(),
				'deadline' => $deadlineFormatted,
			]);

		$this->notificationManager->notify($notification);
	}

	/**
	 * Mark a reminder as sent
	 *
	 * @param Contract $contract The contract
	 * @param string $type 'first' or 'final'
	 */
	private function markReminderSent(Contract $contract, string $type): void {
		$reminder = new ReminderSent();
		$reminder->setContractId($contract->getId());
		$reminder->setReminderType($this->getReminderType($contract, $type));
		$reminder->setSentAt(new DateTime());
		$reminder->setSentTo($contract->getCreatedBy());

		$this->reminderSentMapper->insert($reminder);
	}

	/**
	 * Legacy method for backwards compatibility
	 * @deprecated Use shouldSendFirstReminder or shouldSendFinalReminder instead
	 */
	public function shouldSendReminder(Contract $contract): bool {
		return $this->shouldSendFirstReminder($contract) || $this->shouldSendFinalReminder($contract);
	}
}
