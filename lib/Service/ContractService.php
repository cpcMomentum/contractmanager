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

		if (!empty($errors)) {
			throw new ValidationException($errors);
		}
	}

	/**
	 * Check if a user has access to a contract
	 *
	 * @throws ForbiddenException
	 */
	public function checkAccess(Contract $contract, string $userId): void {
		if ($contract->getCreatedBy() !== $userId) {
			throw new ForbiddenException('Kein Zugriff auf diesen Vertrag');
		}
	}

    /**
     * @return Contract[]
     */
    public function findAll(): array {
        return $this->mapper->findAll();
    }

    /**
     * @return Contract[]
     */
    public function findArchived(): array {
        return $this->mapper->findArchived();
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
    public function search(string $query): array {
        return $this->mapper->search($query);
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
        $contract->setCreatedBy($userId);
        $contract->setCreatedAt(new DateTime());
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->insert($contract);
    }

    /**
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
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * @throws NotFoundException
     */
    public function delete(int $id): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        return $this->mapper->delete($contract);
    }

	/**
	 * Archive a contract
	 *
	 * @throws NotFoundException
	 * @throws ForbiddenException
	 */
	public function archive(int $id, string $userId): Contract {
		try {
			$contract = $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$this->checkAccess($contract, $userId);

		$contract->setArchived(true);
		$contract->setUpdatedAt(new DateTime());

		return $this->mapper->update($contract);
	}

	/**
	 * Restore a contract from archive
	 *
	 * @throws NotFoundException
	 * @throws ForbiddenException
	 */
	public function restore(int $id, string $userId): Contract {
		try {
			$contract = $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$this->checkAccess($contract, $userId);

		$contract->setArchived(false);
		$contract->setUpdatedAt(new DateTime());

		return $this->mapper->update($contract);
	}
}
