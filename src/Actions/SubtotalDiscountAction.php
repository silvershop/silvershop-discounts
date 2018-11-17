<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\Model\Discount;

class SubtotalDiscountAction extends DiscountAction
{
    protected $subtotal;

    public function __construct($subtotal, Discount $discount)
    {
        parent::__construct($discount);

        $this->subtotal = $subtotal;

        // for Amount discounts on Subtotals, prevent amount from ever being greater than the Amount
        $amount = $discount->getAmount();

        if ($discount->Type === 'Amount' && $amount > $this->remaining) {
            $this->remaining = (float) $amount;
            $this->limited = true;
        }
    }

    public function perform()
    {
        $amount =  $this->discount->getDiscountValue($this->subtotal);

        if ($amount > $this->subtotal) {
            $amount = $this->subtotal;
        }

        $amount = $this->limit($amount);

        return $amount;
    }

    public function isForItems()
    {
        return false;
    }
}
