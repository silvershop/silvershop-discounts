<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\ItemPriceInfo;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Extensions\Constraints\ItemDiscountConstraint;

abstract class ItemDiscountAction extends DiscountAction
{
    protected $infoitems;

    public function __construct(array $infoitems, Discount $discount)
    {
        parent::__construct($discount);

        $this->infoitems = $infoitems;
    }

    public function isForItems()
    {
        return true;
    }

    /**
     * Checks if the given item qualifies for a discount.
     *
     * @param ItemPriceInfo $info
     * @return boolean
     */
    protected function itemQualifies(ItemPriceInfo $info)
    {
        return ItemDiscountConstraint::match($info->getItem(), $this->discount);
    }
}
