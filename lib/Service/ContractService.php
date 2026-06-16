<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\AppInfo\Application;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCA\ContractManager\Db\ReminderOptOutMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\L10N\IFactory;

class ContractService {

	private const VALID_STATUSES = [
		Contract::STATUS_ACTIVE,
		Contract::STATUS_CANCELLED,
		Contract::STATUS_ENDED,
	];

	private const VALID_INTERVALS = [
		Contract::INTERVAL_MONTHLY,
		Contract::INTERVAL_QUARTERLY,
		Contract::INTERVAL_SEMI_ANNUAL,
		Contract::INTERVAL_YEARLY,
		Contract::INTERVAL_ONE_TIME,
	];

	private const MAX_STRING_LENGTH = 500;
	private const MAX_NOTES_LENGTH = 5000;

	public function __construct(
		private ContractMapper $mapper,
		private ReminderOptOutMapper $optOutMapper,
		private IFactory $l10nFactory,
	) {
	}

	/**
	 * Whether a user has opted out of reminders for a contract.
	 */
	public function isReminderOptedOut(int $contractId, string $userId): bool {
		return $this->optOutMapper->isOptedOut($contractId, $userId);
	}

	/**
	 * Set or clear a user's reminder opt-out for a contract.
	 */
	public function setReminderOptOut(int $contractId, string $userId, bool $optedOut): void {
		$this->optOutMapper->setOptOut($contractId, $userId, $optedOut);
	}

	/**
	 * Number of contracts whose effective owner is the given user.
	 */
	public function countByEffectiveOwner(string $userId): int {
		return $this->mapper->countByEffectiveOwner($userId);
	}

	/**
	 * Transfer responsibility for all contracts from one user to another.
	 *
	 * @return int Number of contracts reassigned
	 */
	public function transferResponsibility(string $from, string $to): int {
		if ($from === '' || $to === '' || $from === $to) {
			return 0;
		}
		return $this->mapper->reassignResponsible($from, $to);
	}

	private function l(): \OCP\IL10N {
		return $this->l10nFactory->get(Application::APP_ID);
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
			$errors['name'] = $this->l()->t('Name is required');
		}

		// Vendor is required
		if (empty($data['vendor']) || trim($data['vendor']) === '') {
			$errors['vendor'] = $this->l()->t('Vendor is required');
		}

		// Date validation: startDate must be before endDate
		if (!empty($data['startDate']) && !empty($data['endDate'])) {
			try {
				$start = new DateTime($data['startDate']);
				$end = new DateTime($data['endDate']);
				if ($start >= $end) {
					$errors['endDate'] = $this->l()->t('End date must be after start date');
				}
			} catch (\Exception $e) {
				$errors['startDate'] = $this->l()->t('Invalid date format');
			}
		}

		// Cancellation date format validation
		if (!empty($data['cancelledOn'])) {
			try {
				new DateTime($data['cancelledOn']);
			} catch (\Exception $e) {
				$errors['cancelledOn'] = $this->l()->t('Invalid date format');
			}
		}
		if (!empty($data['cancelledTo'])) {
			try {
				new DateTime($data['cancelledTo']);
			} catch (\Exception $e) {
				$errors['cancelledTo'] = $this->l()->t('Invalid date format');
			}
		}
		if (!empty($data['cancelledOn']) && !empty($data['cancelledTo']) && empty($errors['cancelledOn']) && empty($errors['cancelledTo'])) {
			$cancelledOn = new DateTime($data['cancelledOn']);
			$cancelledTo = new DateTime($data['cancelledTo']);
			if ($cancelledTo < $cancelledOn) {
				$errors['cancelledTo'] = $this->l()->t('"Gekündigt zum" must not be before "Gekündigt am"');
			}
		}

