<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;

/** PHP twin of the list's cost, status and date rules; tests/fixtures/contract-figures.json keeps both equal. */
class ContractFigures {

	/** Payments per month on average; a week is 52/12, not 4. */
	private const MONTHLY_FACTOR = [
		Contract::INTERVAL_WEEKLY => 52 / 12,
		Contract::INTERVAL_MONTHLY => 1,
		Contract::INTERVAL_QUARTERLY => 1 / 3,
		Contract::INTERVAL_SEMI_ANNUAL => 1 / 6,
		Contract::INTERVAL_YEARLY => 1 / 12,
	];

	public function __construct(
		private ReminderService $reminderService,
	) {
	}

	public static function hasMonthlyEquivalent(?string $interval): bool {
		return $interval !== null && array_key_exists($interval, self::MONTHLY_FACTOR);
	}

	public static function monthlyCost(float $cost, ?string $interval): ?float {
		if (!self::hasMonthlyEquivalent($interval)) {
			return null;
		}
		return $cost * self::MONTHLY_FACTOR[$interval];
	}

	public static function cost(Contract $contract): ?float {
		$cost = $contract->getCost();
		if ($cost === null || trim($cost) === '' || !is_numeric($cost)) {
			return null;
		}
		return (float)$cost;
	}

	private static function isCancelledOrEnded(Contract $contract): bool {
		return in_array($contract->getStatus(), [Contract::STATUS_CANCELLED, Contract::STATUS_ENDED], true);
	}

	public function runsUntil(Contract $contract, DateTime $today): ?DateTime {
		$end = $contract->getEndDate();
		if ($end === null) {
			return null;
		}
		if (self::isCancelledOrEnded($contract)) {
			$to = $contract->getCancelledTo();
			return clone ($to ?? $end);
		}
		return $this->reminderService->getEffectiveEndDate($contract, $today);
	}

	public function nextCancellationDeadline(Contract $contract, DateTime $today): ?DateTime {
		if (self::isCancelledOrEnded($contract) || $contract->getContractType() !== Contract::TYPE_AUTO_RENEWAL) {
			return null;
		}
		if ($contract->getEndDate() === null || empty($contract->getCancellationPeriod())) {
			return null;
		}
		return $this->reminderService->calculateCancellationDeadline($contract, $today);
	}

	// Check order decides ties, as in getDisplayStatus().
	public function displayStatus(Contract $contract, DateTime $today, int $defaultReminderDays): string {
		if ($this->isPlanned($contract, $today)) {
			return 'planned';
		}
		if ($this->isExpiredFixed($contract, $today)) {
			return 'ended';
		}
		if ($this->isEndingSoon($contract, $today, $defaultReminderDays)) {
			return 'ending_soon';
		}
		if ($this->isEndingSoonFixed($contract, $today, $defaultReminderDays)) {
			return 'ending';
		}
		if ($this->isEndedCancelled($contract, $today)) {
			return 'ended';
		}
		if ($contract->getStatus() === Contract::STATUS_CANCELLED) {
			return 'cancelled';
		}
		if ($contract->getStatus() === Contract::STATUS_ENDED) {
			return 'ended';
		}
		return 'active';
	}

	// Same rule as the list's "Monatliche Kosten" figure.
	public function isRunning(Contract $contract, DateTime $today): bool {
		if ($contract->getStatus() === Contract::STATUS_ENDED || $contract->getArchived() === 1) {
			return false;
		}
		if ($this->isPlanned($contract, $today)) {
			return false;
		}
		$end = $this->runsUntil($contract, $today);
		return $end === null || self::day($end) >= $today;
	}

	private function isPlanned(Contract $contract, DateTime $today): bool {
		if ($contract->getStatus() !== Contract::STATUS_ACTIVE) {
			return false;
		}
		$start = $contract->getStartDate();
		return $start !== null && self::day($start) > $today;
	}

	private function isExpiredFixed(Contract $contract, DateTime $today): bool {
		if ($contract->getStatus() !== Contract::STATUS_ACTIVE || $contract->getContractType() !== Contract::TYPE_FIXED) {
			return false;
		}
		$end = $contract->getEndDate();
		return $end !== null && self::day($end) < $today;
	}

	private function isEndingSoon(Contract $contract, DateTime $today, int $defaultReminderDays): bool {
		if ($contract->getStatus() !== Contract::STATUS_ACTIVE || $contract->getContractType() !== Contract::TYPE_AUTO_RENEWAL) {
			return false;
		}
		$deadline = $this->nextCancellationDeadline($contract, $today);
		if ($deadline === null) {
			return false;
		}
		$deadline = self::day($deadline);
		$windowStart = (clone $deadline)->modify('-' . ($contract->getReminderDays() ?? $defaultReminderDays) . ' days');
		return $today >= $windowStart && $today <= $deadline;
	}

	private function isEndingSoonFixed(Contract $contract, DateTime $today, int $defaultReminderDays): bool {
		if ($contract->getStatus() !== Contract::STATUS_ACTIVE || $contract->getContractType() !== Contract::TYPE_FIXED) {
			return false;
		}
		$end = $contract->getEndDate();
		if ($end === null) {
			return false;
		}
		$end = self::day($end);
		$windowStart = (clone $end)->modify('-' . ($contract->getReminderDays() ?? $defaultReminderDays) . ' days');
		return $today >= $windowStart && $today <= $end;
	}

	private function isEndedCancelled(Contract $contract, DateTime $today): bool {
		if ($contract->getStatus() !== Contract::STATUS_CANCELLED || $contract->getCancelledOn() === null) {
			return false;
		}
		$end = $contract->getCancelledTo() ?? $contract->getEndDate();
		return $end !== null && self::day($end) <= $today;
	}

	public static function day(DateTime $date): DateTime {
		return new DateTime($date->format('Y-m-d'));
	}
}
