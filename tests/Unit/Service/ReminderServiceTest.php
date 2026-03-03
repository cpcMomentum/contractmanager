<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderSentMapper;
use OCA\ContractManager\Service\EmailService;
use OCA\ContractManager\Service\ReminderService;
use OCA\ContractManager\Service\SettingsService;
use OCA\ContractManager\Service\TalkService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReminderServiceTest extends TestCase {

	private ContractMapper $contractMapper;
	private ReminderSentMapper $reminderSentMapper;
	private SettingsService $settingsService;
	private TalkService $talkService;
	private EmailService $emailService;
	private LoggerInterface $logger;
	private ReminderService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->reminderSentMapper = $this->createMock(ReminderSentMapper::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->talkService = $this->createMock(TalkService::class);
		$this->emailService = $this->createMock(EmailService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ReminderService(
			$this->contractMapper,
			$this->reminderSentMapper,
			$this->settingsService,
			$this->talkService,
			$this->emailService,
			$this->logger,
		);
	}

	// ========================================
	// calculateCancellationDeadline Tests
	// ========================================

	public function testCalculateCancellationDeadlineReturnsNullWithoutEndDate(): void {
		$contract = $this->createContract(null, '3 months');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertNull($result);
	}

	public function testCalculateCancellationDeadlineReturnsNullWithEmptyCancellationPeriod(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertNull($result);
	}

	public function testCalculateCancellationDeadlineReturnsNullWithInvalidFormat(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), 'invalid');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertNull($result);
	}

	public function testCalculateCancellationDeadlineWithDays(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '14 days');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-06-16', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineWithWeeks(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '2 weeks');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-06-16', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineWithMonths(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '3 months');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-03-30', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineWithMonthEdgeCase(): void {
		// March 31 - 1 month should be Feb 28 (or 29 in leap year)
		$contract = $this->createContract(new DateTime('2026-03-31'), '1 month');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		// February 2026 has 28 days
		$this->assertEquals('2026-02-28', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineWithYear(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 year');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2025-06-30', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineSingularUnit(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-05-30', $result->format('Y-m-d'));
	}

	// ========================================
	// shouldSendFirstReminder Tests
	// ========================================

	public function testShouldSendFirstReminderReturnsFalseForInactiveContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setReminderEnabled(1);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseWhenReminderDisabled(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(0);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseForArchivedContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(1);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseWhenAlreadySent(): void {
		$endDate = new DateTime();
		$endDate->modify('+30 days');
		$contract = $this->createContract($endDate, '1 month');
		$contract->setId(1);
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(0);

		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(true);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	// ========================================
	// shouldSendFinalReminder Tests
	// ========================================

	public function testShouldSendFinalReminderReturnsFalseForInactiveContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setReminderEnabled(1);

		$result = $this->service->shouldSendFinalReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFinalReminderReturnsFalseWhenAlreadySent(): void {
		$endDate = new DateTime();
		$endDate->modify('+10 days');
		$contract = $this->createContract($endDate, '14 days');
		$contract->setId(1);
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(0);

		$this->settingsService->method('getReminderDays2')->willReturn(3);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(true);

		$result = $this->service->shouldSendFinalReminder($contract);

		$this->assertFalse($result);
	}

	// ========================================
	// Legacy shouldSendReminder Test
	// ========================================

	public function testShouldSendReminderCombinesFirstAndFinal(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setReminderEnabled(1);

		$result = $this->service->shouldSendReminder($contract);

		$this->assertFalse($result);
	}

	// ========================================
	// getEffectiveEndDate Tests
	// ========================================

	public function testGetEffectiveEndDateForFixedContract(): void {
		$endDate = new DateTime('2026-06-30');
		$contract = $this->createContract($endDate, '3 months', 'fixed');

		$result = $this->service->getEffectiveEndDate($contract);

		$this->assertEquals('2026-06-30', $result->format('Y-m-d'));
	}

	public function testGetEffectiveEndDateForAutoRenewalInFuture(): void {
		$endDate = new DateTime('+6 months');
		$contract = $this->createContract($endDate, '3 months', 'auto_renewal', '12 months');

		$result = $this->service->getEffectiveEndDate($contract);

		$this->assertEquals($endDate->format('Y-m-d'), $result->format('Y-m-d'));
	}

	public function testGetEffectiveEndDateForAutoRenewalInPast(): void {
		// Contract started 2021-08-17, ends 2022-08-17, renews every 12 months
		$endDate = new DateTime('2022-08-17');
		$contract = $this->createContract($endDate, '3 months', 'auto_renewal', '12 months');

		$result = $this->service->getEffectiveEndDate($contract);

		// Must be in the future
		$this->assertGreaterThan(new DateTime(), $result);
		// Must be on the 17th of August
		$this->assertEquals('08-17', $result->format('m-d'));
	}

	public function testGetEffectiveEndDateWithoutRenewalPeriod(): void {
		$endDate = new DateTime('2024-01-01');
		$contract = $this->createContract($endDate, '1 month', 'auto_renewal');

		$result = $this->service->getEffectiveEndDate($contract);

		// Without renewal period, falls back to endDate
		$this->assertEquals('2024-01-01', $result->format('Y-m-d'));
	}

	public function testCalculateCancellationDeadlineAutoRenewal(): void {
		// End date in the past, auto_renewal with 12 months, cancellation 3 months
		$endDate = new DateTime('2022-08-17');
		$contract = $this->createContract($endDate, '3 months', 'auto_renewal', '12 months');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		// Deadline must be based on effective end date minus 3 months
		$effectiveEnd = $this->service->getEffectiveEndDate($contract);
		$expected = clone $effectiveEnd;
		$expected->modify('-3 month');
		$this->assertEquals($expected->format('Y-m-d'), $result->format('Y-m-d'));
	}

	public function testGetReminderTypeUsesEffectiveEndDate(): void {
		// Construct a scenario where we're in the reminder window:
		// effectiveEnd ~10 days from now, cancellationPeriod = 3 days -> deadline ~7 days from now
		// reminderDays = 14 -> reminderDate = deadline - 14 days = ~7 days ago -> we're in window
		$now = new DateTime();
		$effectiveTarget = clone $now;
		$effectiveTarget->modify('+10 days');

		// Set endDate to exactly 1 month before the target, so +1 month renewal lands on target
		$pastEnd = clone $effectiveTarget;
		$pastEnd->modify('-1 month');

		$contract = $this->createContract($pastEnd, '3 days', 'auto_renewal', '1 month');
		$contract->setId(1);

		$effectiveEnd = $this->service->getEffectiveEndDate($contract);
		$expectedType = 'cancellation_' . $effectiveEnd->format('Y-m-d') . '_first';

		$this->settingsService->method('getReminderDays1')->willReturn(14);

		// Verify hasBeenSent is called with the effective date in the reminder type
		$this->reminderSentMapper->expects($this->once())
			->method('hasBeenSent')
			->with(1, $expectedType)
			->willReturn(true);

		$this->service->shouldSendFirstReminder($contract);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Create a real Contract instance with the given properties.
	 * Uses real Entity objects instead of mocks because Nextcloud's Entity
	 * uses __call magic for getters/setters which PHPUnit cannot mock.
	 */
	private function createContract(
		?DateTime $endDate,
		string $cancellationPeriod,
		string $contractType = 'fixed',
		?string $renewalPeriod = null
	): Contract {
		$contract = new Contract();
		if ($endDate !== null) {
			$contract->setEndDate($endDate);
		}
		$contract->setCancellationPeriod($cancellationPeriod);
		$contract->setContractType($contractType);
		if ($renewalPeriod !== null) {
			$contract->setRenewalPeriod($renewalPeriod);
		}
		// Set sensible defaults
		$contract->setName('Test Contract');
		$contract->setVendor('Test Vendor');
		$contract->setCreatedBy('testuser');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(0);
		return $contract;
	}
}
