<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use OCA\ContractManager\Db\Category;
use OCA\ContractManager\Db\CategoryMapper;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;

/**
 * Restores a contracts.json document (ContractExportService format) into a user's account.
 */
class ContractImportService {

	private const VALID_STATUSES = [
		Contract::STATUS_ACTIVE,
		Contract::STATUS_CANCELLED,
		Contract::STATUS_ENDED,
	];

	private const VALID_INTERVALS = [
		Contract::INTERVAL_WEEKLY,
		Contract::INTERVAL_MONTHLY,
		Contract::INTERVAL_QUARTERLY,
		Contract::INTERVAL_SEMI_ANNUAL,
		Contract::INTERVAL_YEARLY,
		Contract::INTERVAL_ONE_TIME,
	];

	private const VALID_TYPES = [
		Contract::TYPE_FIXED,
		Contract::TYPE_AUTO_RENEWAL,
	];

	private const VALID_DEADLINE_TYPES = [
		Contract::DEADLINE_TYPE_NORMAL,
		Contract::DEADLINE_TYPE_MONTH_END,
	];

	private const VALID_AMOUNT_TYPES = [
		Contract::AMOUNT_TYPE_NETTO,
		Contract::AMOUNT_TYPE_BRUTTO,
	];

	public function __construct(
		private ContractMapper $contractMapper,
		private CategoryMapper $categoryMapper,
		private ReminderOptOutMapper $optOutMapper,
		private IUserManager $userManager,
		private IRootFolder $rootFolder,
		private ITimeFactory $timeFactory,
		private IL10N $l,
	) {
	}

	/**
	 * @return array<string, mixed>
	 * @throws ValidationException when the file is not a readable VertragsWerk backup
	 */
	public function parse(string $json): array {
		$data = json_decode($json, true);
		if (!is_array($data) || !isset($data['contracts']) || !is_array($data['contracts'])) {
			throw new ValidationException(['file' => $this->l->t('Die Datei ist keine VertragsWerk-Sicherung.')]);
		}
		$version = $data['schemaVersion'] ?? null;
		if ($version !== ContractExportService::SCHEMA_VERSION) {
			throw new ValidationException(['file' => $this->l->t('Diese Sicherung hat ein unbekanntes Format (Version %s). Bitte VertragsWerk aktualisieren.', [is_scalar($version) ? (string)$version : '?'])]);
		}
		foreach (['categories', 'optouts'] as $key) {
			if (isset($data[$key]) && !is_array($data[$key])) {
				throw new ValidationException(['file' => $this->l->t('Die Datei ist keine VertragsWerk-Sicherung.')]);
			}
		}
		return $data;
	}

	/**
	 * @param array<string, mixed> $data result of parse()
	 * @return array{contracts: int, duplicates: int, invalid: int, categories: int, missingFiles: int, unknownUsers: int}
	 */
	public function preview(string $uid, array $data): array {
		return $this->run($uid, $data, false);
	}

	/**
	 * @param array<string, mixed> $data result of parse()
	 * @return array{contracts: int, duplicates: int, invalid: int, categories: int, missingFiles: int, unknownUsers: int}
	 */
	public function import(string $uid, array $data): array {
		return $this->contractMapper->transactional(fn () => $this->run($uid, $data, true));
	}

