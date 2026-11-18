<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;

/** Report data; always built from findAllVisible so a report shows nothing the list hides. */
class ReportService {

	public const TYPE_CONTRACTS = 'contracts';
	public const TYPE_COSTS = 'costs';
	public const TYPE_DEADLINES = 'deadlines';
	public const TYPES = [self::TYPE_CONTRACTS, self::TYPE_COSTS, self::TYPE_DEADLINES];

	public const PERIODS = [3, 6, 12];

	public function __construct(
		private ContractService $contractService,
		private CategoryService $categoryService,
		private ContactService $contactService,
		private ContractFigures $figures,
		private SettingsService $settingsService,
		private ITimeFactory $timeFactory,
		private IDateTimeZone $dateTimeZone,
	) {
	}

	public function today(string $userId): DateTime {
		$zone = $this->dateTimeZone->getTimeZone(false, $userId);
		return new DateTime($this->timeFactory->getDateTime('now', $zone)->format('Y-m-d'));
	}

	/** @param list<int> $ids contract ids as filtered and sorted in the list */
	public function contractSheet(string $userId, bool $isAdmin, array $ids): array {
		$today = $this->today($userId);
		$visible = $this->visibleById($userId, $isAdmin);
		$names = $this->categoryNames();
		$reminderDays = $this->settingsService->getReminderDays1();

		$groups = [];
		foreach (array_unique($ids) as $id) {
			$contract = $visible[$id] ?? null;
			if ($contract === null) {
				continue;
			}
			$category = $names[$contract->getCategoryId() ?? 0] ?? null;
			$groups[$category ?? ''][] = [
				'name' => $contract->getName(),
				'vendor' => $contract->getVendor(),
				'contact' => $this->contactLines($contract),
				'runsUntil' => $this->figures->runsUntil($contract, $today),
				'deadline' => $this->figures->nextCancellationDeadline($contract, $today),
				'status' => $this->figures->displayStatus($contract, $today, $reminderDays),
				'costInterval' => $contract->getCostInterval(),
				'cost' => ContractFigures::cost($contract),
				'currency' => $contract->getCurrency() ?: 'EUR',
				'amountType' => $contract->getAmountType(),
			];
		}

		$result = [];
		foreach ($this->sortedCategoryKeys(array_keys($groups)) as $key) {
			$result[] = ['category' => $key === '' ? null : $key, 'rows' => $groups[$key]];
		}
		return ['today' => $today, 'groups' => $result];
	}

