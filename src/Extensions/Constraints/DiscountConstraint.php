<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\ORM\DataExtension;
use SilverShop\Model\Order;
use SilverStripe\ORM\DataList;

/**
 * Encapsulate a single kind of constraint.
 *
 * This class extends DataExtension, because constraint data needs to be stored
 * in the Discount object - the class which each constraint extends.
 *
 * Constraints are also instantiated on their own. See
 * ItemDiscountConstraint::match and Discount->valid
 */
abstract class DiscountConstraint extends DataExtension
{
    protected $order;

    protected $context;

    protected $message;

    protected $messagetype;

    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    public function setContext(array $context)
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
     *
     * @param  DataList $discounts discount list constrain
     * @return DataList
     */
    public function filter(DataList $discounts)
    {
        return $discounts;
    }

    /**
     * Check if the current set order falls within
     * this constraint.
     *
     * @param  Discount $discount
     * @return boolean
     */
    abstract public function check(Discount $discount);

    protected function message($messsage, $type = "good")
    {
        $this->message = $messsage;
        $this->messagetype = $type;
    }

    protected function error($message)
    {
        $this->message($message, "bad");
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getMessageType()
    {
        return $this->messagetype;
    }
}