	/**
	 * Shared by preview and import so the preview numbers match what the import does.
	 *
	 * @param array<string, mixed> $data
	 * @return array{contracts: int, duplicates: int, invalid: int, categories: int, missingFiles: int, unknownUsers: int}
	 */
	private function run(string $uid, array $data, bool $write): array {
		$summary = [
			'contracts' => 0,
			'duplicates' => 0,
			'invalid' => 0,
			'categories' => 0,
			'missingFiles' => 0,
			'unknownUsers' => 0,
		];

		$categoryNames = [];
		foreach ($data['categories'] ?? [] as $categoryData) {
			if (!is_array($categoryData) || !isset($categoryData['exportId'])) {
				continue;
			}
			$name = $this->limit(trim($this->text($categoryData['name'] ?? null)), 255);
			if ($name !== '') {
				$categoryNames[(string)$categoryData['exportId']] = $name;
			}
		}
		$categoryMap = [];

		$existingKeys = [];
		foreach ($this->contractMapper->findAllByOwner($uid) as $existing) {
			$existingKeys[$this->duplicateKey($existing->getName(), $existing->getVendor(), $existing->getStartDate())] = true;
		}

		$contractMap = [];
		foreach ($data['contracts'] as $c) {
			if (!is_array($c)) {
				$summary['invalid']++;
				continue;
			}
			$name = $this->limit(trim($this->text($c['name'] ?? null)), 255);
			$startDate = $this->stringToDate($c['startDate'] ?? null);
			// name and start_date are NOT NULL in the schema
			if ($name === '' || $startDate === null) {
				$summary['invalid']++;
				continue;
			}
			$vendor = $this->limit($this->text($c['vendor'] ?? null), 255);

			$key = $this->duplicateKey($name, $vendor, $startDate);
			if (isset($existingKeys[$key])) {
				$summary['duplicates']++;
				continue;
			}
			$existingKeys[$key] = true;

			$categoryId = null;
			$categoryExportId = $c['categoryExportId'] ?? null;
			if ($categoryExportId !== null && isset($categoryNames[(string)$categoryExportId])) {
				$categoryId = $this->resolveCategory($categoryNames[(string)$categoryExportId], $categoryMap, $summary, $write);
			}

			$responsibleUser = $this->nullableString($c['responsibleUser'] ?? null);
			if ($responsibleUser !== null && !$this->userManager->userExists($responsibleUser)) {
				$summary['unknownUsers']++;
				$responsibleUser = null;
			}

			$contractFolder = $this->nullableString($c['contractFolder'] ?? null, 1024);
			$mainDocument = $this->nullableString($c['mainDocument'] ?? null, 1024);
			if (!$this->pathResolves($uid, $contractFolder) || !$this->pathResolves($uid, $mainDocument)) {
				$summary['missingFiles']++;
			}

			$summary['contracts']++;
			if (!$write) {
				continue;
			}

			$contract = new Contract();
			$contract->setName($name);
			$contract->setVendor($vendor);
			$contract->setStatus($this->oneOf($c['status'] ?? null, self::VALID_STATUSES, Contract::STATUS_ACTIVE));
			$contract->setCategoryId($categoryId);
			$contract->setStartDate($startDate);
			$contract->setEndDate($this->stringToDate($c['endDate'] ?? null));
			$contract->setCancelledOn($this->stringToDate($c['cancelledOn'] ?? null));
			$contract->setCancelledTo($this->stringToDate($c['cancelledTo'] ?? null));
			$contract->setCancellationPeriod($this->limit($this->text($c['cancellationPeriod'] ?? null), 50));
			$contract->setContractType($this->oneOf($c['contractType'] ?? null, self::VALID_TYPES, Contract::TYPE_FIXED));
			$contract->setRenewalPeriod($this->nullableString($c['renewalPeriod'] ?? null, 50));
			$contract->setCancellationDeadlineType($this->oneOf($c['cancellationDeadlineType'] ?? null, self::VALID_DEADLINE_TYPES, Contract::DEADLINE_TYPE_NORMAL));
			$contract->setCost(isset($c['cost']) && is_numeric($c['cost']) ? (string)$c['cost'] : null);
			$contract->setCurrency($this->nullableString($c['currency'] ?? null, 3));
			$interval = $c['costInterval'] ?? null;
			$contract->setCostInterval(in_array($interval, self::VALID_INTERVALS, true) ? $interval : null);
			$contract->setAmountType($this->oneOf($c['amountType'] ?? null, self::VALID_AMOUNT_TYPES, Contract::AMOUNT_TYPE_NETTO));
			$contract->setContractFolder($contractFolder);
			$contract->setMainDocument($mainDocument);
			$contract->setReminderEnabled((int)($c['reminderEnabled'] ?? 1) === 0 ? 0 : 1);
			$contract->setReminderDays(isset($c['reminderDays']) && is_numeric($c['reminderDays']) ? (int)$c['reminderDays'] : null);
			$contract->setNotes($this->nullableString($c['notes'] ?? null));
			$contract->setCustomField1($this->nullableString($c['customField1'] ?? null, 255));
			$contract->setCustomField2($this->nullableString($c['customField2'] ?? null, 255));
			$contract->setCustomField3($this->nullableString($c['customField3'] ?? null, 255));
			$contract->setArchived((int)($c['archived'] ?? 0) === 1 ? 1 : 0);
			$contract->setIsPrivate((int)($c['isPrivate'] ?? 0) === 1 ? 1 : 0);
			$contract->setResponsibleUser($responsibleUser);
			$contract->setCreatedAt($this->stringToDate($c['createdAt'] ?? null) ?? $this->timeFactory->getDateTime());
			$contract->setUpdatedAt($this->stringToDate($c['updatedAt'] ?? null) ?? $this->timeFactory->getDateTime());
			$contract->setCreatedBy($uid);
			$contract->markAllFieldsUpdated();

			$inserted = $this->contractMapper->insert($contract);
			if (isset($c['exportId'])) {
				$contractMap[(string)$c['exportId']] = $inserted->getId();
			}
		}

		if ($write) {
			foreach ($data['optouts'] ?? [] as $optout) {
				$exportId = is_array($optout) ? ($optout['contractExportId'] ?? null) : null;
				$newId = $exportId !== null ? ($contractMap[(string)$exportId] ?? null) : null;
				if ($newId !== null) {
					$this->optOutMapper->setOptOut($newId, $uid, true);
				}
			}
		}

		return $summary;
	}

