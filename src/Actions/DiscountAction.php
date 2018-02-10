<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\Model\Discount;

abstract class DiscountAction extends Action
{
    /**
     * @var Discount
     */
    protected $discount;

    /**
     * @var float used for keeping total discount within MaxAmount
     */
    protected $remaining;

    /**
     * @var bool
     */
    protected $limited;

    public function __construct(Discount $discount)
    {
        $this->discount = $discount;
        $this->remaining = (float)$this->discount->MaxAmount;
        $this->limited = (bool)$this->remaining;
    }

    /**
     * Limit an amount to be within maximum allowable discount, and update the
     * total remaining discountable amount;
     *
     * @param float $amount
     *
     * @return float new amount
     */
    protected function limit($amount)
    {
        if ($this->limited) {
            if ($amount > $this->remaining) {
                $amount = $this->remaining;
            }

            $this->remaining -= $amount > $this->remaining ? $this->remaining : $amount;
        }

        return $amount;
    }

    /**
     * Check if there is any further allowable amount to be discounted.
     *
     * @return boolean
     */
    protected function hasRemainingDiscount()
    {
        return !$this->limited || $this->remaining > 0;
    }

    /**
     * @param float
     */
    public function reduceRemaining($amount)
    {
        if ($this->remaining) {
            $this->remaining -= $amount;
        }

        return $this;
    }
}
