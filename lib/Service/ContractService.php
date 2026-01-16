<?php

declare(strict_types=1);

namespace OCA\ContractManager\Service;

use DateTime;
use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Db\ContractMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class ContractService {

    public function __construct(
        private ContractMapper $mapper,
    ) {
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
        $contract->setReminderEnabled($reminderEnabled);
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
        $contract->setReminderEnabled($reminderEnabled);
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
     * @throws NotFoundException
     */
    public function archive(int $id): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        $contract->setStatus(Contract::STATUS_ARCHIVED);
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }

    /**
     * @throws NotFoundException
     */
    public function restore(int $id): Contract {
        try {
            $contract = $this->mapper->find($id);
        } catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
            throw new NotFoundException($e->getMessage());
        }

        // Restore to active status
        $contract->setStatus(Contract::STATUS_ACTIVE);
        $contract->setUpdatedAt(new DateTime());

        return $this->mapper->update($contract);
    }
}
