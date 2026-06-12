<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ReminderSent>
 */
class ReminderSentMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'contractmgr_reminders', ReminderSent::class);
    }

    /**
     * Find reminder by contract ID, type and recipient
     *
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByContractTypeUser(int $contractId, string $reminderType, string $userId): ReminderSent {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('reminder_type', $qb->createNamedParameter($reminderType)))
            ->andWhere($qb->expr()->eq('sent_to', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    /**
     * Check if a reminder of this type has already been sent to this recipient for this contract
     */
    public function hasBeenSent(int $contractId, string $reminderType, string $userId): bool {
        try {
            $this->findByContractTypeUser($contractId, $reminderType, $userId);
            return true;
        } catch (DoesNotExistException $e) {
            return false;
        }
    }

    /**
     * Find all reminders for a contract
     *
     * @return ReminderSent[]
     */
    public function findByContract(int $contractId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)))
            ->orderBy('sent_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Delete all reminders for a contract (used when contract is deleted)
     */
    public function deleteByContract(int $contractId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
