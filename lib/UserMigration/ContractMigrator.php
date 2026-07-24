<?php

declare(strict_types=1);

namespace OCA\ContractManager\UserMigration;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exports and imports a user's own contract data through the standard Nextcloud
 * user_migration flow ("Personal settings > Data migration" / occ user:export|import).
 *
 * Only data OWNED by the exporting user is included (created_by). Attachments are
 * kept as path references only — the actual files travel with the standard files
 * migrator. Categories are a global/shared resource and are deduplicated by name
 * on import.
 */
class ContractMigrator implements IMigrator {

	use TMigratorBasicVersionHandling;

	private const PATH_CONTRACTS = Application::APP_ID . '/contracts.json';

	public function __construct(
		private ContractMapper $contractMapper,
		private CategoryMapper $categoryMapper,
		private ReminderOptOutMapper $optOutMapper,
		private IAppManager $appManager,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getDisplayName(): string {
		return $this->l10n->t('Verträge');
	}

	public function getDescription(): string {
		return $this->l10n->t('Deine Verträge samt Kategorien sowie Kündigungs- und Erinnerungseinstellungen');
	}

	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$output->writeln('Exporting contracts…');
		$uid = $user->getUID();

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

		$document = [
			'schemaVersion' => $this->version,
			'exportedAt' => $this->timeFactory->getDateTime()->format('c'),
			'appVersion' => $this->appManager->getAppVersion(Application::APP_ID),
			'categories' => array_values($categories),
			'contracts' => $contractsData,
			'optouts' => $optouts,
		];

		$exportDestination->addFileContents(
			self::PATH_CONTRACTS,
			json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		);
		$output->writeln('Exported ' . count($contractsData) . ' contract(s).');
	}

	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No contracts to import, skipping…');
			return;
		}
		if (!$importSource->pathExists(self::PATH_CONTRACTS)) {
			return;
		}

		$uid = $user->getUID();
		$data = json_decode($importSource->getFileContents(self::PATH_CONTRACTS), true);
		if (!is_array($data)) {
			$output->writeln('contracts.json is not readable, skipping…');
			return;
		}

		// Categories: deduplicate against the global list by name.
		$categoryMap = [];
		foreach ($data['categories'] ?? [] as $categoryData) {
			$name = (string)($categoryData['name'] ?? '');
			if ($name === '') {
				continue;
			}
			$existing = $this->categoryMapper->findByName($name);
			if ($existing !== null) {
				$categoryMap[$categoryData['exportId']] = $existing->getId();
				continue;
			}
			$category = new Category();
			$category->setName($name);
			$category->setSortOrder($this->categoryMapper->getMaxSortOrder() + 1);
			$inserted = $this->categoryMapper->insert($category);
			$categoryMap[$categoryData['exportId']] = $inserted->getId();
		}

		// Contracts: fresh rows owned by the importing user, remapped category.
		$contractMap = [];
		foreach ($data['contracts'] ?? [] as $c) {
			$contract = new Contract();
			$contract->setName((string)($c['name'] ?? ''));
			$contract->setVendor((string)($c['vendor'] ?? ''));
			$contract->setStatus((string)($c['status'] ?? Contract::STATUS_ACTIVE));
			$categoryExportId = $c['categoryExportId'] ?? null;
			$contract->setCategoryId($categoryExportId !== null ? ($categoryMap[$categoryExportId] ?? null) : null);
			$contract->setStartDate($this->stringToDate($c['startDate'] ?? null));
			$contract->setEndDate($this->stringToDate($c['endDate'] ?? null));
			$contract->setCancelledOn($this->stringToDate($c['cancelledOn'] ?? null));
			$contract->setCancelledTo($this->stringToDate($c['cancelledTo'] ?? null));
			// cancellation_period is NOT NULL without a DB default.
			$contract->setCancellationPeriod((string)($c['cancellationPeriod'] ?? ''));
			$contract->setContractType((string)($c['contractType'] ?? Contract::TYPE_FIXED));
			$contract->setRenewalPeriod($c['renewalPeriod'] ?? null);
			$contract->setCancellationDeadlineType((string)($c['cancellationDeadlineType'] ?? Contract::DEADLINE_TYPE_NORMAL));
			$contract->setCost($c['cost'] ?? null);
			$contract->setCurrency($c['currency'] ?? null);
			$contract->setCostInterval($c['costInterval'] ?? null);
			$contract->setAmountType((string)($c['amountType'] ?? Contract::AMOUNT_TYPE_NETTO));
			$contract->setContractFolder($c['contractFolder'] ?? null);
			$contract->setMainDocument($c['mainDocument'] ?? null);
			$contract->setReminderEnabled((int)($c['reminderEnabled'] ?? 1));
			$contract->setReminderDays(isset($c['reminderDays']) && $c['reminderDays'] !== null ? (int)$c['reminderDays'] : null);
			$contract->setNotes($c['notes'] ?? null);
			$contract->setCustomField1($c['customField1'] ?? null);
			$contract->setCustomField2($c['customField2'] ?? null);
			$contract->setCustomField3($c['customField3'] ?? null);
			$contract->setArchived((int)($c['archived'] ?? 0));
			$contract->setIsPrivate((int)($c['isPrivate'] ?? 0));
			$contract->setResponsibleUser($c['responsibleUser'] ?? null);
			$contract->setCreatedAt($this->stringToDate($c['createdAt'] ?? null) ?? $this->timeFactory->getDateTime());
			$contract->setUpdatedAt($this->stringToDate($c['updatedAt'] ?? null) ?? $this->timeFactory->getDateTime());
			$contract->setCreatedBy($uid);
			$contract->markAllFieldsUpdated();

			$inserted = $this->contractMapper->insert($contract);
			$contractMap[$c['exportId']] = $inserted->getId();
		}

		// Opt-outs: remap contract id, owned by the importing user.
		foreach ($data['optouts'] ?? [] as $optout) {
			$newId = $contractMap[$optout['contractExportId']] ?? null;
			if ($newId !== null) {
				$this->optOutMapper->setOptOut($newId, $uid, true);
			}
		}

		$output->writeln('Imported ' . count($contractMap) . ' contract(s).');
	}

	private function dateToString(?\DateTime $date): ?string {
		return $date?->format('c');
	}

	private function stringToDate(?string $value): ?\DateTime {
		if ($value === null || $value === '') {
			return null;
		}
		return new \DateTime($value);
	}
}
