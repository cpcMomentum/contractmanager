<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Category>
 */
class CategoryMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'contractmgr_categories', Category::class);
    }

    /**
     * @return Category[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('name', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function find(int $id): Category {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    public function findByName(string $name): ?Category {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('name', $qb->createNamedParameter($name)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException|MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function getMaxSortOrder(): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->max('sort_order'))
            ->from($this->getTableName());

        $result = $qb->executeQuery();
        $maxOrder = $result->fetchOne();
        $result->closeCursor();

        return (int) ($maxOrder ?? 0);
    }
}
