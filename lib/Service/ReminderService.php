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

class ReminderService {

    public function __construct(
        private ContractMapper $contractMapper,
        private ReminderSentMapper $reminderSentMapper,
        private INotificationManager $notificationManager,
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
            if ($this->shouldSendReminder($contract)) {
                try {
                    $this->sendNotification($contract);
                    $this->markReminderSent($contract);
                    $remindersSent++;
                    $this->logger->info('Sent cancellation reminder for contract: ' . $contract->getName(), [
                        'app' => Application::APP_ID,
                        'contractId' => $contract->getId(),
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to send reminder for contract: ' . $contract->getName(), [
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
     * Check if a reminder should be sent for this contract
     */
    public function shouldSendReminder(Contract $contract): bool {
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

        $cancellationDeadline = $this->calculateCancellationDeadline($contract);
        if ($cancellationDeadline === null) {
            return false;
        }

        $reminderDays = $contract->getReminderDays() ?? 14; // Default 14 days
        $now = new DateTime();
        $reminderDate = clone $cancellationDeadline;
        $reminderDate->modify("-{$reminderDays} days");

        // Check if we're within the reminder window (reminder date has passed, but deadline hasn't)
        if ($now < $reminderDate) {
            return false; // Too early
        }
        if ($now > $cancellationDeadline) {
            return false; // Too late, deadline passed
        }

        // Check if reminder was already sent
        $reminderType = $this->getReminderType($contract);
        if ($this->reminderSentMapper->hasBeenSent($contract->getId(), $reminderType)) {
            return false;
        }

        return true;
    }

    /**
     * Get a unique reminder type identifier for this contract/period
     */
    private function getReminderType(Contract $contract): string {
        // Include end date in type so if contract is renewed, a new reminder can be sent
        $endDateStr = $contract->getEndDate()?->format('Y-m-d') ?? 'unknown';
        return "cancellation_{$endDateStr}";
    }

    /**
     * Send a notification to the contract owner
     */
    public function sendNotification(Contract $contract): void {
        $cancellationDeadline = $this->calculateCancellationDeadline($contract);
        if ($cancellationDeadline === null) {
            return;
        }

        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp(Application::APP_ID)
            ->setUser($contract->getCreatedBy())
            ->setDateTime(new DateTime())
            ->setObject('contract', (string) $contract->getId())
            ->setSubject('cancellation_reminder', [
                'contractId' => $contract->getId(),
                'contractName' => $contract->getName(),
                'deadline' => $cancellationDeadline->format('d.m.Y'),
            ]);

        $this->notificationManager->notify($notification);
    }

    /**
     * Mark a reminder as sent
     */
    public function markReminderSent(Contract $contract): void {
        $reminder = new ReminderSent();
        $reminder->setContractId($contract->getId());
        $reminder->setReminderType($this->getReminderType($contract));
        $reminder->setSentAt(new DateTime());
        $reminder->setSentTo($contract->getCreatedBy());

        $this->reminderSentMapper->insert($reminder);
    }
}
