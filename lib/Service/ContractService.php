<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class ContractService {

	private const VALID_STATUSES = [
		Contract::STATUS_ACTIVE,
		Contract::STATUS_CANCELLED,
		Contract::STATUS_ENDED,
	];

	private const MAX_STRING_LENGTH = 500;
	private const MAX_NOTES_LENGTH = 5000;

	public function __construct(
		private ContractMapper $mapper,
	) {
	}

	/**
	 * Validate contract data
	 *
	 * @param array<string, mixed> $data
	 * @throws ValidationException
	 */
	public function validate(array $data): void {
		$errors = [];

		// Name is required
		if (empty($data['name']) || trim($data['name']) === '') {
			$errors['name'] = 'Name ist erforderlich';
		}

		// Vendor is required
		if (empty($data['vendor']) || trim($data['vendor']) === '') {
			$errors['vendor'] = 'Vertragspartner ist erforderlich';
		}

		// Date validation: startDate must be before endDate
		if (!empty($data['startDate']) && !empty($data['endDate'])) {
			$start = new DateTime($data['startDate']);
			$end = new DateTime($data['endDate']);
			if ($start >= $end) {
				$errors['endDate'] = 'Enddatum muss nach Startdatum liegen';
			}
		}

		// Status validation
		if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
			$errors['status'] = 'Ungültiger Status';
		}

		// String length validation (L2 Security Fix)
		if (!empty($data['name']) && strlen($data['name']) > self::MAX_STRING_LENGTH) {
			$errors['name'] = 'Name ist zu lang (max. ' . self::MAX_STRING_LENGTH . ' Zeichen)';
		}
		if (!empty($data['vendor']) && strlen($data['vendor']) > self::MAX_STRING_LENGTH) {
			$errors['vendor'] = 'Vertragspartner ist zu lang (max. ' . self::MAX_STRING_LENGTH . ' Zeichen)';
		}
		if (!empty($data['notes']) && strlen($data['notes']) > self::MAX_NOTES_LENGTH) {
			$errors['notes'] = 'Notizen sind zu lang (max. ' . self::MAX_NOTES_LENGTH . ' Zeichen)';
		}

		if (!empty($errors)) {
			throw new ValidationException($errors);
		}
	}

	/**
	 * Check if a user has read access to a contract
	 *
	 * Admin can see all contracts.
	 * Others can see non-private contracts + their own contracts.
	 *
	 * @throws ForbiddenException
	 */
	public function checkReadAccess(Contract $contract, string $userId, bool $isAdmin): void {
		if ($isAdmin) {
			return;
		}

		// Private contracts are only visible to creator
		if ($contract->getIsPrivate() && $contract->getCreatedBy() !== $userId) {
			throw new ForbiddenException('Kein Zugriff auf diesen privaten Vertrag');
		}
	}

	/**
	 * Check if a user has write access to a contract
	 *
	 * Admin can edit all contracts.
	 * Editors can edit all visible contracts (not just their own).
	 * Viewers cannot edit.
	 *
	 * @throws ForbiddenException
	 */
	public function checkWriteAccess(Contract $contract, string $userId, bool $isAdmin, bool $isEditor): void {
		// First check read access
		$this->checkReadAccess($contract, $userId, $isAdmin);

		// Then check write permission
		if (!$isAdmin && !$isEditor) {
			throw new ForbiddenException('Keine Berechtigung zum Bearbeiten');
		}
	}

	/**
	 * Check if a user can restore a contract from trash
	 *
	 * Admin can restore any contract.
	 * Others can only restore their own deleted contracts.
	 *
	 * @throws ForbiddenException
	 */
	public function checkRestoreAccess(Contract $contract, string $userId, bool $isAdmin): void {
		if ($isAdmin) {
			return;
		}

		if ($contract->getCreatedBy() !== $userId) {
			throw new ForbiddenException('Nur eigene Verträge können wiederhergestellt werden');
		}
	}

	/**
	 * @deprecated Use checkReadAccess/checkWriteAccess instead
	 * Legacy method for backward compatibility
	 *
	 * @throws ForbiddenException
	 */
	public function checkAccess(Contract $contract, string $userId): void {
		if ($contract->getCreatedBy() !== $userId) {
			throw new ForbiddenException('Kein Zugriff auf diesen Vertrag');
		}
	}

    /**
     * Find all visible contracts for a user
     *
     * @return Contract[]
     */
    public function findAllVisible(string $userId, bool $isAdmin): array {
        return $this->mapper->findAllVisible($userId, $isAdmin);
    }

    /**
     * Find all visible archived contracts for a user
     *
     * @return Contract[]
     */
    public function findArchivedVisible(string $userId, bool $isAdmin): array {
        return $this->mapper->findArchivedVisible($userId, $isAdmin);
    }

    /**
     * Find deleted contracts for a user (their trash)
     *
     * @return Contract[]
     */
    public function findDeletedByUser(string $userId): array {
        return $this->mapper->findDeletedByUser($userId);
    }

    /**
     * Find all deleted contracts (admin trash)
     *
     * @return Contract[]
     */
    public function findAllDeleted(): array {
        return $this->mapper->findAllDeleted();
    }

    /**
     * @deprecated Use findAllVisible() instead
     * @return Contract[]
     */
    public function findAll(string $userId): array {
        return $this->mapper->findAll($userId);
    }

    /**
     * @deprecated Use findArchivedVisible() instead
     * @return Contract[]
     */
    public function findArchived(string $userId): array {
        return $this->mapper->findArchived($userId);
    }

    /**
     * @throws NotFoundException
     */
    public function find(int $id): Contract {
        try {
            return $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }
    }

    /**
     * @return Contract[]
     */
    public function search(string $query, string $userId): array {
        return $this->mapper->search($query, $userId);
    }

    public function create(
        string $name,
        string $vendor,
        string $startDate,
        string $endDate,
        string $cancellationPeriod,
        string $contractType,
        string $userId,
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
    ): Contract {
        $contract = new Contract();
        $contract->setName($name);
        $contract->setVendor($vendor);
        $contract->setStatus(Contract::STATUS_ACTIVE);
        $contract->setCategoryId($categoryId);
        $contract->setStartDate(new DateTime($startDate));
        $contract->setEndDate(new DateTime($endDate));
        $contract->setCancellationPeriod($cancellationPeriod);
        $contract->setContractType($contractType);
        $contract->setRenewalPeriod($renewalPeriod);
        $contract->setCost($cost);
        $contract->setCurrency($currency ?? 'EUR');
        $contract->setCostInterval($costInterval);
        $contract->setContractFolder($contractFolder);
        $contract->setMainDocument($mainDocument);
        $contract->setReminderEnabled($reminderEnabled ? 1 : 0);
        $contract->setReminderDays($reminderDays);
        $contract->setNotes($notes);
        $contract->setIsPrivate($isPrivate);
        $contract->setCreatedBy($userId);
        $contract->setCreatedAt(new DateTime());
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->insert($contract);
    }

    /**
     * Update a contract
     *
     * Note: Access check must be done by caller using checkWriteAccess()
     *
     * @throws NotFoundException
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
    ): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $contract->setName($name);
        $contract->setVendor($vendor);
        $contract->setCategoryId($categoryId);
        if ($status !== null) {
            $contract->setStatus($status);
        }
        $contract->setStartDate(new DateTime($startDate));
        $contract->setEndDate(new DateTime($endDate));
        $contract->setCancellationPeriod($cancellationPeriod);
        $contract->setContractType($contractType);
        $contract->setRenewalPeriod($renewalPeriod);
        $contract->setCost($cost);
        $contract->setCurrency($currency ?? 'EUR');
        $contract->setCostInterval($costInterval);
        $contract->setContractFolder($contractFolder);
        $contract->setMainDocument($mainDocument);
        $contract->setReminderEnabled($reminderEnabled ? 1 : 0);
        $contract->setReminderDays($reminderDays);
        $contract->setNotes($notes);
        if ($isPrivate !== null) {
            $contract->setIsPrivate($isPrivate);
        }
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * Soft-delete a contract (move to trash)
     *
     * Note: Access check must be done by caller using checkWriteAccess()
     *
     * @throws NotFoundException
     */
    public function softDelete(int $id): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $contract->setDeletedAt(new DateTime());
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * Restore a contract from trash
     *
     * Note: Access check must be done by caller using checkRestoreAccess()
     *
     * @throws NotFoundException
     */
    public function restoreFromTrash(int $id): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $contract->setDeletedAt(null);
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * Permanently delete a contract (Admin only)
     *
     * @throws NotFoundException
     */
    public function deletePermanently(int $id): void {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $this->mapper->delete($contract);
    }

    /**
     * Permanently delete all contracts in trash (Admin only)
     *
     * @return int Number of deleted contracts
     */
    public function emptyTrash(): int {
        $contracts = $this->mapper->findAllDeleted();
        $count = 0;

        foreach ($contracts as $contract) {
            $this->mapper->delete($contract);
            $count++;
        }

        return $count;
    }

    /**
     * @deprecated Use softDelete() instead
     * @throws NotFoundException
     * @throws ForbiddenException
     */
    public function delete(int $id, string $userId): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $this->checkAccess($contract, $userId);

        return $this->mapper->delete($contract);
    }

	/**
	 * Archive a contract
	 *
	 * Note: Access check must be done by caller using checkWriteAccess()
	 *
	 * @throws NotFoundException
	 */
	public function archive(int $id): Contract {
		try {
			$contract = $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$contract->setArchived(true);
		$contract->setUpdatedAt(new DateTime());

		return $this->mapper->update($contract);
	}

	/**
	 * Restore a contract from archive
	 *
	 * Note: Access check must be done by caller using checkWriteAccess()
	 *
	 * @throws NotFoundException
	 */
	public function restore(int $id): Contract {
		try {
			$contract = $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$contract->setArchived(false);
		$contract->setUpdatedAt(new DateTime());

		return $this->mapper->update($contract);
	}
}
