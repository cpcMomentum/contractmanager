<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Db\ReminderSentMapper;
use OCA\ContractManager\Service\EmailService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ReminderService;
use OCA\ContractManager\Service\SettingsService;
use OCA\ContractManager\Service\TalkService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReminderServiceTest extends TestCase {

	private const TEST_USER = 'testuser';

	private ContractMapper $contractMapper;
	private ReminderSentMapper $reminderSentMapper;
	private SettingsService $settingsService;
	private TalkService $talkService;
	private EmailService $emailService;
	private LoggerInterface $logger;
	private PermissionService $permissionService;
	private ReminderOptOutMapper $optOutMapper;
	private ReminderService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->contractMapper = $this->createMock(ContractMapper::class);
		$this->reminderSentMapper = $this->createMock(ReminderSentMapper::class);
		$this->settingsService = $this->createMock(SettingsService::class);
		$this->talkService = $this->createMock(TalkService::class);
		$this->emailService = $this->createMock(EmailService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->optOutMapper = $this->createMock(ReminderOptOutMapper::class);

		// User reminder settings default to "no personal override" so the
		// effective lead time falls back to the per-contract/admin values.
		$this->settingsService->method('getUserReminderDays1')->willReturn(null);
		$this->settingsService->method('getUserReminderDays2')->willReturn(null);

		$this->service = new ReminderService(
			$this->contractMapper,
			$this->reminderSentMapper,
			$this->settingsService,
			$this->talkService,
			$this->emailService,
			$this->logger,
			$this->permissionService,
			$this->optOutMapper,
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

	// --- Charakterisierung vor #159 „Kündigen zum" (Ist-Verhalten festschreiben) ---

	/**
	 * Backend clamps a leap-day year subtraction to Feb 28 (29 Feb 2024 - 1 year
	 * → 2023-02-28). NOTE: the JS frontend does NOT clamp this and yields
	 * 2023-03-01 — a known FE/BE divergence documented for #159, pinned here so
	 * any future change is a conscious decision.
	 */
	public function testCalculateCancellationDeadlineYearLeapClampsToFeb28(): void {
		$contract = $this->createContract(new DateTime('2024-02-29'), '1 year');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2023-02-28', $result->format('Y-m-d'));
	}

	/**
	 * Volker's case (#159): a 1-month notice anchors the deadline to the same
	 * day-of-month (the 21st), one month earlier. The "Kündigen zum: zum
	 * Monatsende" feature will later turn this into 2026-10-31 WITHOUT changing
	 * this default ('normal') result. Uses a fixed contract for determinism;
	 * the month arithmetic is identical for auto_renewal.
	 */
	public function testCalculateCancellationDeadlineAnchorsToDayOfMonth(): void {
		$contract = $this->createContract(new DateTime('2026-11-21'), '1 month');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-10-21', $result->format('Y-m-d'));
	}

	/**
	 * auto_renewal rolls a long-past deadline forward until it is in the future,
	 * preserving the anchor day-of-month. Asserted via invariants (not an
	 * absolute date) because the backend reads the real current time.
	 */
	public function testCalculateCancellationDeadlineAutoRenewalRollsIntoFuture(): void {
		$contract = $this->createContract(new DateTime('2018-11-21'), '1 month', 'auto_renewal', '12 months');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$todayMidnight = (new DateTime('today'))->getTimestamp();
		$this->assertGreaterThanOrEqual($todayMidnight, $result->getTimestamp(), 'rolled deadline must not be in the past');
		$this->assertSame('21', $result->format('d'), 'anchor day-of-month is preserved');
	}

	/**
	 * Fixed contracts simply expire and do NOT roll: a past deadline is returned
	 * as-is (mirrors the frontend behavior).
	 */
	public function testCalculateCancellationDeadlineFixedDoesNotRoll(): void {
		$contract = $this->createContract(new DateTime('2020-06-30'), '90 days', 'fixed');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2020-04-01', $result->format('Y-m-d'));
	}

	// --- #159 „Kündigen zum: zum Monatsende" ---

	/**
	 * month_end snaps the deadline to the last calendar day of its month
	 * (Volker: 21.10 → 31.10). Default 'normal' stays 21.10 (covered above).
	 * Fixed contract for determinism — the snap is identical for auto_renewal.
	 */
	public function testCalculateCancellationDeadlineMonthEndSnapsToLastDay(): void {
		$contract = $this->createContract(new DateTime('2026-11-21'), '1 month');
		$contract->setCancellationDeadlineType(Contract::DEADLINE_TYPE_MONTH_END);

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-10-31', $result->format('Y-m-d'));
	}

	/**
	 * month_end on a leap February: 2026-03-10 − 1 month = 2026-02-10 → snap to
	 * 2026-02-28 (non-leap).
	 */
	public function testCalculateCancellationDeadlineMonthEndFebruary(): void {
		$contract = $this->createContract(new DateTime('2026-03-10'), '1 month');
		$contract->setCancellationDeadlineType(Contract::DEADLINE_TYPE_MONTH_END);

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-02-28', $result->format('Y-m-d'));
	}

	/**
	 * month_end with auto_renewal rolls into the future and always lands on the
	 * last day of the month. Invariants (backend reads the real current time).
	 */
	public function testCalculateCancellationDeadlineMonthEndAutoRenewalRolls(): void {
		$contract = $this->createContract(new DateTime('2018-11-21'), '1 month', 'auto_renewal', '12 months');
		$contract->setCancellationDeadlineType(Contract::DEADLINE_TYPE_MONTH_END);

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertGreaterThanOrEqual((new DateTime('today'))->getTimestamp(), $result->getTimestamp());
		$this->assertSame($result->format('t'), $result->format('d'), 'deadline must be the last day of its month');
	}

	/**
	 * #201 — month_end snap happens INSIDE the auto_renewal roll loop (PHP/cron
	 * counterpart of the JS test in periodUtils.test.js). With now=2026-06-15 the
	 * normal deadline (2026-06-10) is just past, but the month-end deadline
	 * (2026-06-30) is still upcoming this period — so it must NOT be rolled to the
	 * next year. A buggy "snap after the loop" would yield 2027-06-30.
	 * Deterministic via the injected clock.
	 */
	public function testCalculateCancellationDeadlineMonthEndDoesNotOverRoll(): void {
		$contract = $this->createContract(new DateTime('2026-07-10'), '1 month', 'auto_renewal', '12 months');
		$contract->setCancellationDeadlineType(Contract::DEADLINE_TYPE_MONTH_END);
		$now = new DateTime('2026-06-15');

		$result = $this->service->calculateCancellationDeadline($contract, $now);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-06-30', $result->format('Y-m-d'));
	}

	// ========================================
	// shouldSendFirstReminder Tests
	// ========================================

	public function testShouldSendFirstReminderReturnsFalseForInactiveContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setReminderEnabled(1);

		$result = $this->service->shouldSendFirstReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseWhenReminderDisabled(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(0);

		$result = $this->service->shouldSendFirstReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	public function testShouldSendFirstReminderReturnsFalseForArchivedContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(1);

		$result = $this->service->shouldSendFirstReminder($contract, self::TEST_USER);

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

		$result = $this->service->shouldSendFirstReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	// ========================================
	// shouldSendFinalReminder Tests
	// ========================================

	public function testShouldSendFinalReminderReturnsFalseForInactiveContract(): void {
		$contract = $this->createContract(new DateTime('2026-06-30'), '1 month');
		$contract->setStatus(Contract::STATUS_CANCELLED);
		$contract->setReminderEnabled(1);

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

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

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	// ========================================
	// shouldSendFinalReminder — contract override Tests
	// ========================================

	/**
	 * Regression: Issue #116 — Final reminder ignoriert contract-spezifisches reminderDays-Override
	 *
	 * Wenn ein Vertrag ein Override hat das <= days2 ist, würde die finale Erinnerung
	 * vor (oder gleichzeitig mit) der ersten feuern — das ist redundant und verwirrend.
	 */
	public function testShouldSendFinalReminderReturnsFalseWhenOverrideLeqDays2(): void {
		// Contract override = 2 Tage, global days2 = 3 → final würde vor first feuern → unterdrücken
		$endDate = new DateTime('+2 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);
		$contract->setReminderDays(2);

		$this->settingsService->method('getReminderDays2')->willReturn(3);

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	public function testShouldSendFinalReminderReturnsFalseWhenOverrideEqualsDays2(): void {
		// Override exakt gleich days2 → ebenfalls unterdrücken
		$endDate = new DateTime('+3 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);
		$contract->setReminderDays(3);

		$this->settingsService->method('getReminderDays2')->willReturn(3);

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

		$this->assertFalse($result);
	}

	public function testShouldSendFinalReminderFiresNormallyWhenOverrideGreaterThanDays2(): void {
		// Override = 30 Tage, global days2 = 3 → final feuert ganz normal 3 Tage vorher
		$endDate = new DateTime('+2 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);
		$contract->setReminderDays(30);

		$this->settingsService->method('getReminderDays2')->willReturn(3);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

		$this->assertTrue($result);
	}

	public function testShouldSendFinalReminderFiresNormallyWithoutOverride(): void {
		// Kein Override → globaler days1/days2 gelten unverändert
		$endDate = new DateTime('+2 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);
		// reminderDays bleibt null (kein Override)

		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->settingsService->method('getReminderDays2')->willReturn(3);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);

		$result = $this->service->shouldSendFinalReminder($contract, self::TEST_USER);

		$this->assertTrue($result);
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
		// Deterministic via injected clock (#201): end on the 1st in the past,
		// auto_renewal 12 months, 3-month notice. With now=2026-06-15 the effective
		// end rolls to 2026-11-01, so the deadline is 2026-08-01. The expected value
		// is a fixed literal — independent of the service's own date math, so a
		// symmetric bug cannot hide.
		$contract = $this->createContract(new DateTime('2018-11-01'), '3 months', 'auto_renewal', '12 months');
		$now = new DateTime('2026-06-15');

		$result = $this->service->calculateCancellationDeadline($contract, $now);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-08-01', $result->format('Y-m-d'));
	}

	/**
	 * Regression: Issue #80 — Falsches Kündigungsdatum
	 * Wenn die Kündigungsfrist bereits abgelaufen ist, muss das Datum
	 * auf die nächste Verlängerungsperiode vorspringen.
	 */
	public function testCancellationDeadlineMustBeInFutureForAutoRenewal(): void {
		// Contract: endDate weit in der Vergangenheit, renewal 12 months, cancellation 3 months
		// Effective end date wird in die Zukunft gerollt, aber die Kündigungsfrist
		// könnte trotzdem in der Vergangenheit liegen.
		// Wir konstruieren einen Fall wo effectiveEnd < 3 Monate in der Zukunft liegt,
		// sodass deadline = effectiveEnd - 3 months in der Vergangenheit liegt.
		$now = new DateTime();

		// End date so setzen, dass effectiveEnd ca. 2 Monate in der Zukunft liegt
		// -> Kündigungsfrist 3 Monate -> Deadline 1 Monat in der Vergangenheit
		$effectiveTarget = clone $now;
		$effectiveTarget->modify('+2 months');

		// endDate = effectiveTarget - 12 months (eine Verlängerungsperiode zurück)
		$endDate = clone $effectiveTarget;
		$endDate->modify('-12 months');

		$contract = $this->createContract($endDate, '3 months', 'auto_renewal', '12 months');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		// KERN-ASSERTION: Kündigungsdatum MUSS in der Zukunft liegen
		$this->assertGreaterThan(
			$now,
			$result,
			'Cancellation deadline must be in the future for auto_renewal contracts, '
			. 'but got ' . $result->format('Y-m-d') . ' (today: ' . $now->format('Y-m-d') . ')'
		);
	}

	/**
	 * Regression: Issue #80 — Fixed contracts dürfen vergangene Deadlines haben
	 */
	public function testCancellationDeadlineCanBePastForFixedContract(): void {
		// Bei fixed contracts ist ein vergangenes Kündigungsdatum korrekt —
		// der Vertrag erneuert sich nicht, die Frist ist einfach abgelaufen.
		$endDate = new DateTime('+1 month');
		$contract = $this->createContract($endDate, '3 months', 'fixed');

		$result = $this->service->calculateCancellationDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		// Deadline liegt 2 Monate in der Vergangenheit — das ist OK bei fixed
		$this->assertLessThan(new DateTime(), $result);
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
			->with(1, $expectedType, self::TEST_USER)
			->willReturn(true);

		$this->service->shouldSendFirstReminder($contract, self::TEST_USER);
	}

	// ========================================
	// getReminderDeadline Tests
	// ========================================

	public function testGetReminderDeadlineForFixedReturnsEndDate(): void {
		$endDate = new DateTime('2026-12-31');
		$contract = $this->createContract($endDate, '', 'fixed');

		$result = $this->service->getReminderDeadline($contract);

		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals('2026-12-31', $result->format('Y-m-d'));
	}

	public function testGetReminderDeadlineForAutoRenewalReturnsCancellationDeadline(): void {
		$endDate = new DateTime('+6 months');
		$contract = $this->createContract($endDate, '3 months', 'auto_renewal', '12 months');

		$result = $this->service->getReminderDeadline($contract);

		// Should be endDate minus 3 months (cancellation deadline)
		$expected = clone $endDate;
		$expected->modify('-3 months');
		$this->assertInstanceOf(DateTime::class, $result);
		$this->assertEquals($expected->format('Y-m-d'), $result->format('Y-m-d'));
	}

	public function testShouldSendFirstReminderForFixedWithoutCancellationPeriod(): void {
		// Fixed contract without cancellation period, end date 5 days from now
		$endDate = new DateTime();
		$endDate->modify('+5 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);

		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);

		$result = $this->service->shouldSendFirstReminder($contract, self::TEST_USER);

		// Should return true: we're within 14 days of end date
		$this->assertTrue($result);
	}

	public function testGetReminderTypeUsesExpiryPrefixForFixed(): void {
		$endDate = new DateTime();
		$endDate->modify('+5 days');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);

		$this->settingsService->method('getReminderDays1')->willReturn(14);

		$expectedType = 'expiry_' . $endDate->format('Y-m-d') . '_first';

		$this->reminderSentMapper->expects($this->once())
			->method('hasBeenSent')
			->with(1, $expectedType, self::TEST_USER)
			->willReturn(true);

		$this->service->shouldSendFirstReminder($contract, self::TEST_USER);
	}

	// ========================================
	// checkAndSendReminders Tests
	// ========================================

	/**
	 * Regression: Issue #111 — Doppelte Mail wenn beide Reminder-Fenster gleichzeitig aktiv
	 *
	 * Wenn ein User Benachrichtigungen aktiviert und der Vertrag bereits im Final-Fenster
	 * liegt, darf nur eine Mail rausgehen (final), nicht zwei (first + final).
	 */
	public function testCheckAndSendRemindersOnlySendsFinalWhenBothWindowsActive(): void {
		// Deadline in 2 Tagen → liegt im first-Fenster (14 Tage) UND final-Fenster (3 Tage)
		$deadline = new DateTime('+2 days');
		// auto_renewal mit cancellationPeriod = 0 days → deadline = endDate
		$contract = new \OCA\ContractManager\Db\Contract();
		$contract->setId(42);
		$contract->setName('Doppelmail-Test');
		$contract->setVendor('Test');
		$contract->setCreatedBy('testuser');
		$contract->setStatus(\OCA\ContractManager\Db\Contract::STATUS_ACTIVE);
		$contract->setReminderEnabled(1);
		$contract->setArchived(0);
		$contract->setEndDate($deadline);
		$contract->setCancellationPeriod('');
		$contract->setContractType('fixed');

		$this->contractMapper->method('findContractsNeedingReminder')->willReturn([$contract]);
		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->settingsService->method('getReminderDays2')->willReturn(3);
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_OWN);
		$this->settingsService->method('getUserEmailReminder')->willReturn(true);
		$this->settingsService->method('getUserTalkChatToken')->willReturn(null);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['testuser']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);
		$this->reminderSentMapper->method('insert')->willReturn(new \OCA\ContractManager\Db\ReminderSent());
		$this->emailService->method('sendReminder')->willReturn(true);
		$this->talkService->method('isTalkAvailable')->willReturn(false);

		// KERN-ASSERTION: emailService darf genau 1x aufgerufen werden, nicht 2x
		$this->emailService->expects($this->exactly(1))
			->method('sendReminder');

		$this->service->checkAndSendReminders();
	}

	/**
	 * #157: Reminders go to every eligible recipient, not just the creator.
	 * A non-creator with mode "all" must receive the reminder; a user who
	 * cannot see the contract must not.
	 */
	public function testCheckAndSendRemindersDeliversToNonCreatorWithModeAll(): void {
		$deadline = new DateTime('+2 days');
		$contract = $this->createContract($deadline, '', 'fixed');
		$contract->setId(7);
		$contract->setCreatedBy('alice');

		$this->contractMapper->method('findContractsNeedingReminder')->willReturn([$contract]);
		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->settingsService->method('getReminderDays2')->willReturn(3);
		// alice = creator (own), bob = non-creator with mode all, carol cannot see it
		$this->settingsService->method('getUserReminderMode')->willReturnMap([
			['alice', SettingsService::REMINDER_MODE_OWN],
			['bob', SettingsService::REMINDER_MODE_ALL],
			['carol', SettingsService::REMINDER_MODE_ALL],
		]);
		$this->settingsService->method('getUserEmailReminder')->willReturn(true);
		$this->settingsService->method('getUserTalkChatToken')->willReturn(null);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['alice', 'bob', 'carol']);
		$this->permissionService->method('canUserSeeContract')->willReturnMap([
			[$contract, 'alice', true],
			[$contract, 'bob', true],
			[$contract, 'carol', false],
		]);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);
		$this->reminderSentMapper->method('insert')->willReturn(new \OCA\ContractManager\Db\ReminderSent());
		$this->talkService->method('isTalkAvailable')->willReturn(false);

		// alice (own/creator) + bob (all, can see) = 2 recipients; carol excluded (cannot see)
		$this->emailService->expects($this->exactly(2))
			->method('sendReminder')
			->willReturn(true);

		$this->service->checkAndSendReminders();
	}

	/**
	 * #157: A recipient who opted out of a specific contract is skipped even
	 * though their mode would otherwise include it.
	 */
	public function testCheckAndSendRemindersRespectsOptOut(): void {
		$deadline = new DateTime('+2 days');
		$contract = $this->createContract($deadline, '', 'fixed');
		$contract->setId(8);
		$contract->setCreatedBy('alice');

		$this->contractMapper->method('findContractsNeedingReminder')->willReturn([$contract]);
		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->settingsService->method('getReminderDays2')->willReturn(3);
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_ALL);
		$this->settingsService->method('getUserEmailReminder')->willReturn(true);
		$this->settingsService->method('getUserTalkChatToken')->willReturn(null);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['alice', 'bob']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		// bob opted out of this contract
		$this->optOutMapper->method('findOptedOutUsers')->willReturn(['bob']);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);
		$this->reminderSentMapper->method('insert')->willReturn(new \OCA\ContractManager\Db\ReminderSent());
		$this->talkService->method('isTalkAvailable')->willReturn(false);

		// only alice receives it, bob opted out
		$this->emailService->expects($this->exactly(1))
			->method('sendReminder')
			->willReturn(true);

		$this->service->checkAndSendReminders();
	}

	/**
	 * #174: "own" counts on the effective owner (responsible user), not the
	 * creator. A contract created by alice but assigned to bob reminds bob
	 * (mode own), not alice (mode own).
	 */
	public function testCheckAndSendRemindersOwnCountsOnResponsibleUser(): void {
		$deadline = new DateTime('+2 days');
		$contract = $this->createContract($deadline, '', 'fixed');
		$contract->setId(9);
		$contract->setCreatedBy('alice');
		$contract->setResponsibleUser('bob');

		$this->contractMapper->method('findContractsNeedingReminder')->willReturn([$contract]);
		$this->settingsService->method('getReminderDays1')->willReturn(14);
		$this->settingsService->method('getReminderDays2')->willReturn(3);
		// both on "own" — only the effective owner (bob) should receive it
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_OWN);
		$this->settingsService->method('getUserEmailReminder')->willReturn(true);
		$this->settingsService->method('getUserTalkChatToken')->willReturn(null);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['alice', 'bob']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);
		$this->reminderSentMapper->method('hasBeenSent')->willReturn(false);
		$this->reminderSentMapper->method('insert')->willReturn(new \OCA\ContractManager\Db\ReminderSent());
		$this->talkService->method('isTalkAvailable')->willReturn(false);

		// only bob (responsible) receives it, not alice (creator)
		$this->emailService->expects($this->exactly(1))
			->method('sendReminder')
			->with($this->anything(), 'bob', $this->anything(), $this->anything(), $this->anything())
			->willReturn(true);

		$this->service->checkAndSendReminders();
	}

	// ========================================
	// Helper Methods
	// ========================================

	/**
	 * Create a real Contract instance with the given properties.
	 * Uses real Entity objects instead of mocks because Nextcloud's Entity
	 * uses __call magic for getters/setters which PHPUnit cannot mock.
	 */

	// ========================================
	// getUpcomingDeadlinesForUser (#68 — calendar feed)
	// ========================================

	public function testGetUpcomingDeadlinesReturnsEligibleContractWithDeadline(): void {
		$endDate = new DateTime('2026-12-01');
		$contract = $this->createContract($endDate, '', 'fixed');
		$contract->setId(1);

		$this->contractMapper->method('findByStatus')->with(Contract::STATUS_ACTIVE)->willReturn([$contract]);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['testuser']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_ALL);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);

		$result = $this->service->getUpcomingDeadlinesForUser('testuser');

		$this->assertCount(1, $result);
		$this->assertSame($contract, $result[0]['contract']);
		// Fixed contract: the deadline is the end date itself.
		$this->assertEquals($endDate, $result[0]['deadline']);
	}

	public function testGetUpcomingDeadlinesExcludesContractWithRemindersDisabled(): void {
		$contract = $this->createContract(new DateTime('2026-12-01'), '', 'fixed');
		$contract->setId(1);
		$contract->setReminderEnabled(0);

		$this->contractMapper->method('findByStatus')->willReturn([$contract]);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['testuser']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_ALL);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);

		$this->assertSame([], $this->service->getUpcomingDeadlinesForUser('testuser'));
	}

	public function testGetUpcomingDeadlinesExcludesUserWithModeNone(): void {
		$contract = $this->createContract(new DateTime('2026-12-01'), '', 'fixed');
		$contract->setId(1);

		$this->contractMapper->method('findByStatus')->willReturn([$contract]);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['testuser']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		// The user switched reminders off entirely — the feed is empty for them.
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_NONE);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);

		$this->assertSame([], $this->service->getUpcomingDeadlinesForUser('testuser'));
	}

	public function testGetUpcomingDeadlinesExcludesAlreadyPassedFixedDeadline(): void {
		// A fixed contract whose end date is in the past but that nobody has
		// archived yet must not appear — the notification path never fires for
		// it either (shouldSendFirstReminder/shouldSendFinalReminder both bail
		// out once "now > deadline"), so the feed must match that.
		$contract = $this->createContract(new DateTime('2020-01-01'), '', 'fixed');
		$contract->setId(1);

		$this->contractMapper->method('findByStatus')->willReturn([$contract]);
		$this->permissionService->method('getAllUsersWithAccess')->willReturn(['testuser']);
		$this->permissionService->method('canUserSeeContract')->willReturn(true);
		$this->settingsService->method('getUserReminderMode')->willReturn(SettingsService::REMINDER_MODE_ALL);
		$this->optOutMapper->method('findOptedOutUsers')->willReturn([]);

		$this->assertSame([], $this->service->getUpcomingDeadlinesForUser('testuser'));
	}

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