		// Status validation
		if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
			$errors['status'] = $this->l()->t('Invalid status');
		}

		// Cost interval validation
		if (!empty($data['costInterval']) && !in_array($data['costInterval'], self::VALID_INTERVALS, true)) {
			$errors['costInterval'] = $this->l()->t('Invalid cost interval');
		}

		// String length validation (L2 Security Fix)
		if (!empty($data['name']) && strlen($data['name']) > self::MAX_STRING_LENGTH) {
			$errors['name'] = $this->l()->t('Name is too long (max. %s characters)', [self::MAX_STRING_LENGTH]);
		}
		if (!empty($data['vendor']) && strlen($data['vendor']) > self::MAX_STRING_LENGTH) {
			$errors['vendor'] = $this->l()->t('Vendor is too long (max. %s characters)', [self::MAX_STRING_LENGTH]);
		}
		if (!empty($data['notes']) && strlen($data['notes']) > self::MAX_NOTES_LENGTH) {
			$errors['notes'] = $this->l()->t('Notes are too long (max. %s characters)', [self::MAX_NOTES_LENGTH]);
		}
		for ($i = 1; $i <= 3; $i++) {
			$key = 'customField' . $i;
			if (!empty($data[$key]) && strlen($data[$key]) > self::MAX_STRING_LENGTH) {
				$errors[$key] = $this->l()->t('Field is too long (max. %s characters)', [self::MAX_STRING_LENGTH]);
			}
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

		// Private contracts are only visible to creator or the responsible user
		if ($contract->getIsPrivate()
			&& $contract->getCreatedBy() !== $userId
			&& $contract->getResponsibleUser() !== $userId) {
			throw new ForbiddenException($this->l()->t('No access to this private contract'));
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
			throw new ForbiddenException($this->l()->t('No permission to edit'));
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
			throw new ForbiddenException($this->l()->t('Only own contracts can be restored'));
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
			throw new ForbiddenException($this->l()->t('No access to this contract'));
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
     * Distinct vendor names from contracts visible to this user.
     *
     * @return string[]
     */
    public function findVisibleVendors(string $userId, bool $isAdmin): array {
        return $this->mapper->findVisibleVendors($userId, $isAdmin);
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
    public function search(string $query, string $userId, bool $isAdmin = false, ?int $limit = null, ?int $offset = null): array {
        return $this->mapper->search($query, $userId, $isAdmin, $limit, $offset);
    }

    public function create(
        string $name,
        string $vendor,
        string $startDate,
        ?string $endDate,
        string $contractType,
        string $userId,
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
        string $amountType = Contract::AMOUNT_TYPE_NETTO,
        ?string $cancelledOn = null,
        ?string $cancelledTo = null,
        ?string $responsibleUser = null,
        string $cancellationDeadlineType = Contract::DEADLINE_TYPE_NORMAL,
    ): Contract {
        $contract = new Contract();
        $contract->setName($name);
        $contract->setVendor($vendor);
        // A cancellation date implies the contract is cancelled
        $contract->setStatus($cancelledOn !== null ? Contract::STATUS_CANCELLED : Contract::STATUS_ACTIVE);
        $contract->setCategoryId($categoryId);
        $contract->setStartDate(new DateTime($startDate));
        $contract->setEndDate($endDate !== null ? new DateTime($endDate) : null);
        $contract->setCancelledOn($cancelledOn !== null ? new DateTime($cancelledOn) : null);
        $contract->setCancelledTo($cancelledTo !== null ? new DateTime($cancelledTo) : null);
        $contract->setCancellationPeriod($cancellationPeriod ?? '');
        $contract->setContractType($contractType);
        $contract->setRenewalPeriod($renewalPeriod);
        $contract->setCancellationDeadlineType($this->normalizeDeadlineType($cancellationDeadlineType));
        $contract->setCost($cost);
        $contract->setCurrency($currency ?? 'EUR');
        $contract->setCostInterval($costInterval);
        $contract->setContractFolder($contractFolder);
        $contract->setMainDocument($mainDocument);
        // Ohne Enddatum kann keine Erinnerung funktionieren
        $contract->setReminderEnabled($endDate !== null && $reminderEnabled ? 1 : 0);
        $contract->setReminderDays($reminderDays);
        $contract->setNotes($notes);
        $contract->setIsPrivate($isPrivate);
        $contract->setCustomField1($customField1);
        $contract->setCustomField2($customField2);
        $contract->setCustomField3($customField3);
        $contract->setAmountType(in_array($amountType, [Contract::AMOUNT_TYPE_BRUTTO, Contract::AMOUNT_TYPE_NETTO], true) ? $amountType : Contract::AMOUNT_TYPE_NETTO);
        $contract->setResponsibleUser($responsibleUser !== null && $responsibleUser !== '' ? $responsibleUser : null);
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
        string $amountType = Contract::AMOUNT_TYPE_NETTO,
        ?string $cancelledOn = null,
        ?string $cancelledTo = null,
        ?string $responsibleUser = null,
        string $cancellationDeadlineType = Contract::DEADLINE_TYPE_NORMAL,
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
        $contract->setEndDate($endDate !== null ? new DateTime($endDate) : null);
        $contract->setCancelledOn($cancelledOn !== null ? new DateTime($cancelledOn) : null);
        $contract->setCancelledTo($cancelledTo !== null ? new DateTime($cancelledTo) : null);
        // A cancellation date implies the contract is at least cancelled
        if ($cancelledOn !== null && $contract->getStatus() === Contract::STATUS_ACTIVE) {
            $contract->setStatus(Contract::STATUS_CANCELLED);
        }
        $contract->setCancellationPeriod($cancellationPeriod ?? '');
        $contract->setContractType($contractType);
        $contract->setRenewalPeriod($renewalPeriod);
        $contract->setCancellationDeadlineType($this->normalizeDeadlineType($cancellationDeadlineType));
        $contract->setCost($cost);
        $contract->setCurrency($currency ?? 'EUR');
        $contract->setCostInterval($costInterval);
        $contract->setContractFolder($contractFolder);
        $contract->setMainDocument($mainDocument);
        // Ohne Enddatum kann keine Erinnerung funktionieren
        $contract->setReminderEnabled($endDate !== null && $reminderEnabled ? 1 : 0);
        $contract->setReminderDays($reminderDays);
        $contract->setNotes($notes);
        $contract->setResponsibleUser($responsibleUser !== null && $responsibleUser !== '' ? $responsibleUser : null);
        if ($isPrivate !== null) {
            $contract->setIsPrivate($isPrivate);
        }
        $contract->setCustomField1($customField1);
        $contract->setCustomField2($customField2);
        $contract->setCustomField3($customField3);
        $contract->setAmountType(in_array($amountType, [Contract::AMOUNT_TYPE_BRUTTO, Contract::AMOUNT_TYPE_NETTO], true) ? $amountType : Contract::AMOUNT_TYPE_NETTO);
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * Validate the cancellation-deadline type, falling back to 'normal' for any
     * unknown value so a malformed payload never stores garbage.
     */
    private function normalizeDeadlineType(string $deadlineType): string {
        return in_array($deadlineType, [Contract::DEADLINE_TYPE_NORMAL, Contract::DEADLINE_TYPE_MONTH_END], true)
            ? $deadlineType
            : Contract::DEADLINE_TYPE_NORMAL;
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

        $this->optOutMapper->deleteByContract($id);
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
            $this->optOutMapper->deleteByContract($contract->getId());
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
