<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Category extends Entity implements JsonSerializable {

    protected string $name = '';
    protected int $sortOrder = 0;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('sortOrder', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
