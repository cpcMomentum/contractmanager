<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
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
 * Reminders are delivered per recipient: every user who may see the contract
 * (subject to their personal mode and per-contract opt-out) is reminded via
 * their own channels (e-mail and/or their personal Talk chat), using their own
 * lead time. See docs/design-reminder-recipients.md (#157 + #172).
 */
class ReminderService {

	public function __construct(
		private ContractMapper $contractMapper,
		private ReminderSentMapper $reminderSentMapper,
		private SettingsService $settingsService,
		private TalkService $talkService,
		private EmailService $emailService,
		private LoggerInterface $logger,
		private PermissionService $permissionService,
		private ReminderOptOutMapper $optOutMapper,
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
		$accessUsers = $this->permissionService->getAllUsersWithAccess();

		foreach ($contracts as $contract) {
			if (!$this->isContractEligibleForReminder($contract)) {
				continue;
			}
			if ($this->getReminderDeadline($contract) === null) {
				continue;
			}

			foreach ($this->getRecipients($contract, $accessUsers) as $userId) {
				// Pre-compute to avoid double DB query when both windows are active
				$sendFinal = $this->shouldSendFinalReminder($contract, $userId);

				// Check for first reminder — skip if final reminder is also due to avoid two emails on the same day
				if ($this->shouldSendFirstReminder($contract, $userId) && !$sendFinal) {
					if ($this->deliverReminder($contract, $userId, 'first')) {
						$remindersSent++;
					}
				}

				// Check for final reminder
				if ($sendFinal && $this->deliverReminder($contract, $userId, 'final')) {
					$remindersSent++;
				}
			}
		}

		return $remindersSent;
	}

	/**
	 * Deliver a reminder to a single recipient and mark it sent.
	 *
	 * @return bool True if at least one channel delivered the reminder
	 */
	private function deliverReminder(Contract $contract, string $userId, string $type): bool {
		try {
			$delivered = $this->sendReminders($contract, $userId, $type);
			if ($delivered) {
				$this->markReminderSent($contract, $userId, $type);
				$this->logger->debug('Sent cancellation reminder', [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
					'type' => $type,
				]);
			}
			return $delivered;
		} catch (\Exception $e) {
			$this->logger->error('Failed to send reminder: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'contractId' => $contract->getId(),
				'type' => $type,
				'exception' => $e,
			]);
			return false;
		}
	}

	/**
	 * Determine which users should receive reminders for a contract.
	 *
	 * Single rule: a user is a recipient if they may see the contract,
	 * their mode includes it, and they have not opted out.
	 *
	 * @param string[] $accessUsers Users with app access (admins + editors + viewers)
	 * @return string[] Recipient user IDs
	 */
	private function getRecipients(Contract $contract, array $accessUsers): array {
		// The creator is always a candidate, even if they currently lack a role.
		$candidates = array_unique([...$accessUsers, $contract->getCreatedBy()]);
		$optedOut = array_flip($this->optOutMapper->findOptedOutUsers($contract->getId()));

		$recipients = [];
		foreach ($candidates as $userId) {
			if ($userId === '' || isset($optedOut[$userId])) {
				continue;
			}
			if (!$this->permissionService->canUserSeeContract($contract, $userId)) {
				continue;
			}
			$mode = $this->settingsService->getUserReminderMode($userId);
			if ($mode === SettingsService::REMINDER_MODE_NONE) {
				continue;
			}
			if ($mode === SettingsService::REMINDER_MODE_OWN && $contract->getCreatedBy() !== $userId) {
				continue;
			}
			$recipients[] = $userId;
		}

		return $recipients;
	}

	/**
	 * Effective first-reminder lead time for a user:
	 * personal setting > per-contract override > admin default.
	 */
	private function getEffectiveDays1(Contract $contract, string $userId): int {
		$personal = $this->settingsService->getUserReminderDays1($userId);
		if ($personal !== null) {
			return $personal;
		}
		$override = $contract->getReminderDays();
		if ($override !== null) {
			return $override;
		}
		return $this->settingsService->getReminderDays1();
	}

	/**
	 * Effective final-reminder lead time for a user:
	 * personal setting > admin default. (No per-contract override for the final reminder.)
	 */
	private function getEffectiveDays2(string $userId): int {
		$personal = $this->settingsService->getUserReminderDays2($userId);
		return $personal ?? $this->settingsService->getReminderDays2();
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

		// Calculate number of periods needed mathematically (O(1) instead of O(n))
		$diffDays = (int) $now->diff($effective)->format('%a');
		$periodsNeeded = 0;

		if ($unit === 'day') {
			$periodsNeeded = (int) ceil($diffDays / $value);
		} elseif ($unit === 'week') {
			$periodsNeeded = (int) ceil($diffDays / ($value * 7));
		} elseif ($unit === 'month') {
			$periodsNeeded = (int) ceil($diffDays / ($value * 30.44));
		} elseif ($unit === 'year') {
			$periodsNeeded = (int) ceil($diffDays / ($value * 365.25));
		}

		// Jump directly to the estimated period
		$totalValue = $value * $periodsNeeded;
		$effective->modify("+{$totalValue} {$unit}");

		// Adjust if estimate was slightly off (max 3 iterations for month-length variance)
		while ($effective <= $now) {
			$effective->modify("+{$value} {$unit}");
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

		$deadline = $this->subtractPeriodFromDate(clone $endDate, $value, $unit);

		// For auto_renewal: if cancellation deadline is past, the contract will
		// renew — advance to the next period where the deadline is in the future.
		$contractType = $contract->getContractType();
		$renewalPeriod = $contract->getRenewalPeriod();
		if ($contractType === 'auto_renewal' && !empty($renewalPeriod)) {
			$now = new DateTime();
			while ($deadline < $now) {
				$endDate = $this->addPeriodToDate($endDate, $renewalPeriod);
				if ($endDate === null) {
					break;
				}
				$deadline = $this->subtractPeriodFromDate(clone $endDate, $value, $unit);
			}
		}

		return $deadline;
	}

	/**
	 * Subtract a parsed period (value + unit) from a date with month-end safety.
	 */
	private function subtractPeriodFromDate(DateTime $date, int $value, string $unit): DateTime {
		if ($unit === 'month') {
			$originalDay = (int) $date->format('d');
			$date->modify("-{$value} month");
			$newDay = (int) $date->format('d');
			if ($newDay > $originalDay || ($originalDay > 28 && $newDay < $originalDay)) {
				$date->modify('last day of previous month');
			}
		} elseif ($unit === 'year') {
			// Same leap-year safety as months: 2024-02-29 - 1 year → 2023-02-28 not 2023-03-01
			$originalDay = (int) $date->format('d');
			$date->modify("-{$value} year");
			$newDay = (int) $date->format('d');
			if ($originalDay > 28 && $newDay < $originalDay) {
				$date->modify('last day of previous month');
			}
		} elseif ($unit === 'week') {
			$date->modify("-{$value} week");
		} else {
			$date->modify("-{$value} day");
		}
		return $date;
	}

	/**
	 * Add a renewal period string (e.g. "12 months") to a date.
	 */
	private function addPeriodToDate(DateTime $date, string $periodString): ?DateTime {
		if (!preg_match('/^(\d+)\s+(day|days|week|weeks|month|months|year|years)$/i', trim($periodString), $matches)) {
			return null;
		}
		$val = (int) $matches[1];
		$u = rtrim(strtolower($matches[2]), 's');
		$result = clone $date;
		$result->modify("+{$val} {$u}");
		return $result;
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
	 * Check if the first reminder should be sent to a recipient for this contract
	 */
	public function shouldSendFirstReminder(Contract $contract, string $userId): bool {
		if (!$this->isContractEligibleForReminder($contract)) {
			return false;
		}

		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return false;
		}

		// Effective lead time: personal > contract override > admin default
		$reminderDays = $this->getEffectiveDays1($contract, $userId);
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

		// Check if first reminder was already sent to this recipient
		$reminderType = $this->getReminderType($contract, 'first');
		if ($this->reminderSentMapper->hasBeenSent($contract->getId(), $reminderType, $userId)) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the final reminder should be sent to a recipient for this contract
	 */
	public function shouldSendFinalReminder(Contract $contract, string $userId): bool {
		if (!$this->isContractEligibleForReminder($contract)) {
			return false;
		}

		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return false;
		}

		$days1 = $this->getEffectiveDays1($contract, $userId);
		$reminderDays = $this->getEffectiveDays2($userId);

		// If the effective first reminder fires at the same time or later than the
		// final reminder window, suppress the final reminder — it would be redundant
		// and confusing. Example: per-contract override = 2 days, days2 = 3 days.
		if ($days1 <= $reminderDays) {
			return false;
		}

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

		// Check if final reminder was already sent to this recipient
		$reminderType = $this->getReminderType($contract, 'final');
		if ($this->reminderSentMapper->hasBeenSent($contract->getId(), $reminderType, $userId)) {
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
	 * Send the reminder for a contract to a single recipient via their channels.
	 *
	 * @param Contract $contract The contract
	 * @param string $userId The recipient
	 * @param string $reminderType 'first' or 'final'
	 * @return bool True if at least one channel delivered the reminder
	 */
	private function sendReminders(Contract $contract, string $userId, string $reminderType): bool {
		$deadline = $this->getReminderDeadline($contract);
		if ($deadline === null) {
			return false;
		}

		$deadlineFormatted = $deadline->format('d.m.Y');
		$contractType = $contract->getContractType();
		$delivered = false;

		// 1. Send to the user's personal Talk chat if they configured one
		$talkToken = $this->settingsService->getUserTalkChatToken($userId);
		if ($talkToken !== null && $this->talkService->isTalkAvailable()) {
			try {
				if ($this->talkService->sendReminderMessage($talkToken, $contract->getName(), $deadlineFormatted, $reminderType, $contractType)) {
					$delivered = true;
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send Talk reminder: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
				]);
			}
		}

		// 2. Send E-Mail if the user has enabled it
		if ($this->settingsService->getUserEmailReminder($userId)) {
			try {
				if ($this->emailService->sendReminder($contract, $userId, $deadlineFormatted, $reminderType, $contractType)) {
					$delivered = true;
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to send Email reminder: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
					'contractId' => $contract->getId(),
				]);
			}
		}

		return $delivered;
	}

	/**
	 * Mark a reminder as sent to a specific recipient
	 *
	 * @param Contract $contract The contract
	 * @param string $userId The recipient
	 * @param string $type 'first' or 'final'
	 */
	private function markReminderSent(Contract $contract, string $userId, string $type): void {
		$reminder = new ReminderSent();
		$reminder->setContractId($contract->getId());
		$reminder->setReminderType($this->getReminderType($contract, $type));
		$reminder->setSentAt(new DateTime());
		$reminder->setSentTo($userId);

		$this->reminderSentMapper->insert($reminder);
	}
}
