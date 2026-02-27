<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {

	public function __construct(
		IRequest $request,
		private ?string $userId,
		private IGroupManager $groupManager,
		private IInitialState $initialState,
		private SettingsService $settingsService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'contractmanager-main');
		Util::addStyle(Application::APP_ID, 'main');

		// Admin-Status über Initial State API bereitstellen
		$isAdmin = $this->userId !== null && $this->groupManager->isAdmin($this->userId);
		$this->initialState->provideInitialState('isAdmin', $isAdmin);

		// Benutzer-Präferenzen über Initial State API bereitstellen
		if ($this->userId !== null) {
			$this->initialState->provideInitialState('userPreferences', [
				'sortBy' => $this->settingsService->getUserSortBy($this->userId),
				'sortDirection' => $this->settingsService->getUserSortDirection($this->userId),
				'filters' => $this->settingsService->getUserFilters($this->userId),
			]);
		}

		return new TemplateResponse(
			Application::APP_ID,
			'main'
		);
	}
}
