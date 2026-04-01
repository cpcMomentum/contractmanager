<?php

declare(strict_types=1);

namespace OCA\ContractManager\Search;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\PermissionService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class ContractSearchProvider implements IProvider {

	public function __construct(
		private ContractService $contractService,
		private PermissionService $permissionService,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Verträge');
	}

	public function getOrder(string $route, array $routeParameters): ?int {
		if (str_starts_with($route, Application::APP_ID . '.')) {
			return -1;
		}
		return 20;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$userId = $user->getUID();

		if (!$this->permissionService->hasAccess($userId)) {
			return SearchResult::complete($this->getName(), []);
		}

		$isAdmin = $this->permissionService->isAdmin($userId);
		$contracts = $this->contractService->search(
			$query->getTerm(),
			$userId,
			$isAdmin,
			$query->getLimit(),
			$query->getCursor() !== null ? (int)$query->getCursor() : null,
		);

		$appUrl = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID . '/');

		$entries = array_map(function (Contract $contract) use ($appUrl) {
			$subline = $contract->getVendor() ?: '';
			if ($contract->getArchived()) {
				$subline .= ($subline ? ' · ' : '') . $this->l->t('Archiviert');
			}

			return new SearchResultEntry(
				'',
				$contract->getName(),
				$subline,
				$appUrl,
				'icon-contractmanager',
				false,
			);
		}, $contracts);

		return SearchResult::paginated(
			$this->getName(),
			$entries,
			$query->getCursor() !== null
				? (int)$query->getCursor() + $query->getLimit()
				: $query->getLimit(),
		);
	}
}
