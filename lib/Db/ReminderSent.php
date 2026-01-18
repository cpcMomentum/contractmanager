<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getContractId()
 * @method void setContractId(int $contractId)
 * @method string getReminderType()
 * @method void setReminderType(string $reminderType)
 * @method DateTime getSentAt()
 * @method void setSentAt(DateTime $sentAt)
 * @method string getSentTo()
 * @method void setSentTo(string $sentTo)
 */
class ReminderSent extends Entity implements JsonSerializable {

    protected int $contractId = 0;
    protected string $reminderType = '';
    protected ?DateTime $sentAt = null;
    protected string $sentTo = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('contractId', 'integer');
        $this->addType('sentAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'contractId' => $this->contractId,
            'reminderType' => $this->reminderType,
            'sentAt' => $this->sentAt?->format('c'),
            'sentTo' => $this->sentTo,
        ];
    }
}
