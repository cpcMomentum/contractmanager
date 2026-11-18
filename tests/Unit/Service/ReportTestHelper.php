<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCA\ContractManager\Db\ReminderSentMapper;
use OCA\ContractManager\Service\ContractFigures;
use OCA\ContractManager\Service\EmailService;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ReminderService;
use OCA\ContractManager\Service\SettingsService;
use OCA\ContractManager\Service\TalkService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

trait ReportTestHelper {

	private function figuresFor(TestCase $test): ContractFigures {
		$mock = fn (string $class) => (fn () => $this->createMock($class))->call($test);
		return new ContractFigures(new ReminderService(
			$mock(ContractMapper::class),
			$mock(ReminderSentMapper::class),
			$mock(SettingsService::class),
			$mock(TalkService::class),
			$mock(EmailService::class),
			$mock(LoggerInterface::class),
			$mock(PermissionService::class),
			$mock(ReminderOptOutMapper::class),
			$mock(ITimeFactory::class),
			$mock(IDateTimeZone::class),
		));
	}

	/** @param array<string, mixed> $data fields as in the contract JSON */
	private static function contract(array $data, int $id = 1): Contract {
		$contract = new Contract();
		$contract->setId($id);
		$contract->setName($data['name'] ?? 'Vertrag ' . $id);
		$contract->setVendor($data['vendor'] ?? '');
		$contract->setStatus($data['status'] ?? Contract::STATUS_ACTIVE);
		$contract->setContractType($data['contractType'] ?? Contract::TYPE_FIXED);
		$contract->setCancellationDeadlineType($data['cancellationDeadlineType'] ?? Contract::DEADLINE_TYPE_NORMAL);
		$contract->setCategoryId($data['categoryId'] ?? null);
		$contract->setRenewalPeriod($data['renewalPeriod'] ?? null);
		$contract->setCancellationPeriod($data['cancellationPeriod'] ?? null);
		$contract->setCost($data['cost'] ?? null);
		$contract->setCurrency($data['currency'] ?? 'EUR');
		$contract->setAmountType($data['amountType'] ?? Contract::AMOUNT_TYPE_NETTO);
		$contract->setCostInterval($data['costInterval'] ?? null);
		$contract->setReminderDays($data['reminderDays'] ?? null);
		$contract->setVendorContactUid($data['vendorContactUid'] ?? null);
		foreach (['startDate', 'endDate', 'cancelledOn', 'cancelledTo'] as $field) {
			if (isset($data[$field])) {
				$contract->{'set' . ucfirst($field)}(new DateTime($data[$field]));
			}
		}
		return $contract;
	}
}
