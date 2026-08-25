<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Builds the JSON serialization of a user's own contract data.
 *
 * This is the single source of truth for the contracts.json format, shared by
 * three consumers so they can never drift apart (#296):
 *  - the user_migration flow (ContractMigrator, "Data migration" / occ user:export),
 *  - the periodic auto-backup job (AutoBackupService),
 *  - the occ contractmanager:export command.
 *
 * Only data OWNED by the given user is included (created_by). Attachments are
 * kept as path references only.
 */
class ContractExportService {

	/**
	 * Version of the contracts.json schema. Kept in sync with the value the
	 * user_migration ContractMigrator has always written, so exports from any
	 * consumer remain interchangeable and importable.
	 */
	public const SCHEMA_VERSION = 1;

	public function __construct(
		private ContractMapper $contractMapper,
		private CategoryMapper $categoryMapper,
		private ReminderOptOutMapper $optOutMapper,
		private IAppManager $appManager,
		private ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Assemble the export document for a user's own contracts.
	 *
	 * @return array<string, mixed>
	 */
	public function buildExportDocument(string $uid): array {
		$contracts = $this->contractMapper->findAllByOwner($uid);

		// Collect referenced categories once (deduplicated by id).
		$categories = [];
		foreach ($contracts as $contract) {
			$categoryId = $contract->getCategoryId();
			if ($categoryId === null || isset($categories[$categoryId])) {
				continue;
			}
			try {
				$category = $this->categoryMapper->find($categoryId);
				$categories[$categoryId] = [
					'exportId' => $category->getId(),
					'name' => $category->getName(),
					'sortOrder' => $category->getSortOrder(),
				];
			} catch (DoesNotExistException) {
				// Orphaned reference — contract keeps a null category on import.
			}
		}

		$contractsData = [];
		$optouts = [];
		foreach ($contracts as $contract) {
			$categoryId = $contract->getCategoryId();
			$hasCategory = $categoryId !== null && isset($categories[$categoryId]);
			$contractsData[] = [
				'exportId' => $contract->getId(),
				'categoryExportId' => $hasCategory ? $categoryId : null,
				'name' => $contract->getName(),
				'vendor' => $contract->getVendor(),
				'status' => $contract->getStatus(),
				'startDate' => $this->dateToString($contract->getStartDate()),
				'endDate' => $this->dateToString($contract->getEndDate()),
				'cancelledOn' => $this->dateToString($contract->getCancelledOn()),
				'cancelledTo' => $this->dateToString($contract->getCancelledTo()),
				'cancellationPeriod' => $contract->getCancellationPeriod(),
				'contractType' => $contract->getContractType(),
				'renewalPeriod' => $contract->getRenewalPeriod(),
				'cancellationDeadlineType' => $contract->getCancellationDeadlineType(),
				'cost' => $contract->getCost(),
				'currency' => $contract->getCurrency(),
				'costInterval' => $contract->getCostInterval(),
				'amountType' => $contract->getAmountType(),
				'contractFolder' => $contract->getContractFolder(),
				'mainDocument' => $contract->getMainDocument(),
				'reminderEnabled' => $contract->getReminderEnabled(),
				'reminderDays' => $contract->getReminderDays(),
				'notes' => $contract->getNotes(),
				'customField1' => $contract->getCustomField1(),
				'customField2' => $contract->getCustomField2(),
				'customField3' => $contract->getCustomField3(),
				'archived' => $contract->getArchived(),
				'isPrivate' => $contract->getIsPrivate(),
				'responsibleUser' => $contract->getResponsibleUser(),
				'createdAt' => $this->dateToString($contract->getCreatedAt()),
				'updatedAt' => $this->dateToString($contract->getUpdatedAt()),
			];
			if ($this->optOutMapper->isOptedOut($contract->getId(), $uid)) {
				$optouts[] = ['contractExportId' => $contract->getId()];
			}
		}

		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'exportedAt' => $this->timeFactory->getDateTime()->format('c'),
			'appVersion' => $this->appManager->getAppVersion(Application::APP_ID),
			'categories' => array_values($categories),
			'contracts' => $contractsData,
			'optouts' => $optouts,
		];
	}

	/**
	 * The export document as a pretty-printed JSON string.
	 */
	public function exportJson(string $uid): string {
		return json_encode(
			$this->buildExportDocument($uid),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
		);
	}

	private function dateToString(?\DateTime $date): ?string {
		return $date?->format('c');
	}
}
