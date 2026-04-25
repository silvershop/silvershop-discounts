<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverStripe\Core\Extension;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Model\Order;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;

/**
 * Encapsulate a single kind of constraint.
 *
 * This class extends DataExtension, because constraint data needs to be stored
 * in the Discount object - the class which each constraint extends.
 *
 * Constraints are also instantiated on their own. See
 * ItemDiscountConstraint::match and Discount->valid
 * @extends Extension<static>
 */
abstract class DiscountConstraint extends Extension
{
    protected Order $order;

    /** @var array<string, mixed> */
    protected array $context = [];

    protected string $message = '';

    protected string $messagetype = '';

    public function setOrder(Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Filter a list of discounts according to this constraint.
     *
     * The filtering must always allow empty data through, for example Value =
     * 'X' OR Value IS NULL
     *
     * See predefined constraints for examples.
     */
    /** @param DataList<Discount>|DataList<DataObject> $dataList
     *  @return DataList<Discount>|DataList<DataObject>
     */
    public function filter(DataList $dataList): DataList
    {
        return $dataList;
    }

    /**
     * Check if the current set order falls within
     * this constraint.
     */
    abstract public function check(Discount $discount): bool;

    protected function message(string $message, string $type = 'good'): void
    {
        $this->message = $message;
        $this->messagetype = $type;
    }

    protected function error(string $message): void
    {
        $this->message($message, 'bad');
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageType(): string
    {
        return $this->messagetype;
    }
}
