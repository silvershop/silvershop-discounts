<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\ItemPriceInfo;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Extensions\Constraints\ItemDiscountConstraint;

abstract class ItemDiscountAction extends DiscountAction
{
    protected array $infoitems;

    public function __construct(array $infoitems, Discount $discount)
    {
        parent::__construct($discount);

        $this->infoitems = $infoitems;
    }

    public function isForItems(): bool
    {
        return true;
    }

    /**
     * Checks if the given item qualifies for a discount.
     */
    protected function itemQualifies(ItemPriceInfo $info): bool
    {
        return ItemDiscountConstraint::match($info->getItem(), $this->discount);
    }
}
