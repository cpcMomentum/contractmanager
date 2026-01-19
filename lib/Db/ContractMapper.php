<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Contract>
 */
class ContractMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'contractmanager_contracts', Contract::class);
    }

    /**
     * Find all non-archived contracts for a user
     *
     * @return Contract[]
     */
    public function findAll(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find all archived contracts for a user
     *
     * @return Contract[]
     */
    public function findArchived(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function find(int $id): Contract {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Find contracts by status
     *
     * @return Contract[]
     */
    public function findByStatus(string $status): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter($status)))
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find contracts by category
     *
     * @return Contract[]
     */
    public function findByCategory(int $categoryId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find active contracts with reminder enabled that need notification
     *
     * @return Contract[]
     */
    public function findForReminder(\DateTime $deadlineDate): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter(Contract::STATUS_ACTIVE)))
            ->andWhere($qb->expr()->eq('reminder_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->lte('end_date', $qb->createNamedParameter($deadlineDate, IQueryBuilder::PARAM_DATE)))
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find contracts that potentially need a reminder
     * Returns active, non-archived contracts with reminders enabled
     *
     * @return Contract[]
     */
    public function findContractsNeedingReminder(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter(Contract::STATUS_ACTIVE)))
            ->andWhere($qb->expr()->eq('reminder_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNotNull('end_date'))
            ->andWhere($qb->expr()->isNotNull('cancellation_period'))
            ->andWhere($qb->expr()->neq('cancellation_period', $qb->createNamedParameter('')))
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Search contracts by name or vendor for a user
     *
     * @return Contract[]
     */
    public function search(string $query, string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $searchPattern = '%' . $this->db->escapeLikeParameter($query) . '%';

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->iLike('name', $qb->createNamedParameter($searchPattern)),
                    $qb->expr()->iLike('vendor', $qb->createNamedParameter($searchPattern))
                )
            )
            ->orderBy('end_date', 'ASC');

        return $this->findEntities($qb);
    }
}