	/**
	 * @param array<string, int|null> $categoryMap
	 * @param array<string, int> $summary
	 */
	private function resolveCategory(string $name, array &$categoryMap, array &$summary, bool $write): ?int {
		if (array_key_exists($name, $categoryMap)) {
			return $categoryMap[$name];
		}
		$existing = $this->categoryMapper->findByName($name);
		if ($existing !== null) {
			return $categoryMap[$name] = $existing->getId();
		}
		$summary['categories']++;
		if (!$write) {
			return $categoryMap[$name] = null;
		}
		$category = new Category();
		$category->setName($name);
		$category->setSortOrder($this->categoryMapper->getMaxSortOrder() + 1);
		return $categoryMap[$name] = $this->categoryMapper->insert($category)->getId();
	}

	// Empty references and external URLs count as resolved
	private function pathResolves(string $uid, ?string $path): bool {
		if ($path === null || preg_match('#^https?://#i', $path) === 1) {
			return true;
		}
		try {
			return $this->rootFolder->getUserFolder($uid)->nodeExists(ltrim($path, '/'));
		} catch (\Throwable) {
			return false;
		}
	}

	private function duplicateKey(string $name, string $vendor, ?\DateTime $startDate): string {
		return mb_strtolower(trim($name)) . "\0" . mb_strtolower(trim($vendor)) . "\0" . ($startDate?->format('Y-m-d') ?? '');
	}

	/**
	 * @param list<string> $allowed
	 */
	private function oneOf(mixed $value, array $allowed, string $default): string {
		return in_array($value, $allowed, true) ? $value : $default;
	}

	private function nullableString(mixed $value, ?int $maxLength = null): ?string {
		if ($value === null || $value === '' || !is_scalar($value)) {
			return null;
		}
		return $maxLength === null ? (string)$value : $this->limit((string)$value, $maxLength);
	}

	private function text(mixed $value): string {
		return is_scalar($value) ? (string)$value : '';
	}

	private function limit(string $value, int $maxLength): string {
		return mb_substr($value, 0, $maxLength);
	}

	private function stringToDate(mixed $value): ?\DateTime {
		if (!is_string($value) || $value === '') {
			return null;
		}
		try {
			return new \DateTime($value);
		} catch (\Exception) {
			return null;
		}
	}
}
