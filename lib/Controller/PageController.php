<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
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

		return new TemplateResponse(
			Application::APP_ID,
			'main'
		);
	}
}
