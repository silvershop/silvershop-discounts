<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\Model\Discount;

abstract class DiscountAction extends Action
{
    protected Discount $discount;

    /**
     * @var float used for keeping total discount within MaxAmount
     */
    protected float $remaining;

    protected bool $limited;

    public function __construct(Discount $discount)
    {
        $this->discount = $discount;
        $this->remaining = (float)$this->discount->MaxAmount;
        $this->limited = (bool)$this->remaining;
    }

    /**
     * Limit an amount to be within maximum allowable discount, and update the
     * total remaining discountable amount;
     */
    protected function limit(float $amount): float
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
     */
    protected function hasRemainingDiscount(): bool
    {
        return !$this->limited || $this->remaining > 0;
    }

    public function reduceRemaining(float $amount): static
    {
        if ($this->remaining !== 0.0) {
            $this->remaining -= $amount;
        }

        return $this;
    }
}
