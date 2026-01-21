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
        parent::__construct($db, 'contractmgr_contracts', Contract::class);
    }

    /**
     * Find all visible, non-archived, non-deleted contracts
     *
     * Visibility rules:
     * - Admin sees all contracts
     * - Others see non-private contracts + their own private contracts
     *
     * @return Contract[]
     */
    public function findAllVisible(string $userId, bool $isAdmin): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'));

        if (!$isAdmin) {
            // Non-admins see: all non-private OR their own contracts
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('is_private', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
                    $qb->expr()->eq('created_by', $qb->createNamedParameter($userId))
                )
            );
        }

        $qb->orderBy('end_date', 'ASC');
        return $this->findEntities($qb);
    }

    /**
     * Find all visible archived contracts (not deleted)
     *
     * @return Contract[]
     */
    public function findArchivedVisible(string $userId, bool $isAdmin): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('archived', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'));

        if (!$isAdmin) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('is_private', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
                    $qb->expr()->eq('created_by', $qb->createNamedParameter($userId))
                )
            );
        }

        $qb->orderBy('updated_at', 'DESC');
        return $this->findEntities($qb);
    }

    /**
     * Find deleted contracts for a specific user (their trash)
     *
     * @return Contract[]
     */
    public function findDeletedByUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->isNotNull('deleted_at'))
            ->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId)))
            ->orderBy('deleted_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Find all deleted contracts (admin trash view)
     *
     * @return Contract[]
     */
    public function findAllDeleted(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->isNotNull('deleted_at'))
            ->orderBy('deleted_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Find expired deleted contracts for auto-cleanup
     * Returns contracts deleted more than X days ago, excluding admin users
     *
     * @param \DateTime $cutoffDate Contracts deleted before this date are expired
     * @param string[] $excludeUserIds User IDs to exclude (e.g., admins)
     * @return Contract[]
     */
    public function findExpiredDeleted(\DateTime $cutoffDate, array $excludeUserIds = []): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->isNotNull('deleted_at'))
            ->andWhere($qb->expr()->lt('deleted_at', $qb->createNamedParameter($cutoffDate, IQueryBuilder::PARAM_DATE)));

        if (!empty($excludeUserIds)) {
            $qb->andWhere(
                $qb->expr()->notIn('created_by', $qb->createNamedParameter($excludeUserIds, IQueryBuilder::PARAM_STR_ARRAY))
            );
        }

        return $this->findEntities($qb);
    }

    /**
     * @deprecated Use findAllVisible() instead
     * Find all non-archived contracts for a user (legacy method)
     *
     * @return Contract[]
     */
    public function findAll(string $userId): array {
        return $this->findAllVisible($userId, false);
    }

    /**
     * @deprecated Use findArchivedVisible() instead
     * Find all archived contracts for a user (legacy method)
     *
     * @return Contract[]
     */
    public function findArchived(string $userId): array {
        return $this->findArchivedVisible($userId, false);
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
     * Returns active, non-archived, non-deleted contracts with reminders enabled
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
            ->andWhere($qb->expr()->isNull('deleted_at'))
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
