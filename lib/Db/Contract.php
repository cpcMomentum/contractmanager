<?php

declare(strict_types=1);

namespace OCA\ContractManager\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getVendor()
 * @method void setVendor(string $vendor)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int|null getCategoryId()
 * @method void setCategoryId(?int $categoryId)
 * @method DateTime getStartDate()
 * @method void setStartDate(DateTime $startDate)
 * @method DateTime getEndDate()
 * @method void setEndDate(DateTime $endDate)
 * @method string getCancellationPeriod()
 * @method void setCancellationPeriod(string $cancellationPeriod)
 * @method string getContractType()
 * @method void setContractType(string $contractType)
 * @method string|null getRenewalPeriod()
 * @method void setRenewalPeriod(?string $renewalPeriod)
 * @method string|null getCost()
 * @method void setCost(?string $cost)
 * @method string|null getCurrency()
 * @method void setCurrency(?string $currency)
 * @method string|null getCostInterval()
 * @method void setCostInterval(?string $costInterval)
 * @method string|null getContractFolder()
 * @method void setContractFolder(?string $contractFolder)
 * @method string|null getMainDocument()
 * @method void setMainDocument(?string $mainDocument)
 * @method int getReminderEnabled()
 * @method void setReminderEnabled(int $reminderEnabled)
 * @method int|null getReminderDays()
 * @method void setReminderDays(?int $reminderDays)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @method int getArchived()
 * @method int getIsPrivate()
 * @method DateTime|null getDeletedAt()
 */
class Contract extends Entity implements JsonSerializable {

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ENDED = 'ended';

    public const TYPE_FIXED = 'fixed';
    public const TYPE_AUTO_RENEWAL = 'auto_renewal';

    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_YEARLY = 'yearly';
    public const INTERVAL_ONE_TIME = 'one_time';

    protected string $name = '';
    protected string $vendor = '';
    protected string $status = self::STATUS_ACTIVE;
    protected ?int $categoryId = null;
    protected ?DateTime $startDate = null;
    protected ?DateTime $endDate = null;
    protected string $cancellationPeriod = '';
    protected string $contractType = self::TYPE_FIXED;
    protected ?string $renewalPeriod = null;
    protected ?string $cost = null;
    protected ?string $currency = 'EUR';
    protected ?string $costInterval = null;
    protected ?string $contractFolder = null;
    protected ?string $mainDocument = null;
    protected int $reminderEnabled = 1;
    protected ?int $reminderDays = null;
    protected ?string $notes = null;
    protected int $archived = 0;
    protected int $isPrivate = 0;
    protected ?DateTime $deletedAt = null;
    protected string $createdBy = '';
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('categoryId', 'integer');
        $this->addType('startDate', 'datetime');
        $this->addType('endDate', 'datetime');
        $this->addType('reminderEnabled', 'integer');
        $this->addType('reminderDays', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
        $this->addType('archived', 'integer');
        $this->addType('isPrivate', 'integer');
        $this->addType('deletedAt', 'datetime');
    }

    /**
     * Custom setter for archived to handle bool/int conversion
     */
    public function setArchived(bool|int $archived): void {
        $value = is_bool($archived) ? ($archived ? 1 : 0) : $archived;
        $this->archived = $value;
        $this->markFieldUpdated('archived');
    }

    /**
     * Custom setter for isPrivate to handle bool/int conversion
     */
    public function setIsPrivate(bool|int $isPrivate): void {
        $value = is_bool($isPrivate) ? ($isPrivate ? 1 : 0) : $isPrivate;
        $this->isPrivate = $value;
        $this->markFieldUpdated('isPrivate');
    }

    /**
     * Custom setter for deletedAt
     */
    public function setDeletedAt(?DateTime $deletedAt): void {
        $this->deletedAt = $deletedAt;
        $this->markFieldUpdated('deletedAt');
    }

    /**
     * Check if contract is in trash
     */
    public function isDeleted(): bool {
        return $this->deletedAt !== null;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vendor' => $this->vendor,
            'status' => $this->status,
            'categoryId' => $this->categoryId,
            'startDate' => $this->startDate?->format('Y-m-d'),
            'endDate' => $this->endDate?->format('Y-m-d'),
            'cancellationPeriod' => $this->cancellationPeriod,
            'contractType' => $this->contractType,
            'renewalPeriod' => $this->renewalPeriod,
            'cost' => $this->cost,
            'currency' => $this->currency,
            'costInterval' => $this->costInterval,
            'contractFolder' => $this->contractFolder,
            'mainDocument' => $this->mainDocument,
            'reminderEnabled' => (bool) $this->reminderEnabled,
            'reminderDays' => $this->reminderDays,
            'notes' => $this->notes,
            'archived' => (bool) $this->archived,
            'isPrivate' => (bool) $this->isPrivate,
            'deletedAt' => $this->deletedAt?->format('c'),
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
