<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderSent;
use OCA\ContractManager\Db\ReminderSentMapper;
use Psr\Log\LoggerInterface;

/**
 * Service for checking contracts and sending reminders
 *
 * Supports two reminder timepoints:
 * - First reminder: X days before cancellation deadline (default: 14 days)
 * - Final reminder: Y days before cancellation deadline (default: 3 days)
 *
 * Notifications are sent via:
 * - Nextcloud Talk (if configured by admin)
 * - E-Mail (if enabled by user)
 */
class ReminderService {

	public function __construct(
		private ContractMapper $contractMapper,
		private ReminderSentMapper $reminderSentMapper,
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
	 * Get the effective end date for a contract, accounting for auto-renewal
	 *
	 * For auto_renewal contracts with an end date in the past, this method
	 * repeatedly adds the renewal period until the date is in the future.
	 *
	 * @param Contract $contract The contract
	 * @return DateTime|null The effective end date, or null if no end date set
	 */
	public function getEffectiveEndDate(Contract $contract): ?DateTime {
		$endDate = $contract->getEndDate();
		if ($endDate === null) {
			return null;
		}

		$contractType = $contract->getContractType();
		$renewalPeriod = $contract->getRenewalPeriod();

		if ($contractType !== 'auto_renewal' || empty($renewalPeriod)) {
			return clone $endDate;
		}

		$now = new DateTime();
		$effective = clone $endDate;

		if ($effective > $now) {
			return $effective;
		}

		// Parse renewal period (e.g., "12 months", "1 year")
		if (!preg_match('/^(\d+)\s+(day|days|week|weeks|month|months|year|years)$/i', trim($renewalPeriod), $matches)) {
			return clone $endDate;
		}

		$value = (int) $matches[1];
		$unit = strtolower(rtrim($matches[2], 's'));

		// Add renewal periods until we reach a future date (max 100 iterations as safety)
		for ($i = 0; $i < 100 && $effective <= $now; $i++) {
			if ($unit === 'month') {
				$effective->modify("+{$value} month");
			} elseif ($unit === 'year') {
				$effective->modify("+{$value} year");
			} elseif ($unit === 'week') {
				$effective->modify("+{$value} week");
			} else {
				$effective->modify("+{$value} day");
			}
		}

		return $effective;
	}

	/**
	 * Calculate the cancellation deadline based on end date and cancellation period
	 * Uses conservative month-end calculation (1 month before March 31 = Feb 28, not Feb 31)
	 */
	public function calculateCancellationDeadline(Contract $contract): ?DateTime {
		$endDate = $this->getEffectiveEndDate($contract);
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
	 * Get the relevant deadline for reminder calculation
	 *
	 * For auto_renewal contracts: cancellation deadline (endDate minus cancellationPeriod)
	 * For fixed contracts: the end date itself (contract simply expires)
	 */
	public function getReminderDeadline(Contract $contract): ?DateTime {
		if ($contract->getContractType() === 'auto_renewal') {
			return $this->calculateCancellationDeadline($contract);
		}
		// Fixed: reminder is tied to the end date directly
		return $this->getEffectiveEndDate($contract);
	}

	/**
	 * Check if the first reminder should be sent for this contract
	 */
	public function shouldSendFirstReminder(Contract $contract): bool {
		if (!$this->isContractEligibleForReminder($contract)) {
			return false;
		}

		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return false;
		}

		// Get reminder days - contract override or global setting
		$reminderDays = $contract->getReminderDays() ?? $this->settingsService->getReminderDays1();
		$now = new DateTime();
		$reminderDate = clone $deadline;
		$reminderDate->modify("-{$reminderDays} days");

		// Check if we're within the first reminder window
		if ($now < $reminderDate) {
			return false; // Too early
		}
		if ($now > $deadline) {
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

		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return false;
		}

		// Final reminder uses the second setting (default: 3 days)
		$reminderDays = $this->settingsService->getReminderDays2();
		$now = new DateTime();
		$reminderDate = clone $deadline;
		$reminderDate->modify("-{$reminderDays} days");

		// Check if we're within the final reminder window
		if ($now < $reminderDate) {
			return false; // Too early
		}
		if ($now > $deadline) {
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
		// Use effective end date so auto-renewal contracts get new reminders per renewal period
		$effectiveEnd = $this->getEffectiveEndDate($contract);
		$endDateStr = $effectiveEnd?->format('Y-m-d') ?? 'unknown';
		$prefix = $contract->getContractType() === 'auto_renewal' ? 'cancellation' : 'expiry';
		return "{$prefix}_{$endDateStr}_{$type}";
	}

	/**
	 * Send all configured reminders for a contract
	 *
	 * @param Contract $contract The contract
	 * @param string $reminderType 'first' or 'final'
	 */
	private function sendReminders(Contract $contract, string $reminderType): void {
		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return;
		}

		$deadlineFormatted = $deadline->format('d.m.Y');
		$userId = $contract->getCreatedBy();
		$contractType = $contract->getContractType();

		// 1. Send Talk message if configured
		if ($this->talkService->isTalkAvailable() && $this->talkService->isTalkConfigured()) {
			try {
				$this->talkService->sendReminderMessage(
					$contract->getName(),
					$deadlineFormatted,
					$reminderType,
					$contractType
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
				$this->emailService->sendReminder($contract, $userId, $deadlineFormatted, $reminderType, $contractType);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send Email reminder: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
				]);
			}
		}
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
