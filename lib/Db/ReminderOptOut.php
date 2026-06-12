<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A user's opt-out from reminders for a single contract.
 *
 * @method int getContractId()
 * @method void setContractId(int $contractId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 */
class ReminderOptOut extends Entity implements JsonSerializable {

	protected int $contractId = 0;
	protected string $userId = '';

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contractId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'contractId' => $this->contractId,
			'userId' => $this->userId,
		];
	}
}
