<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ReminderOptOut>
 */
class ReminderOptOutMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'contractmgr_optouts', ReminderOptOut::class);
	}

	/**
	 * Whether a user has opted out of reminders for a contract.
	 */
	public function isOptedOut(int $contractId, string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$found = $result->fetch() !== false;
		$result->closeCursor();
		return $found;
	}

	/**
	 * Get all user IDs that opted out of reminders for a contract.
	 *
	 * @return string[]
	 */
	public function findOptedOutUsers(int $contractId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$userIds = [];
		while (($row = $result->fetch()) !== false) {
			$userIds[] = (string)$row['user_id'];
		}
		$result->closeCursor();
		return $userIds;
	}

	/**
	 * Set or clear a user's opt-out for a contract. Idempotent.
	 */
	public function setOptOut(int $contractId, string $userId, bool $optedOut): void {
		if ($optedOut) {
			if ($this->isOptedOut($contractId, $userId)) {
				return;
			}
			$entity = new ReminderOptOut();
			$entity->setContractId($contractId);
			$entity->setUserId($userId);
			$this->insert($entity);
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	/**
	 * Delete all opt-outs for a contract (used when a contract is deleted).
	 */
	public function deleteByContract(int $contractId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('contract_id', $qb->createNamedParameter($contractId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Delete all opt-outs of a user (used when their account is deleted, #299).
	 *
	 * An opt-out is a setting, not a record of something that happened: it has
	 * no timestamp and only means "do not remind me about this contract".
	 * Nextcloud drops the app's other per-user settings along with the account;
	 * this one only survives because it lives in the app's own table instead of
	 * oc_preferences. Leaving it behind matters because Nextcloud allows a uid
	 * to be handed out again, and a stale row would silently mute reminders for
	 * whoever gets that uid next.
	 *
	 * @return int Number of rows removed
	 */
	public function deleteByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}
}
