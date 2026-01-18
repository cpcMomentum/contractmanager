<?php

declare(strict_types=1);

namespace OCA\ContractManager\BackgroundJob;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Background job that checks contracts and sends cancellation reminders
 * Runs every 6 hours
 */
class ReminderJob extends TimedJob {

    public function __construct(
        ITimeFactory $time,
        private ReminderService $reminderService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);

        // Run every 6 hours (6 * 60 * 60 = 21600 seconds)
        $this->setInterval(21600);
    }

    protected function run($argument): void {
        $this->logger->info('Starting contract reminder check', [
            'app' => Application::APP_ID,
        ]);

        try {
            $remindersSent = $this->reminderService->checkAndSendReminders();

            $this->logger->info('Contract reminder check completed', [
                'app' => Application::APP_ID,
                'remindersSent' => $remindersSent,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Contract reminder check failed', [
                'app' => Application::APP_ID,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
