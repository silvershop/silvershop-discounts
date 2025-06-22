<?php

namespace SilverShop\Discounts;

use SilverShop\Model\OrderItem;

/**
 * Wrap PriceInfo with order item info
 */
class ItemPriceInfo extends PriceInfo
{
    protected OrderItem $item;

    protected int|float $quantity = 0;

    public function __construct(OrderItem $item)
    {
        $this->item = $item;
        $this->quantity = $item->Quantity;

        $originalprice = $item->hasMethod('DiscountableAmount') ?
                            $item->DiscountableAmount() :
                            $item->UnitPrice();

        parent::__construct($originalprice);
    }

    public function getItem(): OrderItem
    {
        return $this->item;
    }

    public function getQuantity(): int|float
    {
        return $this->quantity;
    }

    public function getOriginalTotal(): int|float
    {
        return $this->originalprice * $this->quantity;
    }

    public function debug(): string
    {
        $discount = $this->getBestDiscount();
        $total = $discount * $this->getQuantity();
        $val = 'item: ' . $this->getItem()->TableTitle();
        $price = $this->getOriginalPrice();
        $val .= " price:{$price} discount:{$discount} total:{$total}.\n";

        if (($best = $this->getBestAdjustment()) instanceof Adjustment) {
            $val .= $this->getBestAdjustment() . ' ';
            $val .= $this->getBestAdjustment()->getAdjuster()->Title;
        } else {
            $val .= 'No adjustments';
        }

        $val .= "\n";
        $val .= implode(',', $this->getAdjustments());

        return $val . "\n\n";
    }
}
