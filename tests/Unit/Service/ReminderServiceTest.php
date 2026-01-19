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
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReminderServiceTest extends TestCase {

	private ContractMapper $contractMapper;
	private ReminderSentMapper $reminderSentMapper;
	private INotificationManager $notificationManager;
	private SettingsService $settingsService;
	private TalkService $talkService;
	private EmailService $emailService;
	private LoggerInterface $logger;
	private ReminderService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->reminderSentMapper = $this->createMock(ReminderSentMapper::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->talkService = $this->createMock(TalkService::class);
		$this->emailService = $this->createMock(EmailService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ReminderService(
			$this->contractMapper,
			$this->reminderSentMapper,
			$this->notificationManager,
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
		$contract->method('getStatus')->willReturn(Contract::STATUS_CANCELLED);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(false);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseWhenReminderDisabled(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->method('getStatus')->willReturn(Contract::STATUS_ACTIVE);
		$contract->method('getReminderEnabled')->willReturn(false);
		$contract->method('getArchived')->willReturn(false);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseForArchivedContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->method('getStatus')->willReturn(Contract::STATUS_ACTIVE);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(true);

		$result = $this->service->shouldSendFirstReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseWhenAlreadySent(): void {
		// Set up contract with deadline in the past (within reminder window)
		$endDate = new DateTime();
		$endDate->modify('+30 days');
		$contract = $this->createContract($endDate, '1 month');
		$contract->method('getStatus')->willReturn(Contract::STATUS_ACTIVE);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(false);
		$contract->method('getReminderDays')->willReturn(null);
		$contract->method('getId')->willReturn(1);

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
		$contract->method('getStatus')->willReturn(Contract::STATUS_CANCELLED);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(false);

		$result = $this->service->shouldSendFinalReminder($contract);

		$this->assertFalse($result);
	}

	public function testShouldSendFinalReminderReturnsFalseWhenAlreadySent(): void {
		$endDate = new DateTime();
		$endDate->modify('+10 days');
		$contract = $this->createContract($endDate, '14 days');
		$contract->method('getStatus')->willReturn(Contract::STATUS_ACTIVE);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(false);
		$contract->method('getId')->willReturn(1);

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
		$contract->method('getStatus')->willReturn(Contract::STATUS_CANCELLED);
		$contract->method('getReminderEnabled')->willReturn(true);
		$contract->method('getArchived')->willReturn(false);

		$result = $this->service->shouldSendReminder($contract);

		$this->assertFalse($result);
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Create a mock contract with the given end date and cancellation period
	 */
	private function createContract(?DateTime $endDate, string $cancellationPeriod): Contract {
		$contract = $this->createMock(Contract::class);
		$contract->method('getEndDate')->willReturn($endDate);
		$contract->method('getCancellationPeriod')->willReturn($cancellationPeriod);
		return $contract;
	}
}
