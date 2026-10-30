<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContactService;
use OCA\ContractManager\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class ContactController extends Controller {

	public function __construct(
		IRequest $request,
		private ContactService $contactService,
		private PermissionService $permissionService,
		private IL10N $l,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return $this->forbidden();
		}
		return new JSONResponse(['available' => $this->contactService->isAvailable()]);
	}

	#[NoAdminRequired]
	public function search(string $query = ''): JSONResponse {
		if ($this->userId === null || !$this->permissionService->canEdit($this->userId)) {
			return $this->forbidden();
		}
		return new JSONResponse($this->contactService->search($query));
	}

	/**
	 * Reads only the viewer's own address books, so a colleague without access gets null.
	 */
	#[NoAdminRequired]
	public function resolve(string $uid = ''): JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return $this->forbidden();
		}
		$uid = trim($uid);
		return new JSONResponse(['contact' => $uid === '' ? null : $this->contactService->resolve($uid)]);
	}

	private function forbidden(): JSONResponse {
		return new JSONResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
	}
}
