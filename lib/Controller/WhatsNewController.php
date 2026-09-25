<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\WhatsNewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * „Was ist neu?"-Fenster (#415). Liefert die noch nicht gesehenen Eintraege der
 * laufenden Version und nimmt die Quittung entgegen.
 */
class WhatsNewController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly IL10N $l,
		private readonly PermissionService $permissionService,
		private readonly WhatsNewService $whatsNewService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Eintraege, die dieser Nutzer noch nicht gesehen hat. Leere Liste heisst:
	 * kein Fenster.
	 */
	#[NoAdminRequired]
	public function index(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l->t('Nicht angemeldet')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->permissionService->hasAccess($this->userId)) {
			return new DataResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($this->whatsNewService->getPending($this->userId));
	}

	/** Quittiert das Fenster: die laufende Version gilt als gesehen. */
	#[NoAdminRequired]
	public function seen(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => $this->l->t('Nicht angemeldet')], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->permissionService->hasAccess($this->userId)) {
			return new DataResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
		}
		$this->whatsNewService->markSeen($this->userId);
		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}
}