	// Sums are keyed by currency and amount type, so net, gross and currencies never mix.
	public function costOverview(string $userId, bool $isAdmin): array {
		$today = $this->today($userId);
		$names = $this->categoryNames();

		$byCategory = [];
		$byVendor = [];
		$totals = [];
		$irregular = [];
		foreach ($this->visibleById($userId, $isAdmin) as $contract) {
			$cost = ContractFigures::cost($contract);
			if ($cost === null || !$this->figures->isRunning($contract, $today)) {
				continue;
			}
			$category = $names[$contract->getCategoryId() ?? 0] ?? null;
			$currency = $contract->getCurrency() ?: 'EUR';
			$amountType = $contract->getAmountType();

			$monthly = ContractFigures::monthlyCost($cost, $contract->getCostInterval());
			if ($monthly === null) {
				$irregular[] = [
					'name' => $contract->getName(),
					'vendor' => $contract->getVendor(),
					'category' => $category,
					'costInterval' => $contract->getCostInterval(),
					'cost' => $cost,
					'currency' => $currency,
					'amountType' => $amountType,
				];
				continue;
			}

			$vendor = trim($contract->getVendor());
			self::add($byCategory, $category, $currency, $amountType, $monthly);
			self::add($byVendor, $vendor === '' ? null : $vendor, $currency, $amountType, $monthly);
			self::add($totals, null, $currency, $amountType, $monthly);
		}

		usort($irregular, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
		return [
			'today' => $today,
			'byCategory' => self::sortedSums($byCategory),
			'byVendor' => self::sortedSums($byVendor),
			'totals' => self::sortedSums($totals),
			'irregular' => $irregular,
		];
	}

	public function deadlinePreview(string $userId, bool $isAdmin, int $months): array {
		if (!in_array($months, self::PERIODS, true)) {
			throw new \InvalidArgumentException('Unsupported period: ' . $months);
		}
		$today = $this->today($userId);
		$until = (clone $today)->modify('+' . $months . ' months');
		if ($until->format('d') !== $today->format('d')) {
			$until->modify('last day of previous month');
		}
		$inWindow = fn (?DateTime $d): bool => $d !== null && ContractFigures::day($d) >= $today && ContractFigures::day($d) <= $until;

		$rows = [];
		foreach ($this->visibleById($userId, $isAdmin) as $contract) {
			if ($contract->getStatus() !== Contract::STATUS_ACTIVE) {
				continue;
			}
			$deadline = $this->figures->nextCancellationDeadline($contract, $today);
			$renews = $contract->getContractType() === Contract::TYPE_AUTO_RENEWAL && !empty($contract->getRenewalPeriod())
				? $this->figures->runsUntil($contract, $today)
				: null;
			$deadline = $inWindow($deadline) ? $deadline : null;
			$renews = $inWindow($renews) ? $renews : null;
			if ($deadline === null && $renews === null) {
				continue;
			}
			$rows[] = [
				'date' => $deadline ?? $renews,
				'deadline' => $deadline,
				'renews' => $renews,
				'renewalPeriod' => $contract->getRenewalPeriod(),
				'name' => $contract->getName(),
				'vendor' => $contract->getVendor(),
				'costInterval' => $contract->getCostInterval(),
				'cost' => ContractFigures::cost($contract),
				'currency' => $contract->getCurrency() ?: 'EUR',
				'amountType' => $contract->getAmountType(),
			];
		}

		usort($rows, fn (array $a, array $b): int => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);
		return ['today' => $today, 'until' => $until, 'months' => $months, 'rows' => $rows];
	}

	/** @return array<int, Contract> */
	private function visibleById(string $userId, bool $isAdmin): array {
		$byId = [];
		foreach ($this->contractService->findAllVisible($userId, $isAdmin) as $contract) {
			$byId[$contract->getId()] = $contract;
		}
		return $byId;
	}

	/** @return array<int, string> */
	private function categoryNames(): array {
		$names = [];
		foreach ($this->categoryService->findAll() as $category) {
			$names[$category->getId()] = $category->getName();
		}
		return $names;
	}

	/** @var array<string, list<string>> */
	private array $contactCache = [];

	/** @return list<string> */
	private function contactLines(Contract $contract): array {
		$uid = $contract->getVendorContactUid();
		if ($uid === null || $uid === '') {
			return [];
		}
		if (!isset($this->contactCache[$uid])) {
			$contact = $this->contactService->resolve($uid);
			$this->contactCache[$uid] = $contact === null ? [] : array_slice(array_values(array_filter(array_merge(
				$contact['addresses'],
				$contact['phones'],
				$contact['emails'],
			))), 0, 6);
		}
		return $this->contactCache[$uid];
	}

	/** @param list<string|int> $keys */
	private function sortedCategoryKeys(array $keys): array {
		$keys = array_map('strval', $keys);
		usort($keys, fn (string $a, string $b): int => ($a === '') <=> ($b === '') ?: strcasecmp($a, $b));
		return $keys;
	}

	/** @param array<string, array<string, mixed>> $sums */
	private static function add(array &$sums, ?string $label, string $currency, string $amountType, float $monthly): void {
		$key = ($label ?? '') . "\0" . $currency . "\0" . $amountType;
		$sums[$key] ??= ['label' => $label, 'currency' => $currency, 'amountType' => $amountType, 'count' => 0, 'monthly' => 0.0, 'yearly' => 0.0];
		$sums[$key]['count']++;
		$sums[$key]['monthly'] += $monthly;
		$sums[$key]['yearly'] += $monthly * 12;
	}

	/** @param array<string, array<string, mixed>> $sums */
	private static function sortedSums(array $sums): array {
		$list = array_values($sums);
		usort($list, fn (array $a, array $b): int => [($a['label'] === null), strtolower((string)$a['label']), $a['currency'], $a['amountType']]
			<=> [($b['label'] === null), strtolower((string)$b['label']), $b['currency'], $b['amountType']]);
		return $list;
	}
}
