<?php

declare(strict_types=1);

namespace OCA\ContractManager\Controller;

use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Service\ContractService;
use OCA\ContractManager\Service\ForbiddenException;
use OCA\ContractManager\Service\NotFoundException;
use OCA\ContractManager\Service\PermissionService;
use OCA\ContractManager\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ContractController extends Controller {

	public function __construct(
		IRequest $request,
		private ContractService $service,
		private PermissionService $permissionService,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Get all visible contracts
	 *
	 * @NoAdminRequired
	 */
	public function index(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);
		return new JSONResponse($this->service->findAllVisible($this->userId, $isAdmin));
	}

	/**
	 * Get all visible archived contracts
	 *
	 * @NoAdminRequired
	 */
	public function archived(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);
		return new JSONResponse($this->service->findArchivedVisible($this->userId, $isAdmin));
	}

	/**
	 * Get contracts in trash (user sees own, admin sees all)
	 *
	 * @NoAdminRequired
	 */
	public function trash(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);

		if ($isAdmin) {
			return new JSONResponse($this->service->findAllDeleted());
		}

		return new JSONResponse($this->service->findDeletedByUser($this->userId));
	}

	/**
	 * Get user permissions info for frontend
	 *
	 * @NoAdminRequired
	 */
	public function permissions(): JSONResponse {
		return new JSONResponse($this->permissionService->getPermissionInfo($this->userId));
	}

	/**
	 * Get a single contract
	 *
	 * @NoAdminRequired
	 */
	public function show(int $id): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);

			$this->service->checkReadAccess($contract, $this->userId, $isAdmin);

			return new JSONResponse($contract);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Create a new contract (Editor or Admin)
	 *
	 * @NoAdminRequired
	 */
	public function create(
		string $name,
		string $vendor,
		string $startDate,
		string $endDate,
		string $cancellationPeriod,
		string $contractType,
		?int $categoryId = null,
		?string $renewalPeriod = null,
		?string $cost = null,
		?string $currency = null,
		?string $costInterval = null,
		?string $contractFolder = null,
		?string $mainDocument = null,
		bool $reminderEnabled = true,
		?int $reminderDays = null,
		?string $notes = null,
		bool $isPrivate = false,
	): JSONResponse {
		// Check if user can create contracts
		if (!$this->permissionService->canEdit($this->userId)) {
			return new JSONResponse(['error' => 'Keine Berechtigung zum Erstellen'], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->service->validate([
				'name' => $name,
				'vendor' => $vendor,
				'startDate' => $startDate,
				'endDate' => $endDate,
			]);

			$contract = $this->service->create(
				$name,
				$vendor,
				$startDate,
				$endDate,
				$cancellationPeriod,
				$contractType,
				$this->userId,
				$categoryId,
				$renewalPeriod,
				$cost,
				$currency,
				$costInterval,
				$contractFolder,
				$mainDocument,
				$reminderEnabled,
				$reminderDays,
				$notes,
				$isPrivate,
			);

			return new JSONResponse($contract, Http::STATUS_CREATED);
		} catch (ValidationException $e) {
			return new JSONResponse(['errors' => $e->getErrors()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update a contract (Editor or Admin, with visibility rules)
	 *
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		string $name,
		string $vendor,
		string $startDate,
		string $endDate,
		string $cancellationPeriod,
		string $contractType,
		?int $categoryId = null,
		?string $status = null,
		?string $renewalPeriod = null,
		?string $cost = null,
		?string $currency = null,
		?string $costInterval = null,
		?string $contractFolder = null,
		?string $mainDocument = null,
		bool $reminderEnabled = true,
		?int $reminderDays = null,
		?string $notes = null,
		?bool $isPrivate = null,
	): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$isEditor = $this->permissionService->isEditor($this->userId);

			$this->service->checkWriteAccess($contract, $this->userId, $isAdmin, $isEditor);

			$this->service->validate([
				'name' => $name,
				'vendor' => $vendor,
				'startDate' => $startDate,
				'endDate' => $endDate,
				'status' => $status,
				'notes' => $notes,
			]);

			$updatedContract = $this->service->update(
				$id,
				$name,
				$vendor,
				$startDate,
				$endDate,
				$cancellationPeriod,
				$contractType,
				$categoryId,
				$status,
				$renewalPeriod,
				$cost,
				$currency,
				$costInterval,
				$contractFolder,
				$mainDocument,
				$reminderEnabled,
				$reminderDays,
				$notes,
				$isPrivate,
			);

			return new JSONResponse($updatedContract);
		} catch (ValidationException $e) {
			return new JSONResponse(['errors' => $e->getErrors()], Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Soft-delete a contract (move to trash)
	 *
	 * @NoAdminRequired
	 */
	public function destroy(int $id): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$isEditor = $this->permissionService->isEditor($this->userId);

			$this->service->checkWriteAccess($contract, $this->userId, $isAdmin, $isEditor);
			$this->service->softDelete($id);

			return new JSONResponse(['success' => true]);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Restore a contract from trash (user can restore own, admin can restore all)
	 *
	 * @NoAdminRequired
	 */
	public function restoreFromTrash(int $id): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);

			$this->service->checkRestoreAccess($contract, $this->userId, $isAdmin);
			$restoredContract = $this->service->restoreFromTrash($id);

			return new JSONResponse($restoredContract);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Permanently delete a contract (Admin only)
	 * No @NoAdminRequired annotation = Nextcloud enforces admin check
	 */
	public function deletePermanently(int $id): JSONResponse {
		try {
			$this->service->deletePermanently($id);
			return new JSONResponse(['success' => true]);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Empty trash (permanently delete all trashed contracts) (Admin only)
	 * No @NoAdminRequired annotation = Nextcloud enforces admin check
	 */
	public function emptyTrash(): JSONResponse {
		$count = $this->service->emptyTrash();
		return new JSONResponse(['success' => true, 'deleted' => $count]);
	}

	/**
	 * Archive a contract
	 *
	 * @NoAdminRequired
	 */
	public function archive(int $id): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$isEditor = $this->permissionService->isEditor($this->userId);

			$this->service->checkWriteAccess($contract, $this->userId, $isAdmin, $isEditor);
			$archivedContract = $this->service->archive($id);

			return new JSONResponse($archivedContract);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Restore a contract from archive
	 *
	 * @NoAdminRequired
	 */
	public function restore(int $id): JSONResponse {
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$isEditor = $this->permissionService->isEditor($this->userId);

			$this->service->checkWriteAccess($contract, $this->userId, $isAdmin, $isEditor);
			$restoredContract = $this->service->restore($id);

			return new JSONResponse($restoredContract);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}
}
