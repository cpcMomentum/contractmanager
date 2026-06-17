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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;

class ContractController extends Controller {

	public function __construct(
		IRequest $request,
		private ContractService $service,
		private PermissionService $permissionService,
		private IL10N $l,
		private IUserManager $userManager,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Search users for the "responsible" picker. Available to anyone who may
	 * edit contracts (not just admins, unlike the settings principal search).
	 */
	#[NoAdminRequired]
	public function searchUsers(string $query = ''): JSONResponse {
		if ($this->userId === null || !$this->permissionService->canEdit($this->userId)) {
			return new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		$results = [];
		foreach ($this->userManager->search($query, 25) as $user) {
			$results[] = [
				'id' => 'user:' . $user->getUID(),
				'uid' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'type' => 'user',
			];
		}
		return new JSONResponse($results);
	}

	/**
	 * Get all visible contracts
	 */
	#[NoAdminRequired]
	public function index(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);
		return new JSONResponse($this->service->findAllVisible($this->userId, $isAdmin));
	}

	/**
	 * Get all visible archived contracts
	 */
	#[NoAdminRequired]
	public function archived(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);
		return new JSONResponse($this->service->findArchivedVisible($this->userId, $isAdmin));
	}

	/**
	 * Get contracts in trash (user sees own, admin sees all)
	 */
	#[NoAdminRequired]
	public function trash(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);

		if ($isAdmin) {
			return new JSONResponse($this->service->findAllDeleted());
		}

		return new JSONResponse($this->service->findDeletedByUser($this->userId));
	}

	/**
	 * Get user permissions info for frontend
	 */
	#[NoAdminRequired]
	public function permissions(): JSONResponse {
		return new JSONResponse($this->permissionService->getPermissionInfo($this->userId));
	}

	/**
	 * Distinct vendor names from contracts visible to the user.
	 * Powers the autocomplete in the contract form.
	 */
	#[NoAdminRequired]
	public function vendors(): JSONResponse {
		$isAdmin = $this->permissionService->isAdmin($this->userId);
		return new JSONResponse($this->service->findVisibleVendors($this->userId, $isAdmin));
	}

	/**
	 * Get a single contract
	 */
	#[NoAdminRequired]
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
	 * Get the current user's reminder opt-out state for a contract.
	 * Anyone who may read the contract can manage their own opt-out.
	 */
	#[NoAdminRequired]
	public function getReminderOptOut(int $id): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$this->service->checkReadAccess($contract, $this->userId, $isAdmin);

			return new JSONResponse(['optedOut' => $this->service->isReminderOptedOut($id, $this->userId)]);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Set the current user's reminder opt-out state for a contract.
	 */
	#[NoAdminRequired]
	public function setReminderOptOut(int $id, bool $optedOut): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$contract = $this->service->find($id);
			$isAdmin = $this->permissionService->isAdmin($this->userId);
			$this->service->checkReadAccess($contract, $this->userId, $isAdmin);

			$this->service->setReminderOptOut($id, $this->userId, $optedOut);

			return new JSONResponse(['optedOut' => $this->service->isReminderOptedOut($id, $this->userId)]);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => 'Contract not found'], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Create a new contract (Editor or Admin)
	 */
	#[NoAdminRequired]
	public function create(
		string $name,
		string $vendor,
		string $startDate,
		?string $endDate,
		string $contractType,
		?string $cancellationPeriod = null,
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
		?string $customField1 = null,
		?string $customField2 = null,
		?string $customField3 = null,
		string $amountType = 'netto',
		?string $cancelledOn = null,
		?string $cancelledTo = null,
		?string $responsibleUser = null,
		string $cancellationDeadlineType = 'normal',
	): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		// Check if user can create contracts
		if (!$this->permissionService->canEdit($this->userId)) {
			return new JSONResponse(['error' => $this->l->t('No permission to create')], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->service->validate([
				'name' => $name,
				'vendor' => $vendor,
				'startDate' => $startDate,
				'endDate' => $endDate,
				'cancelledOn' => $cancelledOn,
				'cancelledTo' => $cancelledTo,
				'customField1' => $customField1,
				'customField2' => $customField2,
				'customField3' => $customField3,
			]);

			$contract = $this->service->create(
				$name,
				$vendor,
				$startDate,
				$endDate,
				$contractType,
				$this->userId,
				$cancellationPeriod,
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
				$customField1,
				$customField2,
				$customField3,
				$amountType,
				$cancelledOn,
				$cancelledTo,
				$responsibleUser,
				$cancellationDeadlineType,
			);

			return new JSONResponse($contract, Http::STATUS_CREATED);
		} catch (ValidationException $e) {
			return new JSONResponse(['errors' => $e->getErrors()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update a contract (Editor or Admin, with visibility rules)
	 */
	#[NoAdminRequired]
	public function update(
		int $id,
		string $name,
		string $vendor,
		string $startDate,
		?string $endDate,
		string $contractType,
		?string $cancellationPeriod = null,
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
		?string $customField1 = null,
		?string $customField2 = null,
		?string $customField3 = null,
		string $amountType = 'netto',
		?string $cancelledOn = null,
		?string $cancelledTo = null,
		?string $responsibleUser = null,
		string $cancellationDeadlineType = 'normal',
	): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
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
				'cancelledOn' => $cancelledOn,
				'cancelledTo' => $cancelledTo,
				'customField1' => $customField1,
				'customField2' => $customField2,
				'customField3' => $customField3,
			]);

			$updatedContract = $this->service->update(
				$id,
				$name,
				$vendor,
				$startDate,
				$endDate,
				$contractType,
				$cancellationPeriod,
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
				$customField1,
				$customField2,
				$customField3,
				$amountType,
				$cancelledOn,
				$cancelledTo,
				$responsibleUser,
				$cancellationDeadlineType,
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
	 */
	#[NoAdminRequired]
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
	 */
	#[NoAdminRequired]
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
	 * No #[NoAdminRequired] attribute = Nextcloud enforces admin check
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
	 * No #[NoAdminRequired] attribute = Nextcloud enforces admin check
	 */
	public function emptyTrash(): JSONResponse {
		$count = $this->service->emptyTrash();
		return new JSONResponse(['success' => true, 'deleted' => $count]);
	}

	/**
	 * Archive a contract
	 */
	#[NoAdminRequired]
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
	 */
	#[NoAdminRequired]
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

	/**
	 * Preview how many contracts would be transferred from a user (Admin only).
	 * No #[NoAdminRequired] = Nextcloud enforces admin.
	 */
	public function transferPreview(string $from): JSONResponse {
		return new JSONResponse(['count' => $this->service->countByEffectiveOwner($from)]);
	}

	/**
	 * Transfer responsibility for all of $from's contracts to $to (Admin only).
	 * No #[NoAdminRequired] = Nextcloud enforces admin.
	 */
	public function transfer(string $from, string $to): JSONResponse {
		if ($from === '' || $to === '') {
			return new JSONResponse(['error' => $this->l->t('Both users are required')], Http::STATUS_BAD_REQUEST);
		}
		if ($from === $to) {
			return new JSONResponse(['error' => $this->l->t('Source and target must differ')], Http::STATUS_BAD_REQUEST);
		}
		$count = $this->service->transferResponsibility($from, $to);
		return new JSONResponse(['transferred' => $count]);
	}
}
